<?php

declare(strict_types=1);

namespace Arqel\Cli\Services;

use Arqel\Cli\Exceptions\MarketplaceException;
use Arqel\Cli\Models\PluginMetadata;
use Closure;

/**
 * HTTP client for the Arqel marketplace plugin metadata API.
 *
 * The HTTP fetcher is injectable so tests can stub network without
 * monkey-patching `file_get_contents`.
 */
final readonly class MarketplaceClient
{
    /**
     * @param  Closure(string): string|null  $httpFetcher  Optional fetcher returning raw JSON for a URL. Throws MarketplaceException on failure.
     */
    public function __construct(
        public string $baseUrl,
        private ?Closure $httpFetcher = null,
    ) {}

    public function fetchPlugin(string $package): PluginMetadata
    {
        if (preg_match('/^[a-z0-9][a-z0-9_.-]*\/[a-z0-9][a-z0-9_.-]*$/i', $package) !== 1) {
            throw new MarketplaceException("Invalid package identifier '{$package}'. Expected 'vendor/name'.");
        }

        $url = rtrim($this->baseUrl, '/').'/plugins/'.rawurlencode($package);

        $raw = $this->fetch($url);

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new MarketplaceException("Marketplace returned malformed JSON for '{$package}'.");
        }

        /** @var array<string, mixed> $normalized */
        $normalized = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return PluginMetadata::fromArray($normalized);
    }

    private function fetch(string $url): string
    {
        if ($this->httpFetcher !== null) {
            return ($this->httpFetcher)($url);
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: arqel-cli\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new MarketplaceException("Failed to fetch marketplace metadata from {$url}.");
        }

        $statusLine = isset($http_response_header[0]) ? (string) $http_response_header[0] : '';
        if ($statusLine !== '' && preg_match('#\s(\d{3})\s#', $statusLine, $m) === 1) {
            $status = (int) $m[1];
            if ($status >= 400) {
                throw new MarketplaceException("Marketplace returned HTTP {$status} for {$url}.");
            }
        }

        return $body;
    }
}
