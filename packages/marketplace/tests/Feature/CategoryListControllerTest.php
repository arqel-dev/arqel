<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\PluginCategory;

it('lists root categories only when ?root=1', function (): void {
    /** @var PluginCategory $root */
    $root = PluginCategory::query()->create([
        'slug' => 'tools',
        'name' => 'Tools',
        'sort_order' => 99,
    ]);
    PluginCategory::query()->create([
        'slug' => 'sub-a',
        'name' => 'Sub A',
        'sort_order' => 1,
        'parent_id' => $root->id,
    ]);

    $response = $this->getJson('/api/marketplace/categories?root=1');

    $response->assertOk();
    $slugs = array_column($response->json('data'), 'slug');
    expect($slugs)->toContain('tools')
        ->and($slugs)->not->toContain('sub-a');
});

it('eager loads children when listing root categories', function (): void {
    /** @var PluginCategory $root */
    $root = PluginCategory::query()->create([
        'slug' => 'parent-cat',
        'name' => 'Parent Cat',
        'sort_order' => 50,
    ]);
    PluginCategory::query()->create([
        'slug' => 'child-1',
        'name' => 'Child 1',
        'sort_order' => 2,
        'parent_id' => $root->id,
    ]);
    PluginCategory::query()->create([
        'slug' => 'child-2',
        'name' => 'Child 2',
        'sort_order' => 1,
        'parent_id' => $root->id,
    ]);

    $response = $this->getJson('/api/marketplace/categories?root=1');

    $response->assertOk();
    /** @var array<int, array<string, mixed>> $rows */
    $rows = $response->json('data');
    $found = null;
    foreach ($rows as $row) {
        if (($row['slug'] ?? null) === 'parent-cat') {
            $found = $row;
            break;
        }
    }
    expect($found)->not->toBeNull();
    $children = is_array($found) && isset($found['children']) && is_array($found['children'])
        ? $found['children']
        : [];
    expect($children)->toHaveCount(2);
    expect($children[0]['slug'])->toBe('child-2');
});

it('orders categories by sort_order asc', function (): void {
    PluginCategory::query()->where('slug', 'fields')->update(['sort_order' => 1]);
    PluginCategory::query()->where('slug', 'utilities')->update(['sort_order' => 5]);

    $response = $this->getJson('/api/marketplace/categories');

    $response->assertOk();
    $slugs = array_column($response->json('data'), 'slug');
    $fieldsIdx = array_search('fields', $slugs, true);
    $utilitiesIdx = array_search('utilities', $slugs, true);
    expect($fieldsIdx)->toBeLessThan($utilitiesIdx);
});
