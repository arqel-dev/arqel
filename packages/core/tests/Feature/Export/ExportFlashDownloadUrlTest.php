<?php

declare(strict_types=1);

use Arqel\Core\Http\Middleware\HandleArqelInertiaRequests;
use Illuminate\Http\Request;
use Illuminate\Session\NullSessionHandler;
use Illuminate\Session\Store;

/**
 * Issue: `ResourceController::bulkAction` flashes `download_url` to the
 * session after a bulk export (see BulkExportRoundTripTest "(B)"), but
 * `HandleArqelInertiaRequests::share()` only serialized
 * success/error/info/warning into the Inertia `flash` prop — so the URL
 * never reached React and the user had no way to download the file.
 *
 * Focused middleware-level test (per task brief, approach b): flash
 * `download_url` into the session directly and assert the middleware's
 * `flash` share prop resolves it, without driving the full bulk-export
 * round trip.
 */
it('serializes download_url into the Inertia flash payload', function (): void {
    $session = new Store('test-session', new NullSessionHandler);
    $session->start();
    $session->flash('download_url', '/admin/exports/abc/download');

    $request = Request::create('/admin');
    $request->setLaravelSession($session);

    $mw = new HandleArqelInertiaRequests;
    $shared = $mw->share($request);

    /** @var array<string, mixed> $flash */
    $flash = $shared['flash'];

    expect($flash)->toHaveKey('download_url')
        ->and($flash['download_url'])->toBeInstanceOf(Closure::class)
        ->and(($flash['download_url'])())->toBe('/admin/exports/abc/download')
        ->and(($flash['download_url'])())->not->toBeNull();
});
