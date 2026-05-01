<?php

declare(strict_types=1);

use Arqel\Ai\Prompts\PromptLibrary;

beforeEach(function (): void {
    PromptLibrary::clear();
});

it('summarize prompt mentions max words and includes the input text', function (): void {
    $prompt = PromptLibrary::summarize('The quick brown fox.', 100);

    expect($prompt)->toContain('100')
        ->and($prompt)->toContain('The quick brown fox.');
});

it('translate prompt mentions the target language and includes the input', function (): void {
    $prompt = PromptLibrary::translate('Hello world', 'pt-BR');

    expect($prompt)->toContain('pt-BR')
        ->and($prompt)->toContain('Hello world');
});

it('translate prompt mentions both source and target when source is provided', function (): void {
    $prompt = PromptLibrary::translate('Hello', 'es', 'en');

    expect($prompt)->toContain('from en to es');
});

it('classify prompt with simple list enumerates each category', function (): void {
    $prompt = PromptLibrary::classify('Article body', ['tech', 'finance']);

    expect($prompt)->toContain('tech')
        ->and($prompt)->toContain('finance')
        ->and($prompt)->toContain('Article body');
});

it('classify prompt with associative array uses keys as expected return value', function (): void {
    $prompt = PromptLibrary::classify('Body', ['tech' => 'Technology', 'finance' => 'Finance']);

    expect($prompt)->toContain('tech: Technology')
        ->and($prompt)->toContain('finance: Finance')
        ->and($prompt)->toContain('one of: tech, finance');
});

it('extractJson prompt lists every schema field with its description', function (): void {
    $prompt = PromptLibrary::extractJson('Random text', [
        'name' => 'Full name of the person',
        'email' => 'Primary email address',
    ]);

    expect($prompt)->toContain('- name: Full name of the person')
        ->and($prompt)->toContain('- email: Primary email address')
        ->and($prompt)->toContain('Random text');
});

it('generateSlug prompt is example-aware', function (): void {
    $prompt = PromptLibrary::generateSlug('Arqel Launch Announcement');

    expect($prompt)->toContain('Arqel Launch Announcement')
        ->and($prompt)->toContain('hello-world');
});

it('keywordExtract prompt mentions the requested count', function (): void {
    $prompt = PromptLibrary::keywordExtract('Lorem ipsum dolor sit amet.', 7);

    expect($prompt)->toContain('7')
        ->and($prompt)->toContain('Lorem ipsum');
});

it('tone prompt mentions the requested tone', function (): void {
    $prompt = PromptLibrary::tone('Some message', 'casual');

    expect($prompt)->toContain('casual')
        ->and($prompt)->toContain('Some message');
});

it('proofread prompt focuses on grammar and spelling correction', function (): void {
    $prompt = PromptLibrary::proofread('teh cat sat');

    expect($prompt)->toContain('grammar')
        ->and($prompt)->toContain('spelling')
        ->and($prompt)->toContain('teh cat sat');
});

it('registers and resolves a custom prompt round-trip', function (): void {
    PromptLibrary::register('company_bio', function (array $data): string {
        $name = is_string($data['company_name'] ?? null) ? $data['company_name'] : '';

        return "Bio for {$name}.";
    });

    expect(PromptLibrary::resolve('company_bio', ['company_name' => 'Arqel']))
        ->toBe('Bio for Arqel.');
});

it('register overwrites an existing name without throwing', function (): void {
    PromptLibrary::register('greet', fn (array $data): string => 'first');
    PromptLibrary::register('greet', fn (array $data): string => 'second');

    expect(PromptLibrary::resolve('greet', []))->toBe('second');
});

it('resolve throws InvalidArgumentException when the name is unknown', function (): void {
    PromptLibrary::resolve('does-not-exist', []);
})->throws(InvalidArgumentException::class, 'does-not-exist');

it('has reports true and false correctly', function (): void {
    expect(PromptLibrary::has('foo'))->toBeFalse();

    PromptLibrary::register('foo', fn (array $data): string => 'x');

    expect(PromptLibrary::has('foo'))->toBeTrue();
});

it('clear empties the custom prompt registry', function (): void {
    PromptLibrary::register('a', fn (array $data): string => '1');
    PromptLibrary::register('b', fn (array $data): string => '2');

    PromptLibrary::clear();

    expect(PromptLibrary::has('a'))->toBeFalse()
        ->and(PromptLibrary::has('b'))->toBeFalse();
});

it('passes the data array through to the registered closure', function (): void {
    PromptLibrary::register('echo', function (array $data): string {
        $name = is_string($data['name'] ?? null) ? $data['name'] : 'none';
        $n = is_int($data['n'] ?? null) ? $data['n'] : 0;

        return "name={$name};n={$n}";
    });

    expect(PromptLibrary::resolve('echo', ['name' => 'Diogo', 'n' => 42]))
        ->toBe('name=Diogo;n=42');
});
