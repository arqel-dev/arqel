<?php

declare(strict_types=1);

use Arqel\Marketplace\Http\Requests\SubmitPluginRequest;
use Illuminate\Support\Facades\Validator;

/**
 * SubmitPluginRequest must localize :attribute placeholders and rule
 * messages so plugin-submission errors never surface raw snake_case English
 * (i18n round 7, g5).
 */
function validateSubmission(array $data): Illuminate\Contracts\Validation\Validator
{
    $request = new SubmitPluginRequest;

    return Validator::make(
        $data,
        $request->rules(),
        $request->messages(),
        $request->attributes(),
    );
}

it('exposes localized attribute names instead of raw snake_case', function (): void {
    app()->setLocale('en');

    $request = new SubmitPluginRequest;
    $attributes = $request->attributes();

    expect($attributes)
        ->toHaveKey('composer_package')
        ->toHaveKey('github_url')
        ->and($attributes['composer_package'])->toBe('Composer package')
        ->and($attributes['github_url'])->toBe('GitHub URL');
});

it('renders the localized attribute name in a required error (en)', function (): void {
    app()->setLocale('en');

    $validator = validateSubmission([]);

    $message = $validator->errors()->first('composer_package');

    expect($message)
        ->not->toContain('composer_package')
        ->toContain('Composer package');
});

it('renders the localized custom rule message for composer_package regex (en)', function (): void {
    app()->setLocale('en');

    $validator = validateSubmission([
        'slug' => 'valid-slug',
        'composer_package' => 'not-a-package',
        'github_url' => 'https://github.com/x/y',
        'type' => 'field',
        'name' => 'Some Plugin',
        'description' => str_repeat('a', 25),
    ]);

    expect($validator->errors()->first('composer_package'))
        ->toBe('The Composer package must follow the vendor/name format.');
});

it('renders pt_BR attribute names and rule messages when locale is pt_BR', function (): void {
    app()->setLocale('pt_BR');

    $request = new SubmitPluginRequest;

    expect($request->attributes()['composer_package'])->toBe('pacote Composer')
        ->and($request->attributes()['github_url'])->toBe('URL do GitHub')
        ->and($request->messages()['composer_package.regex'])
        ->toBe('O pacote Composer deve seguir o formato vendor/nome.')
        ->and($request->messages()['type.in'])
        ->toBe('O tipo selecionado é inválido.');
});
