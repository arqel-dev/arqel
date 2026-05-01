<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 */
final class TestUser extends Model implements Authenticatable
{
    protected $table = 'marketplace_test_users';

    /** @var list<string> */
    protected $fillable = ['name'];

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
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

    public function setRememberToken($value): void
    {
        // not used
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
