<?php

declare(strict_types=1);

namespace Arqel\Realtime\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Minimal Authenticatable used to exercise the presence channel callback
 * without spinning up a full Eloquent User model in tests.
 */
final class FakePresenceUser implements Authenticatable
{
    public function __construct(
        public readonly int $id = 1,
        public readonly ?string $name = 'Ada Lovelace',
        public readonly ?string $avatar_url = 'https://example.test/avatar.png',
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
