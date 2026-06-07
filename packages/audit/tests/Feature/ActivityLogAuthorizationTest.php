<?php

declare(strict_types=1);

use Arqel\Audit\Tests\Fixtures\AllowViewPolicy;
use Arqel\Audit\Tests\Fixtures\DenyViewPolicy;
use Arqel\Audit\Tests\Fixtures\FakeAuditableModel;
use Arqel\Audit\Tests\TestCase;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cobertura de autorização das rotas de leitura do activity-log (issue #181).
 *
 * As rotas `GET /admin/audit/activity` e
 * `GET /admin/audit/{subjectType}/{subjectId}/activity` rodam atrás de
 * `['web', 'auth']` mas precisam honrar gates/policies no controller para
 * não vazar quem-fez-o-quê (causer email, diffs) a qualquer usuário logado.
 */
function authedAuditUser(): AuthUser
{
    $user = new AuthUser;
    $user->forceFill(['id' => 1, 'name' => 'Low Priv', 'email' => 'low@priv.test']);

    return $user;
}

/**
 * @return TestResponse<Response>
 */
function getAsAudit(TestCase $case, string $url): TestResponse
{
    return $case->actingAs(authedAuditUser())->getJson($url);
}

/**
 * Cria um record + uma entrada de activity isolada para ele, sem depender
 * do auto-log do trait `LogsActivity` (que pode ou não disparar conforme
 * config). Limpamos qualquer activity pré-existente e inserimos exatamente
 * uma, garantindo `total === 1` no assert.
 */
function makeSubjectWithActivity(): FakeAuditableModel
{
    /** @var FakeAuditableModel $model */
    $model = FakeAuditableModel::create(['name' => 'Frodo', 'email' => 'frodo@shire.test']);

    Activity::query()->delete();

    /** @var Activity $activity */
    $activity = new Activity;
    $activity->log_name = 'default';
    $activity->description = 'created';
    $activity->event = 'created';
    $activity->subject_type = FakeAuditableModel::class;
    $activity->subject_id = $model->id;
    $activity->properties = collect([]);
    $activity->save();

    return $model;
}

function recordActivityUrl(FakeAuditableModel $model): string
{
    return '/admin/audit/'.urlencode(FakeAuditableModel::class).'/'.$model->id.'/activity';
}

// --- Global activity-log (GET /admin/audit/activity) ----------------------

it('allows the global log in scaffold mode (no gate, no policy)', function (): void {
    /** @var TestCase $this */
    getAsAudit($this, '/admin/audit/activity')->assertStatus(200);
});

it('returns 403 on the global log when a named ability denies it', function (): void {
    Gate::define('view-audit-log', static fn (?AuthUser $user): bool => false);

    /** @var TestCase $this */
    getAsAudit($this, '/admin/audit/activity')->assertStatus(403);
});

it('returns 200 on the global log when a named ability allows it', function (): void {
    Gate::define('view-audit-log', static fn (?AuthUser $user): bool => true);

    /** @var TestCase $this */
    getAsAudit($this, '/admin/audit/activity')->assertStatus(200);
});

it('returns 403 on the global log when a viewAny gate on Activity denies it', function (): void {
    Gate::define('viewAny', static fn (?AuthUser $user, string $class): bool => false);

    /** @var TestCase $this */
    getAsAudit($this, '/admin/audit/activity')->assertStatus(403);
});

// --- Record activity (GET /admin/audit/{subjectType}/{subjectId}/activity)

it('allows record activity in scaffold mode (no policy on subject)', function (): void {
    $model = makeSubjectWithActivity();

    /** @var TestCase $this */
    $response = getAsAudit($this, recordActivityUrl($model));

    $response->assertStatus(200);
    expect($response->json('total'))->toBe(1);
});

it('returns 403 on record activity when a Policy denies view on the subject', function (): void {
    Gate::policy(FakeAuditableModel::class, DenyViewPolicy::class);

    $model = makeSubjectWithActivity();

    /** @var TestCase $this */
    getAsAudit($this, recordActivityUrl($model))->assertStatus(403);
});

it('returns 200 on record activity when a Policy allows view on the subject', function (): void {
    Gate::policy(FakeAuditableModel::class, AllowViewPolicy::class);

    $model = makeSubjectWithActivity();

    /** @var TestCase $this */
    $response = getAsAudit($this, recordActivityUrl($model));

    $response->assertStatus(200);
    expect($response->json('total'))->toBe(1);
});
