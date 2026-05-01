<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests\Fixtures;

use Arqel\Ai\Fields\AiExtractField;
use Arqel\Core\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource fixture com um único `AiExtractField` para validar o
 * `AiExtractController`.
 */
final class FakeAiExtractResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = FakeAiResourceModel::class;

    public static ?string $slug = 'ai-invoices';

    public function fields(): array
    {
        return [
            (new AiExtractField('extracted'))
                ->sourceField('raw_text')
                ->extractTo([
                    'invoice_number' => 'Invoice number from the document',
                    'date' => 'Invoice date in YYYY-MM-DD format',
                ])
                ->provider('fake'),
        ];
    }
}
