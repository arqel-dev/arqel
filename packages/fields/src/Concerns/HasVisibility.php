<?php

declare(strict_types=1);

namespace Arqel\Fields\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/**
 * Per-context visibility for fields.
 *
 * Four canonical contexts: `create`, `edit`, `detail`, `table`.
 * `visibleOn(...)` and `hiddenOn(...)` flip the four flags
 * declaratively; `visibleIf(Closure)` / `hiddenIf(Closure)` add
 * a per-record predicate evaluated at render time. Combining
 * `visibleIf` and `hiddenIf` simultaneously is rejected — the
 * invariant prevents accidentally setting both with conflicting
 * intent.
 *
 * `isVisibleIn(context, ?record)` is the single oracle used by
 * controllers and serialisers.
 */
trait HasVisibility
{
    public const string CONTEXT_CREATE = 'create';

    public const string CONTEXT_EDIT = 'edit';

    public const string CONTEXT_DETAIL = 'detail';

    public const string CONTEXT_TABLE = 'table';

    protected bool $hiddenOnCreate = false;

    protected bool $hiddenOnEdit = false;

    protected bool $hiddenOnDetail = false;

    protected bool $hiddenOnTable = false;

    protected ?Closure $visibleIfCallback = null;

    protected ?Closure $hiddenIfCallback = null;

    protected bool $globallyHidden = false;

    public function hidden(bool $hidden = true): static
    {
        $this->globallyHidden = $hidden;

        return $this;
    }

    public function hiddenOnCreate(bool $hidden = true): static
    {
        $this->hiddenOnCreate = $hidden;

        return $this;
    }

    public function hiddenOnEdit(bool $hidden = true): static
    {
        $this->hiddenOnEdit = $hidden;

        return $this;
    }

    public function hiddenOnDetail(bool $hidden = true): static
    {
        $this->hiddenOnDetail = $hidden;

        return $this;
    }

    public function hiddenOnTable(bool $hidden = true): static
    {
        $this->hiddenOnTable = $hidden;

        return $this;
    }

    /**
     * @param string|array<int, string> $contexts
     */
    public function visibleOn(string|array $contexts): static
    {
        $whitelist = $this->normaliseContexts($contexts);
        foreach ($this->allContexts() as $context) {
            $this->setHiddenFlag($context, ! in_array($context, $whitelist, true));
        }

        return $this;
    }

    /**
     * @param string|array<int, string> $contexts
     */
    public function hiddenOn(string|array $contexts): static
    {
        foreach ($this->normaliseContexts($contexts) as $context) {
            $this->setHiddenFlag($context, true);
        }

        return $this;
    }

    public function visibleIf(Closure $callback): static
    {
        if ($this->hiddenIfCallback !== null) {
            throw new LogicException('Cannot combine visibleIf with hiddenIf on the same field.');
        }

        $this->visibleIfCallback = $callback;

        return $this;
    }

    public function hiddenIf(Closure $callback): static
    {
        if ($this->visibleIfCallback !== null) {
            throw new LogicException('Cannot combine hiddenIf with visibleIf on the same field.');
        }

        $this->hiddenIfCallback = $callback;

        return $this;
    }

    public function isVisibleIn(string $context, ?Model $record = null): bool
    {
        if ($this->globallyHidden) {
            return false;
        }

        if ($this->isHiddenInContext($context)) {
            return false;
        }

        if ($this->visibleIfCallback !== null) {
            return (bool) ($this->visibleIfCallback)($record);
        }

        if ($this->hiddenIfCallback !== null) {
            return ! (bool) ($this->hiddenIfCallback)($record);
        }

        return true;
    }

    /**
     * @param string|array<int, string> $contexts
     *
     * @return array<int, string>
     */
    protected function normaliseContexts(string|array $contexts): array
    {
        $list = is_array($contexts) ? $contexts : [$contexts];
        $valid = $this->allContexts();

        foreach ($list as $context) {
            if (! in_array($context, $valid, true)) {
                throw new InvalidArgumentException(
                    'Unknown visibility context ['.$context.']. '.
                    'Valid: '.implode(', ', $valid),
                );
            }
        }

        return array_values($list);
    }

    /**
     * @return array<int, string>
     */
    protected function allContexts(): array
    {
        return [
            self::CONTEXT_CREATE,
            self::CONTEXT_EDIT,
            self::CONTEXT_DETAIL,
            self::CONTEXT_TABLE,
        ];
    }

    protected function setHiddenFlag(string $context, bool $hidden): void
    {
        match ($context) {
            self::CONTEXT_CREATE => $this->hiddenOnCreate = $hidden,
            self::CONTEXT_EDIT => $this->hiddenOnEdit = $hidden,
            self::CONTEXT_DETAIL => $this->hiddenOnDetail = $hidden,
            self::CONTEXT_TABLE => $this->hiddenOnTable = $hidden,
            default => null,
        };
    }

    protected function isHiddenInContext(string $context): bool
    {
        return match ($context) {
            self::CONTEXT_CREATE => $this->hiddenOnCreate,
            self::CONTEXT_EDIT => $this->hiddenOnEdit,
            self::CONTEXT_DETAIL => $this->hiddenOnDetail,
            self::CONTEXT_TABLE => $this->hiddenOnTable,
            default => false,
        };
    }
}
