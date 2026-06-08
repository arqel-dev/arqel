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
 * payload completo do model com os casts aplicados (per-key
 * `$model->getAttribute()` sobre as chaves de `getAttributes()`) e o
 * diff em `changes` quando aplicável. Ser cast-aware evita corromper
 * casts array/json/object/collection/encrypted no restore (issue #187).
 *
 * Idempotência: nenhum hook escreve quando `wasChanged()` é `false`
 * (após update sem mudanças reais) ou quando `arqel-versioning.enabled`
 * é `false`.
 *
 * O método `restoreToVersion()` é não-destrutivo — ele reaplica os
 * atributos (com casts) e salva, o que dispara o hook e cria uma nova
 * Version (permitindo "undo restore").
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
            // `getDirty()` only flags actually-changed columns. We read the
            // CAST value on both sides (issue #187): `getOriginal($key)` is
            // already cast, and `getAttribute($key)` returns the cast new
            // value (the attribute is set on the model at `updating` time).
            // Using the raw `getDirty()` value for the "after" side would make
            // the diff type-asymmetric (e.g. [array, jsonString]).
            foreach (array_keys($model->getDirty()) as $key) {
                if (in_array($key, ['updated_at', 'created_at'], true)) {
                    continue;
                }
                /** @var mixed $original */
                $original = $model->getOriginal($key);
                /** @var mixed $newValue */
                $newValue = $model->getAttribute($key);
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
        $payload = self::snapshotAttributes($model);

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
     * Constrói o snapshot do model aplicando os casts (issue #187).
     *
     * Itera sobre o MESMO key-set de `getAttributes()` (colunas DB
     * presentes) mas grava o valor **com cast aplicado** via
     * `getAttribute($key)`. Isto garante que um cast `array`/`json`/
     * `object`/`collection`/`encrypted` é persistido como o seu valor
     * desserializado e não como a string JSON crua — que, ao restaurar,
     * seria re-encodada pelo Eloquent (double-encoding → corrupção).
     *
     * Apenas a aplicação do cast muda; o conjunto de chaves capturadas
     * é idêntico ao comportamento anterior, preservando o contrato de
     * `$hidden`/colunas excluídas tal como já era.
     *
     * @return array<string, mixed>
     */
    private static function snapshotAttributes(Model $model): array
    {
        $payload = [];

        foreach (array_keys($model->getAttributes()) as $key) {
            /** @var mixed $value */
            $value = $model->getAttribute($key);
            $payload[$key] = $value;
        }

        return $payload;
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
     * Aceita `int` (id) ou instance de `Version`. Reaplica os casts
     * (via `setAttribute` por chave) ao escrever o payload e salva — o
     * que dispara o hook `saved` e gera uma nova Version (restore é
     * versionado, permitindo desfazer).
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

        // Re-apply casts on restore (issue #187): `setAttribute` runs the cast
        // mutators per key, so an array payload round-trips as an array instead
        // of being re-encoded into a double-encoded JSON string. We keep the
        // mass-assignment bypass intent of the original `forceFill` by writing
        // every snapshot key directly (no `$fillable` filtering).
        foreach ($payload as $key => $value) {
            $this->setAttribute($key, $value);
        }

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
            ->where('versionable_type', $model->getMorphClass())
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

        // Honor any callable resolver: string FQCN::method, Closure, or
        // array callable [$object, 'method']. An empty string is not
        // callable, so is_callable() subsumes the earlier is_string guards.
        if (is_callable($resolver)) {
            /** @var mixed $resolved */
            $resolved = call_user_func($resolver);

            return is_int($resolved) ? $resolved : null;
        }

        $userId = Auth::id();

        return is_int($userId) ? $userId : null;
    }
}
