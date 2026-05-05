# Development tutorial

> Step-by-step guide to building an Arqel plugin from scratch, from `composer init` to marketplace submission.

This tutorial builds a concrete, useful example: **`acme/stripe-card`**, a field-pack that renders Stripe Elements Card as an Arqel Field ready to capture payment methods. The example covers every real step of a production-grade plugin.

## Why this example?

Stripe is universal — any admin that touches payments eventually needs card capture. The field demonstrates:

- A PHP Field with fluent setters (`publishableKey`, `captureMode`, `currency`).
- A companion React component shipped via a separate npm package.
- Integration with an external SDK (`@stripe/stripe-js`) without the PHP package shipping JS assets directly.
- A service provider registering through `FieldRegistry`.
- Pest + Vitest tests covering both PHP and React.

## Before you start: arqel/* vs marketplace plugins

The `arqel-dev/arqel` monorepo maintains "core" packages (`arqel-dev/core`, `arqel-dev/fields`, `arqel-dev/table`, etc.) **maintained by the framework devs**. They live in `packages/*` and are distributed via splitsh.

Community plugins (including the example in this tutorial) live in **separate repositories**, maintained by third parties. They depend on `arqel-dev/*` but have no privileged access — they use exactly the same public APIs any developer uses.

The line:

| Aspect | Core package (`arqel-dev/*`) | Community plugin |
|---|---|---|
| Repository | Monorepo `arqel-dev/arqel` | Author's standalone repo |
| Maintenance | Arqel team | Author (community) |
| Distribution | Composer + npm via splitsh | Composer + npm directly by the author |
| Marketplace submission | Not applicable (already officially listed) | Mandatory |
| Security scan | Runs in internal CI | Runs in the marketplace before publish |
| Versioning | Synced with framework releases | Independent, but with `compat.arqel` |

Plugins should **not** fork core package code — always extend through public APIs (`Field`, `Widget`, `Action`, `Resource`).

## Step 1 — composer.json setup

Create a new `acme/arqel-stripe-card` repo and initialize:

```bash
mkdir arqel-stripe-card && cd arqel-stripe-card
composer init --name=acme/stripe-card --type=arqel-plugin
```

Edit `composer.json` to its canonical state:

```json
{
  "name": "acme/stripe-card",
  "description": "Stripe Card field for Arqel admin panels.",
  "type": "arqel-plugin",
  "license": "MIT",
  "keywords": ["arqel", "plugin", "field", "stripe", "payments"],
  "require": {
    "php": "^8.3",
    "arqel-dev/core": "^1.0",
    "arqel-dev/fields": "^1.0"
  },
  "require-dev": {
    "pestphp/pest": "^3.0",
    "orchestra/testbench": "^9.0",
    "phpstan/phpstan": "^1.11"
  },
  "autoload": {
    "psr-4": {
      "Acme\\StripeCard\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Acme\\StripeCard\\Tests\\": "tests/"
    }
  },
  "extra": {
    "laravel": {
      "providers": ["Acme\\StripeCard\\StripeCardServiceProvider"]
    },
    "arqel": {
      "plugin-type": "field-pack",
      "category": "integrations",
      "compat": {
        "arqel": "^1.0"
      },
      "installation-instructions": "https://github.com/acme/arqel-stripe-card#installation"
    }
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
```

Critical points:

- **`type: arqel-plugin`** — without it, `Composer\InstalledVersions::getInstalledPackagesByType` cannot see the plugin.
- **`extra.arqel.plugin-type`** — required enum (validated by `PluginConventionValidator`).
- **`extra.arqel.compat.arqel`** — semver constraint of the supported framework version.
- **`extra.laravel.providers`** — Laravel auto-discovery registers your provider without the user touching `config/app.php`.

## Step 2 — Service provider

Create `src/StripeCardServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Acme\StripeCard;

use Arqel\Fields\FieldRegistry;
use Illuminate\Support\ServiceProvider;

final class StripeCardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/stripe-card.php', 'stripe-card');
    }

    public function boot(): void
    {
        FieldRegistry::register('stripe-card', StripeCardField::class);

        $this->publishes([
            __DIR__.'/../config/stripe-card.php' => config_path('stripe-card.php'),
        ], 'stripe-card-config');
    }
}
```

`FieldRegistry::register(name, class)` is the canonical API of `arqel-dev/fields` for external field types. The `name` (`'stripe-card'`) is what the Field exposes via `Field::type()` and what the React side uses to resolve the component.

And `config/stripe-card.php`:

```php
<?php

declare(strict_types=1);

return [
    'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
    'capture_mode' => env('STRIPE_CAPTURE_MODE', 'automatic'),
    'currency' => env('STRIPE_CURRENCY', 'usd'),
];
```

