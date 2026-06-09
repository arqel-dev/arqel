<?php

declare(strict_types=1);

namespace Arqel\Actions;

use Arqel\Actions\Concerns\Confirmable;
use Arqel\Actions\Concerns\HasAuthorization;
use Arqel\Actions\Concerns\HasForm;
use Arqel\Core\Support\FieldSchemaSerializer;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

/**
 * Abstract base for every Arqel action (RowAction, BulkAction,
 * ToolbarAction, HeaderAction).
 *
 * An Action is a declarative description of "something the user
 * can invoke". The PHP side only stores intent + serialises the
 * shape; the React side renders the trigger and (when present)
 * confirmation modal / form modal. Execution funnels through
 * `ActionController` (ACTIONS-006), which authorises, validates
 * form data, and calls `execute()`.
 *
 * Action is XOR — either an `action(Closure)` callback or a
 * `url()` link, not both. The shape distinguishes them by which
 * key is present.
 */
abstract class Action
{
    use Confirmable;
    use HasAuthorization;
    use HasForm;

    public const string COLOR_PRIMARY = 'primary';

    public const string COLOR_SECONDARY = 'secondary';

    public const string COLOR_DESTRUCTIVE = 'destructive';

    public const string COLOR_SUCCESS = 'success';

    public const string COLOR_WARNING = 'warning';

    public const string COLOR_INFO = 'info';

    public const string VARIANT_DEFAULT = 'default';

    public const string VARIANT_OUTLINE = 'outline';

    public const string VARIANT_GHOST = 'ghost';

    public const string VARIANT_DESTRUCTIVE = 'destructive';

    public const string METHOD_GET = 'GET';

    public const string METHOD_POST = 'POST';

    /** Action type — overridden by each subclass (row/bulk/toolbar/header). */
    protected string $type;

    protected string $name;

    protected string $label;

    protected ?string $icon = null;

    protected string $color = self::COLOR_PRIMARY;

    protected string $variant = self::VARIANT_DEFAULT;

    protected ?Closure $action = null;

    protected Closure|string|null $url = null;

    protected string $method = self::METHOD_POST;

    protected ?Closure $visible = null;

    protected ?Closure $disabled = null;

    protected ?string $successNotification = null;

    protected ?string $failureNotification = null;

    protected bool $hidden = false;

    protected ?string $tooltip = null;

