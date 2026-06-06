<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests\Fixtures;

use Arqel\Ai\Fields\AiImageField;
use Arqel\Core\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Regression fixture for #104: declares its `AiImageField` ONLY inside
 * form() (layout-aware), with an empty flat fields().
 *
 * The analyze-image controller must resolve the field via effectiveFields().
 */
final class FormOnlyAiImageResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = FakeAiResourceModel::class;

    public static ?string $slug = 'form-only-ai-photos';

    public function fields(): array
    {
        return [];
    }

    public function form(): mixed
    {
        return new StubForm([
            (new AiImageField('cover'))
                ->aiAnalysis([
                    'alt_text' => 'Describe this image in one sentence.',
                    'tags' => 'Extract 5 SEO tags as comma-separated values.',
                ])
                ->populateFields([
                    'alt_text' => 'cover_alt',
                    'tags' => 'cover_tags',
                ])
                ->provider('fake'),
        ]);
    }
}
