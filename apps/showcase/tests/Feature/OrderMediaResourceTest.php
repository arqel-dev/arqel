<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderMediaResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_the_orders_and_media_index_pages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/orders')->assertOk();
        $this->actingAs($user)->get('/admin/media-assets')->assertOk();
    }
}
