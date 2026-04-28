<?php

declare(strict_types=1);

use Arqel\Fields\Translation;
use Arqel\Fields\ValidationBridge;

beforeEach(function (): void {
    ValidationBridge::flush();
});

afterEach(function (): void {
    ValidationBridge::flush();
});

it('translates required string with max', function (): void {
    expect(ValidationBridge::translate(['required', 'string', 'max:255']))
        ->toBe('z.string().min(1).max(255)');
});

it('translates email with nullable', function (): void {
    expect(ValidationBridge::translate(['email', 'nullable']))
        ->toBe('z.string().email().nullable()');
});

it('translates url', function (): void {
    expect(ValidationBridge::translate(['url']))
        ->toBe('z.string().url()');
});

it('translates uuid', function (): void {
    expect(ValidationBridge::translate(['uuid']))
        ->toBe('z.string().uuid()');
});

it('translates numeric and integer', function (): void {
    expect(ValidationBridge::translate(['numeric']))
        ->toBe('z.number()')
        ->and(ValidationBridge::translate(['integer']))
        ->toBe('z.number().int()');
});

it('translates boolean and array', function (): void {
    expect(ValidationBridge::translate(['boolean']))
        ->toBe('z.boolean()')
        ->and(ValidationBridge::translate(['array']))
        ->toBe('z.array(z.any())');
});

it('translates date', function (): void {
    expect(ValidationBridge::translate(['date']))
        ->toBe('z.string().datetime()');
});

it('translates min and max with explicit types', function (): void {
    expect(ValidationBridge::translate(['integer', 'min:1', 'max:10']))
        ->toBe('z.number().int().min(1).max(10)');
});

it('translates size into matched min/max', function (): void {
    expect(ValidationBridge::translate(['string', 'size:5']))
        ->toBe('z.string().min(5).max(5)');
});

it('translates regex verbatim', function (): void {
    expect(ValidationBridge::translate(['string', 'regex:/^[a-z]+$/']))
        ->toBe('z.string().regex(/^[a-z]+$/)');
});

it('translates in into z.enum', function (): void {
    expect(ValidationBridge::translate(['in:draft,published,archived']))
        ->toBe('z.enum(["draft", "published", "archived"])');
});

it('translates not_in into a refine guard', function (): void {
    expect(ValidationBridge::translate(['not_in:admin,api']))
        ->toBe('z.string().refine((v) => ![ "admin", "api" ].includes(v))');
});

it('translates unique into an async refine call', function (): void {
    expect(ValidationBridge::translate(['unique:users,email']))
        ->toBe('z.string().refine(async (v) => !(await checkUnique("users", "email", v)))');
});

it('translates file and image into z.any()', function (): void {
    expect(ValidationBridge::translate(['file']))->toBe('z.any()')
        ->and(ValidationBridge::translate(['image']))->toBe('z.any()');
});

it('combines required, email, max, and nullable in chain order', function (): void {
    expect(ValidationBridge::translate(['required', 'email', 'max:255', 'nullable']))
        ->toBe('z.string().min(1).email().max(255).nullable()');
});

it('falls back to z.string() default when no type rule fires', function (): void {
    expect(ValidationBridge::translate(['required']))
        ->toBe('z.string().min(1)');
});

it('skips unknown rules silently', function (): void {
    expect(ValidationBridge::translate(['string', 'confirmed', 'max:10']))
        ->toBe('z.string().max(10)');
});

it('honours custom registered translators', function (): void {
    ValidationBridge::register('shouty', function (?string $arg, Translation $t): void {
        $t->ensureType('z.string()');
        $t->addChain('.transform((v) => v.toUpperCase())');
    });

    expect(ValidationBridge::hasRule('shouty'))->toBeTrue()
        ->and(ValidationBridge::translate(['string', 'shouty']))
        ->toBe('z.string().transform((v) => v.toUpperCase())');
});

it('returns the default z.string() when given an empty rule list', function (): void {
    expect(ValidationBridge::translate([]))->toBe('z.string()');
});

it('handles empty arg on in and not_in gracefully', function (): void {
    expect(ValidationBridge::translate(['in:']))->toBe('z.string()')
        ->and(ValidationBridge::translate(['not_in:']))->toBe('z.string()');
});

it('treats min/max with non-numeric arg as literal segment', function (): void {
    expect(ValidationBridge::translate(['integer', 'min:0', 'max:100']))
        ->toBe('z.number().int().min(0).max(100)');
});

it('flush clears registered rules and resets boot state', function (): void {
    ValidationBridge::register('shouty', fn () => null);

    expect(ValidationBridge::hasRule('shouty'))->toBeTrue();

    ValidationBridge::flush();

    expect(ValidationBridge::hasRule('shouty'))->toBeFalse()
        ->and(ValidationBridge::hasRule('string'))->toBeTrue();
});