    final public function __construct(string $name)
    {
        $this->name = $name;
        $this->label = Str::of($name)->snake()->replace('_', ' ')->title()->toString();
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function color(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function variant(string $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function action(Closure $callback): static
    {
        $this->action = $callback;
        $this->url = null;
        $this->method = self::METHOD_POST;

        return $this;
    }

    public function url(Closure|string $url, string $method = self::METHOD_GET): static
    {
        $this->url = $url;
        $this->method = $method;
        $this->action = null;

        return $this;
    }

    public function visible(Closure $callback): static
    {
        $this->visible = $callback;

        return $this;
    }

    public function disabled(Closure $callback): static
    {
        $this->disabled = $callback;

        return $this;
    }

    public function hidden(bool $hidden = true): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function tooltip(string $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function successNotification(string $message): static
    {
        $this->successNotification = $message;

        return $this;
    }

    public function failureNotification(string $message): static
    {
        $this->failureNotification = $message;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getSuccessNotification(): ?string
    {
        return $this->successNotification;
    }

    public function getFailureNotification(): ?string
    {
        return $this->failureNotification;
    }

    public function hasCallback(): bool
    {
        return $this->action !== null;
    }

    public function hasUrl(): bool
    {
        return $this->url !== null;
    }

    /**
     * True when the URL is a closure that depends on the record, so it
     * must be resolved per row rather than once at table level (#140).
     */
    public function hasRecordDependentUrl(): bool
    {
        return $this->url instanceof Closure;
    }

    /**
     * True when the disabled predicate is a closure that depends on the
     * record, so it must be evaluated per row rather than once (#140).
     */
    public function hasRecordDependentDisabled(): bool
    {
        return $this->disabled instanceof Closure;
    }

    public function isVisibleFor(mixed $record = null): bool
    {
        if ($this->hidden) {
            return false;
        }

        if ($this->visible === null) {
            return true;
        }

        return (bool) ($this->visible)($record);
    }

    public function isDisabledFor(mixed $record = null): bool
    {
        if ($this->disabled === null) {
            return false;
        }

        return (bool) ($this->disabled)($record);
    }

    public function resolveUrl(mixed $record = null): ?string
    {
        if ($this->url === null) {
            return null;
        }

        if ($this->url instanceof Closure) {
            $resolved = ($this->url)($record);

            return is_string($resolved) ? $resolved : null;
        }

        return $this->url;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function execute(mixed $record = null, array $data = []): mixed
    {
        if ($this->action === null) {
            return null;
        }

        return ($this->action)($record, $data);
    }

    /**
     * Serialise the action for the Inertia payload.
     *
     * The optional `$resource` parameter is duck-typed (`?object`)
     * to avoid an `arqel-dev/actions` ↔ `arqel-dev/core` cycle. When
     * supplied and the action declared neither `->url()` nor
     * `->action()`, `resolveStockUrl()` emits a conventional URL
     * for the 5 stock verbs (view/edit/delete/restore + bulk delete).
     *
     * @return array<string, mixed>
     */
    public function toArray(
        ?Authenticatable $user = null,
        mixed $record = null,
        ?object $resource = null,
    ): array {
        $url = $this->resolveUrl($record);
        $method = $this->method;

        // No explicit `->url()`: derive a conventional URL so the frontend
        // never falls back to a dead route. This covers BOTH
        //   - the stock verbs (view/edit/delete/restore) and bulk actions
        //     that carry no callback, and
        //   - a custom action with a server-side `->action(Closure)`, which
        //     dispatches through core's authorised action endpoint
        //     (`POST /admin/{slug}/actions/{name}[/{id}]`, #231) — without
        //     this it shipped no url and the client POSTed to the removed
        //     `/arqel-dev/actions/{name}` route (#174) → 404.
        if ($url === null && $resource !== null) {
            $stock = $this->resolveStockUrl($resource, $record);
            if ($stock !== null) {
                [$url, $method] = $stock;
            }
        }

        return array_filter([
            'name' => $this->name,
            'type' => $this->type,
            'label' => $this->label,
            'icon' => $this->icon,
            'color' => $this->color,
            'variant' => $this->variant,
            'method' => $method,
            'url' => $url,
            'tooltip' => $this->tooltip,
            'disabled' => $this->isDisabledFor($record) ?: null,
            'requiresConfirmation' => $this->isRequiringConfirmation() ?: null,
            'confirmation' => $this->getConfirmationConfig(),
            'form' => $this->hasForm() ? $this->getFormSchemaArray() : null,
            'formFields' => $this->hasForm() ? $this->serializeFormFields($user) : null,
            'modalSize' => $this->hasForm() ? $this->getModalSize() : null,
            'successNotification' => $this->successNotification,
            'failureNotification' => $this->failureNotification,
        ], fn ($v) => $v !== null);
    }

    /**
     * Materialise the full FieldSchema for the action's form fields (#213).
     *
     * `getFormSchemaArray()` only ships `{name, type}` as the modal layout
     * order; the React `<ActionFormModal>` renders each field from the
     * matching rich FieldSchema (options, label, placeholder, validation,
     * per-type props) keyed by name. Without it every field — notably a
     * `SelectField` with options — renders blank.
     *
     * We defer to `FieldSchemaSerializer` (CORE-010), the single source of
     * truth for the payload shape, reused verbatim from the resource form
     * so action modals and resource forms render identically. Action forms
     * carry no owning record/resource, so relationship options/routes that
     * need an owner are left to the field's own static props.
     *
     * @return list<array<string, mixed>>
     */
    private function serializeFormFields(?Authenticatable $user): array
    {
        return (new FieldSchemaSerializer)->serialize(
            $this->getFormFields(),
            null,
            $user,
        );
    }

    /**
     * Resolve a conventional URL + HTTP method for a stock action
     * (one of view/edit/delete/restore as row, or delete as bulk)
     * when the user-land action has not specified them explicitly.
     *
     * Returns `null` when the resource has no slug, when a row-level
     * stock verb has no record key available, or when the action
     * name does not match any stock verb.
     *
     * @return array{0: string, 1: string}|null
     */
    private function resolveStockUrl(object $resource, mixed $record): ?array
    {
        $slug = $resource::$slug ?? null;
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $id = is_object($record) && method_exists($record, 'getKey')
            ? $record->getKey()
            : null;

        $idSegment = is_scalar($id) ? (string) $id : '{id}';

        // Stock verbs (no user callback) map onto the conventional resource
        // CRUD routes. A custom action that happens to be named after a stock
        // verb is NOT hijacked here — it carries a callback and is routed to
        // the action-dispatch endpoint below.
        if (! $this->hasCallback()) {
            $resolved = match ([$this->type, $this->name]) {
                ['row', 'view'] => ["/admin/{$slug}/{$idSegment}", 'GET'],
                ['row', 'edit'] => ["/admin/{$slug}/{$idSegment}/edit", 'GET'],
                ['row', 'delete'] => ["/admin/{$slug}/{$idSegment}", 'DELETE'],
                ['row', 'restore'] => ["/admin/{$slug}/{$idSegment}/restore", 'POST'],
                default => null,
            };

            if ($resolved !== null) {
                return $resolved;
            }
        }

        // Any bulk action (delete included) targets core's stock
        // `{resource}/bulk/{action}` endpoint via POST when the
        // user-land action declared no explicit url. Without this the
        // serialised action shipped no url and the frontend fell back to
        // an unregistered route → 404 (#48).
        if ($this->type === 'bulk') {
            return ["/admin/{$slug}/bulk/{$this->name}", self::METHOD_POST];
        }

        // A custom row/header/toolbar action with a server-side callback
        // dispatches through core's authorised action endpoint (#231):
        //   - row/header carry the record id segment,
        //   - toolbar (no record) omits it.
        // Core's `ResourceController::rowAction` resolves the action by name
        // on the matching collection, authorises it (resource Gate + the
        // action's own `canBeExecutedBy`), validates the form payload and
        // runs `execute()`. Without this URL the client fell back to the
        // dead `/arqel-dev/actions/{name}` route removed in #174 → 404.
        if ($this->hasCallback() && in_array($this->type, ['row', 'header', 'toolbar'], true)) {
            if ($this->type === 'toolbar') {
                return ["/admin/{$slug}/actions/{$this->name}", self::METHOD_POST];
            }

            return ["/admin/{$slug}/actions/{$this->name}/{$idSegment}", self::METHOD_POST];
        }

        return null;
    }
}
