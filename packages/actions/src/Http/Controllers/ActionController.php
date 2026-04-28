<?php

declare(strict_types=1);

namespace Arqel\Actions\Http\Controllers;

use Arqel\Actions\Action;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * Endpoint for Action invocation.
 *
 * Routes:
 *   POST {panel}/{resource}/{id}/actions/{action}        — row/header
 *   POST {panel}/{resource}/actions/{action}             — toolbar
 *   POST {panel}/{resource}/bulk-actions/{action}        — bulk
 *
 * Each method:
 *   1. Resolves the Resource via the registry (404 if absent).
 *   2. Resolves the action by name from the matching collection
 *      (`actions/bulkActions/toolbarActions/headerActions`).
 *   3. Authorises through the action's `canBeExecutedBy` Closure.
 *   4. Validates the form-modal payload (when present).
 *   5. Executes the action's callback and flashes success/failure.
 *
 * Resources expose four collections through optional public
 * methods (`actions(): array`, `bulkActions(): array`,
 * `toolbarActions(): array`, `headerActions(): array`). The
 * methods are duck-typed so user code does not need to declare
 * them when not used.
 */
final class ActionController
{
    public function __construct(
        private readonly ResourceRegistry $registry,
    ) {}

    public function invokeRow(Request $request, string $resource, string $id, string $action): RedirectResponse
    {
        $instance = $this->resolveOrFail($resource);
        $record = $this->findOrFail($instance, $id);

        return $this->execute(
            $request,
            $instance,
            $this->resolveAction($instance, 'actions', $action),
            $record,
        );
    }

    public function invokeHeader(Request $request, string $resource, string $id, string $action): RedirectResponse
    {
        $instance = $this->resolveOrFail($resource);
        $record = $this->findOrFail($instance, $id);

        return $this->execute(
            $request,
            $instance,
            $this->resolveAction($instance, 'headerActions', $action),
            $record,
        );
    }

    public function invokeToolbar(Request $request, string $resource, string $action): RedirectResponse
    {
        $instance = $this->resolveOrFail($resource);

        return $this->execute(
            $request,
            $instance,
            $this->resolveAction($instance, 'toolbarActions', $action),
            null,
        );
    }

    public function invokeBulk(Request $request, string $resource, string $action): RedirectResponse
    {
        $instance = $this->resolveOrFail($resource);
        $actionInstance = $this->resolveAction($instance, 'bulkActions', $action);

        $ids = $request->input('ids');
        if (! is_array($ids) || $ids === []) {
            abort(HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'Missing selection.');
        }

        $modelClass = $instance::getModel();
        $records = $modelClass::query()->whereIn(
            (new $modelClass)->getKeyName(),
            $ids,
        )->get();

        return $this->execute($request, $instance, $actionInstance, $records);
    }

    private function execute(Request $request, Resource $resource, Action $action, mixed $target): RedirectResponse
    {
        $user = $request->user();
        $authUser = $user instanceof Authenticatable ? $user : null;

        if (! $action->canBeExecutedBy($authUser, $target)) {
            abort(HttpResponse::HTTP_FORBIDDEN);
        }

        /** @var array<string, mixed> $data */
        $data = [];
        if ($action->hasForm()) {
            $rules = $action->getFormValidationRules();
            $validated = $request->validate($rules);
            foreach ($validated as $key => $value) {
                $data[(string) $key] = $value;
            }
        }

        try {
            $action->execute($target, $data);
        } catch (Throwable $e) {
            $message = $action->getFailureNotification() ?? $e->getMessage();

            return back()->with('error', $message);
        }

        $message = $action->getSuccessNotification();
        $redirect = back();

        return $message !== null
            ? $redirect->with('success', $message)
            : $redirect;
    }

    private function resolveOrFail(string $slug): Resource
    {
        $class = $this->registry->findBySlug($slug);

        if ($class === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        /** @var resource $instance */
        $instance = app($class);

        return $instance;
    }

    private function findOrFail(Resource $resource, string $id): Model
    {
        $modelClass = $resource::getModel();
        $record = $modelClass::query()->find($id);

        if (! $record instanceof Model) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        return $record;
    }

    private function resolveAction(Resource $resource, string $collectionMethod, string $name): Action
    {
        if (! method_exists($resource, $collectionMethod)) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $collection = $resource->{$collectionMethod}();

        if (! is_array($collection)) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        foreach ($collection as $action) {
            if ($action instanceof Action && $action->getName() === $name) {
                return $action;
            }
        }

        abort(HttpResponse::HTTP_NOT_FOUND);
    }
}
