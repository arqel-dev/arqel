<?php

declare(strict_types=1);

use Arqel\Actions\Action;
use Arqel\Actions\Types\BulkAction;
use Arqel\Actions\Types\HeaderAction;
use Arqel\Actions\Types\RowAction;
use Arqel\Actions\Types\ToolbarAction;
use Illuminate\Support\Collection;

it('derives a Title-case label from the snake_case name', function (): void {
    expect(RowAction::make('publish_now')->getLabel())->toBe('Publish Now');
});

it('chains fluent setters and serialises a row action', function (): void {
    $action = RowAction::make('publish')
        ->icon('check')
        ->color(Action::COLOR_SUCCESS)
        ->variant(Action::VARIANT_OUTLINE)
        ->tooltip('Publish this record')
        ->successNotification('Published.')
        ->failureNotification('Failed to publish.')
        ->action(fn ($record) => $record);

    $payload = $action->toArray();

    expect($payload)->toMatchArray([
        'name' => 'publish',
        'type' => 'row',
        'label' => 'Publish',
        'icon' => 'check',
        'color' => 'success',
        'variant' => 'outline',
        'method' => 'POST',
        'tooltip' => 'Publish this record',
        'successNotification' => 'Published.',
        'failureNotification' => 'Failed to publish.',
    ])
        ->and($payload)->not->toHaveKey('url')
        ->and($payload)->not->toHaveKey('confirmation');
});

it('switches to URL mode and clears the callback (XOR)', function (): void {
    $action = RowAction::make('docs')->action(fn () => null)->url('/docs', 'GET');

    expect($action->hasCallback())->toBeFalse()
        ->and($action->hasUrl())->toBeTrue()
        ->and($action->getMethod())->toBe('GET')
        ->and($action->resolveUrl())->toBe('/docs');
});

it('resolves a Closure URL with the record', function (): void {
    $action = RowAction::make('view')->url(fn ($r) => "/posts/{$r['id']}");

    expect($action->resolveUrl(['id' => 42]))->toBe('/posts/42');
});

it('respects visible/disabled/hidden flags', function (): void {
    $hidden = RowAction::make('x')->hidden();
    $invisible = RowAction::make('x')->visible(fn ($r) => $r === 'show');
    $disabled = RowAction::make('x')->disabled(fn ($r) => $r === 'lock');

    expect($hidden->isVisibleFor())->toBeFalse()
        ->and($invisible->isVisibleFor('hide'))->toBeFalse()
        ->and($invisible->isVisibleFor('show'))->toBeTrue()
        ->and($disabled->isDisabledFor('lock'))->toBeTrue()
        ->and($disabled->isDisabledFor('open'))->toBeFalse();
});

it('confirmation modal config is omitted when not required', function (): void {
    expect(RowAction::make('x')->getConfirmationConfig())->toBeNull();
});

it('confirmation: modalHeading/Description/RequiresText auto-enable confirmation', function (): void {
    $action = RowAction::make('delete')
        ->modalHeading('Sure?')
        ->modalDescription('Truly final.')
        ->modalConfirmationRequiresText('DELETE')
        ->modalSubmitButtonLabel('Yes, delete')
        ->modalColor(Action::MODAL_COLOR_DESTRUCTIVE);

    $config = $action->getConfirmationConfig();

    expect($action->isRequiringConfirmation())->toBeTrue()
        ->and($config)->toBe([
            'heading' => 'Sure?',
            'description' => 'Truly final.',
            'color' => 'destructive',
            'requiresText' => 'DELETE',
            'submitLabel' => 'Yes, delete',
            'cancelLabel' => 'Cancel',
        ]);
});

it('confirmation: invalid modal color falls back to destructive', function (): void {
    $action = RowAction::make('x')->requiresConfirmation()->modalColor('rainbow');

    expect($action->getConfirmationConfig()['color'])->toBe('destructive');
});

it('authorize: callback gates execution', function (): void {
    $action = RowAction::make('x')->authorize(fn ($u, $r) => $r === 'allow');

    expect($action->canBeExecutedBy(null, 'allow'))->toBeTrue()
        ->and($action->canBeExecutedBy(null, 'deny'))->toBeFalse();
});

it('authorize: defaults to true when no callback is set', function (): void {
    expect(RowAction::make('x')->canBeExecutedBy(null))->toBeTrue();
});

it('execute: invokes the callback with record + data', function (): void {
    $action = RowAction::make('save')->action(fn ($record, $data) => [$record, $data]);

    expect($action->execute(['id' => 1], ['note' => 'hi']))->toBe([['id' => 1], ['note' => 'hi']]);
});

it('execute: returns null when no callback is configured', function (): void {
    expect(RowAction::make('x')->execute('record'))->toBeNull();
});

it('toolbar/header types serialise their type key', function (): void {
    expect(ToolbarAction::make('create')->toArray()['type'])->toBe('toolbar')
        ->and(HeaderAction::make('archive')->toArray()['type'])->toBe('header');
});

it('BulkAction: chunks the selection and calls the callback per chunk', function (): void {
    $records = Collection::make(range(1, 250));
    $seen = [];

    BulkAction::make('process')
        ->chunkSize(100)
        ->action(function ($chunk) use (&$seen) {
            $seen[] = $chunk->count();
        })
        ->execute($records);

    expect($seen)->toBe([100, 100, 50]);
});

it('BulkAction: clamps chunkSize to ≥ 1', function (): void {
    expect(BulkAction::make('x')->chunkSize(0)->getChunkSize())->toBe(1);
});

it('BulkAction: deselectRecordsAfterCompletion default true', function (): void {
    $action = BulkAction::make('x');

    expect($action->shouldDeselectAfterCompletion())->toBeTrue()
        ->and($action->deselectRecordsAfterCompletion(false)->shouldDeselectAfterCompletion())->toBeFalse();
});
