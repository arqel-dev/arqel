<?php

declare(strict_types=1);

namespace App\States;

/**
 * Definições de estados e transições válidas para `Post`.
 *
 * Diagrama:
 *   pending → draft → review → published → archived
 *                ↑       ↓
 *                └───────┘  (review pode voltar para draft)
 */
final class PostStates
{
    public const PENDING = 'pending';

    public const DRAFT = 'draft';

    public const REVIEW = 'review';

    public const PUBLISHED = 'published';

    public const ARCHIVED = 'archived';

    /** @return array<string, array<int, string>> */
    public static function transitions(): array
    {
        return [
            self::PENDING => [self::DRAFT],
            self::DRAFT => [self::REVIEW],
            self::REVIEW => [self::DRAFT, self::PUBLISHED],
            self::PUBLISHED => [self::ARCHIVED],
            self::ARCHIVED => [],
        ];
    }

    /** @return array<int, string> */
    public static function allowedFrom(string $state): array
    {
        return self::transitions()[$state] ?? [];
    }
}
