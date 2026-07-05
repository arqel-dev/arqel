<?php

declare(strict_types=1);

use Arqel\Actions\Http\Controllers\ActionController;
use Arqel\Actions\Types\BulkAction;
use Arqel\Actions\Types\ToolbarAction;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Fields\Types\TextField;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
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

    public static string $model = User::class;

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

it('flashes a localized generic message (not the raw exception) when no failureNotification is set', function (): void {
    StubResourceWithToolbarAction::$toolbar = [
        ToolbarAction::make('export')
            ->action(function (): void {
                throw new RuntimeException('SQLSTATE[HY000]: internal disk full leak');
            }),
    ];

    /** @var ActionController $controller */
    $controller = $this->app->make(ActionController::class);

    $response = $controller->invokeToolbar(new Request, 'controller-stub', 'export');

    expect($response->getSession()?->get('error'))
        ->toBe('The action could not be completed.')
        ->not->toContain('disk full')
        ->not->toContain('SQLSTATE');
});

it('flashes the pt_BR generic message when locale is pt_BR and no failureNotification is set', function (): void {
    app()->setLocale('pt_BR');

    StubResourceWithToolbarAction::$toolbar = [
        ToolbarAction::make('export')
            ->action(function (): void {
                throw new RuntimeException('raw english internals leak');
            }),
    ];

    /** @var ActionController $controller */
    $controller = $this->app->make(ActionController::class);

    $response = $controller->invokeToolbar(new Request, 'controller-stub', 'export');

    expect($response->getSession()?->get('error'))
        ->toBe('A ação não pôde ser concluída.')
        ->not->toContain('raw english internals leak');
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

it('localizes the missing-selection bulk abort message via the arqel:: namespace', function (): void {
    StubResourceWithToolbarAction::$bulk = [
        BulkAction::make('archive')->action(fn () => null),
    ];

    /** @var ActionController $controller */
    $controller = $this->app->make(ActionController::class);

    app()->setLocale('pt_BR');

    try {
        $controller->invokeBulk(new Request, 'controller-stub', 'archive');
        $this->fail('Expected a 422 HttpException for the empty selection.');
    } catch (HttpException $e) {
        expect($e->getMessage())->toBe('Nenhuma seleção informada.')
            ->and($e->getMessage())->not->toBe('arqel::messages.action.missing_selection');
    }

    app()->setLocale('en');

    try {
        $controller->invokeBulk(new Request, 'controller-stub', 'archive');
        $this->fail('Expected a 422 HttpException for the empty selection.');
    } catch (HttpException $e) {
        expect($e->getMessage())->toBe('Missing selection.');
    }
});

it('validates the action form using the field label as :attribute and custom messages', function (): void {
    StubResourceWithToolbarAction::$toolbar = [
        ToolbarAction::make('transfer')
            ->form([
                (new TextField('new_owner'))
                    ->label('Recipient name')
                    ->required(),
                (new TextField('reason'))
                    ->required()
                    ->validationMessage('required', 'You must explain why.'),
            ])
            ->action(fn () => StubResourceWithToolbarAction::$callbackInvoked = true),
    ];

    /** @var ActionController $controller */
    $controller = $this->app->make(ActionController::class);

    try {
        // Empty payload → both required fields fail.
        $controller->invokeToolbar(new Request, 'controller-stub', 'transfer');
        $errors = [];
    } catch (ValidationException $e) {
        $errors = $e->errors();
    }

    expect($errors)->toHaveKeys(['new_owner', 'reason'])
        // :attribute renders the localized field label, not the raw key.
        ->and($errors['new_owner'][0])->toContain('Recipient name')
        // Per-field custom message applies instead of the default.
        ->and($errors['reason'][0])->toBe('You must explain why.')
        ->and(StubResourceWithToolbarAction::$callbackInvoked)->toBeFalse();
});

it('authorizes bulk actions per-record: denies the whole batch if any record fails', function (): void {
    // Build a tiny in-memory table + model to select over.
    Schema::create('bulk_widgets', function ($table): void {
        $table->increments('id');
        $table->boolean('locked')->default(false);
    });

    $model = new class extends Model
    {
        protected $table = 'bulk_widgets';

        public $timestamps = false;

        protected $guarded = [];
    };
    $modelClass = $model::class;

    $modelClass::query()->insert([
        ['id' => 1, 'locked' => false],
        ['id' => 2, 'locked' => true],
    ]);

    $resource = new class extends Resource
    {
        public static string $model = '';

        public static ?string $slug = 'bulk-authz-stub';

        /** @var list<BulkAction> */
        public static array $bulkActions = [];

        public function fields(): array
        {
            return [];
        }

        public function bulkActions(): array
        {
            return self::$bulkActions;
        }
    };
    $resource::$model = $modelClass;

    $seen = [];
    $resource::$bulkActions = [
        BulkAction::make('archive')
            // A per-record predicate: only unlocked widgets may be archived.
            // Each record is inspected individually; a locked one denies.
            ->authorize(function (?Authenticatable $user, mixed $record) use (&$seen): bool {
                $seen[] = $record;

                return $record instanceof Model
                    && $record->getAttribute('locked') === false;
            })
            ->action(fn () => null),
    ];

    /** @var ResourceRegistry $registry */
    $registry = $this->app->make(ResourceRegistry::class);
    $registry->clear();
    $registry->register($resource::class);

    /** @var ActionController $controller */
    $controller = $this->app->make(ActionController::class);

    $request = Request::create('/', 'POST', ['ids' => [1, 2]]);

    try {
        $controller->invokeBulk($request, 'bulk-authz-stub', 'archive');
        $code = null;
    } catch (HttpException $e) {
        $code = $e->getStatusCode();
    }

    // Record 2 is locked → the batch is forbidden. And the predicate must have
    // received individual Models, never the whole Collection.
    expect($code)->toBe(403)
        ->and($seen)->not->toBeEmpty();
    foreach ($seen as $record) {
        expect($record)->toBeInstanceOf(Model::class);
    }
});

it('keeps the action collection resolution duck-typed (no method_exists hard fail)', function (): void {
    $resource = new class extends Resource
    {
        public static string $model = User::class;

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
