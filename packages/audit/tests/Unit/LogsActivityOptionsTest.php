<?php

declare(strict_types=1);

use Arqel\Audit\Tests\Fixtures\FakeAuditableModel;
use Spatie\Activitylog\LogOptions;

it('configures Spatie LogOptions with Arqel defaults', function (): void {
    $model = new FakeAuditableModel;

    $options = $model->getActivitylogOptions();

    expect($options)->toBeInstanceOf(LogOptions::class)
        ->and($options->logName)->toBe('FakeAuditableModel')
        ->and($options->logOnlyDirty)->toBeTrue()
        ->and($options->submitEmptyLogs)->toBeFalse();
});

it('whitelists the model fillable attributes by default', function (): void {
    $model = new FakeAuditableModel;

    $options = $model->getActivitylogOptions();

    expect($options->logAttributes)->toEqualCanonicalizing(['name', 'email']);
});
