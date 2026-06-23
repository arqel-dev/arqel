<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Requests;

use Arqel\Auth\Concerns\ResolvesPanelGuard;
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
    use ResolvesPanelGuard;

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
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
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

    /**
     * Resolve the Eloquent model backing the panel guard's provider,
     * mirroring RegisterController::resolveUserModel() so the email-unique
     * check targets the same table the controller writes to (#191). Falls
     * back to the default `users` provider model.
     *
     * @return class-string|string
     */
    private function resolveUserModel(): string
    {
        $guard = $this->resolvePanelGuard();
        $provider = config("auth.guards.{$guard}.provider", 'users');
        $providerKey = is_string($provider) && $provider !== '' ? $provider : 'users';

        $model = (string) config("auth.providers.{$providerKey}.model", 'App\\Models\\User');

        return class_exists($model) ? $model : 'App\\Models\\User';
    }

    private function resolveUsersTable(): string
    {
        $model = $this->resolveUserModel();

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
