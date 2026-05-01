<?php

declare(strict_types=1);

use Arqel\Ai\AiManager;
use Arqel\Ai\Exceptions\AiException;
use Arqel\Ai\Fields\AiExtractField;
use Arqel\Ai\Tests\Fixtures\ConfigurableFakeProvider;

function makeManagerWithFake(ConfigurableFakeProvider $fake): AiManager
{
    $manager = new AiManager(['fake' => $fake]);
    app()->instance(AiManager::class, $manager);

    return $manager;
}

it('instantiates with a name and reports type/component', function (): void {
    $field = new AiExtractField('extracted');

    expect($field)->toBeInstanceOf(AiExtractField::class)
        ->and($field->getName())->toBe('extracted')
        ->and($field->getType())->toBe('aiExtract')
        ->and($field->getComponent())->toBe('AiExtractInput')
        ->and($field->getButtonLabel())->toBe('Extract with AI');
});

it('exposes fluent setters returning the same instance', function (): void {
    $field = new AiExtractField('extracted');

    expect($field->sourceField('raw_text'))->toBe($field)
        ->and($field->extractTo(['x' => 'desc']))->toBe($field)
        ->and($field->usingJsonMode())->toBe($field)
        ->and($field->usingJsonMode(false))->toBe($field)
        ->and($field->provider('fake'))->toBe($field)
        ->and($field->aiOptions(['temperature' => 0.1]))->toBe($field)
        ->and($field->buttonLabel('Extrair'))->toBe($field);
});

it('extracts decoded JSON returning only declared keys', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = '{"invoice_number":"INV-1","date":"2026-01-02"}';
    makeManagerWithFake($fake);

    $field = (new AiExtractField('extracted'))
        ->sourceField('raw_text')
        ->extractTo([
            'invoice_number' => 'Invoice number from the document',
            'date' => 'Invoice date in YYYY-MM-DD format',
        ])
        ->provider('fake');

    $result = $field->extract('Invoice INV-1 dated 2026-01-02');

    expect($result)->toBe([
        'invoice_number' => 'INV-1',
        'date' => '2026-01-02',
    ])->and($fake->completeCalls)->toBe(1);
});

it('filters extra keys returned by the AI silently', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = '{"invoice_number":"INV-2","date":"2026-02-02","total":99.9,"vendor":"ACME"}';
    makeManagerWithFake($fake);

    $field = (new AiExtractField('extracted'))
        ->extractTo([
            'invoice_number' => 'Invoice number',
            'date' => 'Date',
        ])
        ->provider('fake');

    $result = $field->extract('any');

    expect($result)->toHaveKeys(['invoice_number', 'date'])
        ->and($result)->not->toHaveKey('total')
        ->and($result)->not->toHaveKey('vendor');
});

it('fills missing keys with null', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = '{"invoice_number":"INV-3"}';
    makeManagerWithFake($fake);

    $field = (new AiExtractField('extracted'))
        ->extractTo([
            'invoice_number' => 'Invoice number',
            'date' => 'Date',
            'total' => 'Total amount',
        ])
        ->provider('fake');

    $result = $field->extract('any');

    expect($result)->toBe([
        'invoice_number' => 'INV-3',
        'date' => null,
        'total' => null,
    ]);
});

it('falls back to regex extraction when prose surrounds the JSON', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = "Sure, here's the JSON:\n{\"invoice_number\":\"INV-4\",\"date\":\"2026-04-04\"}\nLet me know if you need anything else.";
    makeManagerWithFake($fake);

    $field = (new AiExtractField('extracted'))
        ->extractTo([
            'invoice_number' => 'Invoice number',
            'date' => 'Date',
        ])
        ->provider('fake');

    $result = $field->extract('any');

    expect($result)->toBe([
        'invoice_number' => 'INV-4',
        'date' => '2026-04-04',
    ]);
});

it('throws AiException when output is not valid JSON even after fallback', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = 'definitely not JSON at all';
    makeManagerWithFake($fake);

    $field = (new AiExtractField('extracted'))
        ->extractTo(['invoice_number' => 'Invoice number'])
        ->provider('fake');

    $field->extract('any');
})->throws(AiException::class, 'Failed to parse AI response as JSON');

it('forwards json_mode option to the manager when usingJsonMode is enabled', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = '{"x":"y"}';

    $captured = null;
    $spy = new class('fake') extends ConfigurableFakeProvider
    {
        /** @var array<string, mixed>|null */
        public ?array $lastOptions = null;

        public function complete(string $prompt, array $options = []): \Arqel\Ai\AiCompletionResult
        {
            $this->lastOptions = $options;

            return parent::complete($prompt, $options);
        }
    };
    $spy->textToReturn = '{"x":"y"}';
    $manager = new AiManager(['fake' => $spy]);
    app()->instance(AiManager::class, $manager);

    $field = (new AiExtractField('extracted'))
        ->extractTo(['x' => 'desc'])
        ->usingJsonMode()
        ->provider('fake');

    $field->extract('input');

    expect($spy->lastOptions)->toHaveKey('json_mode', true)
        ->and($spy->lastOptions)->toHaveKey('provider', 'fake');
});

it('does not include extractTo descriptions in getTypeSpecificProps', function (): void {
    $field = (new AiExtractField('extracted'))
        ->sourceField('raw_text')
        ->extractTo([
            'invoice_number' => 'A super-secret business rule about invoices',
            'date' => 'Another internal description',
        ])
        ->usingJsonMode()
        ->provider('claude');

    $props = $field->getTypeSpecificProps();

    expect($props)->toBe([
        'sourceField' => 'raw_text',
        'targetFields' => ['invoice_number', 'date'],
        'buttonLabel' => 'Extract with AI',
        'usingJsonMode' => true,
        'provider' => 'claude',
    ]);

    $encoded = (string) json_encode($props);
    expect($encoded)->not->toContain('super-secret')
        ->and($encoded)->not->toContain('Another internal description');
});
