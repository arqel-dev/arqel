<?php

declare(strict_types=1);

namespace Arqel\Core\Support;

/**
 * Detects whether the current TTY can sustain `laravel/prompts` interactive
 * widgets. Some embedded terminals (Claude Code, certain Docker `-it` setups,
 * a handful of CIs) expose a pseudo-TTY that passes `posix_isatty(STDIN)`
 * but emits a non-POSIX serialization from `stty -g` that the subsequent
 * `stty <mode>` invocation rejects with "stty: invalid argument ...",
 * crashing any prompt mid-flow.
 *
 * Commands that wrap `confirm()` / `select()` / `text()` should gate them on
 * `InteractiveTerminal::supportsPrompts()` and fall back to defaults (or to
 * --force / --no-interaction style flags) when it returns false.
 */
final class InteractiveTerminal
{
    private static ?bool $cached = null;

    public static function supportsPrompts(): bool
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        return self::$cached = self::probe();
    }

    /** @internal exposed for tests */
    public static function reset(): void
    {
        self::$cached = null;
    }

    private static function probe(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return true;
        }

        if (function_exists('posix_isatty') && ! @posix_isatty(STDIN)) {
            return false;
        }

        $tty = @fopen('/dev/tty', 'r');
        if ($tty === false) {
            return false;
        }
        fclose($tty);

        $mode = self::execAgainstTty('stty -g');
        if ($mode === null || $mode === '') {
            return false;
        }

        // Round-trip: feed the captured serialization back to stty. POSIX
        // terminals accept it as a no-op; non-POSIX ones fail the same way
        // laravel/prompts would, letting us short-circuit before the crash.
        $roundTrip = self::execAgainstTty('stty '.escapeshellarg($mode));

        return $roundTrip !== null;
    }

    private static function execAgainstTty(string $command): ?string
    {
        $process = @proc_open($command.' 2>/dev/null', [
            0 => ['file', '/dev/tty', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (! is_resource($process)) {
            return null;
        }

        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        if ($code !== 0) {
            return null;
        }

        return trim($stdout);
    }
}
