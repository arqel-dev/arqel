<?php

declare(strict_types=1);

it('loads the audit config file via the service provider', function (): void {
    expect(config('audit'))->toBeArray()
        ->and(config('audit'))->toHaveKeys([
            'global_log_url',
            'navigation_label',
            'navigation_group',
            'navigation_icon',
        ]);
});

it('exposes global_log_url pointing to the controller route', function (): void {
    expect(config('audit.global_log_url'))->toBe('/admin/audit/activity');
});
