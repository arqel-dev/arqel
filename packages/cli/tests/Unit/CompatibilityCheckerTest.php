<?php

declare(strict_types=1);

use Arqel\Cli\Services\CompatibilityChecker;

beforeEach(function (): void {
    $this->checker = new CompatibilityChecker;
});

it('matches caret constraints within the same major', function (): void {
    expect($this->checker->check('^1.0', '1.0.0'))->toBeTrue();
    expect($this->checker->check('^1.0', '1.5.7'))->toBeTrue();
});

it('rejects caret constraints crossing major boundary', function (): void {
    expect($this->checker->check('^1.0', '2.0.0'))->toBeFalse();
    expect($this->checker->check('^1.0', '0.9.9'))->toBeFalse();
});

it('matches tilde constraints within the same minor', function (): void {
    expect($this->checker->check('~2.5', '2.5.0'))->toBeTrue();
    expect($this->checker->check('~2.5', '2.9.9'))->toBeTrue();
    expect($this->checker->check('~2.5.1', '2.5.7'))->toBeTrue();
});

it('rejects tilde constraints outside their range', function (): void {
    expect($this->checker->check('~2.5', '3.0.0'))->toBeFalse();
    expect($this->checker->check('~2.5.1', '2.6.0'))->toBeFalse();
});

it('handles >= constraints', function (): void {
    expect($this->checker->check('>=1.0', '1.0.0'))->toBeTrue();
    expect($this->checker->check('>=1.0', '5.4.3'))->toBeTrue();
    expect($this->checker->check('>=1.0', '0.9.9'))->toBeFalse();
});

it('matches exact constraints', function (): void {
    expect($this->checker->check('1.2.3', '1.2.3'))->toBeTrue();
    expect($this->checker->check('=1.2.3', '1.2.3'))->toBeTrue();
    expect($this->checker->check('1.2.3', '1.2.4'))->toBeFalse();
});

it('evaluates composite multi-constraints (AND semantics)', function (): void {
    expect($this->checker->check('>=1.0 <2.0', '1.5.0'))->toBeTrue();
    expect($this->checker->check('>=1.0 <2.0', '2.0.0'))->toBeFalse();
    expect($this->checker->check('>=1.0,<2.0', '1.999.0'))->toBeTrue();
});

it('treats wildcard and empty as always matching', function (): void {
    expect($this->checker->check('*', '99.99.99'))->toBeTrue();
    expect($this->checker->check('', '0.0.0'))->toBeTrue();
});

it('handles caret in zero-major versions', function (): void {
    expect($this->checker->check('^0.2', '0.2.5'))->toBeTrue();
    expect($this->checker->check('^0.2', '0.3.0'))->toBeFalse();
});

it('strips v prefixes and pre-release tags from versions', function (): void {
    expect($this->checker->check('^1.0', 'v1.4.0'))->toBeTrue();
    expect($this->checker->check('^1.0', '1.4.0-dev'))->toBeTrue();
});
