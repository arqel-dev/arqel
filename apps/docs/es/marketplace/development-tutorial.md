# Tutorial de desarrollo

> Guía paso a paso para construir un plugin Arqel desde cero, desde `composer init` hasta la submission al marketplace.

Este tutorial construye un ejemplo concreto y útil: **`acme/stripe-card`**, un field-pack que renderiza Stripe Elements Card como un Field Arqel listo para capturar métodos de pago. El ejemplo cubre cada paso real de un plugin de calidad de producción.

## ¿Por qué este ejemplo?

Stripe es universal — cualquier admin que toque pagos eventualmente necesita captura de tarjeta. El field demuestra:

- Un Field PHP con setters fluidos (`publishableKey`, `captureMode`, `currency`).
- Un componente React asociado que se ship vía un paquete npm separado.
- Integración con un SDK externo (`@stripe/stripe-js`) sin que el paquete PHP shippee assets JS directamente.
- Un service provider que registra a través de `FieldRegistry`.
- Tests Pest + Vitest cubriendo PHP y React.

## Antes de empezar: arqel/* vs plugins del marketplace

El monorepo `arqel-dev/arqel` mantiene los paquetes "core" (`arqel-dev/core`, `arqel-dev/fields`, `arqel-dev/table`, etc.) **mantenidos por los devs del framework**. Viven en `packages/*` y se distribuyen vía splitsh.

Los plugins de la comunidad (incluido el ejemplo de este tutorial) viven en **repositorios separados**, mantenidos por terceros. Dependen de `arqel-dev/*` pero no tienen acceso privilegiado — usan exactamente las mismas APIs públicas que cualquier desarrollador.

La línea:

| Aspecto | Paquete core (`arqel-dev/*`) | Plugin de la comunidad |
|---|---|---|
| Repositorio | Monorepo `arqel-dev/arqel` | Repo standalone del autor |
| Mantenimiento | Equipo Arqel | Autor (comunidad) |
| Distribución | Composer + npm vía splitsh | Composer + npm directamente por el autor |
| Submission al marketplace | No aplica (ya listado oficialmente) | Obligatoria |
| Escaneo de seguridad | Corre en CI interna | Corre en el marketplace antes de publicar |
| Versionado | Sincronizado con releases del framework | Independiente, pero con `compat.arqel` |

Los plugins **no** deben hacer fork del código de paquetes core — siempre extender a través de APIs públicas (`Field`, `Widget`, `Action`, `Resource`).

## Paso 1 — Configuración del composer.json

Crea un nuevo repo `acme/arqel-stripe-card` e inicializa:

```bash
mkdir arqel-stripe-card && cd arqel-stripe-card
composer init --name=acme/stripe-card --type=arqel-plugin
```

Edita `composer.json` a su estado canónico:

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

Puntos críticos:

- **`type: arqel-plugin`** — sin esto, `Composer\InstalledVersions::getInstalledPackagesByType` no puede ver el plugin.
- **`extra.arqel.plugin-type`** — enum requerido (validado por `PluginConventionValidator`).
- **`extra.arqel.compat.arqel`** — constraint semver de la versión del framework soportada.
- **`extra.laravel.providers`** — el auto-discovery de Laravel registra tu provider sin que el usuario toque `config/app.php`.

## Paso 2 — Service provider

Crea `src/StripeCardServiceProvider.php`:

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

`FieldRegistry::register(name, class)` es la API canónica de `arqel-dev/fields` para tipos de field externos. El `name` (`'stripe-card'`) es lo que el Field expone vía `Field::type()` y lo que el lado React usa para resolver el componente.

Y `config/stripe-card.php`:

```php
<?php

declare(strict_types=1);

return [
    'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
    'capture_mode' => env('STRIPE_CAPTURE_MODE', 'automatic'),
    'currency' => env('STRIPE_CURRENCY', 'usd'),
];
```

## Paso 3 — Field PHP

Crea `src/StripeCardField.php`:

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

Convenciones:

