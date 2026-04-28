<?php

declare(strict_types=1);

namespace Arqel\Fields;

use Closure;

/**
 * Translates Laravel validation rule arrays into a Zod schema source
 * string consumable by the React layer.
 *
 * Output is a literal expression like
 * `z.string().email().min(1).max(255).nullable()`. The client runtime
 * is responsible for materialising it into a Zod schema; the
 * generation always happens server-side from controller-known rules,
 * so the input domain is fully trusted.
 *
 * Translators for the built-in rules ship via `bootBuiltins()`. Apps
 * register custom rules with `register()`; unknown rules are skipped
 * silently so server-only rules like `confirmed` do not break the
 * bridge.
 *
 * Translator signature: `Closure(?string $arg, Translation $t): void`.
 * Apps mutate the `Translation` accumulator instead of constructing
 * the Zod string by hand.
 */
final class ValidationBridge
{
    /** @var array<string, Closure(string|null, Translation): void> */
    private static array $rules = [];

    private static bool $booted = false;

    /**
     * Register a custom rule translator.
     *
     * @param Closure(string|null, Translation): void $translator
     */
    public static function register(string $rule, Closure $translator): void
    {
        self::ensureBooted();
        self::$rules[$rule] = $translator;
    }

    public static function hasRule(string $rule): bool
    {
        self::ensureBooted();

        return isset(self::$rules[$rule]);
    }

    /**
     * @param array<int, string> $rules
     */
    public static function translate(array $rules): string
    {
        self::ensureBooted();

        $translation = new Translation;

        foreach ($rules as $rule) {
            [$name, $arg] = self::splitRule($rule);
            $translator = self::$rules[$name] ?? null;

            if ($translator === null) {
                continue;
            }

            $translator($arg, $translation);
        }

        return $translation->toString();
    }

    public static function flush(): void
    {
        self::$rules = [];
        self::$booted = false;
    }

    public static function bootBuiltins(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        self::$rules['string'] = fn (?string $arg, Translation $t) => $t->setType('z.string()');
        self::$rules['numeric'] = fn (?string $arg, Translation $t) => $t->setType('z.number()');
        self::$rules['integer'] = fn (?string $arg, Translation $t) => $t->setType('z.number().int()');
        self::$rules['boolean'] = fn (?string $arg, Translation $t) => $t->setType('z.boolean()');
        self::$rules['array'] = fn (?string $arg, Translation $t) => $t->setType('z.array(z.any())');
        self::$rules['date'] = fn (?string $arg, Translation $t) => $t->setType('z.string().datetime()');
        self::$rules['file'] = fn (?string $arg, Translation $t) => $t->setType('z.any()');
        self::$rules['image'] = fn (?string $arg, Translation $t) => $t->setType('z.any()');

        self::$rules['email'] = function (?string $arg, Translation $t): void {
            $t->ensureType('z.string()');
            $t->addChain('.email()');
        };

        self::$rules['url'] = function (?string $arg, Translation $t): void {
            $t->ensureType('z.string()');
            $t->addChain('.url()');
        };

        self::$rules['uuid'] = function (?string $arg, Translation $t): void {
            $t->ensureType('z.string()');
            $t->addChain('.uuid()');
        };

        self::$rules['min'] = function (?string $arg, Translation $t): void {
            if ($arg !== null) {
                $t->addChain('.min('.$arg.')');
            }
        };

        self::$rules['max'] = function (?string $arg, Translation $t): void {
            if ($arg !== null) {
                $t->addChain('.max('.$arg.')');
            }
        };

        self::$rules['size'] = function (?string $arg, Translation $t): void {
            if ($arg === null) {
                return;
            }
            $t->addChain('.min('.$arg.').max('.$arg.')');
        };

        self::$rules['regex'] = function (?string $arg, Translation $t): void {
            if ($arg !== null) {
                $t->addChain('.regex('.$arg.')');
            }
        };

        self::$rules['in'] = function (?string $arg, Translation $t): void {
            if ($arg === null || $arg === '') {
                return;
            }
            $values = array_map(
                fn (string $v) => '"'.addslashes(trim($v)).'"',
                explode(',', $arg),
            );
            $t->setType('z.enum(['.implode(', ', $values).'])');
        };

        self::$rules['not_in'] = function (?string $arg, Translation $t): void {
            if ($arg === null || $arg === '') {
                return;
            }
            $values = array_map(
                fn (string $v) => '"'.addslashes(trim($v)).'"',
                explode(',', $arg),
            );
            $t->ensureType('z.string()');
            $t->addChain('.refine((v) => ![ '.implode(', ', $values).' ].includes(v))');
        };

        self::$rules['unique'] = function (?string $arg, Translation $t): void {
            if ($arg === null || $arg === '') {
                return;
            }
            [$table, $column] = array_pad(explode(',', $arg, 2), 2, '');
            $t->ensureType('z.string()');
            $t->addChain(sprintf(
                '.refine(async (v) => !(await checkUnique(%s, %s, v)))',
                '"'.addslashes(trim($table)).'"',
                '"'.addslashes(trim($column)).'"',
            ));
        };

        self::$rules['nullable'] = function (?string $arg, Translation $t): void {
            $t->ensureType('z.any()');
            $t->addChain('.nullable()');
        };

        self::$rules['required'] = function (?string $arg, Translation $t): void {
            $t->ensureType('z.string()');
            $t->markRequired();
        };

        self::$rules['mimetypes'] = fn (?string $arg, Translation $t) => $t->ensureType('z.any()');
    }

    private static function ensureBooted(): void
    {
        if (! self::$booted) {
            self::bootBuiltins();
        }
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private static function splitRule(string $rule): array
    {
        $colon = strpos($rule, ':');
        if ($colon === false) {
            return [$rule, null];
        }

        return [substr($rule, 0, $colon), substr($rule, $colon + 1)];
    }
}
