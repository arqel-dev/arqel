<?php

declare(strict_types=1);

use Arqel\FieldsAdvanced\Types\MarkdownField;

it('exposes the correct type and component for MarkdownField', function (): void {
    $field = new MarkdownField('content');

    expect($field->getType())->toBe('markdown')
        ->and($field->getComponent())->toBe('MarkdownInput');
});

it('ships the canonical default state', function (): void {
    $props = (new MarkdownField('content'))->getTypeSpecificProps();

    expect($props)->toBe([
        'preview' => true,
        'previewMode' => 'side-by-side',
        'toolbar' => true,
        'rows' => 10,
        'fullscreen' => true,
        'syncScroll' => true,
    ]);
});

it('toggles preview via preview()', function (): void {
    $defaultOn = new MarkdownField('content');
    $explicitOff = (new MarkdownField('content'))->preview(false);
    $reEnabled = (new MarkdownField('content'))->preview(false)->preview();

    expect($defaultOn->getTypeSpecificProps()['preview'])->toBeTrue()
        ->and($explicitOff->getTypeSpecificProps()['preview'])->toBeFalse()
        ->and($reEnabled->getTypeSpecificProps()['preview'])->toBeTrue();
});

it('honours the three canonical preview modes via constants', function (): void {
    $sideBySide = (new MarkdownField('content'))->previewMode(MarkdownField::PREVIEW_MODE_SIDE_BY_SIDE);
    $tab = (new MarkdownField('content'))->previewMode(MarkdownField::PREVIEW_MODE_TAB);
    $popup = (new MarkdownField('content'))->previewMode(MarkdownField::PREVIEW_MODE_POPUP);

    expect($sideBySide->getTypeSpecificProps()['previewMode'])->toBe('side-by-side')
        ->and($tab->getTypeSpecificProps()['previewMode'])->toBe('tab')
        ->and($popup->getTypeSpecificProps()['previewMode'])->toBe('popup');
});

it('falls back to side-by-side on unknown preview mode', function (): void {
    $unknown = (new MarkdownField('content'))->previewMode('inline');
    $empty = (new MarkdownField('content'))->previewMode('');
    $bogus = (new MarkdownField('content'))->previewMode('SIDE-BY-SIDE');

    expect($unknown->getTypeSpecificProps()['previewMode'])->toBe('side-by-side')
        ->and($empty->getTypeSpecificProps()['previewMode'])->toBe('side-by-side')
        ->and($bogus->getTypeSpecificProps()['previewMode'])->toBe('side-by-side');
});

it('toggles toolbar via toolbar()', function (): void {
    $defaultOn = new MarkdownField('content');
    $explicitOff = (new MarkdownField('content'))->toolbar(false);
    $explicitOn = (new MarkdownField('content'))->toolbar();

    expect($defaultOn->getTypeSpecificProps()['toolbar'])->toBeTrue()
        ->and($explicitOff->getTypeSpecificProps()['toolbar'])->toBeFalse()
        ->and($explicitOn->getTypeSpecificProps()['toolbar'])->toBeTrue();
});

it('clamps rows to a minimum of 3', function (): void {
    $zero = (new MarkdownField('content'))->rows(0);
    $negative = (new MarkdownField('content'))->rows(-7);
    $two = (new MarkdownField('content'))->rows(2);
    $three = (new MarkdownField('content'))->rows(3);
    $valid = (new MarkdownField('content'))->rows(25);

    expect($zero->getTypeSpecificProps()['rows'])->toBe(3)
        ->and($negative->getTypeSpecificProps()['rows'])->toBe(3)
        ->and($two->getTypeSpecificProps()['rows'])->toBe(3)
        ->and($three->getTypeSpecificProps()['rows'])->toBe(3)
        ->and($valid->getTypeSpecificProps()['rows'])->toBe(25);
});

it('defaults rows to 10 when never set', function (): void {
    expect((new MarkdownField('content'))->getTypeSpecificProps()['rows'])->toBe(10);
});

it('toggles fullscreen via fullscreen()', function (): void {
    $defaultOn = new MarkdownField('content');
    $explicitOff = (new MarkdownField('content'))->fullscreen(false);
    $explicitOn = (new MarkdownField('content'))->fullscreen();

    expect($defaultOn->getTypeSpecificProps()['fullscreen'])->toBeTrue()
        ->and($explicitOff->getTypeSpecificProps()['fullscreen'])->toBeFalse()
        ->and($explicitOn->getTypeSpecificProps()['fullscreen'])->toBeTrue();
});

it('toggles syncScroll via syncScroll()', function (): void {
    $defaultOn = new MarkdownField('content');
    $explicitOff = (new MarkdownField('content'))->syncScroll(false);
    $explicitOn = (new MarkdownField('content'))->syncScroll();

    expect($defaultOn->getTypeSpecificProps()['syncScroll'])->toBeTrue()
        ->and($explicitOff->getTypeSpecificProps()['syncScroll'])->toBeFalse()
        ->and($explicitOn->getTypeSpecificProps()['syncScroll'])->toBeTrue();
});

it('serialises the full type-specific props payload end-to-end', function (): void {
    $field = (new MarkdownField('content'))
        ->preview(false)
        ->previewMode(MarkdownField::PREVIEW_MODE_TAB)
        ->toolbar(false)
        ->rows(20)
        ->fullscreen(false)
        ->syncScroll(false);

    expect($field->getTypeSpecificProps())->toBe([
        'preview' => false,
        'previewMode' => 'tab',
        'toolbar' => false,
        'rows' => 20,
        'fullscreen' => false,
        'syncScroll' => false,
    ]);
});
