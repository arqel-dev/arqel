<?php

declare(strict_types=1);

it('persists allowed locale in the session', function (): void {
    $response = $this->from('/admin')->post('/admin/locale', ['locale' => 'pt_BR']);

    $response->assertRedirect('/admin');
    expect(session('locale'))->toBe('pt_BR');
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
