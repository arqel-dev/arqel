<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Resolve the tenant from the authenticated user on every web
        // request. `optional` is a no-op when there's no user/tenant
        // (e.g. the login page), so it is safe to apply group-wide; the
        // core panel route registration does not reliably honour the
        // Panel's own middleware stack, so we wire it here instead.
        // The `arqel.tenant` alias is registered by TenantServiceProvider.
        $middleware->appendToGroup('web', 'arqel.tenant:optional');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
