<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests\Fixtures;

use Arqel\Ai\Fields\AiTextField;
use Arqel\Core\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Regression fixture for #104: declares its `AiTextField` ONLY inside
 * form() (layout-aware), with an empty flat fields().
 *
 * The generate controller must resolve the field via effectiveFields()
 * — reading fields() alone would 422 even though the field exists and
 * renders through form(), matching how #94 fixed the search/upload
 * controllers.
 */
final class FormOnlyAiTextResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = FakeAiResourceModel::class;

    public static ?string $slug = 'form-only-ai-articles';

    public function fields(): array
    {
        return [];
    }

    public function form(): mixed
    {
        return new StubForm([
            (new AiTextField('summary'))
                ->prompt('Summarize {title}')
                ->provider('fake')
                ->maxLength(500),
        ]);
    }
}
