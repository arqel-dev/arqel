<?php

declare(strict_types=1);

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\Providers\RecordSearchCommandProvider;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\GlobalSearch\RsPerson;
use Arqel\Core\Tests\Fixtures\GlobalSearch\RsPersonResource;
use Arqel\Core\Tests\Fixtures\GlobalSearch\RsSilentResource;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('rs_people', function (Blueprint $t) {
        $t->id();
        $t->string('name')->nullable();
        $t->string('email')->nullable();
    });
});

afterEach(fn () => Schema::dropIfExists('rs_people'));

function makeProvider(): RecordSearchCommandProvider
{
    $registry = new ResourceRegistry;
    $registry->register(RsPersonResource::class);

    return new RecordSearchCommandProvider($registry);
}

it('returns [] for a query shorter than the minimum', function () {
    RsPerson::create(['name' => 'Ana Lima']);
    expect(makeProvider()->provide(null, 'a'))->toBe([]);
});

it('finds records by LIKE across multiple attributes', function () {
    RsPerson::create(['name' => 'Ana Lima', 'email' => 'ana@x.com']);
    RsPerson::create(['name' => 'Bob', 'email' => 'bob@ana-corp.com']); // matches via email
    RsPerson::create(['name' => 'Zoe', 'email' => 'zoe@x.com']);

    $commands = makeProvider()->provide(null, 'ana');

    $labels = array_map(fn (Command $c) => $c->label, $commands);
    expect($labels)->toContain('Ana Lima')->toContain('Bob')->not->toContain('Zoe');
});

it('caps results per resource', function () {
    foreach (range(1, 8) as $i) {
        RsPerson::create(['name' => "Ana {$i}"]);
    }
    expect(makeProvider()->provide(null, 'ana'))->toHaveCount(5); // PER_RESOURCE_LIMIT
});

it('gives each record command a fixed rankScore and an edit url', function () {
    $p = RsPerson::create(['name' => 'Ana Lima']);
    $command = makeProvider()->provide(null, 'ana')[0];

    expect($command->rankScore)->toBe(60);
    expect($command->url)->toBe("/admin/people/{$p->id}/edit");
    expect($command->id)->toBe("record:people:{$p->id}");
    expect($command->label)->toBe('Ana Lima');
});

it('treats % and _ in the term as literals', function () {
    RsPerson::create(['name' => '100% cotton']);
    RsPerson::create(['name' => 'anything']); // would match a bare % wildcard

    $commands = makeProvider()->provide(null, '100%');

    $labels = array_map(fn (Command $c) => $c->label, $commands);
    expect($labels)->toBe(['100% cotton']);
});

it('skips a resource whose globallySearchable() is empty', function () {
    $registry = new ResourceRegistry;
    $registry->register(RsPersonResource::class);
    $registry->register(RsSilentResource::class);

    RsPerson::create(['name' => 'Ana Silent']);

    $commands = (new RecordSearchCommandProvider($registry))->provide(null, 'ana');

    $ids = array_map(fn (Command $c) => $c->id, $commands);
    $silentIds = array_filter($ids, fn (string $id) => str_starts_with($id, 'record:silent:'));

    expect($silentIds)->toBe([]);
});

it('skips a resource when viewAny is denied', function () {
    RsPerson::create(['name' => 'Ana Lima']);
    Gate::define('viewAny', fn () => false);

    $user = new class extends Illuminate\Database\Eloquent\Model implements Illuminate\Contracts\Auth\Authenticatable
    {
        use Illuminate\Auth\Authenticatable;
    };

    expect(makeProvider()->provide($user, 'ana'))->toBe([]);
});
