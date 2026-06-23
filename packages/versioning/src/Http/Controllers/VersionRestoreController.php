<?php

declare(strict_types=1);

namespace Arqel\Versioning\Http\Controllers;

use Arqel\Versioning\Concerns\Versionable;
use Arqel\Versioning\Models\Version;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Endpoint HTTP para restaurar um record a uma `Version` anterior (VERS-005).
 *
 * Single-action controller invoked via:
 * `POST /admin/{resource}/{id}/versions/{versionId}/restore`.
 *
 * Resolve `ResourceRegistry` defensivamente via FQCN-string para evitar
 * acoplamento hard com `arqel-dev/core` em ambientes de teste minimalistas.
 *
 * Authorization: o `update` é exigido quando existe um named gate
 * (`Gate::define`) OU uma Policy registrada para o model
 * (`Gate::getPolicyFor`). Só em scaffold-mode — sem named gate e sem
 * policy — o pedido é liberado ("Hello World" path). Espelha o padrão
 * canónico de `Arqel\Core\Http\Controllers\ResourceController::authorize`.
 *
 * Nota: `Gate::has()` sozinho NÃO consulta Policies, então gatear apenas
 * por `Gate::has('update')` deixava models protegidos por Policy
 * passarem sem checagem (issue #91).
 */
final class VersionRestoreController
{
    public function __invoke(Request $request, string $resource, string $id, string $versionId): JsonResponse
    {
        try {
            $record = $this->resolveRecord($resource, $id);

            $usesVersionable = in_array(
                Versionable::class,
                $this->classUsesRecursive($record::class),
                true,
            );

            if (! $usesVersionable) {
                return new JsonResponse([
                    'message' => $this->message(
                        'arqel::messages.versioning.not_versionable',
                        'Model does not use the Versionable trait.',
                    ),
                ], 422);
            }

            $this->authorize('update', $record);

            /** @var Version|null $version */
            $version = Version::query()
                ->where('versionable_type', $record->getMorphClass())
                ->where('versionable_id', $record->getKey())
                ->whereKey($versionId)
                ->first();

            if ($version === null) {
                throw new NotFoundHttpException($this->message(
                    'arqel::messages.versioning.version_not_found',
                    'Version not found for record.',
                ));
            }

            /** @var callable(Version): bool $restore */
            $restore = [$record, 'restoreToVersion'];
            $success = (bool) $restore($version);

            $newVersionId = null;
            if ($success) {
                /** @var Model $fresh */
                $fresh = $record->fresh();
                /** @var Version|null $latest */
                $latest = Version::query()
                    ->where('versionable_type', $fresh->getMorphClass())
                    ->where('versionable_id', $fresh->getKey())
                    ->orderBy('id', 'desc')
                    ->first();
                $newVersionId = $latest?->id;
            }

            return new JsonResponse([
                'restored' => $success,
                'new_version_id' => $newVersionId,
            ]);
        } catch (HttpException|ModelNotFoundException|AuthorizationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('arqel.versioning.restore_failed', [
                'resource' => $resource,
                'id' => $id,
                'version_id' => $versionId,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'restored' => false,
                'message' => $this->message('arqel::messages.versioning.restore_failed', 'Restore failed.'),
            ], 500);
        }
    }

    /**
     * Localize a user-facing JSON message lazily so the request locale
     * applies. Falls back to the English literal when no translator is bound
     * or the key is untranslated, keeping the response text stable.
     *
     * @param array<string, string> $replace placeholder substitutions applied
     *                                       to both the translation and the
     *                                       English fallback (e.g. :resource)
     */
    private function message(string $key, string $fallback, array $replace = []): string
    {
        if (! app()->bound('translator')) {
            return $this->interpolate($fallback, $replace);
        }

        $translated = trans($key, $replace);

        if (is_string($translated) && $translated !== $key) {
            return $translated;
        }

        return $this->interpolate($fallback, $replace);
    }

    /**
     * Apply `:placeholder` substitutions to the English fallback literal so
     * the response stays coherent when the translator is unavailable or the
     * key is untranslated.
     *
     * @param array<string, string> $replace
     */
    private function interpolate(string $text, array $replace): string
    {
        foreach ($replace as $placeholder => $value) {
            $text = str_replace(':'.$placeholder, $value, $text);
        }

        return $text;
    }

    /**
     * Autoriza a ability via Gate, honrando named gates E Policies.
     *
     * `Gate::has()` só conhece abilities registradas com `Gate::define()`;
     * nunca consulta Policies (resolvidas via `Gate::getPolicyFor()`).
     * Por isso exigimos a checagem quando existe named gate OU policy para
     * o model. Só liberamos (scaffold-mode) quando NENHUM dos dois existe.
     */
    private function authorize(string $ability, Model $record): void
    {
        if (! Gate::has($ability) && ! Gate::getPolicyFor($record)) {
            return;
        }

        Gate::authorize($ability, $record);
    }

    /**
     * Resolve o record-alvo via `ResourceRegistry`.
     *
     * Lança `NotFoundHttpException` quando registry, slug ou record não
     * existem — mantém superfície HTTP previsível (404).
     */
    private function resolveRecord(string $resource, string $id): Model
    {
        $registryFqcn = 'Arqel\\Core\\Resources\\ResourceRegistry';

        if (! class_exists($registryFqcn)) {
            throw new NotFoundHttpException($this->message(
                'arqel::messages.versioning.registry_unavailable',
                'Resource registry unavailable.',
            ));
        }

        $registry = app($registryFqcn);

        if (! is_object($registry) || ! method_exists($registry, 'findBySlug')) {
            throw new NotFoundHttpException($this->message(
                'arqel::messages.versioning.registry_unavailable',
                'Resource registry unavailable.',
            ));
        }

        /** @var mixed $resourceClass */
        $resourceClass = $registry->findBySlug($resource);

        if (! is_string($resourceClass) || ! class_exists($resourceClass)) {
            throw new NotFoundHttpException($this->message(
                'arqel::messages.versioning.resource_not_found',
                "Resource ':resource' not found.",
                ['resource' => $resource],
            ));
        }

        /** @var mixed $modelClass */
        $modelClass = $resourceClass::$model ?? null;

        if (! is_string($modelClass) || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            throw new NotFoundHttpException($this->message(
                'arqel::messages.versioning.resource_no_model',
                "Resource ':resource' has no model bound.",
                ['resource' => $resource],
            ));
        }

        /** @var class-string<Model> $modelClass */
        /** @var Model $record */
        $record = $modelClass::query()->findOrFail($id);

        return $record;
    }

    /**
     * @return array<int, string>
     */
    private function classUsesRecursive(string $class): array
    {
        /** @var array<string, string> $traits */
        $traits = [];
        $current = $class;
        while ($current !== false) {
            $used = class_uses($current);
            if ($used === false) {
                break;
            }
            foreach ($used as $trait) {
                $traits[$trait] = $trait;
            }
            $current = get_parent_class($current);
        }

        foreach ($traits as $trait) {
            $nested = class_uses($trait);
            if ($nested === false) {
                continue;
            }
            foreach ($nested as $sub) {
                $traits[$sub] = $sub;
            }
        }

        return array_values($traits);
    }
}
