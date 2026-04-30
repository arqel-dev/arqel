<?php

declare(strict_types=1);

use Arqel\Ai\AiManager;
use Arqel\Ai\Fields\AiTextField;
use Arqel\Ai\Tests\Fixtures\FakeProvider;

it('instantiates via constructor with a name', function (): void {
    $field = new AiTextField('summary');

    expect($field)->toBeInstanceOf(AiTextField::class)
        ->and($field->getName())->toBe('summary')
        ->and($field->getType())->toBe('aiText')
        ->and($field->getComponent())->toBe('AiTextInput');
});

it('exposes fluent setters returning the same instance', function (): void {
    $field = new AiTextField('bio');

    expect($field->prompt('write {name}'))->toBe($field)
        ->and($field->provider('fake'))->toBe($field)
        ->and($field->aiOptions(['temperature' => 0.5]))->toBe($field)
        ->and($field->contextFields(['name', 'role']))->toBe($field)
        ->and($field->maxLength(120))->toBe($field)
        ->and($field->buttonLabel('Generate bio'))->toBe($field);
});

it('interpolates {fieldName} placeholders when resolving prompt', function (): void {
    $field = (new AiTextField('bio'))
        ->prompt('Write a bio for {name}, role {role}');

    expect($field->resolvePrompt(['name' => 'Ada', 'role' => 'Engineer']))
        ->toBe('Write a bio for Ada, role Engineer');
});

it('passes formData to a closure prompt and returns its result', function (): void {
    $field = (new AiTextField('bio'))
        ->prompt(function (array $formData): string {
            $name = $formData['name'] ?? 'world';

            return 'Hello '.(is_string($name) ? $name : 'world');
        });

    expect($field->resolvePrompt(['name' => 'Grace']))->toBe('Hello Grace');
});

it('calls AiManager::complete via container when generate() runs', function (): void {
    $fake = new FakeProvider('fake');
    $manager = new AiManager(['fake' => $fake]);
    app()->instance(AiManager::class, $manager);

    $field = (new AiTextField('bio'))
        ->prompt('Bio for {name}')
        ->provider('fake');

    $text = $field->generate(['name' => 'Linus']);

    expect($text)->toBe('echo:Bio for Linus')
        ->and($fake->completeCalls)->toBe(1)
        ->and($fake->lastPrompt)->toBe('Bio for Linus');
});

it('truncates the generated text when it exceeds maxLength', function (): void {
    $fake = new class extends FakeProvider {
        public function complete(string $prompt, array $options = []): \Arqel\Ai\AiCompletionResult
        {
            $this->completeCalls++;
            $this->lastPrompt = $prompt;

            return new \Arqel\Ai\AiCompletionResult(str_repeat('x', 200), 5, 5, 0.001, 'fake-model', []);
        }
    };
    $manager = new AiManager(['fake' => $fake]);
    app()->instance(AiManager::class, $manager);

    $field = (new AiTextField('bio'))
        ->prompt('any')
        ->provider('fake')
        ->maxLength(50);

    $text = $field->generate([]);

    expect(mb_strlen($text))->toBe(50)
        ->and(str_ends_with($text, '…'))->toBeTrue();
});

it('omits the prompt template from getTypeSpecificProps()', function (): void {
    $field = (new AiTextField('bio'))
        ->prompt('SECRET PROMPT WITH BUSINESS LOGIC {name}')
        ->provider('claude')
        ->buttonLabel('Generate')
        ->maxLength(500)
        ->contextFields(['name']);

    $props = $field->getTypeSpecificProps();

    $encoded = (string) json_encode($props);

    expect($encoded)->not->toContain('SECRET PROMPT')
        ->and($props)->toHaveKey('provider', 'claude')
        ->and($props)->toHaveKey('buttonLabel', 'Generate')
        ->and($props)->toHaveKey('maxLength', 500)
        ->and($props)->toHaveKey('hasContextFields', true);
});

it('returns empty string when prompt is null', function (): void {
    $field = new AiTextField('bio');

    expect($field->resolvePrompt([]))->toBe('');
});
