<?php

declare(strict_types=1);

namespace Arqel\Cli\Commands;

use Arqel\Cli\Services\DeployLinkBuilder;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cloud:deploy-link',
    description: 'Generate a "Deploy to Laravel Cloud" link with pre-filled GitHub repository, region and app name.',
)]
final class CloudDeployLinkCommand extends Command
{
    public function __construct(
        private readonly DeployLinkBuilder $builder = new DeployLinkBuilder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('github-repo', InputArgument::REQUIRED, 'GitHub repository in owner/name format (e.g., arqel/laravel-cloud-template).')
            ->addOption('region', null, InputOption::VALUE_REQUIRED, 'Laravel Cloud region (auto|us-east|us-west|eu-central|eu-west|ap-southeast|sa-east).', 'auto')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Application name pre-filled in the dashboard.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repo = self::coerceString($input->getArgument('github-repo'));
        $region = self::coerceString($input->getOption('region'));
        $nameOpt = $input->getOption('name');
        $name = is_string($nameOpt) && $nameOpt !== '' ? $nameOpt : null;

        try {
            $url = $this->builder->build($repo, $region === '' ? 'auto' : $region, $name);
        } catch (InvalidArgumentException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>Deploy to Laravel Cloud:</info>');
        $output->writeln($url);
        $output->writeln('');

        $copied = getenv('ARQEL_CLI_NO_CLIPBOARD') === '1' ? null : self::tryCopyToClipboard($url);
        if ($copied !== null) {
            $output->writeln("<comment>(URL copied to clipboard via {$copied}.)</comment>");
        }

        $output->writeln('Next steps:');
        $output->writeln('  1. Make sure the repository is pushed to GitHub.');
        $output->writeln('  2. Open the URL above and authorise Laravel Cloud (GitHub OAuth).');
        $output->writeln('  3. Confirm the import and configure environment variables.');

        return Command::SUCCESS;
    }

    /**
     * Attempt to copy the URL to the clipboard via known CLI helpers.
     * Defensive — if no helper is available or any call fails, returns null.
     *
     * @return string|null Helper name (pbcopy/xclip/wl-copy) when copy succeeded.
     */
    private static function tryCopyToClipboard(string $url): ?string
    {
        $candidates = [
            ['pbcopy', ['pbcopy']],
            ['xclip', ['xclip', '-selection', 'clipboard']],
            ['wl-copy', ['wl-copy']],
        ];

        foreach ($candidates as [$name, $argv]) {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $proc = @proc_open($argv, $descriptors, $pipes);
            if (! is_resource($proc)) {
                continue;
            }
            if (isset($pipes[0])) {
                @fwrite($pipes[0], $url);
                @fclose($pipes[0]);
            }
            if (isset($pipes[1])) {
                @stream_get_contents($pipes[1]);
                @fclose($pipes[1]);
            }
            if (isset($pipes[2])) {
                @stream_get_contents($pipes[2]);
                @fclose($pipes[2]);
            }
            $exit = proc_close($proc);
            if ($exit === 0) {
                return $name;
            }
        }

        return null;
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
