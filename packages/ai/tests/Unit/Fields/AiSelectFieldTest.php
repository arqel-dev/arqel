<?php

declare(strict_types=1);

use Arqel\Ai\AiManager;
use Arqel\Ai\Fields\AiSelectField;
use Arqel\Ai\Tests\Fixtures\ConfigurableFakeProvider;

it('instantiates via constructor with a name and exposes fluent setters', function (): void {
    $field = new AiSelectField('category');

    expect($field)->toBeInstanceOf(AiSelectField::class)
        ->and($field->getName())->toBe('category')
        ->and($field->getType())->toBe('aiSelect')
        ->and($field->getComponent())->toBe('AiSelectInput');

    expect($field->options(['tech' => 'Technology']))->toBe($field)
        ->and($field->classifyFromFields(['title']))->toBe($field)
        ->and($field->prompt('Classify {title}'))->toBe($field)
        ->and($field->provider('fake'))->toBe($field)
        ->and($field->aiOptions(['temperature' => 0.0]))->toBe($field)
        ->and($field->fallbackOption('tech'))->toBe($field);
});

it('classifies via AiManager and returns a valid option key', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = 'tech';
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    $field = (new AiSelectField('category'))
        ->options(['tech' => 'Technology', 'finance' => 'Finance'])
        ->classifyFromFields(['title'])
        ->prompt('Classify the title: {title}')
        ->provider('fake');

    $result = $field->classify(['title' => 'Best new laptops 2026']);

    expect($result)->toBe('tech')
        ->and($fake->completeCalls)->toBe(1)
        ->and($fake->lastPrompt)->toContain('Classify the title: Best new laptops 2026')
        ->and($fake->lastPrompt)->toContain('Available categories')
        ->and($fake->lastPrompt)->toContain('- tech: Technology')
        ->and($fake->lastPrompt)->toContain('- finance: Finance');
});

it('returns null when the AI key is invalid and fallbackOption is null', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = 'unknown-bucket';
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    $field = (new AiSelectField('category'))
        ->options(['tech' => 'Technology', 'finance' => 'Finance'])
        ->prompt('p')
        ->provider('fake');

    expect($field->classify([]))->toBeNull();
});

it('returns the fallbackOption when defined and AI returns invalid key', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = 'totally-bogus';
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    $field = (new AiSelectField('category'))
        ->options(['tech' => 'Technology', 'finance' => 'Finance'])
        ->fallbackOption('finance')
        ->prompt('p')
        ->provider('fake');

    expect($field->classify([]))->toBe('finance');
});

it('normalizes lowercase, trim and strip quotes/punctuation', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = ' "Tech" ';
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    $field = (new AiSelectField('category'))
        ->options(['tech' => 'Technology'])
        ->prompt('p')
        ->provider('fake');

    expect($field->classify([]))->toBe('tech');
});

it('also normalizes trailing punctuation like dots and semicolons', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = "FINANCE.\n";
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    $field = (new AiSelectField('category'))
        ->options(['tech' => 'Technology', 'finance' => 'Finance'])
        ->prompt('p')
        ->provider('fake');

    expect($field->classify([]))->toBe('finance');
});

it('passes formData to a closure prompt', function (): void {
    $fake = new ConfigurableFakeProvider('fake');
    $fake->textToReturn = 'tech';
    app()->instance(AiManager::class, new AiManager(['fake' => $fake]));

    $captured = null;
    $field = (new AiSelectField('category'))
        ->options(['tech' => 'Technology'])
        ->prompt(function (array $formData) use (&$captured): string {
            $captured = $formData;

            $title = $formData['title'] ?? '';

            return 'closure prompt for '.(is_scalar($title) ? (string) $title : '');
        })
        ->provider('fake');

    $field->classify(['title' => 'Hello', 'description' => 'World']);

    expect($captured)->toBe(['title' => 'Hello', 'description' => 'World'])
        ->and($fake->lastPrompt)->toContain('closure prompt for Hello');
});

it('returns the correct shape from getTypeSpecificProps and never leaks the prompt', function (): void {
    $field = (new AiSelectField('category'))
        ->options(['tech' => 'Technology', 'finance' => 'Finance'])
        ->classifyFromFields(['title', 'description'])
        ->prompt('SECRET PROMPT TEMPLATE {title}')
        ->provider('claude')
        ->fallbackOption('tech');

    $props = $field->getTypeSpecificProps();

    expect($props)->toBe([
        'options' => ['tech' => 'Technology', 'finance' => 'Finance'],
        'classifyFromFields' => ['title', 'description'],
        'provider' => 'claude',
        'fallbackOption' => 'tech',
        'hasContextFields' => true,
    ]);

    $encoded = (string) json_encode($props);
    expect($encoded)->not->toContain('SECRET PROMPT');
});
