<?php

declare(strict_types=1);

namespace Arqel\Cli\Models;

use Arqel\Cli\Exceptions\MarketplaceException;

/**
 * Value object representing plugin metadata returned by the marketplace API.
 *
 * @phpstan-type CompatArray array{arqel?: string, php?: string, laravel?: string}
 */
final readonly class PluginMetadata
{
    /**
     * @param array<string, string> $compat Compatibility constraints (e.g. `['arqel' => '^1.0']`).
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $composerPackage,
        public ?string $npmPackage,
        public array $compat,
        public ?string $installerCommand,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['name', 'type', 'composerPackage'] as $required) {
            if (! isset($data[$required]) || ! is_string($data[$required]) || $data[$required] === '') {
                throw new MarketplaceException("Plugin metadata missing required field '{$required}'.");
            }
        }

        $name = (string) $data['name'];
        $type = (string) $data['type'];
        $composerPackage = (string) $data['composerPackage'];

        $npmPackage = null;
        if (isset($data['npmPackage']) && is_string($data['npmPackage']) && $data['npmPackage'] !== '') {
            $npmPackage = $data['npmPackage'];
        }

        $installerCommand = null;
        if (isset($data['installerCommand']) && is_string($data['installerCommand']) && $data['installerCommand'] !== '') {
            $installerCommand = $data['installerCommand'];
        }

        $compat = [];
        if (isset($data['compat']) && is_array($data['compat'])) {
            foreach ($data['compat'] as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $compat[$key] = $value;
                }
            }
        }

        return new self(
            name: $name,
            type: $type,
            composerPackage: $composerPackage,
            npmPackage: $npmPackage,
            compat: $compat,
            installerCommand: $installerCommand,
        );
    }
}
