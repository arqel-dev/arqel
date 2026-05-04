# arqel-dev/tenant

Multi-tenancy primitives for Arqel — `TenantManager`, resolver, scoped Eloquent traits, and adapters for `stancl/tenancy` + `spatie/laravel-multitenancy`.

## Status

Phase 2 scaffold (TENANT-001). Public API entries land in TENANT-002..015. See [`SKILL.md`](./SKILL.md) for the full contract surface and roadmap.

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
