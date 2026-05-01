<?php

declare(strict_types=1);

namespace Arqel\Core\Panel;

use Arqel\Core\Contracts\HasResource;

/**
 * Fluent builder for an admin panel definition.
 *
 * A panel groups together a path, branding, theme, middleware, and
 * the set of Resources/Widgets it exposes. Multiple panels can coexist
 * in the same application (e.g. /admin for staff, /customer for clients).
 *
 * The builder is mutable and chainable: each setter returns $this so
 * the configuration can be expressed in one expression. Routes are not
 * registered here — that lands in CORE-006 once the generic
 * ResourceController exists.
 */
final class Panel
{
    private string $path = '/admin';

    private string $brandName = 'Arqel';

    private ?string $brandLogo = null;

    private string $theme = 'default';

    private string $primaryColor = '#0f172a';

    private bool $darkMode = false;

    /** @var array<int, string> */
    private array $middleware = ['web'];

    /** @var array<int, class-string<HasResource>> */
    private array $resources = [];

    /** @var array<int, class-string> */
    private array $widgets = [];

    /** @var array<int, string> */
    private array $navigationGroups = [];

    private string $authGuard = 'web';

    private ?string $tenantScope = null;

    private bool $loginEnabled = false;

    private string $loginUrl = '/admin/login';

    private string $afterLoginUrl = '/admin';

    private bool $registrationEnabled = false;

    private bool $defaultAuth = true;

    public function __construct(public readonly string $id) {}

    /**
     * Enable Arqel's bundled Inertia-React login/logout pages.
     */
    public function login(bool $enabled = true): self
    {
        $this->loginEnabled = $enabled;

        return $this;
    }

    public function loginUrl(string $url = '/admin/login'): self
    {
        $this->loginUrl = '/'.ltrim($url, '/');

        return $this;
    }

    public function afterLoginRedirectTo(string $url = '/admin'): self
    {
        $this->afterLoginUrl = '/'.ltrim($url, '/');

        return $this;
    }

    public function registration(bool $enabled = true): self
    {
        $this->registrationEnabled = $enabled;

        return $this;
    }

    /**
     * Opt-out of Arqel's bundled login routes (use Breeze/Jetstream instead).
     */
    public function withoutDefaultAuth(): self
    {
        $this->defaultAuth = false;
        $this->loginEnabled = false;

        return $this;
    }

    public function loginEnabled(): bool
    {
        return $this->loginEnabled && $this->defaultAuth;
    }

    public function getLoginUrl(): string
    {
        return $this->loginUrl;
    }

    public function getAfterLoginUrl(): string
    {
        return $this->afterLoginUrl;
    }

    public function registrationEnabled(): bool
    {
        return $this->registrationEnabled;
    }

    public function defaultAuthEnabled(): bool
    {
        return $this->defaultAuth;
    }

    public function path(string $path): self
    {
        $this->path = '/'.ltrim($path, '/');

        return $this;
    }

    public function brand(string $name, ?string $logo = null): self
    {
        $this->brandName = $name;
        $this->brandLogo = $logo;

        return $this;
    }

    public function theme(string $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    public function primaryColor(string $color): self
    {
        $this->primaryColor = $color;

        return $this;
    }

    public function darkMode(bool $enabled = true): self
    {
        $this->darkMode = $enabled;

        return $this;
    }

    /**
     * @param array<int, string> $middleware
     */
    public function middleware(array $middleware): self
    {
        $this->middleware = array_values($middleware);

        return $this;
    }

    /**
     * @param array<int, class-string<HasResource>> $resources
     */
    public function resources(array $resources): self
    {
        $this->resources = array_values($resources);

        return $this;
    }

    /**
     * @param array<int, class-string> $widgets
     */
    public function widgets(array $widgets): self
    {
        $this->widgets = array_values($widgets);

        return $this;
    }

    /**
     * @param array<int, string> $groups
     */
    public function navigationGroups(array $groups): self
    {
        $this->navigationGroups = array_values($groups);

        return $this;
    }

    public function authGuard(string $guard): self
    {
        $this->authGuard = $guard;

        return $this;
    }

    public function tenant(?string $scope = null): self
    {
        $this->tenantScope = $scope;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return array{name: string, logo: string|null}
     */
    public function getBrand(): array
    {
        return [
            'name' => $this->brandName,
            'logo' => $this->brandLogo,
        ];
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function getPrimaryColor(): string
    {
        return $this->primaryColor;
    }

    public function isDarkModeEnabled(): bool
    {
        return $this->darkMode;
    }

    /**
     * @return array<int, string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * @return array<int, class-string<HasResource>>
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * @return array<int, class-string>
     */
    public function getWidgets(): array
    {
        return $this->widgets;
    }

    /**
     * @return array<int, string>
     */
    public function getNavigationGroups(): array
    {
        return $this->navigationGroups;
    }

    public function getAuthGuard(): string
    {
        return $this->authGuard;
    }

    public function getTenantScope(): ?string
    {
        return $this->tenantScope;
    }
}
