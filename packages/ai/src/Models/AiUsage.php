<?php

declare(strict_types=1);

namespace Arqel\Ai\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Linha-detalhe de uso AI persistida pelo `CostTracker`. Cada registro
 * representa uma única chamada bem-sucedida (`complete`/`chat`/`embed`),
 * permitindo agregações por usuário, provider e modelo para enforcement
 * de limites diários e auditoria de custo.
 *
 * @property int|null $user_id
 * @property string $provider
 * @property string|null $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property float|null $cost_usd
 * @property string|null $prompt_hash
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AiUsage extends Model
{
    protected $table = 'ai_usage';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'model',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'prompt_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_usd' => 'float',
            'input_tokens' => 'int',
            'output_tokens' => 'int',
        ];
    }
}
