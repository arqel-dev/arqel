<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Payout para o publisher de um plugin premium (MKTPLC-008).
 *
 * @property int $id
 * @property int $plugin_id
 * @property int $publisher_user_id
 * @property int $amount_cents
 * @property string $currency
 * @property string $status
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class PluginPayout extends Model
{
    protected $table = 'arqel_plugin_payouts';

    /** @var list<string> */
    protected $fillable = [
        'plugin_id',
        'publisher_user_id',
        'amount_cents',
        'currency',
        'status',
        'period_start',
        'period_end',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'status' => 'string',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Plugin, $this>
     */
    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function publisher(): BelongsTo
    {
        $rawModel = config('auth.providers.users.model');
        $model = is_string($rawModel) && $rawModel !== '' ? $rawModel : Model::class;

        /** @var class-string<Model> $model */
        return $this->belongsTo($model, 'publisher_user_id');
    }
}
