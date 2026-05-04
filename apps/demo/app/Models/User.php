<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 */
final class User extends Model implements Authenticatable
{
    protected $table = 'users';

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'password'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

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
        return (string) ($this->attributes['password'] ?? '');
    }

    public function getRememberToken(): string
    {
        return (string) ($this->attributes['remember_token'] ?? '');
    }

    public function setRememberToken($value): void
    {
        $this->attributes['remember_token'] = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
