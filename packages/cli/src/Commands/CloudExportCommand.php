<?php

declare(strict_types=1);

namespace Arqel\Cli\Commands;

use Arqel\Cli\Services\TemplateExporter;
use FilesystemIterator;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cloud:export', description: 'Export Arqel-ready Laravel Cloud template into a target directory.')]
final class CloudExportCommand extends Command
{
    public function __construct(
        private readonly ?string $templatesRoot = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('target-dir', InputArgument::REQUIRED, 'Destination directory for the exported template (must be empty or non-existent).')
            ->addOption('with-sample', null, InputOption::VALUE_NONE, 'Include a sample Resource (reserved for future expansion).')
            ->addOption('app-name', null, InputOption::VALUE_REQUIRED, 'Application name used to replace placeholders (defaults to target-dir basename).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetDir = self::coerceString($input->getArgument('target-dir'));
        if ($targetDir === '') {
            $output->writeln('<error>target-dir argument cannot be empty.</error>');

            return Command::FAILURE;
        }

        $absoluteTarget = self::resolveAbsolute($targetDir);

        if (! self::isDirEmptyOrAbsent($absoluteTarget)) {
            $output->writeln("<error>target-dir '{$absoluteTarget}' already exists and is not empty. Choose another path.</error>");

            return Command::FAILURE;
        }

        $appName = self::coerceString($input->getOption('app-name'));
        if ($appName === '') {
            $appName = basename($absoluteTarget);
        }

        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $appName) !== 1) {
            $output->writeln("<error>Invalid app-name '{$appName}'. Use letters, digits, dash or underscore (must start with a letter).</error>");

            return Command::FAILURE;
        }

        $sourceDir = $this->templatesRoot ?? self::defaultTemplatesRoot();
        if (! is_dir($sourceDir)) {
            $output->writeln("<error>Template directory missing: {$sourceDir}</error>");

            return Command::FAILURE;
        }

        $exporter = new TemplateExporter(
            sourceDir: $sourceDir,
            targetDir: $absoluteTarget,
            replacements: [
                '{{APP_NAME}}' => $appName,
            ],
        );

        try {
            $written = $exporter->export();
        } catch (RuntimeException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        $count = count($written);
        $output->writeln("<info>Exported {$count} files to {$absoluteTarget}</info>");
        $output->writeln('');
        $output->writeln('Next steps:');
        $output->writeln("  1. Review the generated files in {$absoluteTarget}");
        $output->writeln('  2. Initialize git:');
        $output->writeln("       cd {$absoluteTarget}");
        $output->writeln('       git init');
        $output->writeln('       git add .');
        $output->writeln("       git commit -m 'Initial Arqel app'");
        $output->writeln('  3. Push to GitHub and click "Deploy to Laravel Cloud" in the README.');

        return Command::SUCCESS;
    }

    private static function defaultTemplatesRoot(): string
    {
        // src/Commands → ../../templates/laravel-cloud
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'laravel-cloud';
    }

    private static function resolveAbsolute(string $path): string
    {
        if ($path !== '' && ($path[0] === DIRECTORY_SEPARATOR || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1)) {
            return rtrim($path, DIRECTORY_SEPARATOR);
        }

        $cwd = getcwd();
        if ($cwd === false) {
            return rtrim($path, DIRECTORY_SEPARATOR);
        }

        return rtrim($cwd.DIRECTORY_SEPARATOR.$path, DIRECTORY_SEPARATOR);
    }

    private static function isDirEmptyOrAbsent(string $path): bool
    {
        if (! file_exists($path)) {
            return true;
        }

        if (! is_dir($path)) {
            return false;
        }

        $iter = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);

        return ! $iter->valid();
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
}
