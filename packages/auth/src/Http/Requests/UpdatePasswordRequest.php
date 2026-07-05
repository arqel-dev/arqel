<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Requests;

use Arqel\Auth\Concerns\ResolvesPanelGuard;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest para troca de senha do usuário autenticado (verifica a
 * senha atual). Escopo Auth UI (Profile).
 */
final class UpdatePasswordRequest extends FormRequest
{
    use ResolvesPanelGuard;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $guard = $this->resolvePanelGuard();

        return [
            'current_password' => ['required', 'string', "current_password:{$guard}"],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => (string) __('arqel::messages.profile.attributes.current_password'),
            'password' => (string) __('arqel::messages.profile.attributes.password'),
        ];
    }
}
