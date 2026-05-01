<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * FormRequest para registro bundled de Arqel.
 *
 * Aplica rate-limiting Laravel-native (3 registros / hora / IP)
 * antes da validação. Em excesso, devolve 422 com mensagem clara.
 */
final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $userTable = $this->resolveUsersTable();

        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($userTable, 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 3)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('Too many registration attempts. Please try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]),
        ]);
    }

    public function recordAttempt(): void
    {
        // 1 hour window = 3600 seconds.
        RateLimiter::hit($this->throttleKey(), 3600);
    }

    public function throttleKey(): string
    {
        return 'arqel-register|'.$this->ip();
    }

    private function resolveUsersTable(): string
    {
        $model = (string) config('auth.providers.users.model', 'App\\Models\\User');

        if (class_exists($model)) {
            try {
                /** @var object $instance */
                $instance = new $model;

                if (method_exists($instance, 'getTable')) {
                    return (string) $instance->getTable();
                }
            } catch (Throwable) {
                // Fallback below.
            }
        }

        return 'users';
    }
}
