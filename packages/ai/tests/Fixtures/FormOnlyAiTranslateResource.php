<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests\Fixtures;

use Arqel\Ai\Fields\AiTranslateField;
use Arqel\Core\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Regression fixture for #104: declares its `AiTranslateField` ONLY inside
 * form() (layout-aware), with an empty flat fields().
 *
 * The translate controller must resolve the field via effectiveFields().
 */
final class FormOnlyAiTranslateResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = FakeAiResourceModel::class;

    public static ?string $slug = 'form-only-ai-pages';

    public function fields(): array
    {
        return [];
    }

    public function form(): mixed
    {
        return new StubForm([
            (new AiTranslateField('description'))
                ->languages(['en', 'pt-BR', 'es'])
                ->defaultLanguage('en')
                ->autoTranslate()
                ->provider('fake'),
        ]);
    }
}
