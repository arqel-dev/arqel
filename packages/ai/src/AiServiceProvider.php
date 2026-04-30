<?php

declare(strict_types=1);

namespace Arqel\Ai;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel/ai`.
 *
 * AI-001 + AI-002 ship the contract surface only — the singleton
 * `AiManager` and provider concretes (`ClaudeProvider`,
 * `OpenAiProvider`, `OllamaProvider`) land in AI-003..AI-006. The
 * config file is published so consumers can opt-in early without
 * having a working manager binding yet.
 */
final class AiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel-ai')
            ->hasConfigFile('arqel-ai');
    }
}
