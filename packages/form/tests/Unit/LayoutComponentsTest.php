<?php

declare(strict_types=1);

use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Form\Layout\Columns;
use Arqel\Form\Layout\Component;
use Arqel\Form\Layout\Fieldset;
use Arqel\Form\Layout\Grid;
use Arqel\Form\Layout\Group;
use Arqel\Form\Layout\Section;
use Arqel\Form\Layout\Tab;
use Arqel\Form\Layout\Tabs;

it('Section: fluent API and type-specific props', function (): void {
    $section = Section::make('Profile')
        ->description('Basic info')
        ->icon('user')
        ->columns(2)
        ->compact()
        ->aside()
        ->collapsed()
        ->schema([new TextField('name')]);

    expect($section->getType())->toBe('section')
        ->and($section->getHeading())->toBe('Profile')
        ->and($section->getSchema())->toHaveCount(1)
        ->and($section->getTypeSpecificProps())->toBe([
            'heading' => 'Profile',
            'description' => 'Basic info',
            'icon' => 'user',
            'collapsible' => true,
            'collapsed' => true,
            'columns' => 2,
            'compact' => true,
            'aside' => true,
        ]);
});

it('Section: collapsed() implies collapsible()', function (): void {
    $section = Section::make('Advanced')->collapsed();

    expect($section->getTypeSpecificProps()['collapsible'])->toBeTrue()
        ->and($section->getTypeSpecificProps()['collapsed'])->toBeTrue();
});

it('Section: clamp columns ≥ 1', function (): void {
    expect(Section::make('x')->columns(0)->getTypeSpecificProps()['columns'])->toBe(1);
});

it('Fieldset: legend + columns', function (): void {
    $fs = Fieldset::make('Permissions')->columns(3);

    expect($fs->getType())->toBe('fieldset')
        ->and($fs->getLegend())->toBe('Permissions')
        ->and($fs->getTypeSpecificProps())->toBe([
            'legend' => 'Permissions',
            'columns' => 3,
        ]);
});

it('Grid: fixed columns serialised verbatim', function (): void {
    $g = Grid::make()->columns(4)->gap('gap-6');

    expect($g->getType())->toBe('grid')
        ->and($g->getCols())->toBe(4)
        ->and($g->getTypeSpecificProps())->toBe([
            'columns' => 4,
            'gap' => 'gap-6',
        ]);
});

it('Grid: responsive breakpoint map preserved', function (): void {
    $g = Grid::make()->columns(['sm' => 1, 'md' => 2, 'lg' => 4]);

    expect($g->getCols())->toBe(['sm' => 1, 'md' => 2, 'lg' => 4])
        ->and($g->getTypeSpecificProps()['columns'])->toBe(['sm' => 1, 'md' => 2, 'lg' => 4]);
});

it('Columns: pure 2-column shorthand', function (): void {
    $c = Columns::make()->schema([new TextField('a'), new TextField('b')]);

    expect($c->getType())->toBe('columns')
        ->and($c->getTypeSpecificProps())->toBe(['columns' => 2])
        ->and($c->getSchema())->toHaveCount(2);
});

it('Group: orientation and visibleIf carry through', function (): void {
    $g = Group::make()
        ->orientation(Group::ORIENTATION_HORIZONTAL)
        ->visibleIf(fn () => true);

    expect($g->getType())->toBe('group')
        ->and($g->getOrientation())->toBe('horizontal')
        ->and($g->isVisibleFor())->toBeTrue();
});

it('Group: invalid orientation falls back to vertical', function (): void {
    expect(Group::make()->orientation('sideways')->getOrientation())->toBe('vertical');
});

it('Tabs: collects Tab children, defaults to first id, orientation', function (): void {
    $tabs = Tabs::make()
        ->tabs([
            Tab::make('general', 'General')->schema([new TextField('name')]),
            Tab::make('advanced', 'Advanced'),
        ]);

    expect($tabs->getType())->toBe('tabs')
        ->and($tabs->getDefaultTab())->toBe('general')
        ->and($tabs->getOrientation())->toBe('horizontal')
        ->and($tabs->getSchema())->toHaveCount(2);
});

it('Tabs: respects an explicit defaultTab id', function (): void {
    $tabs = Tabs::make()
        ->tabs([Tab::make('a', 'A'), Tab::make('b', 'B')])
        ->defaultTab('b')
        ->vertical();

    expect($tabs->getDefaultTab())->toBe('b')
        ->and($tabs->getOrientation())->toBe('vertical');
});

it('Tab: serialises id, label, icon, and badge (int or Closure)', function (): void {
    $literal = Tab::make('general', 'General')->icon('cog')->badge(3);
    $closure = Tab::make('errors', 'Errors')->badge(fn () => 7);

    expect($literal->getTypeSpecificProps())->toBe([
        'id' => 'general',
        'label' => 'General',
        'icon' => 'cog',
        'badge' => 3,
    ])
        ->and($closure->getTypeSpecificProps()['badge'])->toBe(7);
});

it('Component.canSee gates visibility', function (): void {
    $section = Section::make('Hidden')->canSee(fn () => false);

    expect($section->isVisibleFor())->toBeFalse();
});

it('Component.canSee + visibleIf both must pass', function (): void {
    $section = Section::make('x')
        ->canSee(fn () => true)
        ->visibleIf(fn () => false);

    expect($section->isVisibleFor())->toBeFalse();
});

it('Component.toArray exposes type, component, columnSpan and props', function (): void {
    $section = Section::make('Profile')->columnSpan('full');

    $payload = $section->toArray();

    expect($payload['type'])->toBe('section')
        ->and($payload['component'])->toBe('FormSection')
        ->and($payload['columnSpan'])->toBe('full')
        ->and($payload['props']['heading'])->toBe('Profile');
});

it('Form: nested layout components flatten fields recursively', function (): void {
    $form = Form::make()->schema([
        Section::make('Profile')->schema([
            new TextField('name'),
            Grid::make()->schema([
                new TextField('email'),
                new TextField('phone'),
            ]),
        ]),
        new TextField('bio'),
    ]);

    $names = array_map(fn ($f) => $f->getName(), $form->getFields());

    expect($names)->toBe(['name', 'email', 'phone', 'bio']);
});

/** @var Component $_typeProbe (anchors the import for static analysis below) */
$_typeProbe = Section::make('x');
expect($_typeProbe)->toBeInstanceOf(Component::class);
