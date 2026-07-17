<?php

declare(strict_types=1);

use Arqel\Export\Actions\ExportAction;
use Arqel\Export\ExportFormat;
use Arqel\Export\Models\Export;

it('creates an Export row owned by the authenticated user', function (): void {
    $user = new class extends \Illuminate\Foundation\Auth\User {
        protected $table = 'users';

        public function getAuthIdentifier(): mixed
        {
            return 42;
        }
    };
    $this->be($user);

    $dir = sys_get_temp_dir().'/arqel-export-test-'.uniqid();
    $result = ExportAction::make('export')
        ->format(ExportFormat::CSV)
        ->withColumns([['key' => 'id', 'label' => 'ID']])
        ->withDestinationDir($dir)
        ->execute([['id' => 1]]);

    $exports = Export::all();
    expect($exports)->toHaveCount(1);

    $export = $exports->first();
    expect($export->owner_user_id)->toBe('42');
    expect($export->format)->toBe('csv');
    expect($export->path)->toBe($result['path']);
    // filename id matches the record id: export-<id>.csv
    expect($result['filename'])->toBe('export-'.$export->id.'.csv');
});

it('stores a null owner when unauthenticated (CLI/guest)', function (): void {
    $dir = sys_get_temp_dir().'/arqel-export-test-'.uniqid();
    ExportAction::make('export')
        ->format(ExportFormat::CSV)
        ->withColumns([['key' => 'id', 'label' => 'ID']])
        ->withDestinationDir($dir)
        ->execute([['id' => 1]]);

    expect(Export::first()->owner_user_id)->toBeNull();
});
