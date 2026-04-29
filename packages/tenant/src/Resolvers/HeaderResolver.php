<?php

declare(strict_types=1);

namespace Arqel\Tenant\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Resolves the tenant from a request header. Defaults to
 * `X-Tenant-ID`; the column queried defaults to the model key so a
 * client can post `X-Tenant-ID: 42` and hit the row directly.
 *
 * Header names are case-insensitive per the HTTP spec — Symfony's
 * HeaderBag already normalises lookups, so we pass through.
 */
class HeaderResolver extends AbstractTenantResolver
{
    public function __construct(
        string $modelClass,
        string $identifierColumn = 'id',
        private readonly string $header = 'X-Tenant-ID',
    ) {
        parent::__construct($modelClass, $identifierColumn);
    }

    public function resolve(Request $request): ?Model
    {
        $value = $request->header($this->header);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $this->findByIdentifier($value);
    }
}
