<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Arqel\Resources\PostResource;
use Arqel\Realtime\Concerns\BroadcastsResourceUpdates;
use Tests\TestCase;

/**
 * Structural assertion for Task 4.3: PostResource opts into the
 * framework's realtime broadcasting by using the
 * BroadcastsResourceUpdates trait, which auto-dispatches a
 * ResourceUpdated event after each update.
 */
final class PostBroadcastTest extends TestCase
{
    public function test_post_resource_uses_broadcasts_resource_updates_trait(): void
    {
        $this->assertContains(
            BroadcastsResourceUpdates::class,
            class_uses(PostResource::class),
        );
    }
}
