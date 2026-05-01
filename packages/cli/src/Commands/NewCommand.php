<?php

declare(strict_types=1);

namespace Arqel\Cli\Commands;

use Arqel\Cli\Generators\SetupScriptGenerator;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(name: 'new', description: 'Scaffold a new Laravel + Arqel application via reviewable setup script.')]
final class NewCommand extends Command
{
    public const string PLATFORM_BASH = 'bash';

    public const string PLATFORM_POWERSHELL = 'powershell';

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Project / directory name.')
            ->addOption('starter', null, InputOption::VALUE_REQUIRED, 'Starter kit (react|vue|livewire|none). Aliases: breeze, jetstream → react.', 'react')
            ->addOption('tenancy', null, InputOption::VALUE_REQUIRED, 'Tenancy stack (none|simple|stancl|spatie).', 'none')
            ->addOption('first-resource', null, InputOption::VALUE_REQUIRED, 'Optional first resource model name.')
            ->addOption('dark-mode', null, InputOption::VALUE_NEGATABLE, 'Enable dark-mode preset.', true)
            ->addOption('mcp', null, InputOption::VALUE_NEGATABLE, 'Wire arqel/mcp integration.', false)
            ->addOption('no-prompts', null, InputOption::VALUE_NONE, 'Skip interactive prompts.')
            ->addOption('platform', null, InputOption::VALUE_REQUIRED, 'Force script platform (bash|powershell).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = self::coerceString($input->getArgument('name'));

        if ($name === '' || preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $name) !== 1) {
            $output->writeln("<error>Invalid project name: '{$name}'. Use letters, digits, dashes or underscores.</error>");

            return Command::FAILURE;
        }

        $starter = self::coerceString($input->getOption('starter'));
        $tenancy = self::coerceString($input->getOption('tenancy'));
        $firstResourceRaw = $input->getOption('first-resource');
        $firstResource = is_string($firstResourceRaw) && $firstResourceRaw !== '' ? $firstResourceRaw : null;
        $darkMode = (bool) $input->getOption('dark-mode');
        $mcp = (bool) $input->getOption('mcp');

        $promptsEnabled = ! $input->getOption('no-prompts') && $input->isInteractive();

        if ($promptsEnabled && ! self::ttySupportsPrompts()) {
            $output->writeln('<comment>Non-POSIX TTY detected (stty unsupported); skipping interactive prompts. Use --starter, --tenancy, --first-resource, --dark-mode/--no-dark-mode, --mcp/--no-mcp to customize.</comment>');
            $promptsEnabled = false;
        }

        if ($promptsEnabled) {
            $starter = self::coerceString(select(
                label: 'Starter kit?',
                options: SetupScriptGenerator::STARTERS,
                default: $starter,
            ));
            $tenancy = self::coerceString(select(
                label: 'Tenancy strategy?',
                options: SetupScriptGenerator::TENANCIES,
                default: $tenancy,
            ));
            $firstResourceInput = self::coerceString(text(label: 'First resource model name (leave blank to skip)?', default: $firstResource ?? ''));
            $firstResource = $firstResourceInput !== '' ? $firstResourceInput : null;
            $darkMode = confirm(label: 'Enable dark-mode preset?', default: $darkMode);
            $mcp = confirm(label: 'Wire arqel/mcp integration?', default: $mcp);
        }

        try {
            $generator = new SetupScriptGenerator(
                appName: $name,
                starter: $starter,
                tenancy: $tenancy,
                firstResource: $firstResource,
                darkMode: $darkMode,
                mcpIntegration: $mcp,
            );
        } catch (InvalidArgumentException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        $platform = $this->resolvePlatform(self::coerceString($input->getOption('platform')));

        $cwd = getcwd();
        if ($cwd === false) {
            $output->writeln('<error>Unable to resolve current working directory.</error>');

            return Command::FAILURE;
        }

        if ($platform === self::PLATFORM_POWERSHELL) {
            $filename = "arqel-setup-{$name}.ps1";
            $contents = $generator->forPowershell();
        } else {
            $filename = "arqel-setup-{$name}.sh";
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

        return Command::SUCCESS;
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

    /**
     * Probe whether the current TTY supports `stty` round-trips, which laravel/prompts
     * requires for interactive selects/confirms. Some embedded terminals (e.g. Claude
     * Code, certain Docker setups) expose a pseudo-TTY that passes posix_isatty but
     * makes `stty -g` emit non-POSIX output that `stty <mode>` then rejects.
     */
    public static function ttySupportsPrompts(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return true;
        }

        // posix_isatty(STDIN) is the cheapest gate; without a real TTY behind STDIN,
        // /dev/tty is either absent or unopenable for this process.
        if (function_exists('posix_isatty') && ! @posix_isatty(STDIN)) {
            return false;
        }

        $tty = @fopen('/dev/tty', 'r');
        if ($tty === false) {
            return false;
        }
        fclose($tty);

        // Probe with a full round-trip: capture `stty -g` and feed it back to
        // `stty <mode>`. If the terminal is POSIX-compliant, this is a no-op
        // and exits 0; embedded pseudo-TTYs (Claude Code, some CIs) emit a
        // non-POSIX serialization that hex-matches but `stty <mode>` rejects.
        $mode = self::execAgainstTty('stty -g');
        if ($mode === null || $mode === '') {
            return false;
        }

        // Use shell escape to keep the value verbatim through the shell.
        $escaped = escapeshellarg($mode);
        $roundTrip = self::execAgainstTty('stty '.$escaped);

        return $roundTrip !== null;
    }

    /**
     * Run a shell command with /dev/tty as stdin; return stdout on exit 0,
     * null on any failure (proc_open error, non-zero exit, stderr noise).
     */
    private static function execAgainstTty(string $command): ?string
    {
        $process = @proc_open($command.' 2>/dev/null', [
            0 => ['file', '/dev/tty', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (! is_resource($process)) {
            return null;
        }

        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        if ($code !== 0) {
            return null;
        }

        return trim($stdout);
    }

    private function resolvePlatform(string $forced): string
    {
        if ($forced === self::PLATFORM_BASH || $forced === self::PLATFORM_POWERSHELL) {
            return $forced;
        }

        return PHP_OS_FAMILY === 'Windows' ? self::PLATFORM_POWERSHELL : self::PLATFORM_BASH;
    }
}
