<?php

declare(strict_types=1);

namespace Arqel\Tenant\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Resolves the tenant from the request host's leftmost subdomain.
 *
 *   acme.myapp.com → tenant 'acme'
 *   myapp.com      → null (matches central domain)
 *
 * `centralDomain` is required to distinguish the marketing/central
 * site from a tenant. Without it we would treat `myapp.com` as the
 * subdomain `myapp` of `com`.
 */
class SubdomainResolver extends AbstractTenantResolver
{
    public function __construct(
        string $modelClass,
        string $identifierColumn = 'subdomain',
        private readonly ?string $centralDomain = null,
    ) {
        parent::__construct($modelClass, $identifierColumn);
    }

    public function resolve(Request $request): ?Model
    {
        $host = $request->getHost();
        $subdomain = $this->extractSubdomain($host);

        if ($subdomain === null) {
            return null;
        }

        return $this->findByIdentifier($subdomain);
    }

    private function extractSubdomain(string $host): ?string
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return null;
        }

        if ($this->centralDomain !== null) {
            $central = strtolower(trim($this->centralDomain));

            if ($host === $central) {
                return null;
            }

            $suffix = '.'.$central;
            if (str_ends_with($host, $suffix)) {
                $candidate = substr($host, 0, -strlen($suffix));

                return $candidate === '' || $candidate === 'www' ? null : $candidate;
            }

            // Host outside the central domain — refuse to guess.
            return null;
        }

        // Heuristic fallback (no central domain configured): take
        // the leftmost label only when there are 3+ labels.
        $labels = explode('.', $host);
        if (count($labels) < 3) {
            return null;
        }

        $candidate = $labels[0];

        return $candidate === 'www' ? null : $candidate;
    }
}
