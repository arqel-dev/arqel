<?php

declare(strict_types=1);

use Arqel\Versioning\Models\Version;
use Arqel\Versioning\Tests\Fixtures\CastedArticle;

// Regression coverage for issue #187 — versions snapshot/restore/diff must be
// cast-aware so array/json/object/collection/encrypted casts round-trip without
// double-encoding (silent permanent data corruption).

it('round-trips an array-cast attribute through restore without corruption', function (): void {
    $article = CastedArticle::create([
        'title' => 'V1',
        'meta' => ['k' => 1, 'flag' => true],
    ]);

    /** @var Version $first */
    $first = $article->versions()->reorder('id', 'asc')->first();

    $article->update(['meta' => ['k' => 99, 'flag' => false]]);

    expect($article->restoreToVersion($first))->toBeTrue();

    // `refresh()` reloads from the DB in place (non-null `$this`).
    $article->refresh();

    // The cast must yield an ARRAY, not a double-encoded JSON string.
    expect($article->meta)->toBeArray();
    expect($article->meta)->toBe(['k' => 1, 'flag' => true]);
});

it('snapshots an array-cast attribute as an array in the payload', function (): void {
    $article = CastedArticle::create([
        'title' => 'Snap',
        'meta' => ['a' => 1, 'b' => 2],
    ]);

    /** @var Version $version */
    $version = $article->versions()->first();

    // payload is `array`-cast on Version, so a properly snapshotted value
    // decodes to a nested array — not a JSON string nested in the payload.
    expect($version->payload['meta'])->toBeArray();
    expect($version->payload['meta'])->toBe(['a' => 1, 'b' => 2]);
});

it('records a symmetric diff for array-cast attributes (both sides arrays)', function (): void {
    $article = CastedArticle::create([
        'title' => 'Diff',
        'meta' => ['a' => 1],
    ]);

    $article->update(['meta' => ['a' => 2]]);

    /** @var Version $latest */
    $latest = $article->versions()->first();

    $changes = $latest->changes ?? [];
    expect($changes)->toHaveKey('meta');

    [$before, $after] = $changes['meta'];

    // Both sides must be arrays of the same shape — not [array, jsonString].
    expect($before)->toBeArray();
    expect($after)->toBeArray();
    expect($before)->toBe(['a' => 1]);
    expect($after)->toBe(['a' => 2]);
});

it('keeps scalar attributes unchanged through the cast-aware path', function (): void {
    $article = CastedArticle::create([
        'title' => 'Scalar V1',
        'meta' => ['x' => 1],
    ]);

    /** @var Version $first */
    $first = $article->versions()->reorder('id', 'asc')->first();

    $article->update(['title' => 'Scalar V2']);

    expect($article->restoreToVersion($first))->toBeTrue();

    $article->refresh();
    expect($article->title)->toBe('Scalar V1');
    expect($article->title)->toBeString();
});
