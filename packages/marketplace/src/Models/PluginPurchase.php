<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Compra de um plugin premium (MKTPLC-008).
 *
 * Status state-machine: pending → completed → refunded.
 *
 * @property int $id
 * @property int $plugin_id
 * @property int $buyer_user_id
 * @property string $license_key
 * @property int $amount_cents
 * @property string $currency
 * @property string|null $payment_id
 * @property string $status
 * @property Carbon|null $purchased_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class PluginPurchase extends Model
{
    protected $table = 'arqel_plugin_purchases';

    /** @var list<string> */
    protected $fillable = [
        'plugin_id',
        'buyer_user_id',
        'license_key',
        'amount_cents',
        'currency',
        'payment_id',
        'status',
        'purchased_at',
        'refunded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'status' => 'string',
            'purchased_at' => 'datetime',
            'refunded_at' => 'datetime',
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
    public function buyer(): BelongsTo
    {
        $rawModel = config('auth.providers.users.model');
        $model = is_string($rawModel) && $rawModel !== '' ? $rawModel : Model::class;

        /** @var class-string<Model> $model */
        return $this->belongsTo($model, 'buyer_user_id');
    }

    /**
     * @param  Builder<PluginPurchase>  $query
     * @return Builder<PluginPurchase>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * @param  Builder<PluginPurchase>  $query
     * @return Builder<PluginPurchase>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * @param  Builder<PluginPurchase>  $query
     * @return Builder<PluginPurchase>
     */
    public function scopeRefunded(Builder $query): Builder
    {
        return $query->where('status', 'refunded');
    }
}
