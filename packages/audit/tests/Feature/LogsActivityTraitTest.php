<?php

declare(strict_types=1);

use Arqel\Audit\Tests\Fixtures\FakeAuditableModel;
use Spatie\Activitylog\Models\Activity;

it('logs an entry when an auditable model is created', function (): void {
    $model = FakeAuditableModel::create([
        'name' => 'Aragorn',
        'email' => 'aragorn@gondor.test',
    ]);

    /** @var Activity|null $activity */
    $activity = Activity::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity?->log_name)->toBe('FakeAuditableModel')
        ->and($activity?->description)->toBe('created')
        ->and($activity?->subject_id)->toBe($model->id);

    /** @var array<string, mixed> $properties */
    $properties = $activity?->properties?->toArray() ?? [];
    expect($properties)->toHaveKey('attributes')
        ->and($properties['attributes'])->toMatchArray([
            'name' => 'Aragorn',
            'email' => 'aragorn@gondor.test',
        ]);
});

it('records old and new attributes on update', function (): void {
    $model = FakeAuditableModel::create([
        'name' => 'Frodo',
        'email' => 'frodo@shire.test',
    ]);

    $model->update(['email' => 'ringbearer@shire.test']);

    /** @var Activity|null $activity */
    $activity = Activity::query()
        ->where('description', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity?->log_name)->toBe('FakeAuditableModel');

    /** @var array<string, mixed> $properties */
    $properties = $activity?->properties?->toArray() ?? [];
    expect($properties)->toHaveKey('attributes')
        ->and($properties)->toHaveKey('old')
        ->and($properties['attributes'])->toMatchArray(['email' => 'ringbearer@shire.test'])
        ->and($properties['old'])->toMatchArray(['email' => 'frodo@shire.test']);
});

it('logs a delete entry', function (): void {
    $model = FakeAuditableModel::create([
        'name' => 'Boromir',
        'email' => 'boromir@gondor.test',
    ]);

    $modelId = $model->id;
    $model->delete();

    /** @var Activity|null $activity */
    $activity = Activity::query()
        ->where('description', 'deleted')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity?->log_name)->toBe('FakeAuditableModel')
        ->and($activity?->subject_id)->toBe($modelId);
});

it('does not submit an empty log when nothing dirty', function (): void {
    $model = FakeAuditableModel::create([
        'name' => 'Sam',
        'email' => 'sam@shire.test',
    ]);

    $createdLogCount = Activity::query()->count();

    // Saving without any change must not produce a new log entry.
    $model->save();

    expect(Activity::query()->count())->toBe($createdLogCount);
});
