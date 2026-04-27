<?php

declare(strict_types=1);

use Arqel\Core\Contracts\HasFields;
use Arqel\Core\Contracts\HasResource;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Tests\Fixtures\Models\Post;
use Arqel\Core\Tests\Fixtures\Models\User;
use Arqel\Core\Tests\Fixtures\Resources\PostResource;
use Arqel\Core\Tests\Fixtures\Resources\UserResource;
use Arqel\Core\Tests\Fixtures\ResourcesExtras\LifecycleResource;
use Arqel\Core\Tests\Fixtures\ResourcesExtras\MissingModelResource;
use Arqel\Core\Tests\Fixtures\ResourcesExtras\TeamMemberResource;

it('returns the configured model class', function (): void {
    expect(UserResource::getModel())->toBe(User::class)
        ->and(PostResource::getModel())->toBe(Post::class);
});

it('throws a clear error when getModel is called without $model', function (): void {
    MissingModelResource::getModel();
})->throws(LogicException::class, 'must declare a public static string $model');

it('auto-derives slug from the resource class name', function (): void {
    expect(UserResource::getSlug())->toBe('users')
        ->and(PostResource::getSlug())->toBe('posts');
});

it('auto-derives label from the model class basename', function (): void {
    expect(UserResource::getLabel())->toBe('User')
        ->and(PostResource::getLabel())->toBe('Post');
});

it('auto-derives plural label from the singular label', function (): void {
    expect(UserResource::getPluralLabel())->toBe('Users')
        ->and(PostResource::getPluralLabel())->toBe('Posts');
});

it('honours overridden slug, label and plural label', function (): void {
    expect(TeamMemberResource::getSlug())->toBe('team-members')
        ->and(TeamMemberResource::getLabel())->toBe('Team Member')
        ->and(TeamMemberResource::getPluralLabel())->toBe('Team Members');
});

it('exposes navigation metadata declared on the subclass', function (): void {
    expect(UserResource::getNavigationIcon())->toBe('heroicon-o-user')
        ->and(UserResource::getNavigationGroup())->toBe('System')
        ->and(UserResource::getNavigationSort())->toBe(10);
});

it('returns null navigation metadata when not declared', function (): void {
    expect(PostResource::getNavigationIcon())->toBeNull()
        ->and(PostResource::getNavigationGroup())->toBeNull()
        ->and(PostResource::getNavigationSort())->toBeNull();
});

it('falls back to the primary key when no record title attribute is set', function (): void {
    $resource = new PostResource;
    $post = new Post(['id' => 42]);
    $post->setAttribute('id', 42);

    expect($resource->recordTitle($post))->toBe('42');
});

it('uses the configured record title attribute when present', function (): void {
    $post = new Post;
    $post->setAttribute('title', 'Hello world');

    $resource = new class extends Resource
    {
        public static string $model = Post::class;

        public static ?string $recordTitleAttribute = 'title';

        public function fields(): array
        {
            return [];
        }
    };

    expect($resource->recordTitle($post))->toBe('Hello world');
});

it('returns null indexQuery and recordSubtitle by default', function (): void {
    $resource = new PostResource;

    expect($resource->indexQuery())->toBeNull()
        ->and($resource->recordSubtitle(new Post))->toBeNull();
});

it('runs the create pipeline through both beforeCreate/Save and afterCreate/Save in order', function (): void {
    $resource = new LifecycleResource;
    $resource->runCreatePipeline(['title' => 'x'], new Post);

    expect($resource->calls)->toBe([
        'beforeCreate',
        'beforeSave',
        'afterCreate',
        'afterSave',
    ]);
});

it('runs the update pipeline through both beforeUpdate/Save and afterUpdate/Save in order', function (): void {
    $resource = new LifecycleResource;
    $resource->runUpdatePipeline(new Post, ['title' => 'x']);

    expect($resource->calls)->toBe([
        'beforeUpdate',
        'beforeSave',
        'afterUpdate',
        'afterSave',
    ]);
});

it('still satisfies HasResource on subclasses', function (): void {
    expect(new UserResource)->toBeInstanceOf(HasResource::class)
        ->and(new PostResource)->toBeInstanceOf(HasFields::class);
});
