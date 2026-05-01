<?php

declare(strict_types=1);

namespace Arqel\Core\DevTools;

use Illuminate\Database\Eloquent\Model;

/**
 * Request-scoped collector for `Gate::after` events (DEVTOOLS-004).
 *
 * Bound as a singleton in `ArqelServiceProvider`. Laravel rebinds the
 * container between HTTP requests in production-like setups, so the
 * log is effectively request-scoped — between two visits the buffer
 * is empty.
 *
 * Hard-capped at {@see self::ENTRY_LIMIT} entries to avoid memory
 * blow-ups on noisy admin pages: oldest entries are dropped first.
 *
 * MUST never be wired in non-local environments — exposing this
 * payload outside `app()->environment('local')` would leak Gate
 * arguments + stack traces. The provider gates registration; this
 * class is a passive container and does not enforce env on its own.
 */
final class PolicyLogCollector
{
    /**
     * Maximum number of entries kept in the ring buffer. Anything
     * past this limit drops the oldest record (FIFO) so a single
     * request cannot exhaust memory.
     */
    public const int ENTRY_LIMIT = 200;

    /**
     * @var list<array<string, mixed>>
     */
    private array $log = [];

    /**
     * Append an entry. `$arguments` is normalised into a JSON-safe
     * shape (Eloquent models become `{class, key}` to keep the
     * payload free of circular references), and `$backtrace` is
     * trimmed to file/line/class/function only.
     *
     * @param array<int, mixed> $arguments
     * @param array<int, array<string, mixed>> $backtrace
     */
    public function record(string $ability, array $arguments, bool $result, array $backtrace = []): void
    {
        $this->log[] = [
            'ability' => $ability,
            'arguments' => $this->normalizeArguments($arguments),
            'result' => $result,
            'backtrace' => $this->normalizeBacktrace($backtrace),
            'timestamp' => microtime(true),
        ];

        $overflow = count($this->log) - self::ENTRY_LIMIT;
        if ($overflow > 0) {
            $this->log = array_slice($this->log, $overflow);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->log;
    }

    public function flush(): void
    {
        $this->log = [];
    }

    public function count(): int
    {
        return count($this->log);
    }

    /**
     * @param array<int, mixed> $arguments
     *
     * @return list<mixed>
     */
    private function normalizeArguments(array $arguments): array
    {
        $out = [];
        foreach ($arguments as $argument) {
            $out[] = $this->normalizeValue($argument);
        }

        return $out;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return [
                '__model' => $value::class,
                'key' => $value->getKey(),
            ];
        }

        if (is_object($value)) {
            return ['__object' => $value::class];
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $inner) {
                // Bound recursion to one level — Gate arguments are
                // typically (model, ?$context), nesting deeper is
                // rare and keeping it shallow keeps the payload small.
                $out[(string) $key] = is_array($inner) || is_object($inner)
                    ? $this->shallow($inner)
                    : $inner;
            }

            return $out;
        }

        return $value;
    }

    private function shallow(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return ['__model' => $value::class, 'key' => $value->getKey()];
        }
        if (is_object($value)) {
            return ['__object' => $value::class];
        }
        if (is_array($value)) {
            return ['__array' => count($value)];
        }

        return $value;
    }

    /**
     * @param array<int, array<string, mixed>> $backtrace
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeBacktrace(array $backtrace): array
    {
        $out = [];
        foreach ($backtrace as $frame) {
            $out[] = [
                'file' => isset($frame['file']) && is_string($frame['file']) ? $frame['file'] : null,
                'line' => isset($frame['line']) && is_int($frame['line']) ? $frame['line'] : null,
                'class' => isset($frame['class']) && is_string($frame['class']) ? $frame['class'] : null,
                'function' => isset($frame['function']) && is_string($frame['function']) ? $frame['function'] : null,
            ];
        }

        return $out;
    }
}
