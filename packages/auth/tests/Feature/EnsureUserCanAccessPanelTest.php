<?php

declare(strict_types=1);

use Arqel\Auth\Http\Middleware\EnsureUserCanAccessPanel;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->middleware = new EnsureUserCanAccessPanel;
});

function arqelAuthRequest(GenericUser $user): Request
{
    $request = Request::create('/admin');
    $request->setUserResolver(fn () => $user);

    return $request;
}

it('aborts 401 when the request has no user', function (): void {
    $this->middleware->handle(new Request, fn ($req) => 'pass');
})->throws(HttpException::class);

it('allows the request through when the ability is not registered', function (): void {
    $result = $this->middleware->handle(
        arqelAuthRequest(new GenericUser(['id' => 1])),
        fn ($req) => 'pass',
    );

    expect($result)->toBe('pass');
});

it('allows the request through when the gate grants the ability', function (): void {
    Gate::define('viewAdminPanel', fn ($user) => $user->getAuthIdentifier() === 1);

    $result = $this->middleware->handle(
        arqelAuthRequest(new GenericUser(['id' => 1])),
        fn ($req) => 'pass',
    );

    expect($result)->toBe('pass');
});

it('aborts 403 when the gate denies the ability', function (): void {
    Gate::define('viewAdminPanel', fn () => false);

    $this->middleware->handle(
        arqelAuthRequest(new GenericUser(['id' => 1])),
        fn ($req) => 'pass',
    );
})->throws(HttpException::class);

it('honours an ability passed as middleware parameter', function (): void {
    Gate::define('manageSettings', fn () => false);

    $this->middleware->handle(
        arqelAuthRequest(new GenericUser(['id' => 1])),
        fn ($req) => 'pass',
        'manageSettings',
    );
})->throws(HttpException::class);
