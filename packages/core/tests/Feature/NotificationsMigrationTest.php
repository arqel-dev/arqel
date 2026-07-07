<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('creates the notifications table with the Laravel stock schema', function (): void {
    expect(Schema::hasTable('notifications'))->toBeTrue()
        ->and(Schema::hasColumns('notifications', [
            'id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at',
        ]))->toBeTrue();
});
