<?php

declare(strict_types=1);

use Arqel\Ai\AiManager;
use Arqel\Ai\Fields\AiImageField;
use Arqel\Ai\Tests\Fixtures\ConfigurableFakeProvider;

it('passes imageUrl option when value looks like an absolute URL', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textsToReturn = ['ok'];
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    (new AiImageField('cover'))
        ->aiAnalysis(['k' => 'desc'])
        ->provider('fake')
        ->analyze('https://example.com/x.jpg');

    expect($fake->optionsHistory[0]['imageUrl'] ?? null)->toBe('https://example.com/x.jpg')
        ->and($fake->optionsHistory[0]['imageBase64'] ?? null)->toBeNull();
});

it('passes imageBase64 option when value is a data URI', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textsToReturn = ['ok'];
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    (new AiImageField('cover'))
        ->aiAnalysis(['k' => 'desc'])
        ->provider('fake')
        ->analyze('data:image/png;base64,QUFB');

    expect($fake->optionsHistory[0]['imageBase64'] ?? null)->toBe('data:image/png;base64,QUFB')
        ->and($fake->optionsHistory[0]['imageUrl'] ?? null)->toBeNull();
});

it('keeps the legacy `image` option for backwards-compat', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textsToReturn = ['ok'];
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    (new AiImageField('cover'))
        ->aiAnalysis(['k' => 'desc'])
        ->provider('fake')
        ->analyze('https://example.com/y.png');

    expect($fake->optionsHistory[0]['image'] ?? null)->toBe('https://example.com/y.png');
});
