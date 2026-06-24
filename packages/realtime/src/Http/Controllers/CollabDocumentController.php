<?php

declare(strict_types=1);

namespace Arqel\Realtime\Http\Controllers;

use Arqel\Realtime\Collab\YjsDocument;
use Arqel\Realtime\Events\YjsUpdateReceived;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Controller REST para snapshot persistence de Y.Docs (RT-005 scaffold).
 *
 * - `GET  /admin/{resource}/{id}/collab/{field}` — devolve `{state, version}`
 *   onde `state` é base64 do blob binário Yjs ou `null` se ainda não existe.
 * - `POST /admin/{resource}/{id}/collab/{field}` — recebe `{state, version}`
 *   (state base64) e aplica optimistic concurrency: aceita quando
 *   `incoming.version >= current.version`, devolve `409` caso contrário.
 *
 * Sync delta-by-delta de Yjs (via WebSocket / Reverb) fica para
 * follow-up (`@arqel-dev/realtime` cliente). Aqui só persistimos snapshot
 * consolidado.
 */
final class CollabDocumentController
{
    public function show(Request $request, string $resource, string $id, string $field): JsonResponse
    {
        $this->authorizeRecord($request, $resource, $id, 'view');

        $document = YjsDocument::query()
            ->where('model_type', $resource)
            ->where('model_id', $id)
            ->where('field', $field)
            ->first();

        if ($document === null) {
            return new JsonResponse([
                'state' => null,
                'version' => 0,
            ]);
        }

        $stateBlob = $document->state;
        $stateBase64 = $stateBlob === null || $stateBlob === ''
            ? null
            : base64_encode($stateBlob);

        return new JsonResponse([
            'state' => $stateBase64,
            'version' => $document->version,
        ]);
    }

    public function store(Request $request, string $resource, string $id, string $field): JsonResponse
    {
        $this->authorizeRecord($request, $resource, $id, 'update');

        $rawState = $request->input('state');
        $rawVersion = $request->input('version', 0);

        if (! is_string($rawState) || $rawState === '') {
            return new JsonResponse(['message' => $this->message(
                'arqel::messages.realtime.collab.invalid_state',
                'state must be a non-empty base64 string',
            )], 422);
        }

        $incomingVersion = is_numeric($rawVersion) ? (int) $rawVersion : 0;

        $stateBlob = base64_decode($rawState, true);
        if ($stateBlob === false) {
            return new JsonResponse(['message' => $this->message(
                'arqel::messages.realtime.collab.invalid_base64',
                'state is not valid base64',
            )], 422);
        }

        $document = YjsDocument::query()
            ->where('model_type', $resource)
            ->where('model_id', $id)
            ->where('field', $field)
            ->first();

        if ($document === null) {
            $userId = $this->resolveUserId($request);
            $document = new YjsDocument;
            $document->model_type = $resource;
            $document->model_id = (int) $id;
            $document->field = $field;
            $document->state = $stateBlob;
            $document->version = max($incomingVersion, 1);
            $document->last_user_id = $userId;
            $document->updated_at = now();
            $document->save();

            YjsUpdateReceived::dispatch(
                $resource,
                (int) $id,
                $field,
                $rawState,
                $document->version,
                $document->last_user_id,
            );

            return new JsonResponse([
                'version' => $document->version,
            ], 201);
        }

        if ($incomingVersion < $document->version) {
            return new JsonResponse([
                'message' => $this->message(
                    'arqel::messages.realtime.collab.version_conflict',
                    'version conflict',
                ),
                'serverVersion' => $document->version,
            ], 409);
        }

        $document->state = $stateBlob;
        $document->version = $document->version + 1;
        $document->last_user_id = $this->resolveUserId($request);
        $document->updated_at = now();
        $document->save();

        YjsUpdateReceived::dispatch(
            $resource,
            (int) $id,
            $field,
            $rawState,
            $document->version,
            $document->last_user_id,
        );

        return new JsonResponse([
            'version' => $document->version,
        ]);
    }

    /**
     * Localize a user-facing JSON message lazily so the request locale
     * applies. Falls back to the English literal when no translator is bound
     * or the key is untranslated, keeping the response text stable.
     */
    private function message(string $key, string $fallback): string
    {
        if (! app()->bound('translator')) {
            return $fallback;
        }

        $translated = trans($key);

        return is_string($translated) && $translated !== $key ? $translated : $fallback;
    }

    private function resolveUserId(Request $request): ?int
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $id = $user->getAuthIdentifier();

        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    /**
     * Enforce record-level authorization before reading/writing a collab
     * snapshot. Mirrors `AwarenessChannelAuthorizer` (the WebSocket side):
     * resolve the owning model from `{resource}` (slug or FQCN) + `{id}`,
     * then honour the `view`/`update` Gate OR Policy. Aborts 404 when the
     * record can't be resolved (unknown resource, missing record), and 403
     * when the ability is denied.
     *
     * Scaffold mode: when no named Gate AND no Policy exists for the model,
     * access is open — consistent with the awareness authorizer's
     * presence-channel-style default.
     */
    private function authorizeRecord(Request $request, string $resource, string $id, string $ability): void
    {
        $modelClass = $this->resolveModelClass($resource);

        if ($modelClass === null) {
            \abort(404);
        }

        try {
            /** @var Model|null $record */
            $record = $modelClass::query()->find($id);
        } catch (Throwable) {
            $record = null;
        }

        if ($record === null) {
            \abort(404);
        }

        // Honour authorization when the app defines a named Gate for the
        // ability OR registers a Policy for the model. `Gate::has()` only
        // sees named Gates (never Policies), so without the `getPolicyFor()`
        // check a Policy-protected record would fall through to allow-all
        // and leak the snapshot. Mirror of `AwarenessChannelAuthorizer`.
        if (Gate::has($ability) || Gate::getPolicyFor($record) !== null) {
            Gate::forUser($request->user())->authorize($ability, $record);
        }

        // No Gate and no Policy => open (scaffold mode).
    }

    /**
     * Resolve `$resource` (a Resource slug or a model FQCN) to an Eloquent
     * model class. Mirrors `AwarenessChannelAuthorizer::resolveModelClass`
     * but keyed by slug via the optional `arqel-dev/core` registry.
     *
     * @return class-string<Model>|null
     */
    private function resolveModelClass(string $resource): ?string
    {
        if (class_exists($resource) && is_subclass_of($resource, Model::class)) {
            /** @var class-string<Model> $resource */
            return $resource;
        }

        $registryClass = 'Arqel\\Core\\Resources\\ResourceRegistry';

        if (! app()->bound($registryClass)) {
            return null;
        }

        $registry = app($registryClass);

        if (! method_exists($registry, 'findBySlug')) {
            return null;
        }

        /** @var class-string|null $resourceClass */
        $resourceClass = $registry->findBySlug($resource);

        if ($resourceClass === null || ! method_exists($resourceClass, 'getModel')) {
            return null;
        }

        try {
            /** @var class-string $modelClass */
            $modelClass = $resourceClass::getModel();
        } catch (Throwable) {
            return null;
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        return $modelClass;
    }
}
