<?php

declare(strict_types=1);

use Arqel\Ai\AiManager;
use Arqel\Ai\Fields\AiImageField;
use Arqel\Ai\Tests\Fixtures\ConfigurableFakeProvider;

it('instantiates via constructor with a name and exposes type/component', function (): void {
    $field = new AiImageField('cover');

    expect($field)->toBeInstanceOf(AiImageField::class)
        ->and($field->getName())->toBe('cover')
        ->and($field->getType())->toBe('aiImage')
        ->and($field->getComponent())->toBe('AiImageInput');
});

it('exposes fluent setters returning the same instance', function (): void {
    $field = new AiImageField('cover');

    expect($field->aiAnalysis(['alt_text' => 'Describe.']))->toBe($field)
        ->and($field->populateFields(['alt_text' => 'cover_alt']))->toBe($field)
        ->and($field->provider('claude'))->toBe($field)
        ->and($field->aiOptions(['temperature' => 0.1]))->toBe($field)
        ->and($field->acceptedMimes(['image/png']))->toBe($field)
        ->and($field->maxFileSize(5_000_000))->toBe($field)
        ->and($field->buttonLabel('Run'))->toBe($field);
});

it('defaults acceptedMimes/maxFileSize/buttonLabel to sensible values', function (): void {
    $field = new AiImageField('cover');

    expect($field->getAcceptedMimes())->toBe(['image/jpeg', 'image/png', 'image/webp'])
        ->and($field->getMaxFileSize())->toBe(10_485_760)
        ->and($field->getButtonLabel())->toBe('Analyze with AI');
});

it('calls AiManager::complete once per analysis and returns the keyed map', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textsToReturn = ['A red apple.', 'fruit, food, red, healthy, snack'];
    $manager = new AiManager(['fake' => $fake]);
    app()->instance(AiManager::class, $manager);

    $field = (new AiImageField('cover'))
        ->aiAnalysis([
            'alt_text' => 'Describe this image in one sentence.',
            'tags' => 'Extract 5 SEO tags as comma-separated values.',
        ])
        ->provider('fake');

    $results = $field->analyze('https://example.com/apple.jpg');

    expect($fake->completeCalls)->toBe(2)
        ->and($results)->toBe([
            'alt_text' => 'A red apple.',
            'tags' => 'fruit, food, red, healthy, snack',
        ]);
});

it('forwards the image and provider in options to AiManager', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = 'ok';
    $manager = new AiManager(['fake' => $fake]);
    app()->instance(AiManager::class, $manager);

    $field = (new AiImageField('cover'))
        ->aiAnalysis(['alt_text' => 'Describe.'])
        ->provider('fake')
        ->aiOptions(['temperature' => 0.0]);

    $field->analyze('data:image/png;base64,XXXX');

    expect($fake->optionsHistory)->toHaveCount(1)
        ->and($fake->optionsHistory[0])->toMatchArray([
            'image' => 'data:image/png;base64,XXXX',
            'provider' => 'fake',
            'temperature' => 0.0,
        ]);
});

it('does not leak the analysis prompt descriptions through getTypeSpecificProps()', function (): void {
    $field = (new AiImageField('cover'))
        ->aiAnalysis([
            'alt_text' => 'Describe in detail this proprietary scene.',
            'tags' => 'Extract tags using the hidden taxonomy.',
        ])
        ->populateFields(['alt_text' => 'cover_alt'])
        ->provider('claude')
        ->buttonLabel('Run AI');

    $props = $field->getTypeSpecificProps();
    $encoded = (string) json_encode($props);

    expect($props)->toMatchArray([
        'analyses' => ['alt_text', 'tags'],
        'populateFields' => ['alt_text' => 'cover_alt'],
        'provider' => 'claude',
        'acceptedMimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'maxFileSize' => 10_485_760,
        'buttonLabel' => 'Run AI',
    ])
        ->and($encoded)->not->toContain('proprietary scene')
        ->and($encoded)->not->toContain('hidden taxonomy');
});

it('exposes the populate mapping via getPopulateFields()', function (): void {
    $field = (new AiImageField('cover'))
        ->populateFields(['alt_text' => 'cover_alt', 'tags' => 'cover_tags']);

    expect($field->getPopulateFields())->toBe([
        'alt_text' => 'cover_alt',
        'tags' => 'cover_tags',
    ]);
});
