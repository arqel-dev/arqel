<?php

declare(strict_types=1);

namespace Arqel\Cli\Support;

/**
 * Detects whether the current TTY can sustain `laravel/prompts` interactive
 * widgets. Some embedded terminals (Claude Code, certain Docker `-it` setups,
 * a handful of CIs) expose a pseudo-TTY that passes `posix_isatty(STDIN)`
 * but emits a non-POSIX serialization from `stty -g` that the subsequent
 * `stty <mode>` invocation rejects with "stty: invalid argument ...",
 * crashing any prompt mid-flow.
 *
 * Standalone copy of `Arqel\Core\Support\InteractiveTerminal` — `arqel/cli`
 * has no dependency on `arqel/core` (and shouldn't, the CLI ships before
 * any framework code runs), so the helper is duplicated. Both copies
 * implement the same probe and should stay in sync.
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
