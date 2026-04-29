<?php

declare(strict_types=1);

use Arqel\Widgets\TableWidget;
use Arqel\Widgets\Tests\Fixtures\FakeBuilder;

it('exposes the canonical type + component', function (): void {
    $widget = TableWidget::make('latest-orders');

    expect($widget->getType())->toBe('table')
        ->and($widget->getComponent())->toBe('TableWidget');
});

it('emits empty records when no query is configured', function (): void {
    $widget = TableWidget::make('x');

    $data = $widget->data();

    expect($data['records'])->toBe([])
        ->and($data['limit'])->toBe(10)
        ->and($data['columns'])->toBe([])
        ->and($data['seeAllUrl'])->toBeNull()
        ->and($data)->not->toHaveKey('loadError');
});

it('limit clamps to ≥ 1 and defaults to 10', function (): void {
    expect(TableWidget::make('x')->getLimit())->toBe(10)
        ->and(TableWidget::make('x')->limit(5)->getLimit())->toBe(5)
        ->and(TableWidget::make('x')->limit(0)->getLimit())->toBe(1)
        ->and(TableWidget::make('x')->limit(-3)->getLimit())->toBe(1);
});

it('columns serialise objects exposing toArray() and drop the rest', function (): void {
    $col = new class
    {
        public function toArray(): array
        {
            return ['key' => 'name', 'label' => 'Name'];
        }
    };

    $widget = TableWidget::make('x')->columns([$col, 'string-not-allowed', 42, null]);

    expect($widget->getColumns())->toHaveCount(4); // raw passthrough at storage
    expect($widget->data()['columns'])->toBe([
        ['key' => 'name', 'label' => 'Name'],
    ]);
});

it('seeAllUrl accepts strings, Closures, and null', function (): void {
    expect(TableWidget::make('x')->seeAllUrl('/orders')->data()['seeAllUrl'])->toBe('/orders')
        ->and(TableWidget::make('x')->seeAllUrl(fn () => '/orders/all')->data()['seeAllUrl'])->toBe('/orders/all')
        ->and(TableWidget::make('x')->seeAllUrl(null)->data()['seeAllUrl'])->toBeNull();
});

it('seeAllUrl Closure returning non-string falls back to null', function (): void {
    /** @var Closure $bad */
    $bad = fn () => 123;

    expect(TableWidget::make('x')->seeAllUrl($bad)->data()['seeAllUrl'])->toBeNull();
});

it('data() invokes the query Closure and returns serialised records', function (): void {
    $rows = [
        ['id' => 1, 'name' => 'Alpha'],
        ['id' => 2, 'name' => 'Bravo'],
        ['id' => 3, 'name' => 'Charlie'],
    ];

    $widget = TableWidget::make('orders')
        ->limit(2)
        ->query(fn () => new FakeBuilder($rows));

    $data = $widget->data();

    expect($data['records'])->toBe([
        ['id' => 1, 'name' => 'Alpha'],
        ['id' => 2, 'name' => 'Bravo'],
    ])->and($data['limit'])->toBe(2);
});

it('data() catches throwing query Closures and surfaces loadError', function (): void {
    $widget = TableWidget::make('orders')->query(function (): never {
        throw new RuntimeException('database is sleeping');
    });

    $data = $widget->data();

    expect($data['records'])->toBe([])
        ->and($data['loadError'])->toBe('database is sleeping');
});

it('toArray emits the table data inline by default', function (): void {
    $widget = TableWidget::make('orders')
        ->query(fn () => new FakeBuilder([['id' => 1]]))
        ->seeAllUrl('/orders');

    $payload = $widget->toArray();

    expect($payload['type'])->toBe('table')
        ->and($payload['component'])->toBe('TableWidget')
        ->and($payload['data']['records'])->toBe([['id' => 1]])
        ->and($payload['data']['seeAllUrl'])->toBe('/orders');
});

it('deferred TableWidget emits data: null without invoking the query', function (): void {
    $invoked = 0;
    $widget = TableWidget::make('orders')
        ->deferred()
        ->query(function () use (&$invoked) {
            $invoked++;

            return new FakeBuilder([]);
        });

    $payload = $widget->toArray();

    expect($payload['deferred'])->toBeTrue()
        ->and($payload['data'])->toBeNull()
        ->and($invoked)->toBe(0);
});
