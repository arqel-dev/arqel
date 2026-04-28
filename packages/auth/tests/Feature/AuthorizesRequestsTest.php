<?php

declare(strict_types=1);

use Arqel\Auth\Concerns\AuthorizesRequests;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Trait host class — only exists to expose the protected oracles
 * to the test suite.
 */
final class TraitHost
{
    use AuthorizesRequests;

    public function callAuthorizeResource(string $resourceClass, string $action, mixed $record = null): void
    {
        $this->authorizeResource($resourceClass, $action, $record);
    }

    public function callAuthorizeAction(object $action, mixed $record = null): void
    {
        $this->authorizeAction($action, $record);
    }

    public function callAuthorizeField(object $field, string $operation, mixed $record = null): void
    {
        $this->authorizeField($field, $operation, $record);
    }
}

/**
 * Minimal fixture mimicking the Resource contract.
 */
final class FauxResource
{
    public static function getModel(): string
    {
        return FauxModel::class;
    }
}

final class FauxModel {}

beforeEach(function (): void {
    $this->host = new TraitHost;
});

it('authorizeResource: allows when no policy or gate is registered', function (): void {
    $this->host->callAuthorizeResource(FauxResource::class, 'view');
    expect(true)->toBeTrue();
});

it('authorizeResource: aborts 403 when the gate denies', function (): void {
    Gate::define('view', fn () => false);
    Auth::setUser(new GenericUser(['id' => 1]));

    $this->host->callAuthorizeResource(FauxResource::class, 'view');
})->throws(HttpException::class);

it('authorizeResource: passes when the gate allows', function (): void {
    Gate::define('view', fn () => true);
    Auth::setUser(new GenericUser(['id' => 1]));

    $this->host->callAuthorizeResource(FauxResource::class, 'view');
    expect(true)->toBeTrue();
});

it('authorizeAction: aborts when canBeExecutedBy returns false', function (): void {
    $action = new class
    {
        public function canBeExecutedBy(?object $user, mixed $record): bool
        {
            return false;
        }
    };

    $this->host->callAuthorizeAction($action);
})->throws(HttpException::class);

it('authorizeField: edit operation defers to canBeEditedBy', function (): void {
    $field = new class
    {
        public function canBeSeenBy(?object $user, mixed $record): bool
        {
            return true;
        }

        public function canBeEditedBy(?object $user, mixed $record): bool
        {
            return false;
        }
    };

    $this->host->callAuthorizeField($field, 'edit');
})->throws(HttpException::class);

it('authorizeField: view operation defers to canBeSeenBy', function (): void {
    $field = new class
    {
        public function canBeSeenBy(?object $user, mixed $record): bool
        {
            return false;
        }
    };

    $this->host->callAuthorizeField($field, 'view');
})->throws(HttpException::class);
