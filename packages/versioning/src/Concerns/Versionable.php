<?php

declare(strict_types=1);

namespace Arqel\Versioning\Concerns;

use Arqel\Versioning\Models\Version;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Trait aplicado por user-land Eloquent models para auto-versionar.
 *
 * Hook único: `static::saved(...)` regista uma `Version` quando o model
 * é criado ou atualizado e tem mudanças efetivas. O snapshot grava o
 * payload completo do model (`$model->getAttributes()`) e o diff em
 * `changes` quando aplicável.
 *
 * Idempotência: nenhum hook escreve quando `wasChanged()` é `false`
 * (após update sem mudanças reais) ou quando `arqel-versioning.enabled`
 * é `false`.
 *
 * O método `restoreToVersion()` é não-destrutivo — ele faz força-fill
 * dos atributos e salva, o que dispara o hook e cria uma nova Version
 * (permitindo "undo restore").
 *
 * @phpstan-require-extends Model
 */
trait Versionable
{
    /**
     * Pending diff capturado em `saving` e consumido em `saved`.
     * Indexado por `spl_object_id($model)` para evitar poluir os
     * attributes do Eloquent.
     *
     * @var array<int, array<string, array{0: mixed, 1: mixed}>>
     */
    private static array $arqelVersioningPendingDiff = [];

    public static function bootVersionable(): void
    {
        static::created(static function (Model $model): void {
            if (config('arqel-versioning.enabled', true) === false) {
                return;
            }

            self::writeVersion($model, null);
        });

        // Capture pre-save dirty diff during `updating` — at this point
        // `getOriginal()` ainda tem o estado do DB e `getDirty()` expõe
        // as mudanças pendentes.
        static::updating(static function (Model $model): void {
            if (config('arqel-versioning.enabled', true) === false) {
                return;
            }

            $diff = [];
            foreach ($model->getDirty() as $key => $newValue) {
                if (in_array($key, ['updated_at', 'created_at'], true)) {
                    continue;
                }
                /** @var mixed $original */
                $original = $model->getOriginal($key);
                $diff[$key] = [$original, $newValue];
            }

            self::$arqelVersioningPendingDiff[spl_object_id($model)] = $diff;
        });

        static::updated(static function (Model $model): void {
            $oid = spl_object_id($model);

            if (config('arqel-versioning.enabled', true) === false) {
                unset(self::$arqelVersioningPendingDiff[$oid]);

                return;
            }

            /** @var array<string, array{0: mixed, 1: mixed}> $diff */
            $diff = self::$arqelVersioningPendingDiff[$oid] ?? [];
            unset(self::$arqelVersioningPendingDiff[$oid]);

            // Idempotência — saves que só mexem nos timestamps não geram
            // version (o `updating` hook já filtrou esses campos).
            if ($diff === []) {
                return;
            }

            self::writeVersion($model, $diff);
        });
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}>|null $diff
     */
    private static function writeVersion(Model $model, ?array $diff): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $model->getAttributes();

        $userId = self::resolveAuditUserId();

        $version = new Version;
        $version->fill([
            'payload' => $payload,
            'changes' => $diff,
            'created_by_user_id' => $userId,
            'created_at' => $model->freshTimestamp(),
        ]);
        $version->versionable()->associate($model);
        $version->save();

        self::pruneOldVersionsFor($model);
    }

    /**
     * Histórico append-only de versions deste record (VERS-002).
     *
     * Ordenado por `created_at` desc + `id` desc para uso direto em
     * timelines (record mais recente primeiro).
     *
     * @return MorphMany<Version, $this>
     */
    public function versions(): MorphMany
    {
        assert($this instanceof Model);

        /** @var MorphMany<Version, $this> $relation */
        $relation = $this->morphMany(Version::class, 'versionable')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        return $relation;
    }

    /**
     * Atalho para `versions()->first()` — última version registrada.
     */
    public function currentVersion(): ?Version
    {
        /** @var Version|null $version */
        $version = $this->versions()->first();

        return $version;
    }

    /**
     * Restaura o record para uma version anterior.
     *
     * Aceita `int` (id) ou instance de `Version`. Faz `forceFill` do
     * payload e salva — o que dispara o hook `saved` e gera uma nova
     * Version (restore é versionado, permitindo desfazer).
     *
     * Devolve `false` quando a version não pertence a este record
     * (defensive contra cross-record restore acidental).
     */
    public function restoreToVersion(int|Version $version): bool
    {
        assert($this instanceof Model);

        if (is_int($version)) {
            /** @var Version|null $resolved */
            $resolved = $this->versions()->whereKey($version)->first();
            if ($resolved === null) {
                return false;
            }
            $version = $resolved;
        }

        if ($version->versionable_id !== $this->getKey()
            || $version->versionable_type !== $this->getMorphClass()) {
            return false;
        }

        /** @var array<string, mixed> $payload */
        $payload = $version->payload;

        $this->forceFill($payload);

        return $this->save();
    }

    /**
     * Prune helper público — pode ser chamado pelo cleanup job de
     * VERS-006. Estratégia 'count' apenas; 'time' fica para VERS-006.
     */
    public function pruneOldVersions(): int
    {
        return self::pruneOldVersionsFor($this);
    }

    /**
     * @internal
     */
    private static function pruneOldVersionsFor(Model $model): int
    {
        $strategy = config('arqel-versioning.prune_strategy', 'count');
        if ($strategy !== 'count') {
            return 0;
        }

        /** @var mixed $rawKeep */
        $rawKeep = config('arqel-versioning.keep_versions', 50);
        $keep = is_numeric($rawKeep) ? (int) $rawKeep : 50;
        if ($keep <= 0) {
            return 0;
        }

        $stale = Version::query()
            ->where('versionable_type', $model::class)
            ->where('versionable_id', $model->getKey())
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->get();

        $deleted = 0;
        foreach ($stale as $version) {
            $version->delete();
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Resolve o user id a gravar em `created_by_user_id`.
     *
     * Usa o callable de `arqel-versioning.audit_user` quando configurado;
     * fallback para `Auth::id()`.
     */
    private static function resolveAuditUserId(): ?int
    {
        /** @var mixed $resolver */
        $resolver = config('arqel-versioning.audit_user');

        if (is_string($resolver) && $resolver !== '' && is_callable($resolver)) {
            /** @var mixed $resolved */
            $resolved = call_user_func($resolver);

            return is_int($resolved) ? $resolved : null;
        }

        $userId = Auth::id();

        return is_int($userId) ? $userId : null;
    }
}
