<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests\Fixtures;

use Arqel\Ai\Fields\AiSelectField;
use Arqel\Core\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Regression fixture for #104: declares its `AiSelectField` ONLY inside
 * form() (layout-aware), with an empty flat fields().
 *
 * The classify controller must resolve the field via effectiveFields().
 */
final class FormOnlyAiSelectResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = FakeAiResourceModel::class;

    public static ?string $slug = 'form-only-ai-posts';

    public function fields(): array
    {
        return [];
    }

    public function form(): mixed
    {
        return new StubForm([
            (new AiSelectField('category'))
                ->options([
                    'tech' => 'Technology',
                    'finance' => 'Finance',
                    'health' => 'Health',
                ])
                ->classifyFromFields(['title', 'description'])
                ->prompt('Classify based on Title: {title}, Description: {description}')
                ->provider('fake'),
        ]);
    }
}
