<?php

declare(strict_types=1);

namespace Arqel\Table\Filters;

use Arqel\Table\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter that applies an Eloquent scope when the value is truthy.
 *
 * Typical usage: `Filter::make('published')->scope('published')`
 * — when the user toggles the filter on, the controller calls
 * `$query->published()`.
 */
final class ScopeFilter extends Filter
{
    protected string $type = 'scope';

    protected ?string $scopeName = null;

    public function scope(string $name): static
    {
        $this->scopeName = $name;

        return $this;
    }

    public function getScopeName(): string
    {
        return $this->scopeName ?? $this->name;
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $query
     *
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyDefault(Builder $query, mixed $value): Builder
    {
        if (! $value) {
            return $query;
        }

        $scope = $this->getScopeName();
        /** @var Builder<\Illuminate\Database\Eloquent\Model> $result */
        $result = $query->{$scope}();

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'scope' => $this->getScopeName(),
        ];
    }
}
