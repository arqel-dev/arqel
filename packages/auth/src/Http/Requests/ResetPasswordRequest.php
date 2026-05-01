<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * FormRequest para o reset de senha bundled de Arqel.
 *
 * Aplica rate-limit (3/IP+email/hora) e valida token + email + nova senha
 * confirmada com no mínimo 8 chars.
 */
final class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
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

    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), 3600);
    }

    public function throttleKey(): string
    {
        return 'arqel.reset-password|'.Str::transliterate(Str::lower((string) $this->input('email'))).'|'.$this->ip();
    }
}
