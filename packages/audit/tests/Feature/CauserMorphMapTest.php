<?php

declare(strict_types=1);

use Arqel\Audit\Tests\Fixtures\FakeAuditableModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Auth\User as AuthUser;
use Spatie\Activitylog\Models\Activity;

/**
 * Causer morph-map support (issue #230).
 *
 * Under `Relation::enforceMorphMap()` (strict, merge=false), Spatie's
 * `causedBy()` does `causer()->associate($authUser)`, which calls
 * `$authUser->getMorphClass()` — that throws `ClassMorphViolationException`
 * when the framework's auth provider model is NOT in the morph map.
 *
 * The audit provider must auto-register the configured auth provider model
 * under a sensible alias when (and only when) a strict map is active and the
 * model is not already mapped — so the framework's own `LogsActivity` causer
 * round-trips out of the box, storing the ALIAS (not the FQCN).
 */
afterEach(function (): void {
    // Reset the morph map (and the enforce flag) so the alias does not
    // leak into other tests in this file or the wider suite.
    Relation::morphMap([], false);
    Relation::requireMorphMap(false);
});

it('persists a write with an authenticated causer under a strict morph map', function (): void {
    // The app's strict map intentionally does NOT include the auth User.
    Relation::enforceMorphMap(['post' => FakeAuditableModel::class]);

    $causer = new AuthUser;
    $causer->forceFill(['id' => 7, 'name' => 'Author', 'email' => 'author@example.test']);

    // The write must SUCCEED — no ClassMorphViolationException.
    $this->actingAs($causer);

    $model = FakeAuditableModel::create([
        'name' => 'Aragorn',
        'email' => 'aragorn@gondor.test',
    ]);

    /** @var Activity|null $activity */
    $activity = Activity::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity?->subject_id)->toBe($model->id)
        // Causer stored as the auto-registered alias, NOT the FQCN.
        ->and($activity?->causer_type)->not->toBe(AuthUser::class)
        ->and($activity?->causer_id)->toBe(7);

    // The auth model resolves back to itself from the registered alias.
    /** @var string $causerType */
    $causerType = $activity?->causer_type;
    expect(Relation::getMorphedModel($causerType))->toBe(AuthUser::class);
});

it('does not register a morph alias when no strict map is enforced (no regression)', function (): void {
    config()->set('auth.providers.users.model', AuthUser::class);

    $causer = new AuthUser;
    $causer->forceFill(['id' => 3, 'name' => 'Plain', 'email' => 'plain@example.test']);
    $this->actingAs($causer);

    $model = FakeAuditableModel::create(['name' => 'Sam', 'email' => 'sam@shire.test']);

    /** @var Activity|null $activity */
    $activity = Activity::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity?->subject_id)->toBe($model->id)
        // Without an enforced map the causer is stored as the FQCN, and the
        // global map is NOT forced into existence.
        ->and($activity?->causer_type)->toBe(AuthUser::class)
        ->and(Relation::requiresMorphMap())->toBeFalse();
});

it('never overrides an app-supplied alias for the auth model', function (): void {
    // App already maps the auth User under its own chosen alias.
    Relation::enforceMorphMap([
        'post' => FakeAuditableModel::class,
        'app-user' => AuthUser::class,
    ]);

    $causer = new AuthUser;
    $causer->forceFill(['id' => 9, 'name' => 'Owned', 'email' => 'owned@example.test']);
    $this->actingAs($causer);

    FakeAuditableModel::create(['name' => 'Bilbo', 'email' => 'bilbo@shire.test']);

    /** @var Activity|null $activity */
    $activity = Activity::query()->latest('id')->first();

    // The app's mapping wins — we must not clobber it.
    expect($activity?->causer_type)->toBe('app-user');
});
