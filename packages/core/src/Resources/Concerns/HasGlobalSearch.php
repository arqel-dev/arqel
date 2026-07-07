<?php

declare(strict_types=1);

namespace Arqel\Core\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Global search opt-in for a Resource.
 *
 * A Resource lists the attributes it wants searchable from the Cmd+K
 * command palette. Default is empty (opt-out): no records are exposed
 * until the owner declares which columns are searchable — security by
 * default. {@see \Arqel\Core\CommandPalette\Providers\RecordSearchCommandProvider}
 * consumes this contract.
 */
trait HasGlobalSearch
{
    /**
     * Model attributes searched by the global command palette. Empty
     * (default) means the Resource is excluded from global search.
     *
     * @return array<int, string>
     */
    public static function globallySearchable(): array
    {
        return [];
    }

    /**
     * Human label for a record in global search results. Defaults to the
     * string value of the first searchable attribute, falling back to
     * "#{key}" when that value is empty or no attributes are declared.
     */
    public static function globalSearchResultTitle(Model $record): string
    {
        $attributes = static::globallySearchable();
        $first = $attributes[0] ?? null;

        if ($first !== null) {
            $value = $record->getAttribute($first);
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return '#'.$record->getKey();
    }
}
