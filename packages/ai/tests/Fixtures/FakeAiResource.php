<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests\Fixtures;

use Arqel\Ai\Fields\AiTextField;
use Arqel\Core\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource fixture com um único `AiTextField` para validar o
 * `AiGenerateController`.
 */
final class FakeAiResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = FakeAiResourceModel::class;

    public static ?string $slug = 'ai-articles';

    public function fields(): array
    {
        return [
            (new AiTextField('summary'))
                ->prompt('Summarize {title}')
                ->provider('fake')
                ->maxLength(500),
        ];
    }
}
