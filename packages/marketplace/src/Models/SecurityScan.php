<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registro de scan de segurança executado contra um Plugin (MKTPLC-009).
 *
 * Status state-machine: `pending` → `running` → (`passed`|`flagged`|`failed`).
 *
 * @property int $id
 * @property int $plugin_id
 * @property Carbon|null $scan_started_at
 * @property Carbon|null $scan_completed_at
 * @property string $status
 * @property array<int, array<string, mixed>>|null $findings
 * @property string|null $severity
 * @property string $scanner_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class SecurityScan extends Model
{
    protected $table = 'arqel_plugin_security_scans';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plugin_id',
        'scan_started_at',
        'scan_completed_at',
        'status',
        'findings',
        'severity',
        'scanner_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scan_started_at' => 'datetime',
            'scan_completed_at' => 'datetime',
            'findings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Plugin, $this>
     */
    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }
}
