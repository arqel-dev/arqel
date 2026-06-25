# arqel-dev/tenant

Multi-tenancy primitives for Arqel — `TenantManager`, resolver, scoped Eloquent traits, and adapters for `stancl/tenancy` + `spatie/laravel-multitenancy`.

## Status

Shipped. The public API is implemented: `TenantManager` singleton, 5 concrete resolvers (subdomain, path, header, session, auth-user), `BelongsToTenant` trait + global scope, `ScopedUnique` rule, adapters for `stancl/tenancy` + `spatie/laravel-multitenancy`, scaffolders, white-labeling theme, and feature gates. See [`SKILL.md`](./SKILL.md) for the full contract surface.

## Install

In a Laravel app already running `arqel-dev/core`:

```bash
composer require arqel-dev/tenant
```

The service provider is auto-discovered.

## Tests

```bash
composer test
```
