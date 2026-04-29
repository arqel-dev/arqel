<?php

declare(strict_types=1);

use Arqel\Audit\Tests\Fixtures\FakeAuditableModel;
use Illuminate\Pagination\LengthAwarePaginator;

it('returns a paginator with the model activities via activityLog()', function (): void {
    $model = FakeAuditableModel::create(['name' => 'Gimli', 'email' => 'gimli@erebor.test']);
    $model->update(['email' => 'lord-of-glittering@erebor.test']);

    $paginator = $model->activityLog();

    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginator->total())->toBe(2)
        ->and($paginator->perPage())->toBe(20);
});

it('honors the perPage parameter', function (): void {
    $model = FakeAuditableModel::create(['name' => 'Legolas', 'email' => 'legolas@mirkwood.test']);
    $model->update(['email' => 'archer@mirkwood.test']);
    $model->update(['name' => 'Greenleaf']);

    $paginator = $model->activityLog(perPage: 1);

    expect($paginator->perPage())->toBe(1)
        ->and($paginator->total())->toBe(3)
        ->and($paginator->lastPage())->toBe(3);
});
