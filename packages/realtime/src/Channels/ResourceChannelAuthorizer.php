<?php

declare(strict_types=1);

namespace Arqel\Realtime\Channels;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Authorization helpers for the broadcast channels exposed by
 * `routes/channels.php` (RT-009).
 *
 * Concentrates the actual gate-checking logic so it can be tested
 * in isolation without spinning up the broadcast machinery. Each
 * method is **defensive by default** — qualquer falha em resolver
 * a Resource, model ou cache devolve `false` (deny) e regista
 * via `Log::warning()` para auditoria, ao invés de propagar
 * exceptions para o pipeline de auth do broadcaster.
 *
 * O acoplamento com `arqel-dev/core` é opcional: usamos
 * `app()->bound(ResourceRegistry::class)` antes de resolver. Apps
 * que usem `arqel-dev/realtime` standalone (sem o core) recebem `false`
 * sem erros, preservando o contrato deny-by-default.
 */
final readonly class ResourceChannelAuthorizer
{
    /**
     * FQCN do `ResourceRegistry` do core. Mantido como string para
     * que o realtime não dependa do core em compile-time.
     *
     * @var string
     */
    private const RESOURCE_REGISTRY_CLASS = 'Arqel\\Core\\Resources\\ResourceRegistry';

    /**
     * Authorize the user to listen to the list-level channel
     * `arqel.{resource}`. Verifica a Gate `viewAny` contra o model
     * declarado pela Resource.
     */
    public static function authorizeResource(Authenticatable $user, string $resourceSlug): bool
    {
        try {
            $resourceClass = self::resolveResourceClass($resourceSlug);

            if ($resourceClass === null) {
                return false;
            }

            /** @var class-string $modelClass */
            $modelClass = $resourceClass::getModel();

            return Gate::forUser($user)->check('viewAny', $modelClass);
        } catch (Throwable $e) {
            Log::warning('Arqel realtime: failed to authorize resource channel', [
                'resource' => $resourceSlug,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Authorize the user for the per-record channel
     * `arqel.{resource}.{recordId}`. Carrega o record via
     * `Model::find()` e delega ao Gate `view`.
     */
    public static function authorizeRecord(
        Authenticatable $user,
        string $resourceSlug,
        int|string $recordId,
    ): bool {
        try {
            $record = self::resolveRecord($resourceSlug, $recordId);

            if ($record === null) {
                return false;
            }

            return Gate::forUser($user)->check('view', $record);
        } catch (Throwable $e) {
            Log::warning('Arqel realtime: failed to authorize record channel', [
                'resource' => $resourceSlug,
                'recordId' => $recordId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resolve a Resource slug + id to an Eloquent record, or `null` quando
     * o registry não está bound, o slug não bate com nenhuma Resource, ou o
     * record não existe. Defensivo: qualquer falha (registry unbound, slug
     * desconhecido, query throw) devolve `null` sem propagar exceptions.
     *
     * Centraliza a resolução slug→Model para que `authorizeRecord` e o
     * presence channel callback (routes/channels.php) partilhem exatamente
     * o mesmo caminho de resolução (DRY).
     */
    public static function resolveRecord(string $resourceSlug, int|string $recordId): ?Model
    {
        try {
            $resourceClass = self::resolveResourceClass($resourceSlug);

            if ($resourceClass === null) {
                return null;
            }

            /** @var class-string<Model> $modelClass */
            $modelClass = $resourceClass::getModel();

            return $modelClass::query()->find($recordId);
        } catch (Throwable $e) {
            Log::warning('Arqel realtime: failed to resolve record', [
                'resource' => $resourceSlug,
                'recordId' => $recordId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Whether the core `ResourceRegistry` is bound in the container. Distingue
     * "app Arqel real (registry bound) mas slug/record não resolveu" — onde
     * negar é o correto — de "realtime standalone (registry unbound)" — onde
     * não existe Policy/core possível e o presence channel pode abrir
     * (scaffold mode).
     */
    public static function registryBound(): bool
    {
        return app()->bound(self::RESOURCE_REGISTRY_CLASS);
    }

    /**
     * Member-info payload published to the presence roster for `$user`.
     * Extraído do callback inline em routes/channels.php para que a shape
     * `{id, name, avatar}` seja testável e o route file fique fino.
     *
     * @return array{id: int|string|null, name: string|null, avatar: string|null}
     */
    public static function presenceMemberInfo(Authenticatable $user): array
    {
        /** @var mixed $id */
        $id = $user->getAuthIdentifier();
        /** @var mixed $name */
        $name = $user->name ?? null; // @phpstan-ignore-line property.notFound
        /** @var mixed $avatar */
        $avatar = $user->avatar_url ?? null; // @phpstan-ignore-line property.notFound

        return [
            'id' => is_int($id) || is_string($id) ? $id : null,
            'name' => is_string($name) ? $name : null,
            'avatar' => is_string($avatar) ? $avatar : null,
        ];
    }

    /**
     * Authorize the user for the action progress channel
     * `arqel.action.{jobId}`. O dispatcher do action job grava
     * o id do owner em `Cache::put("arqel.action.{jobId}.user", $userId)`
     * — a comparação é estrita contra o auth identifier do user.
     */
    public static function authorizeActionJob(Authenticatable $user, string $jobId): bool
    {
        try {
            $owner = Cache::get("arqel.action.{$jobId}.user");

            if ($owner === null) {
                return false;
            }

            return $owner === $user->getAuthIdentifier();
        } catch (Throwable $e) {
            Log::warning('Arqel realtime: failed to authorize action channel', [
                'jobId' => $jobId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resolve the Resource class for a slug, returning `null` quando
     * o `ResourceRegistry` do core não está bound (pacote opcional)
     * ou quando o slug não bate com nenhuma Resource.
     *
     * @return class-string|null
     */
    private static function resolveResourceClass(string $slug): ?string
    {
        if (! app()->bound(self::RESOURCE_REGISTRY_CLASS)) {
            return null;
        }

        $registry = app(self::RESOURCE_REGISTRY_CLASS);

        if (! method_exists($registry, 'findBySlug')) {
            return null;
        }

        /** @var class-string|null $resourceClass */
        $resourceClass = $registry->findBySlug($slug);

        return $resourceClass;
    }
}
