<?php

declare(strict_types=1);

use Arqel\Cli\Services\DeployLinkBuilder;

it('builds a canonical deploy URL with default region auto', function (): void {
    $builder = new DeployLinkBuilder;

    $url = $builder->build('arqel-dev/laravel-cloud-template');

    expect($url)->toStartWith(DeployLinkBuilder::BASE_URL.'?')
        ->and($url)->toContain('repo=https%3A%2F%2Fgithub.com%2Farqel%2Flaravel-cloud-template')
        ->and($url)->toContain('region=auto');
});

it('throws on invalid github-repo format', function (): void {
    $builder = new DeployLinkBuilder;

    expect(fn (): string => $builder->build('not-a-repo'))
        ->toThrow(InvalidArgumentException::class, 'Invalid github-repo');
});

it('rejects empty owner or name', function (): void {
    $builder = new DeployLinkBuilder;

    expect(fn (): string => $builder->build('/name'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): string => $builder->build('owner/'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): string => $builder->build('a b/c'))->toThrow(InvalidArgumentException::class);
});

it('honours custom valid region', function (): void {
    $builder = new DeployLinkBuilder;

    $url = $builder->build('arqel-dev/template', 'eu-central');

    expect($url)->toContain('region=eu-central');
});

it('rejects unknown region', function (): void {
    $builder = new DeployLinkBuilder;

    expect(fn (): string => $builder->build('arqel-dev/template', 'mars-1'))
        ->toThrow(InvalidArgumentException::class, 'Invalid region');
});

it('appends name option when provided', function (): void {
    $builder = new DeployLinkBuilder;

    $url = $builder->build('arqel-dev/template', 'auto', 'acme-admin');

    expect($url)->toContain('name=acme-admin');
});

it('rejects invalid name option', function (): void {
    $builder = new DeployLinkBuilder;

    expect(fn (): string => $builder->build('arqel-dev/template', 'auto', '1bad'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn (): string => $builder->build('arqel-dev/template', 'auto', ''))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn (): string => $builder->build('arqel-dev/template', 'auto', str_repeat('a', 41)))
        ->toThrow(InvalidArgumentException::class);
});

it('escapes special characters via RFC3986 query encoding', function (): void {
    $builder = new DeployLinkBuilder;

    $url = $builder->build('owner.x/repo_name-1');

    expect($url)->toContain('https%3A%2F%2Fgithub.com%2Fowner.x%2Frepo_name-1');
});
