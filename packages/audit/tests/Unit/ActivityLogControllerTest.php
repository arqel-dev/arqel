<?php

declare(strict_types=1);

use Arqel\Audit\Http\Controllers\ActivityLogController;
use Arqel\Audit\Tests\Fixtures\FakeAuditableModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

it('exposes a callable index method', function (): void {
    $reflection = new ReflectionMethod(ActivityLogController::class, 'index');

    expect($reflection->isPublic())->toBeTrue()
        ->and($reflection->getNumberOfParameters())->toBe(1);
});

it('returns a paginated JSON payload', function (): void {
    FakeAuditableModel::create(['name' => 'Gimli', 'email' => 'gimli@erebor.test']);
    FakeAuditableModel::create(['name' => 'Legolas', 'email' => 'legolas@mirkwood.test']);

    $controller = new ActivityLogController;
    $response = $controller->index(Request::create('/activity', 'GET'));

    expect($response)->toBeInstanceOf(JsonResponse::class);

    /** @var array{data: array<int, array<string, mixed>>, meta: array<string, mixed>} $payload */
    $payload = $response->getData(true);

    expect($payload)->toHaveKeys(['data', 'meta'])
        ->and($payload['meta'])->toHaveKeys(['current_page', 'last_page', 'per_page', 'total'])
        ->and(count($payload['data']))->toBeGreaterThanOrEqual(2);

    $first = $payload['data'][0];
    expect($first)->toHaveKeys([
        'id', 'log_name', 'description', 'subject_type',
        'subject_id', 'causer_type', 'causer_id', 'properties', 'created_at',
    ]);
});

it('clamps per_page to a safe maximum', function (): void {
    $controller = new ActivityLogController;
    $response = $controller->index(Request::create('/activity', 'GET', ['per_page' => 5000]));

    /** @var array{meta: array{per_page: int}} $payload */
    $payload = $response->getData(true);

    expect($payload['meta']['per_page'])->toBeLessThanOrEqual(200);
});
