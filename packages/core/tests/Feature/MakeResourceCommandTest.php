<?php

declare(strict_types=1);

use Arqel\Core\Tests\Fixtures\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->files = new Filesystem;

    config()->set('arqel.resources.path', app_path('Arqel/Resources'));
    config()->set('arqel.resources.namespace', 'App\\Arqel\\Resources');

    $this->cleanup = [
        app_path('Arqel'),
        app_path('Policies'),
    ];

    foreach ($this->cleanup as $path) {
        $this->files->deleteDirectory($path);
    }
});

afterEach(function (): void {
    foreach ($this->cleanup as $path) {
        $this->files->deleteDirectory($path);
    }
});

it('generates a Resource file at the configured path', function (): void {
    $exit = Artisan::call('arqel:resource', [
        'model' => User::class,
    ]);

    $target = app_path('Arqel/Resources/UserResource.php');

    expect($exit)->toBe(0)
        ->and($this->files->exists($target))->toBeTrue();

    $contents = (string) $this->files->get($target);

    expect($contents)
        ->toContain('namespace App\\Arqel\\Resources;')
        ->toContain('use '.User::class.';')
        ->toContain('final class UserResource extends Resource')
        ->toContain('public static string $model = User::class;')
        ->not->toContain('{{');
});

it('resolves a short model name against App\\Models', function (): void {
    $exit = Artisan::call('arqel:resource', [
        'model' => 'Missing',
    ]);

    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('App\\Models\\Missing');
});

it('returns failure for an unknown FQN model', function (): void {
    $exit = Artisan::call('arqel:resource', [
        'model' => 'App\\Nope\\Ghost',
    ]);

    expect($exit)->toBe(1);
});

it('runs make:policy when --with-policy is passed', function (): void {
    Artisan::call('arqel:resource', [
        'model' => User::class,
        '--with-policy' => true,
    ]);

    $policy = app_path('Policies/UserPolicy.php');

    expect($this->files->exists($policy))->toBeTrue();

    $contents = (string) $this->files->get($policy);

    expect($contents)
        ->toContain('class UserPolicy')
        ->toContain('viewAny')
        ->toContain('view')
        ->toContain('create')
        ->toContain('update')
        ->toContain('delete');
});

it('overwrites an existing Resource when --force is used', function (): void {
    $target = app_path('Arqel/Resources/UserResource.php');
    $this->files->ensureDirectoryExists(dirname($target));
    $this->files->put($target, '<?php // sentinel');

    Artisan::call('arqel:resource', [
        'model' => User::class,
        '--force' => true,
    ]);

    expect((string) $this->files->get($target))
        ->not->toContain('// sentinel')
        ->toContain('final class UserResource');
});

it('honours arqel.resources.path/namespace overrides', function (): void {
    config()->set('arqel.resources.path', app_path('Custom/Place'));
    config()->set('arqel.resources.namespace', 'App\\Custom\\Place');

    $this->cleanup[] = app_path('Custom');

    Artisan::call('arqel:resource', [
        'model' => User::class,
    ]);

    $target = app_path('Custom/Place/UserResource.php');

    expect($this->files->exists($target))->toBeTrue()
        ->and((string) $this->files->get($target))
        ->toContain('namespace App\\Custom\\Place;');
});
