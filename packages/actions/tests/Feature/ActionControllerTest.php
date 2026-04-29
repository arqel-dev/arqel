<?php

declare(strict_types=1);

use Arqel\Actions\Http\Controllers\ActionController;
use Arqel\Actions\Types\BulkAction;
use Arqel\Actions\Types\ToolbarAction;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * ACTIONS-008: Feature tests of `Arqel\Actions\Http\Controllers\ActionController`.
 *
 * The host environment does not ship `pdo_sqlite`, so we focus on
 * paths that do not need DB access:
 *  - resolveOrFail  (slug → Resource)
 *  - resolveAction  (collection lookup by name)
 *  - invokeToolbar  (no record required)
 *  - invokeBulk     (rejects payloads without ids[] before any
 *                    DB call)
 *
 * Row/header invocations and the bulk fetch path stay covered by
 * the controller's existing duck-typed dispatch in CORE-006 and
 * the BulkAction chunking unit test (250 records → 3 chunks).
 */
final class StubResourceWithToolbarAction extends Resource
{
    /** @var list<ToolbarAction> */
    public static array $toolbar = [];

    /** @var list<BulkAction> */
    public static array $bulk = [];

    public static bool $callbackInvoked = false;

    public static string $model = Illuminate\Foundation\Auth\User::class;

    public static ?string $slug = 'controller-stub';

    public function fields(): array
    {
        return [];
    }

    public function toolbarActions(): array
    {
        return self::$toolbar;
    }

    public function bulkActions(): array
    {
        return self::$bulk;
    }
}

beforeEach(function (): void {
    StubResourceWithToolbarAction::$toolbar = [];
    StubResourceWithToolbarAction::$bulk = [];
    StubResourceWithToolbarAction::$callbackInvoked = false;

    /** @var ResourceRegistry $registry */
    $registry = $this->app->make(ResourceRegistry::class);
    $registry->clear();
    $registry->register(StubResourceWithToolbarAction::class);
});

it('aborts 404 when the slug does not resolve to a Resource', function (): void {
    /** @var ActionController $controller */
    $controller = $this->app->make(ActionController::class);

    $controller->invokeToolbar(new Request, 'unknown-slug', 'export');
})->throws(NotFoundHttpException::class);

it('aborts 404 when the toolbar action name does not exist on the Resource', function (): void {
    /** @var ActionController $controller */
    $controller = $this->app->make(ActionController::class);

    $controller->invokeToolbar(new Request, 'controller-stub', 'doesnt-exist');
})->throws(NotFoundHttpException::class);

it('invokes a toolbar action callback and flashes the success notification', function (): void {
    StubResourceWithToolbarAction::$toolbar = [
        ToolbarAction::make('export')
            ->action(function (): void {
                StubResourceWithToolbarAction::$callbackInvoked = true;
            })
            ->successNotification('Export queued.'),
    ];

    /** @var ActionController $controller */
    $controller = $this->app->make(ActionController::class);

    $response = $controller->invokeToolbar(new Request, 'controller-stub', 'export');

    expect(StubResourceWithToolbarAction::$callbackInvoked)->toBeTrue()
        ->and($response->getSession()?->get('success'))->toBe('Export queued.');
});

it('honours the action authorize Closure (403 on denied)', function (): void {
    StubResourceWithToolbarAction::$toolbar = [
        ToolbarAction::make('export')
            ->action(fn () => StubResourceWithToolbarAction::$callbackInvoked = true)
            ->authorize(fn (): bool => false),
    ];

    /** @var ActionController $controller */
    $controller = $this->app->make(ActionController::class);

    try {
        $controller->invokeToolbar(new Request, 'controller-stub', 'export');
        $thrown = false;
    } catch (HttpException $e) {
        $thrown = true;
        $code = $e->getStatusCode();
    }

    expect($thrown)->toBeTrue()
        ->and($code ?? null)->toBe(403)
        ->and(StubResourceWithToolbarAction::$callbackInvoked)->toBeFalse();
});

it('flashes the failure notification when the action callback throws', function (): void {
    StubResourceWithToolbarAction::$toolbar = [
        ToolbarAction::make('export')
            ->action(function (): void {
                throw new RuntimeException('disk full');
            })
            ->failureNotification('Export failed.'),
    ];

    /** @var ActionController $controller */
    $controller = $this->app->make(ActionController::class);

    $response = $controller->invokeToolbar(new Request, 'controller-stub', 'export');

    expect($response->getSession()?->get('error'))->toBe('Export failed.');
});

it('rejects bulk requests with no ids before any DB lookup (422)', function (): void {
    StubResourceWithToolbarAction::$bulk = [
        BulkAction::make('archive')->action(fn () => null),
    ];

    /** @var ActionController $controller */
    $controller = $this->app->make(ActionController::class);

    try {
        $controller->invokeBulk(new Request, 'controller-stub', 'archive');
        $thrown = false;
    } catch (HttpException $e) {
        $thrown = true;
        $code = $e->getStatusCode();
    }

    expect($thrown)->toBeTrue()
        ->and($code ?? null)->toBe(422);
});

it('keeps the action collection resolution duck-typed (no method_exists hard fail)', function (): void {
    $resource = new class extends Resource
    {
        public static string $model = Illuminate\Foundation\Auth\User::class;

        public static ?string $slug = 'no-collections';

        public function fields(): array
        {
            return [];
        }
    };

    /** @var ResourceRegistry $registry */
    $registry = $this->app->make(ResourceRegistry::class);
    $registry->clear();
    $registry->register($resource::class);

    /** @var ActionController $controller */
    $controller = $this->app->make(ActionController::class);

    try {
        $controller->invokeToolbar(new Request, 'no-collections', 'export');
        $thrown = false;
    } catch (HttpException $e) {
        $thrown = true;
        $code = $e->getStatusCode();
    }

    expect($thrown)->toBeTrue()
        ->and($code ?? null)->toBe(404);
});
