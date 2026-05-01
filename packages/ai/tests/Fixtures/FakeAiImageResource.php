<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests\Fixtures;

use Arqel\Ai\Fields\AiImageField;
use Arqel\Core\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource fixture com um único `AiImageField` para validar o
 * `AiAnalyzeImageController`.
 */
final class FakeAiImageResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = FakeAiResourceModel::class;

    public static ?string $slug = 'ai-photos';

    public function fields(): array
    {
        return [
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
        ];
    }
}
