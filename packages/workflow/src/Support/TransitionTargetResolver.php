<?php

declare(strict_types=1);

namespace Arqel\Workflow\Support;

use ReflectionMethod;
use Throwable;

/**
 * Single source of truth for deriving a transition's declared target state.
 *
 * Prefers a public static `to()` method on the transition class; otherwise
 * falls back to the `XxxToYyy` class-name convention, extracting the target
 * segment (`Yyy`) via `/To([A-Z][A-Za-z0-9]*)$/`. When neither resolves, the
 * short class name is returned unchanged.
 *
 * Kept as a shared helper so the read path (`StateTransitionField`), the write
 * path (`HasWorkflow`), and the authorizer (`TransitionAuthorizer`) cannot
 * drift into emitting divergent target tokens (issue #119).
 */
final class TransitionTargetResolver
{
    /**
     * Resolve the target token for a transition class.
     *
     * @param class-string $transition
     */
    public static function resolve(string $transition): string
    {
        $declared = self::fromStaticMethod($transition);

        if ($declared !== null) {
            return $declared;
        }

        $short = self::shortName($transition);

        if (preg_match('/To([A-Z][A-Za-z0-9]*)$/', $short, $matches) === 1) {
            return $matches[1];
        }

        return $short;
    }

    /**
     * Read the target from a public static `to()` method, when present.
     *
     * @param class-string $transition
     */
    private static function fromStaticMethod(string $transition): ?string
    {
        if (! method_exists($transition, 'to')) {
            return null;
        }

        try {
            $reflection = new ReflectionMethod($transition, 'to');

            if ($reflection->isStatic() && $reflection->isPublic()) {
                /** @var mixed $result */
                $result = $reflection->invoke(null);

                if (is_string($result) && $result !== '') {
                    return $result;
                }
            }
        } catch (Throwable) {
            // Fall through to the class-name convention.
        }

        return null;
    }

    private static function shortName(string $value): string
    {
        if (! str_contains($value, '\\')) {
            return $value;
        }

        $pos = strrpos($value, '\\');

        return $pos === false ? $value : substr($value, $pos + 1);
    }
}