## Step 3 — PHP Field

Create `src/StripeCardField.php`:

```php
<?php

declare(strict_types=1);

namespace Acme\StripeCard;

use Arqel\Fields\Field;

final class StripeCardField extends Field
{
    protected string $type = 'stripe-card';
    protected string $component = 'StripeCardInput';

    public function publishableKey(string $key): self
    {
        $this->meta['publishableKey'] = $key;

        return $this;
    }

    public function captureMode(string $mode): self
    {
        if (! in_array($mode, ['automatic', 'manual'], true)) {
            throw new \InvalidArgumentException("Invalid capture mode: {$mode}");
        }

        $this->meta['captureMode'] = $mode;

        return $this;
    }

    public function currency(string $code): self
    {
        $this->meta['currency'] = strtolower($code);

        return $this;
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'publishableKey' => $this->meta['publishableKey']
                ?? config('stripe-card.publishable_key'),
            'captureMode' => $this->meta['captureMode']
                ?? config('stripe-card.capture_mode'),
            'currency' => $this->meta['currency']
                ?? config('stripe-card.currency'),
        ];
    }
}
```

Conventions:

- `$type` is the serialized identifier (matches the `FieldRegistry::register` name).
- `$component` is the name of the React component that resolves on the client side.
- Setters return `$this` for fluent chaining (idiomatic in `Field` derivatives).
- `toArray()` is what the Resource serializes into the Inertia prop.

Typical use inside a Resource:

```php
StripeCardField::make('payment_method')
    ->publishableKey(config('services.stripe.publishable'))
    ->captureMode('manual')
    ->currency('EUR')
    ->required();
```

## Step 4 — Companion npm package

The React side lives in a separate package. Create `package.json`:

```json
{
  "name": "@acme/arqel-stripe-fields",
  "version": "0.1.0",
  "type": "module",
  "main": "./dist/index.js",
  "module": "./dist/index.js",
  "types": "./dist/index.d.ts",
  "exports": {
    ".": {
      "types": "./dist/index.d.ts",
      "import": "./dist/index.js"
    }
  },
  "files": ["dist"],
  "scripts": {
    "build": "tsup",
    "test": "vitest"
  },
  "keywords": ["arqel", "plugin", "field", "stripe"],
  "peerDependencies": {
    "@arqel-dev/types": "^1.0",
    "@stripe/stripe-js": "^4.0",
    "react": "^19.2"
  },
  "devDependencies": {
    "tsup": "^8.0",
    "vitest": "^2.0"
  },
  "arqel": {
    "plugin-type": "field-pack"
  }
}
```

Now `src/StripeCardInput.tsx`:

```tsx
import type { FieldProps } from '@arqel-dev/types';
import { CardElement, Elements } from '@stripe/react-stripe-js';
import { loadStripe, type Stripe } from '@stripe/stripe-js';
import { useEffect, useMemo, useState } from 'react';

interface StripeCardInputProps extends FieldProps<string | null> {
  publishableKey: string;
  captureMode: 'automatic' | 'manual';
  currency: string;
}

export function StripeCardInput(props: StripeCardInputProps) {
  const [stripe, setStripe] = useState<Stripe | null>(null);

  useEffect(() => {
    loadStripe(props.publishableKey).then(setStripe);
  }, [props.publishableKey]);

  const options = useMemo(
    () => ({ mode: props.captureMode, currency: props.currency }),
    [props.captureMode, props.currency],
  );

  if (!stripe) {
    return <div className="arqel-field-loading">Loading Stripe…</div>;
  }

  return (
    <Elements stripe={stripe} options={options}>
      <div className="arqel-field-stripe-card">
        <label>{props.label}</label>
        <CardElement
          onChange={(event) => {
            if (event.complete) {
              props.onChange(event.elementType);
            }
          }}
        />
        {props.error && <span className="arqel-field-error">{props.error}</span>}
      </div>
    </Elements>
  );
}
```

Create `src/index.ts` with the aggregator export:

```ts
import { registerField } from '@arqel-dev/react';

import { StripeCardInput } from './StripeCardInput';

registerField('StripeCardInput', StripeCardInput);

export { StripeCardInput };
```

`registerField(name, component)` is the JS equivalent of `FieldRegistry::register` on the PHP side — the name must **match exactly** `$component` on `StripeCardField`.

## Step 5 — Tests

PHP test (Pest 3 + Orchestra Testbench) in `tests/Unit/StripeCardFieldTest.php`:

