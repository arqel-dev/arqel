<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;

// A minimal in-memory model + resource for the concern defaults.
class GlobalSearchStubModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

it('is opt-out by default (empty globallySearchable)', function () {
    $resource = new class extends Arqel\Core\Resources\Resource
    {
        public static string $model = GlobalSearchStubModel::class;

        public function fields(): array
        {
            return [];
        }
    };

    expect($resource::globallySearchable())->toBe([]);
});

it('titles a record by the first searchable attribute', function () {
    $resource = new class extends Arqel\Core\Resources\Resource
    {
        public static string $model = GlobalSearchStubModel::class;

        public function fields(): array
        {
            return [];
        }

        public static function globallySearchable(): array
        {
            return ['name', 'email'];
        }
    };
    $record = new GlobalSearchStubModel(['name' => 'Ana Lima', 'email' => 'ana@x.com']);

    expect($resource::globalSearchResultTitle($record))->toBe('Ana Lima');
});

it('falls back to #key when the title attribute is empty', function () {
    $resource = new class extends Arqel\Core\Resources\Resource
    {
        public static string $model = GlobalSearchStubModel::class;

        public function fields(): array
        {
            return [];
        }

        public static function globallySearchable(): array
        {
            return ['name'];
        }
    };
    $record = new GlobalSearchStubModel(['name' => null]);
    $record->setAttribute($record->getKeyName(), 42);

    expect($resource::globalSearchResultTitle($record))->toBe('#42');
});
