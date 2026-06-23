<?php

declare(strict_types=1);

it('persists allowed locale in the session', function (): void {
    $response = $this->from('/admin')->post('/admin/locale', ['locale' => 'pt_BR']);

    $response->assertRedirect('/admin');
    expect(session('locale'))->toBe('pt_BR');
});

it('queues the arqel_locale cookie so the choice survives session expiry', function (): void {
    $response = $this->from('/admin')->post('/admin/locale', ['locale' => 'pt_BR']);

    $response->assertRedirect('/admin');
    // tier-1: within-session source remains unchanged
    expect(session('locale'))->toBe('pt_BR');
    // tier-2: cross-session source the SetLocaleMiddleware reads on a fresh session
    $response->assertCookie('arqel_locale', 'pt_BR');
});

it('rejects locale outside the allowlist', function (): void {
    $response = $this->post('/admin/locale', ['locale' => 'xx_YY']);

    $response->assertStatus(422);
    expect(session('locale'))->toBeNull();
});

it('redirects back after switching locale', function (): void {
    $response = $this->from('/admin/posts')->post('/admin/locale', ['locale' => 'en']);

    $response->assertRedirect('/admin/posts');
    expect(session('locale'))->toBe('en');
});

it('rejects empty locale input', function (): void {
    $response = $this->post('/admin/locale', []);

    $response->assertStatus(422);
});

it('accepts a hyphenated input against an underscored allowlist and persists the canonical form', function (): void {
    config()->set('arqel.i18n.locales', ['en', 'pt_BR']);

    $response = $this->from('/admin')->post('/admin/locale', ['locale' => 'pt-BR']);

    $response->assertRedirect('/admin');
    // The hyphenated input is matched against pt_BR and the canonical allowlist
    // form is persisted, so the SetLocaleMiddleware recognizes it next request.
    expect(session('locale'))->toBe('pt_BR');
    $response->assertCookie('arqel_locale', 'pt_BR');
});