```php
<?php

declare(strict_types=1);

use Acme\StripeCard\StripeCardField;

it('serializes publishable key', function (): void {
    $field = StripeCardField::make('payment_method')
        ->publishableKey('pk_test_123')
        ->captureMode('manual')
        ->currency('EUR');

    expect($field->toArray())
        ->toMatchArray([
            'type' => 'stripe-card',
            'publishableKey' => 'pk_test_123',
            'captureMode' => 'manual',
            'currency' => 'eur',
        ]);
});

it('rejects invalid capture mode', function (): void {
    StripeCardField::make('pm')->captureMode('invalid');
})->throws(InvalidArgumentException::class);

it('falls back to config when key is missing', function (): void {
    config(['stripe-card.publishable_key' => 'pk_from_config']);

    $field = StripeCardField::make('pm');

    expect($field->toArray()['publishableKey'])->toBe('pk_from_config');
});
```

JS test (Vitest + Testing Library) in `src/StripeCardInput.test.tsx`:

```tsx
import { render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { StripeCardInput } from './StripeCardInput';

vi.mock('@stripe/stripe-js', () => ({
  loadStripe: vi.fn().mockResolvedValue({ id: 'mock-stripe' }),
}));

describe('StripeCardInput', () => {
  it('renders loading state initially', () => {
    render(
      <StripeCardInput
        name="pm"
        label="Payment method"
        value={null}
        onChange={() => {}}
        publishableKey="pk_test"
        captureMode="automatic"
        currency="usd"
      />,
    );

    expect(screen.getByText(/Loading Stripe/)).toBeInTheDocument();
  });

  it('renders Elements after stripe loads', async () => {
    render(
      <StripeCardInput
        name="pm"
        label="Card"
        value={null}
        onChange={() => {}}
        publishableKey="pk_test"
        captureMode="manual"
        currency="eur"
      />,
    );

    await waitFor(() => expect(screen.getByText('Card')).toBeInTheDocument());
  });
});
```

Run both:

```bash
vendor/bin/pest
pnpm test
```

Coverage targets following ADR-008: PHP ≥90%, JS ≥80%.

## Step 6 — README + screenshots

The README (`README.md` at the repo root) must cover at minimum:

1. **Badges** for Packagist version + npm + MIT license.
2. **Installation** with `composer require` + `pnpm add` snippets.
3. **Configuration** showing `.env` and `config/stripe-card.php`.
4. **Usage example** with a real Resource snippet.
5. **Screenshots** (minimum 2) — drop them in `docs/screen-1.png`, `docs/screen-2.png` so the submission form can point at them via `raw.githubusercontent.com`.
6. **Compatibility table** with supported Arqel versions.
7. **License** + DCO.

## Step 7 — Submit to the marketplace

With Packagist + npm published and a tagged GitHub release (`v0.1.0`), open `arqel.dev/marketplace/submit` and fill out the form. Behind the scenes:

```http
POST /api/marketplace/plugins/submit
Authorization: Bearer <publisher_sanctum_token>

{
  "composer_package": "acme/stripe-card",
  "npm_package": "@acme/arqel-stripe-fields",
  "github_url": "https://github.com/acme/arqel-stripe-card",
  "type": "field-pack",
  "name": "Stripe Card Field",
  "description": "Renderiza Stripe Elements Card como Field Arqel.",
  "screenshots": [
    "https://raw.githubusercontent.com/acme/arqel-stripe-card/main/docs/screen-1.png"
  ]
}
```

The `201` response returns `{plugin: {...}, checks: {...}}`. From there on, the pipeline described in [Publishing plugins](./publishing.md) takes over — auto-checks, security scan, manual review, published.

## Future iteration

New releases:

```bash
git tag v0.2.0
git push origin v0.2.0
# packagist auto-detecta via webhook; npm publish manual
```

And to register the version with the marketplace:

```http
POST /api/marketplace/plugins/acme-stripe-card/versions
{
  "version": "0.2.0",
  "changelog": "Fix em currency=EUR, added 3DS support."
}
```

## Common anti-patterns

- ❌ **Do not bundle JS assets inside the PHP package**. Use the companion npm package.
- ❌ **Do not require the entire `arqel-dev/arqel`** — declare only `arqel-dev/core` + the packages you actually use.
- ❌ **Do not use raw `setMeta()`** instead of building typed setters — you lose DX and break autocomplete.
- ❌ **Do not call `Stripe::setApiKey()` globally** inside the field — leaks state between requests.
- ❌ **Do not force `arqel: ^1` when your plugin needs feature `^1.5`** — set `^1.5` so it fails at `composer require`, not at runtime.

## Next steps

- First-time submission? [Publishing plugins](./publishing.md) covers the post-submit pipeline.
- Paid plugin? [Payments & licensing](./payments-and-licensing.md) explains pricing + license keys.
- Want to avoid security-scan rejections? [Security best practices](./security-best-practices.md) lists the patterns to avoid.
