<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Order;
use App\Models\Post;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the polymorphic Attachment uses the enforced morph MAP alias
 * (e.g. 'post') rather than the FQCN (App\Models\Post) when persisting
 * `attachable_type`, and that it resolves both ways under
 * Relation::enforceMorphMap.
 */
final class AttachmentMorphTest extends TestCase
{
    use RefreshDatabase;

    public function test_attachment_stores_morph_alias_and_resolves_back(): void
    {
        $post = Post::factory()->create();

        $attachment = $post->attachments()->create([
            'label' => 'spec.pdf',
            'url' => '/files/spec.pdf',
        ]);

        // enforceMorphMap is active, so the stored type is the ALIAS, not the FQCN.
        $this->assertSame('post', $attachment->attachable_type);
        $this->assertNotSame(Post::class, $attachment->attachable_type);

        // attachable resolves back to a Post instance pointing at the same row.
        $resolved = $attachment->fresh()?->attachable;
        $this->assertInstanceOf(Post::class, $resolved);
        $this->assertSame($post->id, $resolved->id);
    }

    public function test_morph_map_resolves_aliases_to_models(): void
    {
        $this->assertSame(Post::class, Relation::getMorphedModel('post'));
        $this->assertSame(Order::class, Relation::getMorphedModel('order'));
    }
}
