<?php

declare(strict_types=1);

namespace App\Arqel;

/**
 * Stub leve de Panel — em produção este builder vem de `Arqel\Core\Panel\Panel`.
 *
 * Mantém um registry estático para permitir testes do bootstrap do demo
 * sem depender da implementação completa do core durante o setup do worktree.
 */
final class Panel
{
    /** @var array<string, self> */
    private static array $panels = [];

    public string $id;

    public string $path = '/';

    /** @var array<int, class-string> */
    public array $resources = [];

    public bool $login = false;

    public bool $registration = false;

    public bool $emailVerification = false;

    public bool $passwordReset = false;

    private function __construct(string $id)
    {
        $this->id = $id;
    }

    public static function configure(string $id): self
    {
        $panel = new self($id);
        self::$panels[$id] = $panel;

        return $panel;
    }

    public static function get(string $id): ?self
    {
        return self::$panels[$id] ?? null;
    }

    /** @return array<string, self> */
    public static function all(): array
    {
        return self::$panels;
    }

    public static function flush(): void
    {
        self::$panels = [];
    }

    public function path(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @param array<int, class-string> $resources
     */
    public function resources(array $resources): self
    {
        $this->resources = $resources;

        return $this;
    }

    public function login(): self
    {
        $this->login = true;

        return $this;
    }

    public function registration(): self
    {
        $this->registration = true;

        return $this;
    }

    public function emailVerification(): self
    {
        $this->emailVerification = true;

        return $this;
    }

    public function passwordReset(): self
    {
        $this->passwordReset = true;

        return $this;
    }
}
