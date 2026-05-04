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
 * Authorization é gated por `Gate::authorize('update', $record)` quando
 * a Gate está definida; caso contrário (sem ability registrada) o
 * pedido é permitido — pattern usado por outros controllers do projeto.
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
                    'message' => 'Model does not use the Versionable trait.',
                ], 422);
            }

            if (Gate::has('update')) {
                Gate::authorize('update', $record);
            }

            /** @var Version|null $version */
            $version = Version::query()
                ->where('versionable_type', $record->getMorphClass())
                ->where('versionable_id', $record->getKey())
                ->whereKey($versionId)
                ->first();

            if ($version === null) {
                throw new NotFoundHttpException('Version not found for record.');
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
                'message' => 'Restore failed.',
            ], 500);
        }
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
            throw new NotFoundHttpException('Resource registry unavailable.');
        }

        $registry = app($registryFqcn);

        if (! is_object($registry) || ! method_exists($registry, 'findBySlug')) {
            throw new NotFoundHttpException('Resource registry unavailable.');
        }

        /** @var mixed $resourceClass */
        $resourceClass = $registry->findBySlug($resource);

        if (! is_string($resourceClass) || ! class_exists($resourceClass)) {
            throw new NotFoundHttpException("Resource '{$resource}' not found.");
        }

        /** @var mixed $modelClass */
        $modelClass = $resourceClass::$model ?? null;

        if (! is_string($modelClass) || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            throw new NotFoundHttpException("Resource '{$resource}' has no model bound.");
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
