<?php

declare(strict_types=1);

use Arqel\Core\Http\Middleware\SetLocaleMiddleware;
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
        // Resolve the active locale (session/cookie/Accept-Language) on every
        // web request so a choice persisted via POST /admin/locale actually
        // applies. See Arqel\Core\Http\Middleware\SetLocaleMiddleware.
        $middleware->web(append: [
            SetLocaleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
