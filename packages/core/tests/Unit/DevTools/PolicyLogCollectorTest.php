<?php

declare(strict_types=1);

use Arqel\Core\DevTools\PolicyLogCollector;
use Illuminate\Database\Eloquent\Model;

it('records entries appendably and returns them via all()', function (): void {
    $collector = new PolicyLogCollector;

    $collector->record('view', ['x'], true, []);
    $collector->record('update', ['y'], false, []);

    $entries = $collector->all();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['ability'])->toBe('view')
        ->and($entries[0]['result'])->toBeTrue()
        ->and($entries[1]['ability'])->toBe('update')
        ->and($entries[1]['result'])->toBeFalse()
        ->and($entries[0]['arguments'])->toBe(['x']);
});

it('truncates older entries past ENTRY_LIMIT', function (): void {
    $collector = new PolicyLogCollector;

    $total = PolicyLogCollector::ENTRY_LIMIT + 25;
    for ($i = 0; $i < $total; $i++) {
        $collector->record("ability{$i}", [], $i % 2 === 0, []);
    }

    $entries = $collector->all();

    expect($entries)->toHaveCount(PolicyLogCollector::ENTRY_LIMIT)
        // Oldest 25 dropped — first surviving ability is index 25.
        ->and($entries[0]['ability'])->toBe('ability25')
        ->and($entries[count($entries) - 1]['ability'])->toBe('ability'.($total - 1));
});

it('flushes the buffer back to empty', function (): void {
    $collector = new PolicyLogCollector;
    $collector->record('view', [], true, []);
    expect($collector->count())->toBe(1);

    $collector->flush();

    expect($collector->all())->toBe([])
        ->and($collector->count())->toBe(0);
});

it('serialises Eloquent models to {__model, key} without circular refs', function (): void {
    $model = new class extends Model
    {
        protected $primaryKey = 'id';

        public $timestamps = false;

        protected $guarded = [];

        public function getKey(): mixed
        {
            return 42;
        }
    };

    $collector = new PolicyLogCollector;
    $collector->record('update', [$model, ['scope' => 'admin']], true, []);

    $entry = $collector->all()[0];

    expect($entry['arguments'][0])
        ->toMatchArray(['__model' => $model::class, 'key' => 42])
        ->and($entry['arguments'][1])->toBe(['scope' => 'admin']);
});

it('normalises backtrace frames to file/line/class/function only', function (): void {
    $collector = new PolicyLogCollector;
    $collector->record('view', [], true, [
        ['file' => '/app/Foo.php', 'line' => 12, 'class' => 'App\\Foo', 'function' => 'bar', 'args' => ['secret']],
        ['function' => 'closure'],
    ]);

    $frames = $collector->all()[0]['backtrace'];

    expect($frames)->toHaveCount(2)
        ->and($frames[0])->toMatchArray([
            'file' => '/app/Foo.php',
            'line' => 12,
            'class' => 'App\\Foo',
            'function' => 'bar',
        ])
        ->and($frames[0])->not->toHaveKey('args')
        ->and($frames[1]['function'])->toBe('closure')
        ->and($frames[1]['file'])->toBeNull();
});
