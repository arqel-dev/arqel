<?php

declare(strict_types=1);

namespace Arqel\Cli\Commands;

use Arqel\Cli\Application;
use Arqel\Cli\Exceptions\MarketplaceException;
use Arqel\Cli\Generators\InstallScriptGenerator;
use Arqel\Cli\Models\PluginMetadata;
use Arqel\Cli\Services\CompatibilityChecker;
use Arqel\Cli\Services\MarketplaceClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;

#[AsCommand(name: 'install', description: 'Install an Arqel plugin from the marketplace via reviewable script.')]
final class InstallCommand extends Command
{
    public const string PLATFORM_BASH = 'bash';

    public const string PLATFORM_POWERSHELL = 'powershell';

    public const string DEFAULT_MARKETPLACE_URL = 'https://arqel.dev/api/marketplace';

    public function __construct(
        private readonly ?MarketplaceClient $client = null,
        private readonly ?CompatibilityChecker $compat = null,
        private readonly string $arqelVersion = Application::VERSION,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('package', InputArgument::REQUIRED, "Plugin package id (vendor/name).")
            ->addOption('marketplace-url', null, InputOption::VALUE_REQUIRED, 'Marketplace API base URL.', self::DEFAULT_MARKETPLACE_URL)
            ->addOption('no-prompts', null, InputOption::VALUE_NONE, 'Skip interactive prompts.')
            ->addOption('platform', null, InputOption::VALUE_REQUIRED, 'Force script platform (bash|powershell).')
            ->addOption('no-installer', null, InputOption::VALUE_NONE, 'Do not include `php artisan {plugin}:install` in the generated script.')
            ->addOption('migrate', null, InputOption::VALUE_NONE, 'Append `php artisan migrate` to the generated script.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $package = self::coerceString($input->getArgument('package'));

        if (preg_match('/^[a-z0-9][a-z0-9_.-]*\/[a-z0-9][a-z0-9_.-]*$/i', $package) !== 1) {
            $output->writeln("<error>Invalid package '{$package}'. Expected format 'vendor/name'.</error>");

            return Command::FAILURE;
        }

        $marketplaceUrl = self::coerceString($input->getOption('marketplace-url'));
        if ($marketplaceUrl === '') {
            $marketplaceUrl = self::DEFAULT_MARKETPLACE_URL;
        }
        $client = $this->client ?? new MarketplaceClient($marketplaceUrl);
        $compat = $this->compat ?? new CompatibilityChecker;

        $interactive = ! $input->getOption('no-prompts') && $input->isInteractive();

        try {
            $metadata = $client->fetchPlugin($package);
        } catch (MarketplaceException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        $output->writeln("<info>Found plugin {$metadata->name} (type: {$metadata->type})</info>");

        if (! $this->verifyCompatibility($metadata, $compat, $output)) {
            return Command::FAILURE;
        }

        $runInstaller = ! (bool) $input->getOption('no-installer');
        $runMigrate = (bool) $input->getOption('migrate');

        if ($interactive) {
            if (! confirm(label: "Install {$metadata->composerPackage}?", default: true)) {
                $output->writeln('<comment>Aborted.</comment>');

                return Command::SUCCESS;
            }

            if ($metadata->installerCommand !== null) {
                $runInstaller = confirm(
                    label: "Include `php artisan {$metadata->installerCommand}` in the script?",
                    default: $runInstaller,
                );
            }
            $runMigrate = confirm(label: 'Include `php artisan migrate` in the script?', default: $runMigrate);
        }

        $generator = new InstallScriptGenerator(
            plugin: $metadata,
            runArtisanInstaller: $runInstaller,
            runArtisanMigrate: $runMigrate,
        );

        $platform = $this->resolvePlatform(self::coerceString($input->getOption('platform')));

        $cwd = getcwd();
        if ($cwd === false) {
            $output->writeln('<error>Unable to resolve current working directory.</error>');

            return Command::FAILURE;
        }

        $slug = self::slugify($package);
        if ($platform === self::PLATFORM_POWERSHELL) {
            $filename = "arqel-install-{$slug}.ps1";
            $contents = $generator->forPowershell();
        } else {
            $filename = "arqel-install-{$slug}.sh";
            $contents = $generator->forBash();
        }

        $path = $cwd.DIRECTORY_SEPARATOR.$filename;
        if (file_put_contents($path, $contents) === false) {
            $output->writeln("<error>Failed to write {$path}</error>");

            return Command::FAILURE;
        }

        if ($platform === self::PLATFORM_BASH) {
            @chmod($path, 0o755);
        }

        $runHint = $platform === self::PLATFORM_POWERSHELL
            ? "powershell -File {$filename}"
            : "bash {$filename}";

        $output->writeln("<info>Generated {$filename}.</info>");
        $output->writeln("Review then run: {$runHint}");
        $output->writeln('Next steps:');
        $output->writeln("  1. Review {$filename} for any sensitive commands.");
        $output->writeln("  2. Execute it: {$runHint}");
        if ($metadata->installerCommand !== null && $runInstaller) {
            $output->writeln("  3. The plugin's own installer (`php artisan {$metadata->installerCommand}`) will run as part of the script.");
        }

        return Command::SUCCESS;
    }

    private function verifyCompatibility(
        PluginMetadata $metadata,
        CompatibilityChecker $compat,
        OutputInterface $output,
    ): bool {
        $constraint = $metadata->compat['arqel'] ?? null;
        if ($constraint === null || $constraint === '') {
            $output->writeln('<comment>Plugin declares no Arqel compatibility constraint; proceeding.</comment>');

            return true;
        }

        if (! $compat->check($constraint, $this->arqelVersion)) {
            $output->writeln("<error>Plugin {$metadata->name} requires Arqel {$constraint} but you have {$this->arqelVersion}. Upgrade Arqel or pick a compatible plugin version.</error>");

            return false;
        }

        $output->writeln("<info>Compatibility OK ({$constraint} satisfied by {$this->arqelVersion}).</info>");

        return true;
    }

    private function resolvePlatform(string $forced): string
    {
        if ($forced === self::PLATFORM_BASH || $forced === self::PLATFORM_POWERSHELL) {
            return $forced;
        }

        return PHP_OS_FAMILY === 'Windows' ? self::PLATFORM_POWERSHELL : self::PLATFORM_BASH;
    }

    private static function coerceString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return '';
    }

    private static function slugify(string $package): string
    {
        $slug = strtolower(str_replace(['/', '\\'], '-', $package));
        $slug = (string) preg_replace('/[^a-z0-9-]+/', '-', $slug);

        return trim($slug, '-');
    }
}
