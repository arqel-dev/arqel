<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests\Fixtures;

use Arqel\Ai\Fields\AiTranslateField;
use Arqel\Core\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource fixture com um único `AiTranslateField` para validar o
 * `AiTranslateController`.
 */
final class FakeAiTranslateResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = FakeAiResourceModel::class;

    public static ?string $slug = 'ai-pages';

    public function fields(): array
    {
        return [
            (new AiTranslateField('description'))
                ->languages(['en', 'pt-BR', 'es'])
                ->defaultLanguage('en')
                ->autoTranslate()
                ->provider('fake'),
        ];
    }
}
