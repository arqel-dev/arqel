<?php

declare(strict_types=1);

use Arqel\Audit\Http\Controllers\RecordActivityController;
use Arqel\Audit\Tests\Fixtures\FakeAuditableModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

it('returns paginator with activities for the given record', function (): void {
    $model = FakeAuditableModel::create(['name' => 'Aragorn', 'email' => 'aragorn@gondor.test']);
    $model->update(['email' => 'king@gondor.test']);

    $controller = new RecordActivityController;
    $response = $controller->show(
        Request::create('/admin/audit/x/'.$model->id.'/activity', 'GET'),
        FakeAuditableModel::class,
        (string) $model->id,
    );

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200);

    /** @var array{data: array<int, array<string, mixed>>, current_page: int, last_page: int, per_page: int, total: int} $payload */
    $payload = $response->getData(true);

    expect($payload)->toHaveKeys(['data', 'current_page', 'last_page', 'per_page', 'total'])
        ->and($payload['total'])->toBe(2)
        ->and(count($payload['data']))->toBe(2);

    $first = $payload['data'][0];
    expect($first)->toHaveKeys(['id', 'log_name', 'description', 'event', 'properties', 'causer', 'created_at'])
        ->and($first['log_name'])->toBe('FakeAuditableModel');
});

it('returns 400 when subjectType is not a real Eloquent model class', function (): void {
    $controller = new RecordActivityController;
    $response = $controller->show(
        Request::create('/admin/audit/invalid/1/activity', 'GET'),
        'Not\\A\\Class',
        '1',
    );

    expect($response->getStatusCode())->toBe(400);

    /** @var array{error: string} $payload */
    $payload = $response->getData(true);
    expect($payload)->toHaveKey('error')
        ->and($payload['error'])->toBe('invalid_subject_type');
});

it('returns 400 when subjectType is empty', function (): void {
    $controller = new RecordActivityController;
    $response = $controller->show(Request::create('/x', 'GET'), '', '1');

    expect($response->getStatusCode())->toBe(400);
});

it('returns an empty paginator when no activities exist for the subject', function (): void {
    $controller = new RecordActivityController;
    $response = $controller->show(
        Request::create('/x', 'GET'),
        FakeAuditableModel::class,
        '99999',
    );

    /** @var array{data: array<int, mixed>, total: int} $payload */
    $payload = $response->getData(true);

    expect($payload['total'])->toBe(0)
        ->and($payload['data'])->toBe([]);
});

it('isolates activities by subject_id', function (): void {
    $a = FakeAuditableModel::create(['name' => 'Frodo', 'email' => 'frodo@shire.test']);
    $b = FakeAuditableModel::create(['name' => 'Sam', 'email' => 'sam@shire.test']);

    $a->update(['email' => 'ringbearer@shire.test']);
    $b->update(['email' => 'gardener@shire.test']);
    $b->update(['name' => 'Samwise']);

    $controller = new RecordActivityController;
    $response = $controller->show(
        Request::create('/x', 'GET'),
        FakeAuditableModel::class,
        (string) $a->id,
    );

    /** @var array{data: array<int, array<string, mixed>>, total: int} $payload */
    $payload = $response->getData(true);

    expect($payload['total'])->toBe(2); // create + update for $a only
});
