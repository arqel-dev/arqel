<?php

declare(strict_types=1);

namespace Arqel\Core\CommandPalette;

/**
 * Tiny fuzzy scorer for the command palette.
 *
 * The scoring buckets are intentionally coarse — exact / contains /
 * subsequence / miss. We do not aim for parity with fzf or
 * similar tools; we just need stable, predictable ordering for at
 * most a few hundred commands. If the catalogue grows, swap this
 * for a domain-specific implementation.
 */
final class FuzzyMatcher
{
    public const int LIMIT = 20;

    /**
     * Score a single haystack against the query.
     *
     * Buckets:
     *   - empty query           → 100 (everything matches)
     *   - case-insensitive      → 95
     *     exact match
     *   - case-insensitive      → 80
     *     str_contains match
     *   - subsequence match     → 50 + bonus per consecutive run
     *   - otherwise             → 0
     */
    public static function score(string $query, string $haystack): int
    {
        if ($query === '') {
            return 100;
        }

        $needle = mb_strtolower($query);
        $hay = mb_strtolower($haystack);

        if ($needle === $hay) {
            return 95;
        }

        if ($hay !== '' && str_contains($hay, $needle)) {
            return 80;
        }

        $runBonus = self::subsequenceBonus($needle, $hay);

        if ($runBonus === null) {
            return 0;
        }

        return 50 + $runBonus;
    }

    /**
     * Rank a list of commands, dropping zero-scored entries and
     * keeping at most $limit results in descending score order.
     *
     * @param array<int, Command> $commands
     *
     * @return array<int, Command>
     */
    public static function rank(array $commands, string $query, int $limit = self::LIMIT): array
    {
        if ($commands === []) {
            return [];
        }

        $scored = [];

        foreach ($commands as $index => $command) {
            $labelScore = self::score($query, $command->label);
            $descriptionScore = $command->description !== null
                ? self::score($query, $command->description)
                : 0;

            $score = max($labelScore, $descriptionScore);

            if ($score === 0) {
                continue;
            }

            $scored[] = ['score' => $score, 'index' => $index, 'command' => $command];
        }

        // Stable sort: higher score first, ties broken by original index.
        usort($scored, function (array $a, array $b): int {
            if ($a['score'] === $b['score']) {
                return $a['index'] <=> $b['index'];
            }

            return $b['score'] <=> $a['score'];
        });

        $sliced = array_slice($scored, 0, max(0, $limit));

        return array_map(static fn (array $entry): Command => $entry['command'], $sliced);
    }

    /**
     * Walk the haystack once and try to match every needle char in
     * order. Returns null if any char is missing; otherwise a small
     * bonus that grows with the length of consecutive matches.
     */
    private static function subsequenceBonus(string $needle, string $haystack): ?int
    {
        if ($needle === '') {
            return 0;
        }

        $needleLength = mb_strlen($needle);
        $hayLength = mb_strlen($haystack);

        $needleIndex = 0;
        $bonus = 0;
        $run = 0;

        for ($i = 0; $i < $hayLength && $needleIndex < $needleLength; $i++) {
            if (mb_substr($haystack, $i, 1) === mb_substr($needle, $needleIndex, 1)) {
                $needleIndex++;
                $run++;
                if ($run > 1) {
                    $bonus++;
                }
            } else {
                $run = 0;
            }
        }

        if ($needleIndex < $needleLength) {
            return null;
        }

        return $bonus;
    }
}
