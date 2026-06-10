<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\ResourceController;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Core\Tests\Fixtures\Models\Stub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * #231 (endpoint half): a custom row action with a server-side
 * `->action(Closure)` and no explicit `->url()` had no working HTTP
 * path — the standalone `arqel.actions.*` routes were removed in #174
 * and `resolveStockUrl()` only covered the stock verbs + bulk. The
 * frontend then POSTed to the dead `/arqel-dev/actions/{name}` route → 404.
 *
 * Mirroring the bulk fix (#48), the dispatch now flows through core's
 * authorised `ResourceController::rowAction` endpoint
 * (`POST /admin/{slug}/actions/{name}[/{id}]`). The controller resolves
 * the action by name on the matching duck-typed collection, authorises
 * it (the resource Gate + the action's own `canBeExecutedBy`), validates
 * the form payload and runs `execute()` against the record.
 *
 * The Action contract is duck-typed (no hard dep on `arqel-dev/actions`)
 * exactly like BulkActionDispatchTest.
 */

/**
 * A custom row action: carries a server-side callback (`hasCallback()`)
 * and records its execution + the record it ran against in public static
 * state so the dispatch path is observable.
 */
final class SideEffectRowAction
{
    public static int $executions = 0;

    public static mixed $ranAgainst = null;

    /** @var array<string, mixed> */
    public static array $ranWithData = [];

    public static bool $allowExecution = true;

    public static bool $visible = true;

    public static bool $disabled = false;

    public function __construct(
        private readonly string $name,
        private readonly string $type = 'row',
    ) {}

    public function getType(): string
    {
        return $this->type;
    }

    public function isVisibleFor(mixed $record = null): bool
    {
        return self::$visible;
    }

    public function isDisabledFor(mixed $record = null): bool
    {
        return self::$disabled;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function hasCallback(): bool
    {
        return true;
    }

    public function canBeExecutedBy(mixed $user = null, mixed $record = null): bool
    {
        return self::$allowExecution;
    }

    public function hasForm(): bool
    {
        return false;
    }

    public function getSuccessNotification(): ?string
    {
        return 'Published.';
    }

    public function getFailureNotification(): ?string
    {
        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function execute(mixed $record = null, array $data = []): mixed
    {
        self::$executions++;
        self::$ranAgainst = $record;
        self::$ranWithData = $data;

        return null;
    }
}

final class RowActionResource extends Resource
{
    public static string $model = Stub::class;

    public static ?string $slug = 'row-dispatch';

    public function fields(): array
    {
        return [];
    }

    /** @return array<int, object> */
    public function actions(): array
    {
        return [new SideEffectRowAction('publish')];
    }
}

beforeEach(function (): void {
    SideEffectRowAction::$executions = 0;
    SideEffectRowAction::$ranAgainst = null;
    SideEffectRowAction::$ranWithData = [];
    SideEffectRowAction::$allowExecution = true;
    SideEffectRowAction::$visible = true;
    SideEffectRowAction::$disabled = false;

    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(RowActionResource::class);

    $this->dataBuilder = app(InertiaDataBuilder::class);

    Schema::create('stubs', function ($table): void {
        $table->increments('id');
        $table->string('name')->nullable();
        $table->timestamps();
    });

    Stub::query()->insert([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ]);
});

it('registers the arqel.resources.action route bound as POST under the panel path', function (): void {
    $route = app('router')->getRoutes()->getByName('arqel.resources.action');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('POST')
        ->and($route->uri())->toContain('{resource}/actions/{action}');
});

it('runs a custom row action callback against the resolved record and flashes success', function (): void {
    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $request = Request::create('/admin/row-dispatch/actions/publish/2', 'POST');

    $response = $controller->rowAction($request, 'row-dispatch', 'publish', '2');

    expect(SideEffectRowAction::$executions)->toBe(1)
        ->and(SideEffectRowAction::$ranAgainst)->toBeInstanceOf(Stub::class)
        ->and(SideEffectRowAction::$ranAgainst->getKey())->toBe(2)
        ->and($response->getSession()->get('success'))->toBe('Published.')
        ->and($response->getSession()->get('error'))->toBeNull();
});

it('aborts 404 when the slug does not resolve', function (): void {
    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $controller->rowAction(Request::create('/x', 'POST'), 'unknown', 'publish', '1');
})->throws(HttpException::class);

it('aborts 404 when the action name does not exist on the resource', function (): void {
    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $controller->rowAction(Request::create('/x', 'POST'), 'row-dispatch', 'nope', '1');
})->throws(HttpException::class);

it('aborts 404 when the record id does not exist', function (): void {
    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $controller->rowAction(Request::create('/x', 'POST'), 'row-dispatch', 'publish', '999');
})->throws(HttpException::class);

it('aborts 403 when the action canBeExecutedBy denies the user (authorization enforced)', function (): void {
    SideEffectRowAction::$allowExecution = false;

    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $call = fn () => $controller->rowAction(
        Request::create('/admin/row-dispatch/actions/publish/1', 'POST'),
        'row-dispatch',
        'publish',
        '1',
    );

    expect($call)->toThrow(HttpException::class)
        ->and(SideEffectRowAction::$executions)->toBe(0);
});

it('aborts 403 when the action is disabled for the record (state gate enforced)', function (): void {
    SideEffectRowAction::$disabled = true;

    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $call = fn () => $controller->rowAction(
        Request::create('/admin/row-dispatch/actions/publish/1', 'POST'),
        'row-dispatch',
        'publish',
        '1',
    );

    expect($call)->toThrow(HttpException::class)
        ->and(SideEffectRowAction::$executions)->toBe(0);
});

it('aborts 403 when the action is not visible/hidden for the record (state gate enforced)', function (): void {
    SideEffectRowAction::$visible = false;

    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $call = fn () => $controller->rowAction(
        Request::create('/admin/row-dispatch/actions/publish/1', 'POST'),
        'row-dispatch',
        'publish',
        '1',
    );

    expect($call)->toThrow(HttpException::class)
        ->and(SideEffectRowAction::$executions)->toBe(0);
});

/**
 * A duck-typed table stub exposing `getActions()` — mirrors the shape of
 * `Arqel\Table\Table` without a hard dep on `arqel-dev/table` (core stays
 * duck-typed). This is exactly the contract `findResourceAction` probes.
 */
final class StubTableWithActions
{
    /** @param array<int, object> $actions */
    public function __construct(private readonly array $actions) {}

    /** @return array<int, object> */
    public function getActions(): array
    {
        return $this->actions;
    }
}

/**
 * Row actions are most commonly declared on the resource's table
 * (`table()->actions([...])` → `Table::getActions()`), NOT via a top-level
 * `actions()` method. The dispatch endpoint must resolve those too —
 * otherwise the common case 404s even though the URL + modal are correct
 * (the residual gap found while updating the Round-22 E2E specs).
 */
final class TableActionResource extends Resource
{
    public static string $model = Stub::class;

    public static ?string $slug = 'table-dispatch';

    public function fields(): array
    {
        return [];
    }

    public function table(): mixed
    {
        return new StubTableWithActions([new SideEffectRowAction('publish')]);
    }
}

it('resolves a row action declared on the resource table (not a top-level actions() method)', function (): void {
    $this->registry->clear();
    $this->registry->register(TableActionResource::class);

    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $request = Request::create('/admin/table-dispatch/actions/publish/2', 'POST');

    $response = $controller->rowAction($request, 'table-dispatch', 'publish', '2');

    expect(SideEffectRowAction::$executions)->toBe(1)
        ->and(SideEffectRowAction::$ranAgainst)->toBeInstanceOf(Stub::class)
        ->and(SideEffectRowAction::$ranAgainst->getKey())->toBe(2)
        ->and($response->getSession()->get('success'))->toBe('Published.');
});

/**
 * #246: the dispatch endpoint chose the gate ability + record binding by
 * id-presence, never by the action's declared TYPE. So a TOOLBAR action could
 * be POSTed WITH an `{id}` (loading + mutating a record it should never touch
 * under the `update` gate), and a ROW action POSTed WITHOUT an id ran with a
 * null record under the weaker `viewAny` gate. The controller now derives the
 * binding from `getType()` and rejects the cross-forms.
 *
 * A resource exposing a toolbar-type action — invoked WITHOUT an id in the
 * legitimate case (toolbar actions operate on no specific record).
 */
final class ToolbarActionResource extends Resource
{
    public static string $model = Stub::class;

    public static ?string $slug = 'toolbar-dispatch';

    public function fields(): array
    {
        return [];
    }

    /** @return array<int, object> */
    public function toolbarActions(): array
    {
        return [new SideEffectRowAction('bulkpublish', 'toolbar')];
    }
}

it('runs a toolbar action with no id (legitimate record-less invocation)', function (): void {
    $this->registry->clear();
    $this->registry->register(ToolbarActionResource::class);

    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $request = Request::create('/admin/toolbar-dispatch/actions/bulkpublish', 'POST');
    $response = $controller->rowAction($request, 'toolbar-dispatch', 'bulkpublish', null);

    expect(SideEffectRowAction::$executions)->toBe(1)
        ->and(SideEffectRowAction::$ranAgainst)->toBeNull()
        ->and($response->getSession()->get('success'))->toBe('Published.');
});

it('rejects a toolbar action invoked WITH an id (cross-form blocked, record never touched)', function (): void {
    $this->registry->clear();
    $this->registry->register(ToolbarActionResource::class);

    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $call = fn () => $controller->rowAction(
        Request::create('/admin/toolbar-dispatch/actions/bulkpublish/1', 'POST'),
        'toolbar-dispatch',
        'bulkpublish',
        '1',
    );

    expect($call)->toThrow(HttpException::class)
        ->and(SideEffectRowAction::$executions)->toBe(0);
});

it('rejects a row action invoked WITHOUT an id (cross-form blocked)', function (): void {
    $controller = new ResourceController($this->registry, $this->dataBuilder);

    $call = fn () => $controller->rowAction(
        Request::create('/admin/row-dispatch/actions/publish', 'POST'),
        'row-dispatch',
        'publish',
        null,
    );

    expect($call)->toThrow(HttpException::class)
        ->and(SideEffectRowAction::$executions)->toBe(0);
});
