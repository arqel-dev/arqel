<?php

declare(strict_types=1);

namespace Arqel\Versioning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Snapshot append-only de um record versionado (VERS-001/VERS-002).
 *
 * Persistido pelo trait `Versionable` no hook `saved` quando o model
 * sofre mudanças efetivas. A tabela é append-only — não tem
 * `updated_at` e o model expõe `$timestamps = false`.
 *
 * @property int $id
 * @property string $versionable_type
 * @property int|string $versionable_id
 * @property array<string, mixed> $payload
 * @property array<string, array{0: mixed, 1: mixed}>|null $changes
 * @property int|null $created_by_user_id
 * @property string|null $reason
 * @property Carbon|null $created_at
 */
final class Version extends Model
{
    protected $table = 'arqel_versions';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'versionable_type',
        'versionable_id',
        'payload',
        'changes',
        'created_by_user_id',
        'reason',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function versionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relacionamento defensivo com o user model do app.
     *
     * Lê `arqel-versioning.user_model` (default `App\Models\User`).
     * Devolve `null` quando a classe não existe ou não é um Eloquent
     * `Model` — útil para apps minimalistas / testes.
     *
     * @return BelongsTo<Model, $this>|null
     */
    public function user(): ?BelongsTo
    {
        $userModel = config('arqel-versioning.user_model', 'App\\Models\\User');

        if (! is_string($userModel) || ! class_exists($userModel)) {
            return null;
        }

        if (! is_subclass_of($userModel, Model::class)) {
            return null;
        }

        /** @var class-string<Model> $userModel */

        /** @var BelongsTo<Model, $this> $relation */
        $relation = $this->belongsTo($userModel, 'created_by_user_id');

        return $relation;
    }
}
