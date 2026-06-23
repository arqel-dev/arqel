<?php

declare(strict_types=1);

use Arqel\Audit\Http\Controllers\GlobalActivityLogController;
use Arqel\Audit\Tests\Fixtures\FakeAuditableModel;
use Arqel\Audit\Tests\TestCase;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * i18n round 6 — os corpos de erro 403/400 dos controllers de leitura do
 * activity-log eram literais ingleses ('Forbidden' / 'subjectType must
 * be...'). Devem passar por `__('arqel-audit::messages.*')` para que o
 * locale da request se aplique (UI React em pt_BR vê a mensagem traduzida).
 */
function localeAuditUser(): AuthUser
{
    $user = new AuthUser;
    $user->forceFill(['id' => 1, 'name' => 'Low Priv', 'email' => 'low@priv.test']);

    return $user;
}

/**
 * @return TestResponse<Response>
 */
function getLocalizedAudit(TestCase $case, string $url): TestResponse
{
    return $case->actingAs(localeAuditUser())->getJson($url);
}

it('localizes the record-activity invalid-subject 400 message under pt_BR', function (): void {
    App::setLocale('pt_BR');

    /** @var TestCase $this */
    $response = getLocalizedAudit($this, '/admin/audit/'.urlencode('Not\\A\\Model').'/1/activity');

    $response->assertStatus(400);
    expect($response->json('error'))->toBe('invalid_subject_type')
        ->and($response->json('message'))->toBe(
            (string) __('arqel-audit::messages.invalid_subject_type'),
        )
        ->and($response->json('message'))->toContain('morph');
});

it('keeps the English record-activity invalid-subject 400 message under en', function (): void {
    App::setLocale('en');

    /** @var TestCase $this */
    $response = getLocalizedAudit($this, '/admin/audit/'.urlencode('Not\\A\\Model').'/1/activity');

    $response->assertStatus(400);
    expect($response->json('message'))->toBe(
        'subjectType must be a fully-qualified Eloquent model class or a registered morph alias.',
    );
});

it('localizes the record-activity 403 forbidden message under pt_BR', function (): void {
    App::setLocale('pt_BR');
    Gate::define('view', static fn (?AuthUser $user): bool => false);

    /** @var FakeAuditableModel $model */
    $model = FakeAuditableModel::create(['name' => 'Frodo', 'email' => 'frodo@shire.test']);
    $url = '/admin/audit/'.urlencode(FakeAuditableModel::class).'/'.$model->id.'/activity';

    /** @var TestCase $this */
    $response = getLocalizedAudit($this, $url);

    $response->assertStatus(403);
    expect($response->json('message'))->toBe(
        (string) __('arqel-audit::messages.forbidden'),
    );
});

it('localizes the global-log 403 forbidden HttpException message under pt_BR', function (): void {
    App::setLocale('pt_BR');
    Gate::define('view-audit-log', static fn (?AuthUser $user): bool => false);

    $controller = new GlobalActivityLogController;
    $request = Request::create('/admin/audit/activity');

    try {
        $controller->index($request);
        $this->fail('Expected a 403 HttpException to be thrown.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403)
            // The denial reason phrase must be localized, not the English literal.
            ->and($e->getMessage())->toBe((string) __('arqel-audit::messages.forbidden'))
            ->and($e->getMessage())->not->toBe('Forbidden');
    }
});
