<?php

declare(strict_types=1);

use Arqel\Cli\Commands\CloudDeployLinkCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

function runDeployLink(array $input): array
{
    putenv('ARQEL_CLI_NO_CLIPBOARD=1');
    $command = new CloudDeployLinkCommand;
    $app = new Application;
    $app->addCommand($command);

    $tester = new CommandTester($command);
    $exit = $tester->execute($input);

    return [$exit, $tester->getDisplay()];
}

it('prints a deploy URL and exits 0 with valid github-repo', function (): void {
    [$exit, $display] = runDeployLink(['github-repo' => 'arqel-dev/laravel-cloud-template']);

    expect($exit)->toBe(0)
        ->and($display)->toContain('Deploy to Laravel Cloud:')
        ->and($display)->toContain('https://cloud.laravel.com/deploy?')
        ->and($display)->toContain('repo=https%3A%2F%2Fgithub.com%2Farqel%2Flaravel-cloud-template')
        ->and($display)->toContain('region=auto');
});

it('fails with non-zero exit on invalid github-repo', function (): void {
    [$exit, $display] = runDeployLink(['github-repo' => 'not-a-valid-repo']);

    expect($exit)->not->toBe(0)
        ->and($display)->toContain('Invalid github-repo');
});

it('applies --region option', function (): void {
    [$exit, $display] = runDeployLink([
        'github-repo' => 'arqel-dev/template',
        '--region' => 'us-west',
    ]);

    expect($exit)->toBe(0)
        ->and($display)->toContain('region=us-west');
});

it('applies --name option', function (): void {
    [$exit, $display] = runDeployLink([
        'github-repo' => 'arqel-dev/template',
        '--name' => 'acme',
    ]);

    expect($exit)->toBe(0)
        ->and($display)->toContain('name=acme');
});

it('rejects unknown --region', function (): void {
    [$exit, $display] = runDeployLink([
        'github-repo' => 'arqel-dev/template',
        '--region' => 'mars-1',
    ]);

    expect($exit)->not->toBe(0)
        ->and($display)->toContain('Invalid region');
});
