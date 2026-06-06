<?php

declare(strict_types=1);

namespace Arqel\Realtime\Collab;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Authorizer for Yjs collab channels (`arqel.collab.{modelType}.{modelId}.{field}`).
 *
 * Defensive by default: any failure path (registry unbound, model not found,
 * Gate throws, FQCN inválido) retorna `false`. O acoplamento com `arqel-dev/core`
 * é opcional — quando `Arqel\Core\Resources\ResourceRegistry` não está bound
 * o authorizer só consegue resolver o record se `$modelType` for FQCN direto
 * de uma classe Eloquent.
 */
final readonly class AwarenessChannelAuthorizer
{
    /**
     * @var string
     */
    private const RESOURCE_REGISTRY_CLASS = 'Arqel\\Core\\Resources\\ResourceRegistry';

    public function authorize(
        Authenticatable $user,
        string $modelType,
        int|string $modelId,
        string $field,
    ): bool {
        try {
            $modelClass = $this->resolveModelClass($modelType);

            if ($modelClass === null) {
                return false;
            }

            /** @var Model|null $record */
            $record = $modelClass::query()->find($modelId);

            if ($record === null) {
                return false;
            }

            // Honre a autorização quando a app definir um Gate `view` OU
            // registrar uma Policy para o model. `Gate::has()` só enxerga
            // Gates nomeados (nunca Policies), então sem o check de
            // `getPolicyFor()` um record protegido por Policy cairia no
            // allow-all abaixo e vazaria o canal de colaboração. Mirror do
            // `ResourceController::authorize()` em arqel-dev/core.
            if (Gate::has('view') || Gate::getPolicyFor($record) !== null) {
                return Gate::forUser($user)->check('view', $record);
            }

            // Sem Gate E sem Policy => aberto (scaffold mode), consistente
            // com o pattern do PresenceChannel.
            return true;
        } catch (Throwable $e) {
            Log::warning('Arqel realtime: failed to authorize collab awareness channel', [
                'modelType' => $modelType,
                'modelId' => $modelId,
                'field' => $field,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resolve `$modelType` para uma classe Eloquent FQCN.
     *
     * Estratégia (espelha `CollabDocumentController::resolveModelClass`, de
     * modo que o lado WS aceita exatamente as chaves que o lado REST emite):
     *  1. Se for FQCN válido subclass de Model → retorna direto.
     *  2. Se `ResourceRegistry` está bound, procura um Resource cujo
     *     `getModel()` bate exatamente com `$modelType` (chave por FQCN).
     *  3. Ainda no registry, resolve `$modelType` como slug de Resource via
     *     `findBySlug()`. É essa a chave que o REST persiste em
     *     `YjsDocument::$model_type` e na qual `YjsUpdateReceived`
     *     broadcasta (`arqel.collab.{slug}.{id}.{field}`); sem este passo o
     *     authorizer negaria o próprio canal para o qual o write transmite.
     *
     * @return class-string<Model>|null
     */
    private function resolveModelClass(string $modelType): ?string
    {
        if (class_exists($modelType) && is_subclass_of($modelType, Model::class)) {
            /** @var class-string<Model> $modelType */
            return $modelType;
        }

        if (! app()->bound(self::RESOURCE_REGISTRY_CLASS)) {
            return null;
        }

        $registry = app(self::RESOURCE_REGISTRY_CLASS);

        if (method_exists($registry, 'all')) {
            /** @var iterable<int, class-string> $resources */
            $resources = $registry->all();

            foreach ($resources as $resourceClass) {
                if (! method_exists($resourceClass, 'getModel')) {
                    continue;
                }

                try {
                    /** @var class-string $modelClass */
                    $modelClass = $resourceClass::getModel();
                } catch (Throwable) {
                    continue;
                }

                if ($modelClass === $modelType && is_subclass_of($modelClass, Model::class)) {
                    /** @var class-string<Model> $modelClass */
                    return $modelClass;
                }
            }
        }

        if (! method_exists($registry, 'findBySlug')) {
            return null;
        }

        /** @var class-string|null $resourceClass */
        $resourceClass = $registry->findBySlug($modelType);

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
