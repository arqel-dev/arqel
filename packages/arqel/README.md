# arqel-dev/arqel

Meta-package that requires the full Arqel stack:

- `arqel-dev/core` — panels, resources, polymorphic routes, Inertia middleware, command palette, telemetry
- `arqel-dev/auth` — bundled login / register / forgot / reset / verify-email pages
- `arqel-dev/fields` — field schema types
- `arqel-dev/form` — form rendering server-side
- `arqel-dev/actions` — action contracts + invokers
- `arqel-dev/nav` — navigation builder
- `arqel-dev/table` — table query/sort/filter/paginate
- `inertiajs/inertia-laravel` — required peer

## Install

```bash
composer require arqel-dev/framework
php artisan arqel:install
```

The install command scaffolds providers, middleware, Blade root, Vite config, JS app entry, the `UserResource` example, and publishes the hero illustration. See [https://arqel.dev/docs/install](https://arqel.dev/docs/install) for details.

## License

MIT.
