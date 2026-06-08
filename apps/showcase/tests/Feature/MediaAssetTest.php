<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the MediaAsset factory persists the given file_path/mime so the
 * media-library showcase pages have real upload-shaped rows to render.
 */
final class MediaAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_media_asset_with_given_attributes(): void
    {
        $asset = MediaAsset::factory()->create([
            'file_path' => 'media/sample.png',
            'mime' => 'image/png',
        ]);

        $this->assertSame('media/sample.png', $asset->file_path);
        $this->assertSame('image/png', $asset->mime);

        $this->assertDatabaseHas('media_assets', [
            'id' => $asset->id,
            'file_path' => 'media/sample.png',
            'mime' => 'image/png',
        ]);
    }
}
