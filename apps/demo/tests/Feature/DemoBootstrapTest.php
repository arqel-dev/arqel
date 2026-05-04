<?php

declare(strict_types=1);

use App\Arqel\Panel;
use App\Arqel\Resources\CategoryResource;
use App\Arqel\Resources\PostResource;
use App\Arqel\Resources\TagResource;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Schema;

it('registers the admin panel with all 3 resources', function (): void {
    $panel = Panel::get('admin');
    expect($panel)->not->toBeNull();
    expect($panel->path)->toBe('admin');
    expect($panel->resources)->toBe([
        PostResource::class,
        TagResource::class,
        CategoryResource::class,
    ]);
    expect($panel->login)->toBeTrue();
    expect($panel->registration)->toBeTrue();
});

it('creates all expected tables on migration', function (): void {
    foreach (['users', 'posts', 'tags', 'categories', 'post_tag'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("table {$table} missing");
    }
});

it('runs DemoSeeder producing the expected fixture counts', function (): void {
    (new DemoSeeder)->run();

    expect(App\Models\User::query()->count())->toBe(3);
    expect(App\Models\Category::query()->count())->toBe(5);
    expect(App\Models\Tag::query()->count())->toBe(20);
    expect(App\Models\Post::query()->count())->toBe(50);
});
