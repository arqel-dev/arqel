<?php

declare(strict_types=1);

use Arqel\Core\Relations\RelationManager;
use Arqel\Core\Tests\Fixtures\Relations\CommentsRelationManager;
use Arqel\Core\Tests\Fixtures\Resources\RelPostResource;

it('returns an empty relations array by default', function (): void {
    $resource = new class extends Arqel\Core\Resources\Resource
    {
        public static string $model = Arqel\Core\Tests\Fixtures\Models\RelComment::class;

        public function fields(): array
        {
            return [];
        }
    };

    expect($resource->relations())->toBe([])
        ->and($resource->getRelations())->toBe([]);
});

it('instantiates declared relation managers keyed by slug', function (): void {
    $managers = (new RelPostResource)->getRelations();

    expect($managers)->toHaveKey('comments')
        ->and($managers['comments'])->toBeInstanceOf(CommentsRelationManager::class)
        ->and($managers['comments'])->toBeInstanceOf(RelationManager::class);
});
