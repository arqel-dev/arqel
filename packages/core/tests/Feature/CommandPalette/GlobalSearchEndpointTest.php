<?php

declare(strict_types=1);

use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\GlobalSearch\RsPerson;
use Arqel\Core\Tests\Fixtures\GlobalSearch\RsPersonResource;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Schema;

it('serves record hits through GET /admin/commands', function () {
    Schema::create('rs_people', function (Blueprint $t) {
        $t->id();
        $t->string('name')->nullable();
    });
    RsPerson::create(['name' => 'Ana Lima']);

    // Register the resource so the provider sees it.
    app(ResourceRegistry::class)->register(RsPersonResource::class);

    $user = new AuthUser;
    $user->forceFill(['id' => 1, 'name' => 'Test', 'email' => 't@e.dev']);

    $response = $this->actingAs($user)->getJson('/admin/commands?q=ana');

    $response->assertOk();
    $labels = array_column($response->json('commands'), 'label');
    expect($labels)->toContain('Ana Lima');

    Schema::dropIfExists('rs_people');
});
