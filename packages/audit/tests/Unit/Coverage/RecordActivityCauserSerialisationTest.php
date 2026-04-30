<?php

declare(strict_types=1);

use Arqel\Audit\Http\Controllers\RecordActivityController;
use Illuminate\Database\Eloquent\Model;

/**
 * AUDIT-004 coverage gap: stringAttr() defensive helper that serialises
 * causer name/email — must tolerate causer models without those attributes.
 */
it('returns null when the attribute does not exist on the causer', function (): void {
    $reflection = new ReflectionMethod(RecordActivityController::class, 'stringAttr');
    $reflection->setAccessible(true);

    $causer = new class extends Model
    {
        protected $table = 'minimal_users';

        protected $guarded = [];

        public $incrementing = false;
    };

    expect($reflection->invoke(null, $causer, 'name'))->toBeNull();
    expect($reflection->invoke(null, $causer, 'email'))->toBeNull();
});

it('returns the string value when the attribute is present and string', function (): void {
    $reflection = new ReflectionMethod(RecordActivityController::class, 'stringAttr');
    $reflection->setAccessible(true);

    $causer = new class(['name' => 'Alice', 'email' => 'a@b.c']) extends Model
    {
        protected $table = 'users';

        protected $guarded = [];
    };

    expect($reflection->invoke(null, $causer, 'name'))->toBe('Alice');
    expect($reflection->invoke(null, $causer, 'email'))->toBe('a@b.c');
});

it('returns null when the attribute is non-string (array)', function (): void {
    $reflection = new ReflectionMethod(RecordActivityController::class, 'stringAttr');
    $reflection->setAccessible(true);

    $causer = new class(['name' => ['array', 'bug']]) extends Model
    {
        protected $table = 'users';

        protected $guarded = [];
    };

    expect($reflection->invoke(null, $causer, 'name'))->toBeNull();
});
