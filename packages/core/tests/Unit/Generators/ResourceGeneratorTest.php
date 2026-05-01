<?php

declare(strict_types=1);

use Arqel\Core\Generators\ResourceGenerator;
use Arqel\Core\Tests\Fixtures\Models\Post;

function makeGenerator(array $overrides = []): ResourceGenerator
{
    return new ResourceGenerator(
        modelClass: $overrides['modelClass'] ?? Post::class,
        label: $overrides['label'] ?? 'Post',
        group: $overrides['group'] ?? null,
        icon: $overrides['icon'] ?? null,
        fields: $overrides['fields'] ?? [],
        withPolicy: $overrides['withPolicy'] ?? false,
        withFormRequests: $overrides['withFormRequests'] ?? false,
        testFramework: $overrides['testFramework'] ?? null,
    );
}

it('renders the Resource file with each declared field', function (): void {
    $php = makeGenerator([
        'fields' => [
            ['name' => 'title', 'type' => 'text'],
            ['name' => 'body', 'type' => 'textarea'],
            ['name' => 'published_at', 'type' => 'dateTime'],
        ],
    ])->generateResourceFile();

    expect($php)
        ->toContain('final class PostResource extends Resource')
        ->toContain('public static string $model = Post::class;')
        ->toContain("Field::text('title'),")
        ->toContain("Field::textarea('body'),")
        ->toContain("Field::dateTime('published_at'),");
});

it('writes label, group and icon metadata when provided', function (): void {
    $php = makeGenerator([
        'label' => 'Article',
        'group' => 'Content',
        'icon' => 'file-text',
    ])->generateResourceFile();

    expect($php)
        ->toContain("public static ?string \$label = 'Article';")
        ->toContain("public static ?string \$navigationGroup = 'Content';")
        ->toContain("public static ?string \$navigationIcon = 'file-text';");
});

it('generates a Policy file with the five default abilities', function (): void {
    $php = makeGenerator()->generatePolicyFile();

    expect($php)
        ->toContain('final class PostPolicy')
        ->toContain('public function viewAny(User $user): bool')
        ->toContain('public function view(User $user, Post $post): bool')
        ->toContain('public function create(User $user): bool')
        ->toContain('public function update(User $user, Post $post): bool')
        ->toContain('public function delete(User $user, Post $post): bool');
});

it('generates Store/Update FormRequest classes', function (): void {
    $generator = makeGenerator();

    expect($generator->generateRequestFile('store'))
        ->toContain('final class StorePostRequest extends FormRequest')
        ->toContain('public function rules(): array');

    expect($generator->generateRequestFile('update'))
        ->toContain('final class UpdatePostRequest extends FormRequest');
});

it('rejects unsupported request kinds', function (): void {
    makeGenerator()->generateRequestFile('delete'); // @phpstan-ignore-line argument.type
})->throws(InvalidArgumentException::class);

it('rejects unsupported test frameworks', function (): void {
    makeGenerator(['testFramework' => 'phpunit']);
})->throws(InvalidArgumentException::class);

it('produces syntactically valid PHP', function (): void {
    $generator = makeGenerator([
        'group' => 'Content',
        'icon' => 'file-text',
        'fields' => [['name' => 'title', 'type' => 'text']],
        'withPolicy' => true,
        'testFramework' => 'pest',
    ]);

    foreach ([
        $generator->generateResourceFile(),
        $generator->generatePolicyFile(),
        $generator->generateRequestFile('store'),
        $generator->generateRequestFile('update'),
        $generator->generateTestFile(),
    ] as $code) {
        $tokens = token_get_all($code);
        expect($tokens)->toBeArray()
            ->and(count($tokens))->toBeGreaterThan(5);
    }
});

it('emits an empty placeholder when no fields are declared', function (): void {
    $php = makeGenerator()->generateResourceFile();

    expect($php)->toContain("// Field::text('name')->required(),");
});