- `$type` es el identificador serializado (coincide con el name del `FieldRegistry::register`).
- `$component` es el nombre del componente React que se resuelve en el lado cliente.
- Los setters devuelven `$this` para encadenamiento fluido (idiomático en derivados de `Field`).
- `toArray()` es lo que el Resource serializa al prop Inertia.

Uso típico dentro de un Resource:

```php
StripeCardField::make('payment_method')
    ->publishableKey(config('services.stripe.publishable'))
    ->captureMode('manual')
    ->currency('EUR')
    ->required();
```

## Paso 4 — Paquete npm asociado

El lado React vive en un paquete separado. Crea `package.json`:

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

Ahora `src/StripeCardInput.tsx`:

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

Crea `src/index.ts` con el export agregador:

```ts
import { registerField } from '@arqel-dev/react';

import { StripeCardInput } from './StripeCardInput';

registerField('StripeCardInput', StripeCardInput);

export { StripeCardInput };
```

`registerField(name, component)` es el equivalente JS de `FieldRegistry::register` en el lado PHP — el name debe **coincidir exactamente** con `$component` en `StripeCardField`.

## Paso 5 — Tests

Test PHP (Pest 3 + Orchestra Testbench) en `tests/Unit/StripeCardFieldTest.php`:

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

Test JS (Vitest + Testing Library) en `src/StripeCardInput.test.tsx`:

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

Ejecuta ambos:

```bash
vendor/bin/pest
pnpm test
```

Targets de coverage siguiendo ADR-008: PHP ≥90%, JS ≥80%.

## Paso 6 — README + screenshots

El README (`README.md` en la raíz del repo) debe cubrir como mínimo:

1. **Badges** para versión Packagist + npm + licencia MIT.
2. **Instalación** con snippets `composer require` + `pnpm add`.
3. **Configuración** mostrando `.env` y `config/stripe-card.php`.
4. **Ejemplo de uso** con un snippet real de Resource.
5. **Screenshots** (mínimo 2) — déjalos en `docs/screen-1.png`, `docs/screen-2.png` para que el formulario de submission pueda apuntar a ellos vía `raw.githubusercontent.com`.
6. **Tabla de compatibilidad** con versiones soportadas de Arqel.
7. **Licencia** + DCO.

## Paso 7 — Submission al marketplace

Con Packagist + npm publicados y un release tagged en GitHub (`v0.1.0`), abre `arqel.dev/marketplace/submit` y rellena el formulario. Por detrás:

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

La respuesta `201` devuelve `{plugin: {...}, checks: {...}}`. A partir de ahí, el pipeline descrito en [Publicar plugins](./publishing.md) toma el control — auto-checks, escaneo de seguridad, revisión manual, publicado.

## Iteración futura

Nuevos releases:

```bash
git tag v0.2.0
git push origin v0.2.0
# packagist auto-detecta via webhook; npm publish manual
```

Y para registrar la versión en el marketplace:

```http
POST /api/marketplace/plugins/acme-stripe-card/versions
{
  "version": "0.2.0",
  "changelog": "Fix em currency=EUR, added 3DS support."
}
```

## Anti-patrones comunes

- ❌ **No incluyas assets JS dentro del paquete PHP**. Usa el paquete npm asociado.
- ❌ **No requieras todo `arqel-dev/framework`** — declara solo `arqel-dev/core` + los paquetes que realmente uses.
- ❌ **No uses `setMeta()` crudo** en lugar de construir setters tipados — pierdes DX y rompes el autocomplete.
- ❌ **No llames a `Stripe::setApiKey()` globalmente** dentro del field — filtra estado entre requests.
- ❌ **No fuerces `arqel: ^1` cuando tu plugin necesita la feature `^1.5`** — pon `^1.5` para que falle en `composer require`, no en runtime.

## Próximos pasos

- ¿Primera submission? [Publicar plugins](./publishing.md) cubre el pipeline post-submit.
- ¿Plugin pago? [Pagos y licencias](./payments-and-licensing.md) explica pricing + license keys.
- ¿Quieres evitar rechazos por escaneo de seguridad? [Buenas prácticas de seguridad](./security-best-practices.md) lista los patrones a evitar.
