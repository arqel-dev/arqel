<?php

declare(strict_types=1);

use Arqel\Ai\Fields\AiExtractField;
use Arqel\Ai\Fields\AiImageField;
use Arqel\Ai\Fields\AiTextField;

/**
 * Round 15 i18n regression for the AI field default button labels.
 *
 * The PHP-supplied default button face (serialized via getTypeSpecificProps()
 * as `buttonLabel`) must route through `arqel::messages.ai.fields.*.button`
 * so it honours the request locale. English values are pinned to the original
 * literals for accessible-name stability; pt_BR proves real localization.
 * An explicit ->buttonLabel() override must still win verbatim.
 */
it('serializes the localized default text-field button label in pt_BR', function (): void {
    app()->setLocale('pt_BR');

    $props = (new AiTextField('summary'))->getTypeSpecificProps();

    expect($props['buttonLabel'])->toBe('Gerar com IA');
});

it('serializes the localized default extract-field button label in pt_BR', function (): void {
    app()->setLocale('pt_BR');

    $props = (new AiExtractField('data'))->getTypeSpecificProps();

    expect($props['buttonLabel'])->toBe('Extrair com IA');
});

it('serializes the localized default image-field button label in pt_BR', function (): void {
    app()->setLocale('pt_BR');

    $props = (new AiImageField('cover'))->getTypeSpecificProps();

    expect($props['buttonLabel'])->toBe('Analisar com IA');
});

it('keeps the English default button labels identical to the original literals', function (): void {
    app()->setLocale('en');

    expect((new AiTextField('summary'))->getTypeSpecificProps()['buttonLabel'])
        ->toBe('Generate with AI')
        ->and((new AiExtractField('data'))->getTypeSpecificProps()['buttonLabel'])
        ->toBe('Extract with AI')
        ->and((new AiImageField('cover'))->getTypeSpecificProps()['buttonLabel'])
        ->toBe('Analyze with AI');
});

it('lets an explicit buttonLabel override win over the localized default', function (): void {
    app()->setLocale('pt_BR');

    $field = (new AiTextField('summary'))->buttonLabel('Custom label');

    expect($field->getButtonLabel())->toBe('Custom label')
        ->and($field->getTypeSpecificProps()['buttonLabel'])->toBe('Custom label');
});
