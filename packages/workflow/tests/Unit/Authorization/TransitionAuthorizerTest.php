<?php

declare(strict_types=1);

use Arqel\Workflow\Authorization\TransitionAuthorizer;
use Arqel\Workflow\Tests\Fixtures\AmbiguousTransition;
use Arqel\Workflow\Tests\Fixtures\AuthorizedTransition;
use Arqel\Workflow\Tests\Fixtures\DeniedTransition;
use Arqel\Workflow\Tests\Fixtures\InstanceAuthorizedTransition;
use Arqel\Workflow\Tests\Fixtures\PaidState;
use Arqel\Workflow\Tests\Fixtures\PendingState;
use Arqel\Workflow\Tests\Fixtures\ThrowingTransition;
use Arqel\Workflow\Tests\Fixtures\WorkflowOrder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

it('uses static authorizeFor when present and returns true', function (): void {
    $order = new WorkflowOrder;

    expect(TransitionAuthorizer::authorize(AuthorizedTransition::class, null, $order))->toBeTrue();
});

it('uses instance authorizeFor when static is absent', function (): void {
    $order = new WorkflowOrder;

    expect(TransitionAuthorizer::authorize(InstanceAuthorizedTransition::class, null, $order))->toBeTrue();
});

it('returns false when authorizeFor returns false', function (): void {
    $order = new WorkflowOrder;

    expect(TransitionAuthorizer::authorize(DeniedTransition::class, null, $order))->toBeFalse();
});

it('returns false when authorizeFor throws an exception', function (): void {
    expect(TransitionAuthorizer::authorize(ThrowingTransition::class, null, null))->toBeFalse();
});

it('delegates to Gate when no authorizeFor and ability is registered', function (): void {
    $user = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
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
            return '';
        }
    };

    Gate::define('transition-pending-to-paid', fn ($user, $record): bool => true);

    expect(TransitionAuthorizer::authorize(AmbiguousTransition::class, $user, new WorkflowOrder))->toBeTrue();

    Gate::define('transition-pending-to-paid', fn ($user, $record): bool => false);

    expect(TransitionAuthorizer::authorize(AmbiguousTransition::class, $user, new WorkflowOrder))->toBeFalse();
});

it('denies by default when neither authorizeFor nor Gate are defined', function (): void {
    config()->set('arqel-workflow.authorization.deny_when_undefined', true);

    expect(TransitionAuthorizer::authorize(AmbiguousTransition::class, null, new WorkflowOrder))->toBeFalse();
});

it('allows by legacy fallback when deny_when_undefined is false', function (): void {
    config()->set('arqel-workflow.authorization.deny_when_undefined', false);

    expect(TransitionAuthorizer::authorize(AmbiguousTransition::class, null, new WorkflowOrder))->toBeTrue();
});

it('returns false when transition class does not exist', function (): void {
    // The authorizer is documented as resilient to bogus class-strings; pass
    // one via a runtime-built FQCN to keep PHPStan honest.
    $missing = implode('\\', ['Arqel', 'Workflow', 'Tests', 'Fixtures', 'NonExistentTransition']);

    expect(TransitionAuthorizer::authorize($missing, null, null))->toBeFalse();
});

it('slugifies states correctly across naming variants', function (): void {
    expect(TransitionAuthorizer::slugifyState(PendingState::class))->toBe('pending')
        ->and(TransitionAuthorizer::slugifyState(PaidState::class))->toBe('paid')
        ->and(TransitionAuthorizer::slugifyState('PendingPaymentState'))->toBe('pending-payment')
        ->and(TransitionAuthorizer::slugifyState('CamelCase'))->toBe('camel-case')
        ->and(TransitionAuthorizer::slugifyState('pending'))->toBe('pending')
        ->and(TransitionAuthorizer::slugifyState('App\\Domain\\Order\\States\\AwaitingShipment'))->toBe('awaiting-shipment')
        ->and(TransitionAuthorizer::slugifyState('State'))->toBe('state')
        ->and(TransitionAuthorizer::slugifyState('*'))->toBe('*');
});
