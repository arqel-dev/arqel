<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests\Fixtures;

use Arqel\Ai\Fields\AiExtractField;
use Arqel\Core\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Regression fixture for #104: declares its `AiExtractField` ONLY inside
 * form() (layout-aware), with an empty flat fields().
 *
 * The extract controller must resolve the field via effectiveFields().
 */
final class FormOnlyAiExtractResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = FakeAiResourceModel::class;

    public static ?string $slug = 'form-only-ai-invoices';

    public function fields(): array
    {
        return [];
    }

    public function form(): mixed
    {
        return new StubForm([
            (new AiExtractField('extracted'))
                ->sourceField('raw_text')
                ->extractTo([
                    'invoice_number' => 'Invoice number from the document',
                    'date' => 'Invoice date in YYYY-MM-DD format',
                ])
                ->provider('fake'),
        ]);
    }
}
