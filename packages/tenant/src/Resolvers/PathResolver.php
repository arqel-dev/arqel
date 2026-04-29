<?php

declare(strict_types=1);

namespace Arqel\Tenant\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Resolves the tenant from the first path segment.
 *
 *   /acme/dashboard      → tenant 'acme'
 *   /admin/users         → null (when 'admin' is on the ignore list)
 *
 * `ignoreSegments` is intentional — Arqel itself owns `/admin` (or
 * the configured panel path), so consumers can list those prefixes
 * to avoid resolving them as tenants.
 */
class PathResolver extends AbstractTenantResolver
{
    /**
     * @param list<string> $ignoreSegments
     */
    public function __construct(
        string $modelClass,
        string $identifierColumn = 'slug',
        private readonly array $ignoreSegments = [],
    ) {
        parent::__construct($modelClass, $identifierColumn);
    }

    public function resolve(Request $request): ?Model
    {
        $segment = $this->extractFirstSegment($request->path());

        if ($segment === null) {
            return null;
        }

        if (in_array($segment, array_map('strtolower', $this->ignoreSegments), true)) {
            return null;
        }

        return $this->findByIdentifier($segment);
    }

    private function extractFirstSegment(string $path): ?string
    {
        $trimmed = trim($path, '/');

        if ($trimmed === '') {
            return null;
        }

        $first = explode('/', $trimmed, 2)[0];
        $first = strtolower($first);

        return $first === '' ? null : $first;
    }
}
