<?php

declare(strict_types=1);

use Arqel\Audit\Tests\Fixtures\FakeAuditableModel;

/**
 * AUDIT-004 coverage gap: existing trait tests partially asserted the
 * LogOptions config but didn't verify all 4 expected behaviors at once.
 */
it('produces LogOptions with all 4 canonical Arqel defaults', function (): void {
    $model = new FakeAuditableModel;
    $options = $model->getActivitylogOptions();

    expect($options->logOnlyDirty)->toBeTrue();
    expect($options->submitEmptyLogs)->toBeFalse();
    expect($options->logName)->toBe('FakeAuditableModel');
    expect($options->logFillable)->toBeFalse();
    // logOnly should reflect the model's fillable list (or ['*'] fallback)
    expect($options->logAttributes)->not->toBe([]);
});

it('uses class basename (not FQCN) as the log name', function (): void {
    $model = new FakeAuditableModel;
    $options = $model->getActivitylogOptions();

    expect($options->logName)->not->toContain('\\');
    expect($options->logName)->toBe('FakeAuditableModel');
});
