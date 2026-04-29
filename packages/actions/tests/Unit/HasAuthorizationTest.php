<?php

declare(strict_types=1);

use Arqel\Actions\Types\RowAction;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;

it('canBeExecutedBy defaults to true when no authorize closure is registered', function (): void {
    $action = RowAction::make('publish');

    expect($action->canBeExecutedBy(null))->toBeTrue()
        ->and($action->canBeExecutedBy(new User))->toBeTrue();
});

it('canBeExecutedBy delegates to the registered closure', function (): void {
    $action = RowAction::make('approve')->authorize(
        fn (?Authenticatable $user, mixed $record): bool => $user !== null && is_array($record) && ($record['status'] ?? null) === 'pending',
    );

    expect($action->canBeExecutedBy(new User, ['status' => 'pending']))->toBeTrue()
        ->and($action->canBeExecutedBy(new User, ['status' => 'approved']))->toBeFalse()
        ->and($action->canBeExecutedBy(null, ['status' => 'pending']))->toBeFalse();
});

it('coerces non-bool returns to bool', function (): void {
    $action = RowAction::make('approve')->authorize(
        fn (?Authenticatable $user, mixed $record): mixed => $record,
    );

    expect($action->canBeExecutedBy(new User, 'truthy'))->toBeTrue()
        ->and($action->canBeExecutedBy(new User, 0))->toBeFalse()
        ->and($action->canBeExecutedBy(new User, ''))->toBeFalse();
});

it('passes both user and record through to the closure', function (): void {
    $seen = [];

    $action = RowAction::make('inspect')->authorize(function (?Authenticatable $user, mixed $record) use (&$seen): bool {
        $seen[] = ['user' => $user, 'record' => $record];

        return true;
    });

    $user = new User;
    $action->canBeExecutedBy($user, ['id' => 1]);

    expect($seen)->toHaveCount(1)
        ->and($seen[0]['user'])->toBe($user)
        ->and($seen[0]['record'])->toBe(['id' => 1]);
});
