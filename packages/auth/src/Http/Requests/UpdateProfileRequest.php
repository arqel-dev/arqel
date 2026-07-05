<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Requests;

use Arqel\Auth\Concerns\ResolvesPanelGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest para atualização dos dados de conta (nome + email) do
 * usuário autenticado. Escopo Auth UI (Profile).
 */
final class UpdateProfileRequest extends FormRequest
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
        $user = $this->user();
        $userId = $user?->getAuthIdentifier();
        $usersTable = $this->resolveUsersTable();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique($usersTable, 'email')->ignore($userId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => (string) __('arqel::messages.profile.attributes.name'),
            'email' => (string) __('arqel::messages.profile.attributes.email'),
        ];
    }

    /**
     * Resolve the users table backing the panel guard's provider model,
     * mirroring RegisterRequest so the unique check targets the right table.
     */
    private function resolveUsersTable(): string
    {
        $guard = $this->resolvePanelGuard();
        $provider = config("auth.guards.{$guard}.provider", 'users');
        $providerKey = is_string($provider) && $provider !== '' ? $provider : 'users';
        $model = (string) config("auth.providers.{$providerKey}.model", 'App\\Models\\User');

        if (class_exists($model)) {
            /** @var \Illuminate\Database\Eloquent\Model $instance */
            $instance = new $model;

            return $instance->getTable();
        }

        return 'users';
    }
}
