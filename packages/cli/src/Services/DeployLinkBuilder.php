<?php

declare(strict_types=1);

namespace Arqel\Cli\Services;

use InvalidArgumentException;

/**
 * Builds canonical "Deploy to Laravel Cloud" links from a GitHub repository.
 *
 * Laravel Cloud doesn't expose a stable public API for deploy automation yet,
 * so this service produces the deterministic dashboard URL with query params
 * pre-filled. The user lands on Laravel Cloud already authenticated via GitHub
 * OAuth and only needs to confirm the import.
 */
final readonly class DeployLinkBuilder
{
    /** @var non-empty-string */
    public const string BASE_URL = 'https://cloud.laravel.com/deploy';

    /** @var list<string> */
    public const array ALLOWED_REGIONS = [
        'auto',
        'us-east',
        'us-west',
        'eu-central',
        'eu-west',
        'ap-southeast',
        'sa-east',
    ];

    public function __construct() {}

    /**
     * @param string $githubRepo Repository in `owner/name` format.
     * @param string $region One of {@see self::ALLOWED_REGIONS}.
     * @param string|null $name Optional application name (1-40 chars).
     */
    public function build(string $githubRepo, string $region = 'auto', ?string $name = null): string
    {
        $repo = trim($githubRepo);
        if (! self::isValidRepo($repo)) {
            throw new InvalidArgumentException(
                "Invalid github-repo '{$githubRepo}'. Expected format 'owner/name' with letters, digits, dot, dash or underscore.",
            );
        }

        if (! in_array($region, self::ALLOWED_REGIONS, true)) {
            $allowed = implode(', ', self::ALLOWED_REGIONS);
            throw new InvalidArgumentException(
                "Invalid region '{$region}'. Allowed: {$allowed}.",
            );
        }

        $params = [
            'repo' => 'https://github.com/'.$repo,
            'region' => $region,
        ];

        if ($name !== null) {
            $trimmed = trim($name);
            if ($trimmed === '' || strlen($trimmed) > 40 || preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $trimmed) !== 1) {
                throw new InvalidArgumentException(
                    "Invalid name '{$name}'. Use 1-40 chars: letters, digits, dash or underscore (must start with a letter).",
                );
            }
            $params['name'] = $trimmed;
        }

        return self::BASE_URL.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private static function isValidRepo(string $repo): bool
    {
        return preg_match('#^[A-Za-z0-9](?:[A-Za-z0-9._-]{0,38}[A-Za-z0-9])?/[A-Za-z0-9](?:[A-Za-z0-9._-]{0,98}[A-Za-z0-9])?$#', $repo) === 1;
    }
}
