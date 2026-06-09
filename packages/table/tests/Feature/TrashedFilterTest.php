<?php

declare(strict_types=1);

use Arqel\Table\Filters\TrashedFilter;
use Arqel\Table\Tests\Fixtures\PlainUser;
use Arqel\Table\Tests\Fixtures\TrashableUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('trashable_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('plain_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    $active = TrashableUser::query()->create(['name' => 'active-1']);
    TrashableUser::query()->create(['name' => 'active-2']);
    $deleted = TrashableUser::query()->create(['name' => 'deleted-1']);
    $deleted->delete();
});

afterEach(function (): void {
    Schema::dropIfExists('trashable_users');
    Schema::dropIfExists('plain_users');
});

it('TrashedFilter: default/without shows only active rows', function (): void {
    $filter = TrashedFilter::make();

    $names = $filter->applyToQuery(TrashableUser::query(), TrashedFilter::STATE_WITHOUT)
        ->pluck('name')->all();

    expect($names)->toEqualCanonicalizing(['active-1', 'active-2']);
});

it('TrashedFilter: only shows only soft-deleted rows', function (): void {
    $filter = TrashedFilter::make();

    $names = $filter->applyToQuery(TrashableUser::query(), TrashedFilter::STATE_ONLY)
        ->pluck('name')->all();

    expect($names)->toEqualCanonicalizing(['deleted-1']);
});

it('TrashedFilter: with shows every row including trashed', function (): void {
    $filter = TrashedFilter::make();

    $names = $filter->applyToQuery(TrashableUser::query(), TrashedFilter::STATE_WITH)
        ->pluck('name')->all();

    expect($names)->toEqualCanonicalizing(['active-1', 'active-2', 'deleted-1']);
});

it('TrashedFilter: serialises the 3 options like a select', function (): void {
    $filter = TrashedFilter::make();

    $payload = $filter->toArray();

    expect($payload['type'])->toBe('trashed')
        ->and($payload['name'])->toBe('trashed')
        ->and($payload['label'])->toBe('Trashed')
        ->and($payload['default'])->toBe(TrashedFilter::STATE_WITHOUT)
        ->and($payload['props']['options'])->toBe([
            ['value' => TrashedFilter::STATE_WITHOUT, 'label' => 'Without deleted'],
            ['value' => TrashedFilter::STATE_WITH, 'label' => 'With deleted'],
            ['value' => TrashedFilter::STATE_ONLY, 'label' => 'Only deleted'],
        ]);
});

it('TrashedFilter: harmless no-op on a non-SoftDeletes model', function (): void {
    PlainUser::query()->create(['name' => 'plain-1']);

    $filter = TrashedFilter::make();

    $names = $filter->applyToQuery(PlainUser::query(), TrashedFilter::STATE_ONLY)
        ->pluck('name')->all();

    // No `onlyTrashed()` applied because the model is not SoftDeletes:
    // the query stays untouched and returns the row.
    expect($names)->toBe(['plain-1']);
});
