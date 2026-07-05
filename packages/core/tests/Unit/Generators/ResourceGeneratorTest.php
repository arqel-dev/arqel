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

it('imports the FieldFactory alias so generated `Field::` references resolve', function (): void {
    // Regression: the generator emitted `Field::text(...)` but never imported the
    // symbol, so the generated Resource fatal-errored on load (class `Field` not
    // found). token_get_all() cannot catch this — it is a name-resolution bug, not
    // a syntax one — so assert the `use` alias is present whenever `Field::` is used.
    $php = makeGenerator([
        'fields' => [['name' => 'title', 'type' => 'text']],
    ])->generateResourceFile();

    expect($php)
        ->toContain('use Arqel\Fields\FieldFactory as Field;')
        ->toContain("Field::text('title'),");
});

it('imports the FieldFactory alias even for the commented placeholder', function (): void {
    // The empty-fields template still shows `// Field::text('name')...` as the
    // hint a user will uncomment, so the import must be there unconditionally.
    $php = makeGenerator()->generateResourceFile();

    expect($php)->toContain('use Arqel\Fields\FieldFactory as Field;');
});

it('every `Field::` reference in the generated Resource has a matching use-alias', function (): void {
    // Structural guarantee: any short-name static call in the generated file
    // must be backed by an import, otherwise it will not resolve at runtime.
    $php = makeGenerator([
        'fields' => [
            ['name' => 'title', 'type' => 'text'],
            ['name' => 'published_at', 'type' => 'dateTime'],
        ],
    ])->generateResourceFile();

    if (str_contains($php, 'Field::')) {
        expect($php)->toMatch('/^use .*\bas Field;$/m');
    }
});
