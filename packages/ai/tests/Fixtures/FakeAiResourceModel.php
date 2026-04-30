<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model dummy associado ao `FakeAiResource`. Não é persistido
 * — existe apenas para satisfazer `Resource::getModel()`.
 */
final class FakeAiResourceModel extends Model
{
    protected $table = 'ai_articles';

    protected $guarded = [];
}
