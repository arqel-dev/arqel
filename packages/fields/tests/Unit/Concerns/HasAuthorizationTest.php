<?php

declare(strict_types=1);

use Arqel\Fields\Tests\Fixtures\StubField;
use Illuminate\Contracts\Auth\Authenticatable;

function fakeUser(bool $admin = false): Authenticatable
{
    return new class($admin) implements Authenticatable
    {
        public function __construct(public bool $admin) {}

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 1;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): string
        {
            return '';
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }

        public function isAdmin(): bool
        {
            return $this->admin;
        }
    };
}

it('defaults to visible and editable when no callbacks are set', function (): void {
    $field = new StubField('email');

    expect($field->canBeSeenBy())->toBeTrue()
        ->and($field->canBeEditedBy())->toBeTrue();
});

it('honours canSee Closure with the user argument', function (): void {
    $field = (new StubField('admin_notes'))
        ->canSee(fn ($user) => $user !== null && $user->isAdmin());

    expect($field->canBeSeenBy(fakeUser(admin: true)))->toBeTrue()
        ->and($field->canBeSeenBy(fakeUser(admin: false)))->toBeFalse()
        ->and($field->canBeSeenBy(null))->toBeFalse();
});

it('honours canEdit Closure with user and record arguments', function (): void {
    $record = new class extends Illuminate\Database\Eloquent\Model
    {
        protected $guarded = [];

        public int $owner_id = 1;
    };
    $record->owner_id = 1;

    $field = (new StubField('owner_notes'))
        ->canEdit(fn ($user, $rec) => $user !== null && $rec !== null && $user->getAuthIdentifier() === $rec->owner_id);

    expect($field->canBeEditedBy(fakeUser(), $record))->toBeTrue()
        ->and($field->canBeEditedBy(null, $record))->toBeFalse();
});

it('cascades a canSee=false into canBeEditedBy=false', function (): void {
    $field = (new StubField('audit'))
        ->canSee(fn () => false)
        ->canEdit(fn () => true);

    expect($field->canBeEditedBy(fakeUser()))->toBeFalse();
});

it('exposes the registered Closures via getters', function (): void {
    $see = fn () => true;
    $edit = fn () => true;

    $field = (new StubField('x'))->canSee($see)->canEdit($edit);

    expect($field->getCanSeeCallback())->toBe($see)
        ->and($field->getCanEditCallback())->toBe($edit);
});

it('coerces non-bool returns to bool', function (): void {
    $field = (new StubField('x'))->canSee(fn () => 1);

    expect($field->canBeSeenBy())->toBeTrue();
});
