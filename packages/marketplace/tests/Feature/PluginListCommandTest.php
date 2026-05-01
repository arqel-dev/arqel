<?php

declare(strict_types=1);

use Arqel\Marketplace\Console\PluginListCommand;
use Illuminate\Support\Facades\Artisan;

it('registra o comando arqel:plugin:list', function (): void {
    $commands = Artisan::all();
    expect($commands)->toHaveKey('arqel:plugin:list');
    expect($commands['arqel:plugin:list'])->toBeInstanceOf(PluginListCommand::class);
});

it('imprime mensagem amigável quando nenhum plugin Arqel está instalado', function (): void {
    // Em ambiente Testbench, nenhum pacote terá type=arqel-plugin instalado.
    $exit = Artisan::call('arqel:plugin:list');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('No Arqel plugins installed.');
});

it('aceita a flag --validate sem erro', function (): void {
    $exit = Artisan::call('arqel:plugin:list', ['--validate' => true]);
    expect($exit)->toBe(0);
});

it('comando descreve seu propósito', function (): void {
    $command = new PluginListCommand;
    expect($command->getDescription())->toContain('arqel-plugin');
});
