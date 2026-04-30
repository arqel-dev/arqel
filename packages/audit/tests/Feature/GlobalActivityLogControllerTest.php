<?php

declare(strict_types=1);

use Arqel\Audit\Http\Controllers\GlobalActivityLogController;
use Arqel\Audit\Tests\Fixtures\FakeAuditableModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @param array<string, mixed> $overrides
 */
function makeActivity(array $overrides = []): Activity
{
    /** @var Activity $activity */
    $activity = new Activity;
    $activity->log_name = $overrides['log_name'] ?? 'default';
    $activity->description = $overrides['description'] ?? 'created';
    $activity->event = $overrides['event'] ?? 'created';
    $activity->subject_type = $overrides['subject_type'] ?? FakeAuditableModel::class;
    $activity->subject_id = $overrides['subject_id'] ?? 1;
    $activity->causer_type = $overrides['causer_type'] ?? null;
    $activity->causer_id = $overrides['causer_id'] ?? null;
    $activity->properties = collect($overrides['properties'] ?? []);
    $activity->save();

    if (isset($overrides['created_at'])) {
        Activity::query()
            ->where('id', $activity->getKey())
            ->update(['created_at' => $overrides['created_at']]);
        $activity->refresh();
    }

    return $activity;
}

beforeEach(function (): void {
    // Seed three fixture activities with distinct log_name + event.
    $a = FakeAuditableModel::create(['name' => 'Aragorn', 'email' => 'a@test']);
    $b = FakeAuditableModel::create(['name' => 'Boromir', 'email' => 'b@test']);
    $c = FakeAuditableModel::create(['name' => 'Frodo', 'email' => 'f@test']);

    Activity::query()->delete();

    makeActivity([
        'log_name' => 'orders',
        'event' => 'created',
        'subject_id' => $a->id,
        'created_at' => Carbon::parse('2026-01-01T10:00:00Z'),
    ]);
    makeActivity([
        'log_name' => 'invoices',
        'event' => 'updated',
        'subject_id' => $b->id,
        'causer_type' => FakeAuditableModel::class,
        'causer_id' => $a->id,
        'created_at' => Carbon::parse('2026-02-01T10:00:00Z'),
    ]);
    makeActivity([
        'log_name' => 'orders',
        'event' => 'deleted',
        'subject_id' => $c->id,
        'created_at' => Carbon::parse('2026-03-01T10:00:00Z'),
    ]);
});

it('returns paginator with all activities sorted desc by created_at', function (): void {
    $controller = new GlobalActivityLogController;
    $response = $controller->index(Request::create('/admin/audit/activity', 'GET'));

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200);

    /** @var array{data: array<int, array<string, mixed>>, total: int} $payload */
    $payload = $response->getData(true);
    expect($payload['total'])->toBe(3)
        ->and(count($payload['data']))->toBe(3)
        ->and($payload['data'][0]['event'])->toBe('deleted')
        ->and($payload['data'][1]['event'])->toBe('updated')
        ->and($payload['data'][2]['event'])->toBe('created');

    expect($payload['data'][0])->toHaveKeys([
        'id', 'log_name', 'description', 'event', 'subject_type', 'subject_id', 'causer', 'properties', 'created_at',
    ]);
});

it('filters by log_name', function (): void {
    $controller = new GlobalActivityLogController;
    $response = $controller->index(Request::create('/admin/audit/activity?log_name=orders', 'GET'));

    /** @var array{data: array<int, array<string, mixed>>, total: int} $payload */
    $payload = $response->getData(true);
    expect($payload['total'])->toBe(2);
    foreach ($payload['data'] as $row) {
        expect($row['log_name'])->toBe('orders');
    }
});

it('filters by event', function (): void {
    $controller = new GlobalActivityLogController;
    $response = $controller->index(Request::create('/admin/audit/activity?event=updated', 'GET'));

    /** @var array{data: array<int, array<string, mixed>>, total: int} $payload */
    $payload = $response->getData(true);
    expect($payload['total'])->toBe(1)
        ->and($payload['data'][0]['event'])->toBe('updated');
});

it('returns 400 for invalid event values', function (): void {
    $controller = new GlobalActivityLogController;
    $response = $controller->index(Request::create('/admin/audit/activity?event=hacked', 'GET'));

    expect($response->getStatusCode())->toBe(400);

    /** @var array{error: string} $payload */
    $payload = $response->getData(true);
    expect($payload['error'])->toBe('invalid_event');
});

it('filters by causer_type + causer_id', function (): void {
    $controller = new GlobalActivityLogController;
    $causerId = (int) Activity::query()->whereNotNull('causer_id')->value('causer_id');
    $causerType = urlencode(FakeAuditableModel::class);

    $response = $controller->index(Request::create(
        '/admin/audit/activity?causer_type='.$causerType.'&causer_id='.$causerId,
        'GET',
    ));

    /** @var array{data: array<int, array<string, mixed>>, total: int} $payload */
    $payload = $response->getData(true);
    expect($payload['total'])->toBe(1)
        ->and($payload['data'][0]['event'])->toBe('updated');
});

it('filters by date range — from only', function (): void {
    $controller = new GlobalActivityLogController;
    $response = $controller->index(Request::create('/admin/audit/activity?from=2026-02-15T00:00:00Z', 'GET'));

    /** @var array{total: int} $payload */
    $payload = $response->getData(true);
    expect($payload['total'])->toBe(1); // only the March entry
});

it('filters by date range — to only', function (): void {
    $controller = new GlobalActivityLogController;
    $response = $controller->index(Request::create('/admin/audit/activity?to=2026-01-15T00:00:00Z', 'GET'));

    /** @var array{total: int} $payload */
    $payload = $response->getData(true);
    expect($payload['total'])->toBe(1); // only the January entry
});

it('filters by date range — both from and to', function (): void {
    $controller = new GlobalActivityLogController;
    $response = $controller->index(Request::create(
        '/admin/audit/activity?from=2026-01-15T00:00:00Z&to=2026-02-15T00:00:00Z',
        'GET',
    ));

    /** @var array{total: int} $payload */
    $payload = $response->getData(true);
    expect($payload['total'])->toBe(1); // only the February entry
});

it('returns 400 for invalid date strings', function (): void {
    $controller = new GlobalActivityLogController;
    $response = $controller->index(Request::create('/admin/audit/activity?from=not-a-date', 'GET'));

    expect($response->getStatusCode())->toBe(400);

    /** @var array{error: string} $payload */
    $payload = $response->getData(true);
    expect($payload['error'])->toBe('invalid_date');
});

it('clamps per_page=999 to 200', function (): void {
    $controller = new GlobalActivityLogController;
    $response = $controller->index(Request::create('/admin/audit/activity?per_page=999', 'GET'));

    /** @var array{per_page: int} $payload */
    $payload = $response->getData(true);
    expect($payload['per_page'])->toBe(200);
});

it('clamps per_page=0 to 1', function (): void {
    $controller = new GlobalActivityLogController;
    $response = $controller->index(Request::create('/admin/audit/activity?per_page=0', 'GET'));

    /** @var array{per_page: int} $payload */
    $payload = $response->getData(true);
    expect($payload['per_page'])->toBe(1);
});
