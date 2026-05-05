# Tutorial de desenvolvimento

> Passo a passo para criar um plugin Arqel do zero, do `composer init` à submissão no marketplace.

Este tutorial constrói um exemplo concreto e útil: **`acme/stripe-card`**, um field-pack que renderiza o Stripe Elements Card como um Field do Arqel pronto para capturar payment methods. O exemplo cobre todas as etapas reais de um plugin de produção.

## Por que este exemplo?

Stripe é universal — qualquer admin que toca em pagamentos eventualmente precisa de captura de cartão. O field demonstra:

- Field PHP com setters fluent (`publishableKey`, `captureMode`, `currency`).
- Component React companion via npm package separado.
- Integração com SDK externo (`@stripe/stripe-js`) sem que o pacote PHP carregue assets JS direto.
- Service provider registrando via `FieldRegistry`.
- Tests Pest + Vitest cobrindo PHP + React.

## Antes de começar: arqel/* vs marketplace plugins

O monorepo `arqel-dev/arqel` mantém pacotes "core" (`arqel-dev/core`, `arqel-dev/fields`, `arqel-dev/table`, etc) que são **maintained pelos devs do framework**. Eles vivem em `packages/*` e são distribuídos via splitsh.

Plugins community (incluindo o exemplo deste tutorial) vivem em **repositórios separados**, mantidos por terceiros. Eles dependem de `arqel-dev/*` mas não têm acesso privilegiado — usam exatamente as mesmas APIs públicas que qualquer dev usa.

A linha:

| Aspecto | Pacote core (`arqel-dev/*`) | Plugin community |
|---|---|---|
| Repositório | Monorepo `arqel-dev/arqel` | Repo standalone do autor |
| Manutenção | Time Arqel | Autor (community) |
| Distribuição | Composer + npm via splitsh | Composer + npm direto pelo autor |
| Submissão ao marketplace | Não aplicável (já listado oficialmente) | Obrigatória |
| Security scan | Roda no CI interno | Roda no marketplace antes do publish |
| Versionamento | Sincronizado com framework releases | Independente, mas com `compat.arqel` |

Plugins **não** devem fork código de pacotes core — sempre estendam via APIs públicas (`Field`, `Widget`, `Action`, `Resource`).

## Step 1 — Setup composer.json

Crie um repo novo `acme/arqel-stripe-card` e inicialize:

```bash
mkdir arqel-stripe-card && cd arqel-stripe-card
composer init --name=acme/stripe-card --type=arqel-plugin
```

Edite `composer.json` para o estado canônico:

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

Os pontos críticos:

- **`type: arqel-plugin`** — sem isso o `Composer\InstalledVersions::getInstalledPackagesByType` não enxerga o plugin.
- **`extra.arqel.plugin-type`** — enum obrigatória (validada por `PluginConventionValidator`).
- **`extra.arqel.compat.arqel`** — constraint semver da versão do framework suportada.
- **`extra.laravel.providers`** — auto-discovery do Laravel registra seu provider sem o usuário precisar tocar `config/app.php`.

## Step 2 — Service provider

Crie `src/StripeCardServiceProvider.php`:

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

A `FieldRegistry::register(name, class)` é a API canônica de `arqel-dev/fields` para field types externos. O `name` (`'stripe-card'`) é o que o Field expõe via `Field::type()` e o que o React side usa para resolver o componente.

E `config/stripe-card.php`:

```php
<?php

declare(strict_types=1);

return [
    'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
    'capture_mode' => env('STRIPE_CAPTURE_MODE', 'automatic'),
    'currency' => env('STRIPE_CURRENCY', 'usd'),
];
```

## Step 3 — Field PHP

Crie `src/StripeCardField.php`:

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

Convenções:

- `$type` é o identificador serializado (matches `FieldRegistry::register` name).
- `$component` é o nome do React component que vai resolver no client side.
- Setters retornam `$this` para chaining fluent (idiomatic em `Field` derivativas).
- `toArray()` é o que o Resource serializa para o Inertia prop.

Uso típico em um Resource:

```php
StripeCardField::make('payment_method')
    ->publishableKey(config('services.stripe.publishable'))
    ->captureMode('manual')
    ->currency('EUR')
    ->required();
```

## Step 4 — NPM package companion

O lado React vive em pacote separado. Crie `package.json`:

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

Agora `src/StripeCardInput.tsx`:

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

Crie `src/index.ts` com export agregador:

```ts
import { registerField } from '@arqel-dev/react';

import { StripeCardInput } from './StripeCardInput';

registerField('StripeCardInput', StripeCardInput);

export { StripeCardInput };
```

`registerField(name, component)` é o equivalente JS do `FieldRegistry::register` PHP — o name precisa **bater exatamente** com `$component` do `StripeCardField`.

## Step 5 — Tests

PHP test (Pest 3 + Orchestra Testbench) em `tests/Unit/StripeCardFieldTest.php`:

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

JS test (Vitest + Testing Library) em `src/StripeCardInput.test.tsx`:

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

Rode os dois:

```bash
vendor/bin/pest
pnpm test
```

Coverage targets seguindo ADR-008: PHP ≥90%, JS ≥80%.

## Step 6 — README + screenshots

O README (`README.md` na raiz do repo) precisa cobrir mínimo:

1. **Badge** de versão Packagist + npm + license MIT.
2. **Installation** com snippet de `composer require` + `pnpm add`.
3. **Configuration** mostrando `.env` e `config/stripe-card.php`.
4. **Usage example** com snippet de Resource real.
5. **Screenshots** (mínimo 2) — coloque em `docs/screen-1.png`, `docs/screen-2.png` para que o submission form possa apontar para elas via `raw.githubusercontent.com`.
6. **Compatibility table** com Arqel versions suportadas.
7. **License** + DCO.

## Step 7 — Submeter ao marketplace

Com Packagist + npm publicados e GitHub release tagueada (`v0.1.0`), abra `arqel.dev/marketplace/submit` e preencha o form. Por trás dos panos:

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

A resposta `201` traz `{plugin: {...}, checks: {...}}`. A partir daí o pipeline descrito em [Publicando plugins](./publishing.md) toma conta — auto-checks, security scan, manual review, published.

## Iteração futura

Releases novas:

```bash
git tag v0.2.0
git push origin v0.2.0
# packagist auto-detecta via webhook; npm publish manual
```

E para registrar a versão no marketplace:

```http
POST /api/marketplace/plugins/acme-stripe-card/versions
{
  "version": "0.2.0",
  "changelog": "Fix em currency=EUR, added 3DS support."
}
```

## Anti-patterns comuns

- ❌ **Não embarque assets JS no pacote PHP**. Use companion npm package.
- ❌ **Não require `arqel-dev/framework` inteiro** — declare apenas `arqel-dev/core` + os pacotes que você de fato usa.
- ❌ **Não use `setMeta()` raw** ao invés de criar setters tipados — perde DX e quebra autocomplete.
- ❌ **Não chame `Stripe::setApiKey()` global** dentro do field — vaza estado entre requests.
- ❌ **Não force `arqel: ^1` quando seu plugin precisa de feature `^1.5`** — coloque `^1.5` para falhar no `composer require`, não em runtime.

## Próximos passos

- Submeter pela primeira vez? [Publicando plugins](./publishing.md) cobre o pipeline post-submit.
- Plugin pago? [Pagamentos & licenças](./payments-and-licensing.md) explica pricing + license keys.
- Quer evitar reprovação por security scan? [Boas práticas de segurança](./security-best-practices.md) lista patterns a evitar.
