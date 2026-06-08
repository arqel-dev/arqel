<?php

declare(strict_types=1);

use Arqel\Audit\Tests\Fixtures\DenyViewPolicy;
use Arqel\Audit\Tests\Fixtures\FakeAuditableModel;
use Arqel\Audit\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

/**
 * Morph-map support for the per-record activity timeline (issue #190).
 *
 * Spatie persists `subject_type` via `getMorphClass()` — under
 * `Relation::enforceMorphMap()` that is the alias (`'post'`), not the
 * FQCN. `RecordActivityController::show()` must (a) accept an alias as
 * `{subjectType}` and (b) query the value that is actually STORED, so
 * drilling from the global feed (which serialises the stored alias) into
 * the per-record endpoint round-trips, and an FQCN query under a map
 * still finds the alias-keyed rows.
 */
function authedMorphUser(): AuthUser
{
    $user = new AuthUser;
    $user->forceFill(['id' => 1, 'name' => 'Low Priv', 'email' => 'low@priv.test']);

    return $user;
}

/**
 * @return TestResponse<Response>
 */
function getAsMorph(TestCase $case, string $url): TestResponse
{
    return $case->actingAs(authedMorphUser())->getJson($url);
}

/**
 * Record exactly one activity entry whose `subject_type` is whatever the
 * model's `getMorphClass()` resolves to (the alias under an active map,
 * the FQCN otherwise) — mirroring how Spatie's `LogsActivity` writes.
 */
function recordMorphActivity(): FakeAuditableModel
{
    /** @var FakeAuditableModel $model */
    $model = FakeAuditableModel::create(['name' => 'Frodo', 'email' => 'frodo@shire.test']);

    Activity::query()->delete();

    /** @var Activity $activity */
    $activity = new Activity;
    $activity->log_name = 'default';
    $activity->description = 'created';
    $activity->event = 'created';
    $activity->subject_type = $model->getMorphClass();
    $activity->subject_id = $model->id;
    $activity->properties = collect([]);
    $activity->save();

    return $model;
}

afterEach(function (): void {
    // Reset the morph map (and the enforce flag) so the alias does not
    // leak into other tests in this file or the wider suite.
    Relation::morphMap([], false);
    Relation::requireMorphMap(false);
});

it('returns 200 with rows when drilling in via a morph alias', function (): void {
    Relation::enforceMorphMap(['post' => FakeAuditableModel::class]);

    $model = recordMorphActivity();

    // Sanity: storage holds the alias, not the FQCN.
    expect(Activity::query()->value('subject_type'))->toBe('post');

    /** @var TestCase $this */
    $response = getAsMorph($this, '/admin/audit/post/'.$model->id.'/activity');

    $response->assertStatus(200);
    expect($response->json('total'))->toBe(1);
});

it('returns 200 with rows when querying by FQCN under an active morph map', function (): void {
    Relation::enforceMorphMap(['post' => FakeAuditableModel::class]);

    $model = recordMorphActivity();

    /** @var TestCase $this */
    $response = getAsMorph(
        $this,
        '/admin/audit/'.urlencode(FakeAuditableModel::class).'/'.$model->id.'/activity',
    );

    $response->assertStatus(200);
    expect($response->json('total'))->toBe(1);
});

it('returns 200 with rows for an FQCN with no morph map (no regression)', function (): void {
    $model = recordMorphActivity();

    expect(Activity::query()->value('subject_type'))->toBe(FakeAuditableModel::class);

    /** @var TestCase $this */
    $response = getAsMorph(
        $this,
        '/admin/audit/'.urlencode(FakeAuditableModel::class).'/'.$model->id.'/activity',
    );

    $response->assertStatus(200);
    expect($response->json('total'))->toBe(1);
});

it('still returns 400 for a subjectType that is neither a class nor a mapped alias', function (): void {
    Relation::enforceMorphMap(['post' => FakeAuditableModel::class]);

    /** @var TestCase $this */
    getAsMorph($this, '/admin/audit/not-a-known-alias/1/activity')->assertStatus(400);
});

it('enforces the view policy for morph-alias input', function (): void {
    Relation::enforceMorphMap(['post' => FakeAuditableModel::class]);
    Gate::policy(FakeAuditableModel::class, DenyViewPolicy::class);

    $model = recordMorphActivity();

    /** @var TestCase $this */
    getAsMorph($this, '/admin/audit/post/'.$model->id.'/activity')->assertStatus(403);
});

it('enforces the view policy for FQCN input under a morph map', function (): void {
    Relation::enforceMorphMap(['post' => FakeAuditableModel::class]);
    Gate::policy(FakeAuditableModel::class, DenyViewPolicy::class);

    $model = recordMorphActivity();

    /** @var TestCase $this */
    getAsMorph(
        $this,
        '/admin/audit/'.urlencode(FakeAuditableModel::class).'/'.$model->id.'/activity',
    )->assertStatus(403);
});
