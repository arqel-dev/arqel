<?php

declare(strict_types=1);

namespace Arqel\Core\Commands;

use Arqel\Core\Support\InteractiveTerminal;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Throwable;

use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * `arqel:make-user` — comando à la `filament:make-user`/`nova:user`.
 *
 * Usado depois de `arqel:install` + `php artisan migrate` para criar
 * o primeiro usuário admin sem precisar de `tinker`. Funciona com o
 * model `User` configurado no auth guard padrão da aplicação.
 */
final class MakeUserCommand extends Command
{
    /** @var string */
    protected $signature = 'arqel:make-user
                            {--name= : Nome completo do usuário}
                            {--email= : E-mail (login)}
                            {--password= : Senha em texto puro (será passada por Hash::make)}';

    /** @var string */
    protected $description = 'Create a new admin user.';

    public function handle(): int
    {
        $modelClass = $this->resolveUserModel();

        if ($modelClass === null) {
            $this->error('Could not resolve the User model from auth.providers.users.model.');

            return self::FAILURE;
        }

        [$name, $email, $plainPassword] = $this->collectInput();

        if ($email === '' || $plainPassword === '') {
            $this->error('Name, email and password are required.');

            return self::FAILURE;
        }

        try {
            /** @var Authenticatable&\Illuminate\Database\Eloquent\Model $user */
            $user = new $modelClass;
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($plainPassword),
                'email_verified_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $this->error('Failed to create user: '.$e->getMessage());

            return self::FAILURE;
        }

        info(sprintf('User created: %s <%s>', $name, $email));

        return self::SUCCESS;
    }

    /**
     * Resolve the configured User model class.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>|null
     */
    private function resolveUserModel(): ?string
    {
        $guard = (string) config('auth.defaults.guard', 'web');
        $providerKey = config("auth.guards.{$guard}.provider");

        if (! is_string($providerKey)) {
            return null;
        }

        $model = config("auth.providers.{$providerKey}.model");

        return is_string($model) && class_exists($model) ? $model : null;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function collectInput(): array
    {
        $name = $this->stringOption('name');
        $email = $this->stringOption('email');
        $plainPassword = $this->stringOption('password');

        if (! InteractiveTerminal::supportsPrompts()) {
            if ($name === '' || $email === '' || $plainPassword === '') {
                warning('Non-interactive terminal detected — pass --name, --email, --password.');
            }

            return [$name, $email, $plainPassword];
        }

        if ($name === '') {
            $name = (string) text(label: 'Name', required: true);
        }

        if ($email === '') {
            $email = (string) text(
                label: 'Email',
                required: true,
                validate: static fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL)
                    ? null
                    : 'Invalid email address.',
            );
        }

        if ($plainPassword === '') {
            $plainPassword = (string) password(label: 'Password', required: true);
        }

        return [$name, $email, $plainPassword];
    }

    private function stringOption(string $name): string
    {
        $value = $this->option($name);

        return is_string($value) ? trim($value) : '';
    }
}
