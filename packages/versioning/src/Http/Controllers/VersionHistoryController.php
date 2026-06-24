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
 * Authorization: o `view` é exigido quando existe um named gate
 * (`Gate::define`) OU uma Policy registrada para o model
 * (`Gate::getPolicyFor`). Só em scaffold-mode — sem named gate e sem
 * policy — o acesso é liberado. Espelha o padrão canónico de
 * `Arqel\Core\Http\Controllers\ResourceController::authorize`.
 *
 * Nota: `Gate::has()` sozinho NÃO consulta Policies, então gatear apenas
 * por `Gate::has('view')` deixava models protegidos por Policy
 * vazarem o snapshot (`?include=payload`) — issue #91.
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
            return new JsonResponse(['message' => $this->message('arqel::messages.versioning.registry_not_bound', 'ResourceRegistry not bound')], 404);
        }

        if (! method_exists($registry, 'findBySlug')) {
            return new JsonResponse(['message' => $this->message('arqel::messages.versioning.registry_not_bound', 'ResourceRegistry not bound')], 404);
        }

        /** @var class-string|null $resourceClass */
        $resourceClass = $registry->findBySlug($resource);

        if ($resourceClass === null) {
            return new JsonResponse(['message' => $this->message(
                'arqel::messages.versioning.resource_not_registered',
                "Resource [{$resource}] not registered",
                ['resource' => $resource],
            )], 404);
        }

        if (! method_exists($resourceClass, 'getModel')) {
            return new JsonResponse(['message' => $this->message(
                'arqel::messages.versioning.resource_invalid',
                "Resource [{$resource}] is invalid",
                ['resource' => $resource],
            )], 404);
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $resourceClass::getModel();

        /** @var Model $model */
        $model = $modelClass::query()->findOrFail($id);

        if (! $this->usesVersionable($model)) {
            return new JsonResponse([
                'message' => $this->message(
                    'arqel::messages.versioning.not_versionable',
                    "Model [{$modelClass}] does not use the Versionable trait",
                    ['model' => $modelClass],
                ),
            ], 422);
        }

        if ($this->deniesView($model)) {
            return new JsonResponse(['message' => $this->message('arqel::messages.versioning.forbidden', 'Forbidden')], 403);
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

    /**
     * Decide se o `view` deve ser negado, honrando named gates E Policies.
     *
     * `Gate::has()` só conhece abilities de `Gate::define()`; nunca
     * consulta Policies (`Gate::getPolicyFor()`). Exigimos a checagem
     * quando existe named gate OU policy para o model; só liberamos
     * (scaffold-mode) quando NENHUM dos dois existe.
     */
    private function deniesView(Model $model): bool
    {
        if (! Gate::has('view') && ! Gate::getPolicyFor($model)) {
            return false;
        }

        return Gate::denies('view', $model);
    }

    /**
     * Localize a user-facing JSON message lazily so the request locale
     * applies. Falls back to the English literal when no translator is bound
     * or the key is untranslated, keeping the response text stable.
     *
     * @param array<string, string> $replace
     */
    private function message(string $key, string $fallback, array $replace = []): string
    {
        if (! app()->bound('translator')) {
            return $fallback;
        }

        $translated = trans($key, $replace);

        return is_string($translated) && $translated !== $key ? $translated : $fallback;
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
