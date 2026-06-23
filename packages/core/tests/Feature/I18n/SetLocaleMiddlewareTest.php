<?php

declare(strict_types=1);

use Arqel\Core\Http\Middleware\SetLocaleMiddleware;
use Arqel\Core\I18n\TranslationLoader;
use Illuminate\Http\Request;

function runMiddleware(Request $request): string
{
    $middleware = app(SetLocaleMiddleware::class);

    $middleware->handle($request, fn () => response('ok'));

    return app()->getLocale();
}

it('prefers the locale stored in the session', function (): void {
    $request = Request::create('/admin');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('locale', 'pt_BR');

    expect(runMiddleware($request))->toBe('pt_BR');
});

it('falls back to the cookie when no session locale is set', function (): void {
    $request = Request::create('/admin', 'GET', [], ['arqel_locale' => 'pt_BR']);
    $request->setLaravelSession(app('session.store'));

    expect(runMiddleware($request))->toBe('pt_BR');
});

it('uses the Accept-Language header when neither session nor cookie are set', function (): void {
    $request = Request::create('/admin', 'GET', [], [], [], [
        'HTTP_ACCEPT_LANGUAGE' => 'pt-BR,pt;q=0.9,en;q=0.8',
    ]);
    $request->setLaravelSession(app('session.store'));

    expect(runMiddleware($request))->toBe('pt_BR');
});

it('falls back to the default locale when no signal is available', function (): void {
    config()->set('arqel.i18n.default', 'en');

    $request = Request::create('/admin');
    $request->setLaravelSession(app('session.store'));

    expect(runMiddleware($request))->toBe('en');
});

it('ignores locales outside the allowlist', function (): void {
    config()->set('arqel.i18n.default', 'en');

    $request = Request::create('/admin');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('locale', 'zz_ZZ');

    expect(runMiddleware($request))->toBe('en');
});

it('falls back to the language base when only a generic locale dir exists', function (): void {
    // Allowlist offers generic Portuguese only ('pt'); a browser sending the
    // regional 'pt-BR;q=0.9' must still match via the language-base fallback
    // instead of dropping to the English default.
    $langBase = dirname(__DIR__, 3).'/resources/lang';
    $ptDir = $langBase.'/pt';
    @mkdir($ptDir, 0775, true);
    file_put_contents($ptDir.'/messages.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn [];\n");

    try {
        config()->set('arqel.i18n.default', 'en');
        config()->set('arqel.i18n.locales', ['en', 'pt']);

        $request = Request::create('/admin', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'pt-BR;q=0.9,en;q=0.4',
        ]);
        $request->setLaravelSession(app('session.store'));

        expect(runMiddleware($request))->toBe('pt');
    } finally {
        @unlink($ptDir.'/messages.php');
        @rmdir($ptDir);
    }
});

it('exposes the loader as a singleton', function (): void {
    $a = app(TranslationLoader::class);
    $b = app(TranslationLoader::class);

    expect($a)->toBe($b);
});
