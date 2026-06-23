<?php

declare(strict_types=1);

namespace Arqel\Versioning;

use Arqel\Versioning\Models\Version;
use Throwable;

/**
 * Helper de serialização de `Version` para payloads consumíveis pela
 * UI (Inertia / JSON).
 *
 * Por design, o `payload` completo do snapshot **não é exposto por
 * default** — pode conter PII e segredos do model. Para incluí-lo,
 * o consumidor passa `$includePayload = true` (o controller faz isso
 * apenas mediante o flag explícito `?include=payload`).
 *
 * O `changes_summary` é otimizado para timelines (ex.: "Changed 3
 * fields: title, body, status"). Quando o snapshot é o registo
 * inicial (`changes` nulo) emitimos "Created"; sem mudanças efetivas
 * (array vazio) ficamos com "No changes".
 */
final readonly class VersionPresenter
{
    /**
     * @return array{
     *     id: int,
     *     created_at: string|null,
     *     changes_summary: string,
     *     changes: array<string, array{0: mixed, 1: mixed}>|null,
     *     user: array{id: int, name: string|null}|null,
     *     is_initial: bool,
     *     payload?: array<string, mixed>,
     * }
     */
    public static function toArray(Version $version, bool $includePayload = false): array
    {
        /** @var array<string, array{0: mixed, 1: mixed}>|null $changes */
        $changes = $version->changes;

        $isInitial = $changes === null;

        $createdAt = $version->created_at?->toIso8601String();

        $user = self::resolveUserSummary($version);

        $result = [
            'id' => (int) $version->id,
            'created_at' => $createdAt,
            'changes_summary' => self::summarize($changes),
            'changes' => $changes,
            'user' => $user,
            'is_initial' => $isInitial,
        ];

        if ($includePayload) {
            /** @var array<string, mixed> $payload */
            $payload = $version->payload;
            $result['payload'] = $payload;
        }

        return $result;
    }

    /**
     * Build the human-readable `changes_summary` lazily through the
     * translator so the request locale applies and plural selection follows
     * the target locale's rules (trans_choice). Falls back to the English
     * literals when no translator is bound or the key is untranslated,
     * keeping the payload text stable for existing consumers.
     *
     * @param array<string, array{0: mixed, 1: mixed}>|null $changes
     */
    private static function summarize(?array $changes): string
    {
        if ($changes === null) {
            return self::trans('arqel.versioning.summary.created', 'Created');
        }

        if ($changes === []) {
            return self::trans('arqel.versioning.summary.no_changes', 'No changes');
        }

        $fields = array_keys($changes);
        $count = count($fields);
        $joined = implode(', ', $fields);

        $key = 'arqel::arqel.versioning.summary.changed';

        if (app()->bound('translator')) {
            $translated = trans_choice($key, $count, [
                'count' => (string) $count,
                'fields' => $joined,
            ]);

            if ($translated !== $key) {
                return $translated;
            }
        }

        return sprintf('Changed %d field%s: %s', $count, $count === 1 ? '' : 's', $joined);
    }

    /**
     * Resolve a single localized summary literal, falling back to the
     * English text when the translator is absent or the key is untranslated.
     */
    private static function trans(string $key, string $fallback): string
    {
        $namespaced = 'arqel::'.$key;

        if (! app()->bound('translator')) {
            return $fallback;
        }

        $translated = trans($namespaced);

        return is_string($translated) && $translated !== $namespaced ? $translated : $fallback;
    }

    /**
     * @return array{id: int, name: string|null}|null
     */
    private static function resolveUserSummary(Version $version): ?array
    {
        $userId = $version->created_by_user_id;

        if ($userId === null) {
            return null;
        }

        $relation = $version->user();

        if ($relation === null) {
            return ['id' => $userId, 'name' => null];
        }

        try {
            /** @var \Illuminate\Database\Eloquent\Model|null $user */
            $user = $version->getRelationValue('user');
        } catch (Throwable) {
            $user = null;
        }

        if ($user === null) {
            return ['id' => $userId, 'name' => null];
        }

        $name = $user->getAttribute('name');

        return [
            'id' => $userId,
            'name' => is_string($name) ? $name : null,
        ];
    }
}
