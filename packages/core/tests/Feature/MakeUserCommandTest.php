<?php

declare(strict_types=1);

use Arqel\Core\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config([
        'auth.defaults.guard' => 'web',
        'auth.guards.web.provider' => 'users',
        'auth.providers.users.model' => User::class,
    ]);

    Schema::create('users', function ($t): void {
        $t->id();
        $t->string('name')->nullable();
        $t->string('email')->unique();
        $t->string('password');
        $t->timestamp('email_verified_at')->nullable();
        $t->timestamps();
    });
});

it('creates a user on first run', function (): void {
    $this->artisan('arqel:make-user', [
        '--name' => 'Admin',
        '--email' => 'a@x.test',
        '--password' => 'secret',
    ])->assertExitCode(0);

    expect(DB::table('users')->count())->toBe(1);
});

it('fails on a duplicate email without --force', function (): void {
    DB::table('users')->insert(['email' => 'a@x.test', 'name' => 'Old', 'password' => 'x']);

    $this->artisan('arqel:make-user', [
        '--name' => 'Admin',
        '--email' => 'a@x.test',
        '--password' => 'secret',
    ])->assertExitCode(1);
});

it('updates an existing user with --force and exits 0', function (): void {
    DB::table('users')->insert(['email' => 'a@x.test', 'name' => 'Old', 'password' => 'x']);

    $this->artisan('arqel:make-user', [
        '--name' => 'New Name',
        '--email' => 'a@x.test',
        '--password' => 'secret',
        '--force' => true,
    ])->assertExitCode(0);

    $row = DB::table('users')->where('email', 'a@x.test')->first();
    expect($row->name)->toBe('New Name')
        ->and(DB::table('users')->count())->toBe(1);
});

it('creates the user with --force when the email does not yet exist', function (): void {
    $this->artisan('arqel:make-user', [
        '--name' => 'Fresh',
        '--email' => 'fresh@x.test',
        '--password' => 'secret',
        '--force' => true,
    ])->assertExitCode(0);

    expect(DB::table('users')->where('email', 'fresh@x.test')->count())->toBe(1);
});
