<?php

declare(strict_types=1);

use Arqel\Export\Exporters\FormatsDateCells;
use Illuminate\Support\Facades\App;

/**
 * Bare host for the trait so the relative-date formatting can be
 * exercised in isolation (without writing a real export file).
 */
function makeDateCellFormatter(): object
{
    return new class
    {
        use FormatsDateCells;

        /**
         * @param array<string, mixed> $column
         */
        public function format(DateTimeInterface $value, array $column): string
        {
            return $this->formatDateCell($value, $column);
        }
    };
}

it('renders a since-mode cell in English under the en locale', function (): void {
    App::setLocale('en');

    $formatter = makeDateCellFormatter();
    $cell = $formatter->format(
        new DateTime('-2 days'),
        ['props' => ['mode' => 'since', 'format' => 'Y-m-d']],
    );

    expect($cell)->toContain('ago')
        ->and($cell)->toContain('day');
});

it('binds Carbon diffForHumans to the pt_BR request locale for since cells', function (): void {
    App::setLocale('pt_BR');

    $formatter = makeDateCellFormatter();
    $cell = $formatter->format(
        new DateTime('-2 days'),
        ['props' => ['mode' => 'since', 'format' => 'Y-m-d']],
    );

    // pt-BR relative strings use "dias" and never the English "ago".
    expect($cell)->toContain('dias')
        ->and($cell)->not->toContain('ago');
});

it('does not leak a previously set locale into a later en export', function (): void {
    App::setLocale('pt_BR');
    makeDateCellFormatter()->format(
        new DateTime('-1 day'),
        ['props' => ['mode' => 'since']],
    );

    App::setLocale('en');
    $cell = makeDateCellFormatter()->format(
        new DateTime('-1 day'),
        ['props' => ['mode' => 'since']],
    );

    expect($cell)->toContain('ago');
});
