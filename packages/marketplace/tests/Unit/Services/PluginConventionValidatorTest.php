<?php

declare(strict_types=1);

use Arqel\Marketplace\Services\PluginConventionValidator;

beforeEach(function (): void {
    $this->validator = new PluginConventionValidator;
});

it('passa quando composer.json declara todos os campos obrigatórios', function (): void {
    $composer = [
        'name' => 'acme/arqel-stripe-fields',
        'type' => 'arqel-plugin',
        'keywords' => ['arqel', 'plugin', 'stripe'],
        'extra' => [
            'arqel' => [
                'plugin-type' => 'field-pack',
                'compat' => ['arqel' => '^1.0'],
                'category' => 'integrations',
                'installation-instructions' => 'See README.md',
            ],
        ],
    ];

    $result = $this->validator->validateComposerJson($composer);

    expect($result->passed)->toBeTrue()
        ->and($result->errors)->toBe([])
        ->and($result->warnings)->toBe([]);
});

it('falha quando type não é arqel-plugin', function (): void {
    $composer = [
        'type' => 'library',
        'keywords' => ['arqel', 'plugin'],
        'extra' => [
            'arqel' => [
                'plugin-type' => 'field-pack',
                'compat' => ['arqel' => '^1.0'],
                'category' => 'integrations',
                'installation-instructions' => 'See README.md',
            ],
        ],
    ];

    $result = $this->validator->validateComposerJson($composer);

    expect($result->passed)->toBeFalse()
        ->and($result->errors)->not()->toBe([]);
});

it('falha quando plugin-type não está na lista permitida', function (): void {
    $composer = [
        'type' => 'arqel-plugin',
        'keywords' => ['arqel', 'plugin'],
        'extra' => [
            'arqel' => [
                'plugin-type' => 'unknown-type',
                'compat' => ['arqel' => '^1.0'],
                'category' => 'misc',
                'installation-instructions' => 'See README.',
            ],
        ],
    ];

    $result = $this->validator->validateComposerJson($composer);

    expect($result->passed)->toBeFalse();
    $names = array_column($result->checks, 'name', 'name');
    expect($names)->toHaveKey('plugin_type');
});

it('falha quando compat.arqel está ausente ou inválido', function (): void {
    $composer = [
        'type' => 'arqel-plugin',
        'keywords' => ['arqel', 'plugin'],
        'extra' => [
            'arqel' => [
                'plugin-type' => 'tool',
                'compat' => ['arqel' => 'not-a-semver!!'],
                'category' => 'tools',
                'installation-instructions' => 'docs.',
            ],
        ],
    ];

    expect($this->validator->validateComposerJson($composer)->passed)->toBeFalse();
});

it('falha quando category está ausente ou vazia', function (): void {
    $composer = [
        'type' => 'arqel-plugin',
        'keywords' => ['arqel', 'plugin'],
        'extra' => [
            'arqel' => [
                'plugin-type' => 'theme',
                'compat' => ['arqel' => '^1.0'],
                'category' => '',
                'installation-instructions' => 'docs.',
            ],
        ],
    ];

    expect($this->validator->validateComposerJson($composer)->passed)->toBeFalse();
});

it('avisa (warn) quando installation-instructions está ausente', function (): void {
    $composer = [
        'type' => 'arqel-plugin',
        'keywords' => ['arqel', 'plugin'],
        'extra' => [
            'arqel' => [
                'plugin-type' => 'integration',
                'compat' => ['arqel' => '^1.0'],
                'category' => 'integrations',
            ],
        ],
    ];

    $result = $this->validator->validateComposerJson($composer);

    expect($result->passed)->toBeTrue()
        ->and($result->warnings)->not()->toBe([]);
});

it('avisa (warn) quando keywords não contém arqel e plugin', function (): void {
    $composer = [
        'type' => 'arqel-plugin',
        'keywords' => ['stripe'],
        'extra' => [
            'arqel' => [
                'plugin-type' => 'field-pack',
                'compat' => ['arqel' => '^1.0'],
                'category' => 'integrations',
                'installation-instructions' => 'docs.',
            ],
        ],
    ];

    $result = $this->validator->validateComposerJson($composer);

    expect($result->passed)->toBeTrue()
        ->and($result->warnings)->not()->toBe([]);
});

it('falha quando extra.arqel está ausente por completo', function (): void {
    $composer = ['type' => 'arqel-plugin', 'keywords' => ['arqel', 'plugin']];
    expect($this->validator->validateComposerJson($composer)->passed)->toBeFalse();
});

it('valida package.json npm via arqel.plugin-type', function (): void {
    $package = [
        'name' => '@acme/arqel-stripe-fields',
        'arqel' => ['plugin-type' => 'field-pack'],
    ];

    expect($this->validator->validateNpmPackageJson($package)->passed)->toBeTrue();
});

it('valida package.json npm via peerDependency @arqel-dev/types', function (): void {
    $package = [
        'name' => '@acme/awesome',
        'peerDependencies' => ['@arqel-dev/types' => '^1.0'],
    ];

    expect($this->validator->validateNpmPackageJson($package)->passed)->toBeTrue();
});

it('falha package.json npm sem arqel.plugin-type nem peer @arqel-dev/types', function (): void {
    $package = ['name' => '@acme/foo', 'peerDependencies' => ['react' => '^19.2']];

    expect($this->validator->validateNpmPackageJson($package)->passed)->toBeFalse();
});

it('aceita semver constraints variados', function (): void {
    foreach (['^1.0', '~2.5', '>=1.0', '1.2.3', '^1.0 || ^2.0'] as $constraint) {
        $composer = [
            'type' => 'arqel-plugin',
            'keywords' => ['arqel', 'plugin'],
            'extra' => [
                'arqel' => [
                    'plugin-type' => 'tool',
                    'compat' => ['arqel' => $constraint],
                    'category' => 'tools',
                    'installation-instructions' => 'docs.',
                ],
            ],
        ];
        expect($this->validator->validateComposerJson($composer)->passed)
            ->toBeTrue("Failed for constraint {$constraint}");
    }
});
