<?php

declare(strict_types=1);

use Arqel\Actions\Types\RowAction;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;

it('authorize(string) gates on a Gate ability against the record', function (): void {
    Gate::define('refund', fn (?Authenticatable $user, mixed $record): bool => is_object($record) && ($record->refundable ?? false) === true);

    $action = RowAction::make('refund')->authorize('refund');

    expect($action->canBeExecutedBy(new User, (object) ['refundable' => true]))->toBeTrue()
        ->and($action->canBeExecutedBy(new User, (object) ['refundable' => false]))->toBeFalse();
});

it('authorize(string) denies when the Gate denies', function (): void {
    Gate::define('ban-user', fn (?Authenticatable $user): bool => false);

    $action = RowAction::make('ban-user')->authorize('ban-user');

    expect($action->canBeExecutedBy(new User, (object) ['id' => 1]))->toBeFalse();
});

it('authorize(string) allows when the Gate allows', function (): void {
    Gate::define('approve', fn (?Authenticatable $user): bool => true);

    $action = RowAction::make('approve')->authorize('approve');

    expect($action->canBeExecutedBy(new User, (object) ['id' => 1]))->toBeTrue();
});

it('authorize(string) checks the ability with a null record for record-less actions', function (): void {
    // Laravel's Gate treats a null target as "no extra arguments", so a
    // record-less ability gate only receives the user.
    Gate::define('export', fn (?Authenticatable $user, mixed $record = null): bool => $record === null);

    $action = RowAction::make('export')->authorize('export');

    expect($action->canBeExecutedBy(new User))->toBeTrue();
});

it('authorize(Closure) still works (no regression)', function (): void {
    $action = RowAction::make('approve')->authorize(
        fn (?Authenticatable $user, mixed $record): bool => $user !== null && is_array($record) && ($record['status'] ?? null) === 'pending',
    );

    expect($action->canBeExecutedBy(new User, ['status' => 'pending']))->toBeTrue()
        ->and($action->canBeExecutedBy(new User, ['status' => 'approved']))->toBeFalse();
});

it('action with no authorize() keeps the permissive default', function (): void {
    $action = RowAction::make('publish');

    expect($action->canBeExecutedBy(null))->toBeTrue()
        ->and($action->canBeExecutedBy(new User))->toBeTrue();
});

it('both a string ability and a closure must pass (AND precedence)', function (): void {
    Gate::define('refund', fn (?Authenticatable $user): bool => true);

    $action = RowAction::make('refund')
        ->authorize('refund')
        ->authorize(fn (?Authenticatable $user, mixed $record): bool => is_object($record) && ($record->amount ?? 0) > 0);

    // gate allows + closure true => true
    expect($action->canBeExecutedBy(new User, (object) ['amount' => 10]))->toBeTrue()
        // gate allows + closure false => false
        ->and($action->canBeExecutedBy(new User, (object) ['amount' => 0]))->toBeFalse();

    Gate::define('refund', fn (?Authenticatable $user): bool => false);

    // gate denies + closure true => false
    expect($action->canBeExecutedBy(new User, (object) ['amount' => 10]))->toBeFalse();
});
