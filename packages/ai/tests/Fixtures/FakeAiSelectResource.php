<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests\Fixtures;

use Arqel\Ai\Fields\AiSelectField;
use Arqel\Core\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource fixture com um único `AiSelectField` para validar o
 * `AiClassifyController`.
 */
final class FakeAiSelectResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = FakeAiResourceModel::class;

    public static ?string $slug = 'ai-posts';

    public function fields(): array
    {
        return [
            (new AiSelectField('category'))
                ->options([
                    'tech' => 'Technology',
                    'finance' => 'Finance',
                    'health' => 'Health',
                ])
                ->classifyFromFields(['title', 'description'])
                ->prompt('Classify based on Title: {title}, Description: {description}')
                ->provider('fake'),
        ];
    }
}
