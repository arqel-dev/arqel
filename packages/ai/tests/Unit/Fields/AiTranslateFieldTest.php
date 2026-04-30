<?php

declare(strict_types=1);

use Arqel\Ai\AiManager;
use Arqel\Ai\Fields\AiTranslateField;
use Arqel\Ai\Tests\Fixtures\FakeProvider;

it('instantiates via constructor with a name', function (): void {
    $field = new AiTranslateField('description');

    expect($field)->toBeInstanceOf(AiTranslateField::class)
        ->and($field->getName())->toBe('description')
        ->and($field->getType())->toBe('aiTranslate')
        ->and($field->getComponent())->toBe('AiTranslateInput');
});

it('exposes fluent setters returning the same instance', function (): void {
    $field = new AiTranslateField('description');

    expect($field->languages(['en', 'pt-BR']))->toBe($field)
        ->and($field->defaultLanguage('en'))->toBe($field)
        ->and($field->autoTranslate())->toBe($field)
        ->and($field->autoTranslate(false))->toBe($field)
        ->and($field->provider('fake'))->toBe($field)
        ->and($field->aiOptions(['temperature' => 0.2]))->toBe($field);
});

it('throws when defaultLanguage is not in the configured languages', function (): void {
    $field = (new AiTranslateField('description'))->languages(['en', 'pt-BR']);

    $field->defaultLanguage('fr');
})->throws(InvalidArgumentException::class, 'Default language [fr]');

it('calls AiManager::complete via container when translate() runs', function (): void {
    $fake = new FakeProvider('fake');
    $manager = new AiManager(['fake' => $fake]);
    app()->instance(AiManager::class, $manager);

    $field = (new AiTranslateField('description'))
        ->languages(['en', 'pt-BR'])
        ->defaultLanguage('en')
        ->provider('fake');

    $translated = $field->translate('Hello world', 'pt-BR', 'en');

    expect($translated)->toBe('echo:Translate the following text from en to pt-BR. Return only the translated text, nothing else.'."\n\n".'Hello world')
        ->and($fake->completeCalls)->toBe(1);
});

it('translates without an explicit source language', function (): void {
    $fake = new FakeProvider('fake');
    $manager = new AiManager(['fake' => $fake]);
    app()->instance(AiManager::class, $manager);

    $field = (new AiTranslateField('description'))
        ->languages(['en', 'pt-BR'])
        ->defaultLanguage('en')
        ->provider('fake');

    $field->translate('Olá mundo', 'en');

    expect($fake->lastPrompt)->toContain('from the source language to en');
});

it('fills only missing or empty languages with translateAll and skips the source', function (): void {
    $fake = new FakeProvider('fake');
    $manager = new AiManager(['fake' => $fake]);
    app()->instance(AiManager::class, $manager);

    $field = (new AiTranslateField('description'))
        ->languages(['en', 'pt-BR', 'es', 'fr'])
        ->defaultLanguage('en')
        ->provider('fake');

    $result = $field->translateAll([
        'en' => 'Hello world',
        'pt-BR' => 'Olá mundo manual',
        'es' => '',
        // 'fr' ausente
    ], 'en');

    expect($result)->toHaveKey('en', 'Hello world')
        ->and($result)->toHaveKey('pt-BR', 'Olá mundo manual')
        ->and($result['es'])->toStartWith('echo:Translate the following text from en to es')
        ->and($result['fr'])->toStartWith('echo:Translate the following text from en to fr')
        ->and($fake->completeCalls)->toBe(2);
});

it('returns the correct shape from getTypeSpecificProps()', function (): void {
    $field = (new AiTranslateField('description'))
        ->languages(['en', 'pt-BR', 'es'])
        ->defaultLanguage('en')
        ->autoTranslate()
        ->provider('claude');

    expect($field->getTypeSpecificProps())->toBe([
        'languages' => ['en', 'pt-BR', 'es'],
        'defaultLanguage' => 'en',
        'autoTranslate' => true,
        'provider' => 'claude',
    ]);
});

it('does not leak any prompt template through getTypeSpecificProps()', function (): void {
    $field = (new AiTranslateField('description'))
        ->languages(['en', 'pt-BR'])
        ->defaultLanguage('en');

    $encoded = (string) json_encode($field->getTypeSpecificProps());

    expect($encoded)->not->toContain('Translate the following text');
});
