<?php

declare(strict_types=1);

namespace Arqel\Versioning\Http\Controllers;

use Arqel\Versioning\Concerns\Versionable;
use Arqel\Versioning\Models\Version;
use Arqel\Versioning\VersionPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * Endpoint single-action que devolve o histórico paginado de
 * `Version` para um record gerido por um `Resource` registrado.
 *
 * Resolução do `ResourceRegistry`: por design, o `arqel-dev/versioning`
 * permanece utilizável sem o `arqel-dev/core` em runtime. Resolvemos o
 * registry pelo FQCN-string e devolvemos `404` quando ele não está
 * bound — apps sem core (ex.: standalone CLI) ainda podem usar o
 * trait `Versionable`, mesmo que esta rota não seja útil.
 *
 * Authorization: quando há policy `view` registrada no Gate para o
 * model, é honrada; sem policy registrada, libera o acesso. Apps
 * que precisem de hard-gate devem registrar a policy explicitamente.
 */
final class VersionHistoryController
{
    private const REGISTRY_FQCN = 'Arqel\\Core\\Resources\\ResourceRegistry';

    private const PER_PAGE_DEFAULT = 20;

    private const PER_PAGE_MAX = 100;

    public function __invoke(Request $request, string $resource, int|string $id): JsonResponse
    {
        $registry = $this->resolveRegistry();

        if ($registry === null) {
            return new JsonResponse(['message' => 'ResourceRegistry not bound'], 404);
        }

        if (! method_exists($registry, 'findBySlug')) {
            return new JsonResponse(['message' => 'ResourceRegistry not bound'], 404);
        }

        /** @var class-string|null $resourceClass */
        $resourceClass = $registry->findBySlug($resource);

        if ($resourceClass === null) {
            return new JsonResponse(['message' => "Resource [{$resource}] not registered"], 404);
        }

        if (! method_exists($resourceClass, 'getModel')) {
            return new JsonResponse(['message' => "Resource [{$resource}] is invalid"], 404);
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $resourceClass::getModel();

        /** @var Model $model */
        $model = $modelClass::query()->findOrFail($id);

        if (! $this->usesVersionable($model)) {
            return new JsonResponse([
                'message' => sprintf('Model [%s] does not use the Versionable trait', $modelClass),
            ], 422);
        }

        if (Gate::has('view') && ! Gate::allows('view', $model)) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        $perPage = $this->resolvePerPage($request);

        // Construímos a query diretamente em `Version` em vez de usar
        // `$model->versions()` — assim mantemos a tipagem estática
        // (Eloquent\Model::versions() não está declarado no parent) e
        // controlamos eager-load condicional.
        $query = Version::query()
            ->where('versionable_type', $model->getMorphClass())
            ->where('versionable_id', $model->getKey())
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        // O `Version::user()` é defensivo e devolve `null` quando o
        // `user_model` configurado não é um Eloquent Model — nesse
        // caso eager-load falha, então só anexamos quando aplicável.
        if ((new Version)->user() !== null) {
            $query = $query->with('user');
        }

        /** @var LengthAwarePaginator<int, Version> $paginator */
        $paginator = $query->paginate($perPage);

        $includePayload = $this->shouldIncludePayload($request);

        $items = [];
        foreach ($paginator->items() as $version) {
            assert($version instanceof Version);
            $items[] = VersionPresenter::toArray($version, $includePayload);
        }

        /** @var mixed $rawKeep */
        $rawKeep = config('arqel-versioning.keep_versions', 50);
        $keepVersions = is_numeric($rawKeep) ? (int) $rawKeep : 50;

        return new JsonResponse([
            'versions' => [
                'data' => $items,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'meta' => [
                'keep_versions' => $keepVersions,
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function resolveRegistry(): ?object
    {
        if (! class_exists(self::REGISTRY_FQCN)) {
            return null;
        }

        if (! app()->bound(self::REGISTRY_FQCN)) {
            return null;
        }

        /** @var object $registry */
        $registry = app(self::REGISTRY_FQCN);

        return $registry;
    }

    private function usesVersionable(Model $model): bool
    {
        $traits = class_uses_recursive($model);

        return in_array(Versionable::class, $traits, true);
    }

    private function resolvePerPage(Request $request): int
    {
        /** @var mixed $raw */
        $raw = $request->query('per_page', self::PER_PAGE_DEFAULT);

        $perPage = is_numeric($raw) ? (int) $raw : self::PER_PAGE_DEFAULT;

        if ($perPage < 1) {
            return self::PER_PAGE_DEFAULT;
        }

        return min($perPage, self::PER_PAGE_MAX);
    }

    private function shouldIncludePayload(Request $request): bool
    {
        /** @var mixed $raw */
        $raw = $request->query('include');

        if (! is_string($raw)) {
            return false;
        }

        $tokens = array_map('trim', explode(',', $raw));

        return in_array('payload', $tokens, true);
    }
}
