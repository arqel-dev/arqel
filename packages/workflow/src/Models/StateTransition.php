<?php

declare(strict_types=1);

namespace Arqel\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Append-only audit log entry para transições de state (WF-007).
 *
 * Persistido automaticamente pelo listener
 * `Arqel\Workflow\Listeners\PersistStateTransitionToHistory` quando o
 * evento `StateTransitioned` é disparado e a flag
 * `arqel-workflow.history.enabled` está ativa.
 *
 * @property int $id
 * @property string $model_type
 * @property int|string $model_id
 * @property string|null $from_state
 * @property string $to_state
 * @property int|null $transitioned_by_user_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 */
final class StateTransition extends Model
{
    protected $table = 'arqel_state_transitions';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'model_type',
        'model_id',
        'from_state',
        'to_state',
        'transitioned_by_user_id',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relacionamento opcional com o user model do app.
     *
     * Lê `arqel-workflow.user_model` (default `App\Models\User`). Quando
     * a classe não existe ou não é um Eloquent `Model`, retorna `null`
     * — defensive para apps minimalistas / testes.
     *
     * @return BelongsTo<Model, $this>|null
     */
    public function user(): ?BelongsTo
    {
        $userModel = config('arqel-workflow.user_model', 'App\\Models\\User');

        if (! is_string($userModel) || ! class_exists($userModel)) {
            return null;
        }

        if (! is_subclass_of($userModel, Model::class)) {
            return null;
        }

        /** @var class-string<Model> $userModel */

        /** @var BelongsTo<Model, $this> $relation */
        $relation = $this->belongsTo($userModel, 'transitioned_by_user_id');

        return $relation;
    }
}
