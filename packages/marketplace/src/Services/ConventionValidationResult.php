<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Services;

/**
 * Resultado imutável de uma validação de convention de plugin (MKTPLC-003).
 *
 * Cada `check` segue o shape `{name: string, status: 'ok'|'warn'|'fail', message: string}`.
 * `errors` e `warnings` são listas derivadas de checks com status `fail`/`warn`
 * respectivamente — pré-computadas para conveniência de consumidores (CLI/admin).
 */
final readonly class ConventionValidationResult
{
    /**
     * @param list<array{name: string, status: string, message: string}> $checks
     * @param list<string> $warnings
     * @param list<string> $errors
     */
    public function __construct(
        public array $checks,
        public bool $passed,
        public array $warnings,
        public array $errors,
    ) {}

    /**
     * Construtor estático para resultado bem-sucedido (sem erros, possivelmente warnings).
     *
     * @param list<array{name: string, status: string, message: string}> $checks
     */
    public static function success(array $checks): self
    {
        $warnings = [];
        foreach ($checks as $check) {
            if ($check['status'] === 'warn') {
                $warnings[] = $check['message'];
            }
        }

        return new self(
            checks: $checks,
            passed: true,
            warnings: $warnings,
            errors: [],
        );
    }

    /**
     * Construtor estático para resultado falho.
     *
     * @param list<array{name: string, status: string, message: string}> $checks
     */
    public static function failed(array $checks): self
    {
        $warnings = [];
        $errors = [];
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $errors[] = $check['message'];
            } elseif ($check['status'] === 'warn') {
                $warnings[] = $check['message'];
            }
        }

        return new self(
            checks: $checks,
            passed: false,
            warnings: $warnings,
            errors: $errors,
        );
    }

    /**
     * @return array{checks: list<array{name: string, status: string, message: string}>, passed: bool, warnings: list<string>, errors: list<string>}
     */
    public function toArray(): array
    {
        return [
            'checks' => $this->checks,
            'passed' => $this->passed,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
        ];
    }
}
