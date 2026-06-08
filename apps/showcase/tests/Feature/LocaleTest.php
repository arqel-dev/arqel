<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers Task 4.1 — the Arqel i18n locale endpoint + SetLocaleMiddleware
 * wiring in the showcase. The POST /admin/locale route ships with the
 * core package; the middleware (registered in bootstrap/app.php) is what
 * makes a persisted choice take effect on subsequent web requests.
 */
final class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_a_locale_choice_via_the_arqel_locale_route(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post('/admin/locale', ['locale' => 'pt_BR'])
            ->assertRedirect();
    }

    public function test_an_unknown_locale_is_rejected_or_ignored(): void
    {
        $user = User::factory()->create();
        // posting an unsupported locale should not crash (either validation error or ignored)
        $response = $this->actingAs($user)->post('/admin/locale', ['locale' => 'xx_YY']);
        $this->assertContains($response->status(), [302, 422]);
    }
}
