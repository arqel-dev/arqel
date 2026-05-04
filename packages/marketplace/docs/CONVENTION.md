# Plugin Convention — Arqel Marketplace (MKTPLC-003)

> Esta convention define os metadados que um plugin Arqel precisa expor em
> `composer.json` (e opcionalmente `package.json`) para ser reconhecido pelo
> marketplace, pelo Arqel CLI (`arqel:plugin:list`) e pelo
> `PluginConventionValidator`.

## 1. Schema do `composer.json`

Todo plugin Arqel **deve** seguir o seguinte schema mínimo:

```json
{
    "name": "acme/arqel-stripe-fields",
    "description": "Stripe-aware field types para Arqel.",
    "type": "arqel-plugin",
    "license": "MIT",
    "keywords": ["arqel", "plugin", "stripe", "payments"],
    "extra": {
        "arqel": {
            "plugin-type": "field-pack",
            "compat": {
                "arqel": "^1.0"
            },
            "category": "integrations",
            "installation-instructions": "See README.md"
        }
    }
}
```

### Campos validados

| Campo | Obrigatório | Regra |
|---|---|---|
| `type` | sim | Deve ser exatamente `"arqel-plugin"` |
| `extra.arqel.plugin-type` | sim | Um de: `field-pack`, `widget-pack`, `theme`, `integration`, `language-pack`, `tool` |
| `extra.arqel.compat.arqel` | sim | Constraint semver válida (`^1.0`, `~2.5`, `>=1.0`, `1.2.3`, `^1.0 || ^2.0`) |
| `extra.arqel.category` | sim | String não vazia (ex: `integrations`, `ui`, `i18n`, `devtools`) |
| `extra.arqel.installation-instructions` | recomendado | String com instruções (warning se ausente) |
| `keywords` | recomendado | Deve incluir `"arqel"` e `"plugin"` (warning se faltar) |

## 2. Plugin types

| Tipo | Significado |
|---|---|
| `field-pack` | Pacote de Field types adicionais (ex: Stripe, GeoJSON, RichEditor custom) |
| `widget-pack` | Pacote de dashboard Widgets (charts, KPIs, feeds) |
| `theme` | Tema visual (paleta, tokens, layout) — consumido pelo `arqel-dev/themes` |
| `integration` | Integração com serviço externo (Slack, Sentry, Stripe, Pusher) |
| `language-pack` | Pacote de tradução (locale `xx_YY` com strings UI) |
| `tool` | CLI tool, dev utility, ou helper sem UI |

Plugins que misturam responsabilidades devem escolher o tipo dominante; o
marketplace não permite múltiplos `plugin-type` simultâneos para manter a
filtragem por categoria limpa.

## 3. Schema do `package.json` (opcional)

Quando o plugin distribui também assets npm (componentes React, types
TypeScript), o `package.json` companheiro deve declarar **uma** das duas
formas:

### Forma A: campo `arqel.plugin-type`

```json
{
    "name": "@acme/arqel-stripe-fields",
    "version": "1.0.0",
    "arqel": {
        "plugin-type": "field-pack"
    }
}
```

### Forma B: peerDependency `@arqel-dev/types`

```json
{
    "name": "@acme/arqel-stripe-fields",
    "version": "1.0.0",
    "peerDependencies": {
        "@arqel-dev/types": "^1.0"
    }
}
```

A Forma B é a recomendada para packs que não precisam declarar nada além dos
peers (porque o ecossistema Arqel infere o tipo a partir dos exports).

## 4. Estrutura recomendada de um field-pack

```
acme/arqel-stripe-fields/
├── composer.json            # type=arqel-plugin + extra.arqel
├── package.json             # opcional, peer @arqel-dev/types
├── src/
│   ├── Fields/
│   │   ├── StripeCustomerField.php
│   │   └── StripeSubscriptionField.php
│   └── ServiceProvider.php  # registra Fields no FieldRegistry
├── resources/
│   └── js/
│       └── fields/
│           ├── StripeCustomer.tsx
│           └── StripeSubscription.tsx
├── README.md                # docs + screenshots
└── LICENSE                  # MIT compatível
```

## 5. Autocheck local

Antes de submeter ao marketplace, rode o validator localmente:

```bash
php artisan arqel:plugin:list --validate
```

Saída esperada para um plugin bem formado:

```
+-----------------------------+---------+-------------+--------------+--------+
| Name                        | Version | Plugin Type | Category     | Status |
+-----------------------------+---------+-------------+--------------+--------+
| acme/arqel-stripe-fields    | 1.0.0   | field-pack  | integrations | ok     |
+-----------------------------+---------+-------------+--------------+--------+

acme/arqel-stripe-fields
  [ok] composer_type: Composer type is "arqel-plugin".
  [ok] extra_arqel: extra.arqel object present.
  [ok] plugin_type: Plugin type is "field-pack".
  [ok] compat_arqel: Compat constraint "^1.0" is valid.
  [ok] category: Category is "integrations".
  [ok] installation_instructions: Installation instructions provided.
  [ok] keywords: Keywords include "arqel" and "plugin".
```

`Status` no top-table:

- `ok` — todos os checks passaram sem warnings
- `warn` — passou, mas há recomendações (ex: keywords incompletas)
- `fail` — algum check obrigatório falhou (publicação bloqueada)

## 6. Versionamento e compat

`extra.arqel.compat.arqel` declara o range de versões do core Arqel suportadas.
Quando o core Arqel publica uma major nova, plugins precisam atualizar a
constraint OU continuarão sendo descobertos para a major anterior.

A política de versionamento dos plugins segue **semver** estrito:

- `MAJOR` — break em comportamento (ex: rename de Field, mudança de schema)
- `MINOR` — features compatíveis
- `PATCH` — bugfix sem mudança de API

## 7. Submissão ao marketplace

Após validar localmente:

```bash
php artisan arqel:plugin:list --validate     # status=ok
```

Submeta via API:

```bash
curl -X POST https://arqel.dev/api/marketplace/plugins/submit \
    -H "Authorization: Bearer $TOKEN" \
    -d '{
        "composer_package": "acme/arqel-stripe-fields",
        "github_url": "https://github.com/acme/arqel-stripe-fields",
        "type": "field",
        "name": "Stripe Fields",
        "description": "Stripe customer and subscription field types for Arqel admin panels.",
        "screenshots": ["https://example.com/s1.png"]
    }'
```

A submissão dispara `PluginAutoChecker` (MKTPLC-002) e fica em `status=pending`
até moderação admin (`POST /admin/plugins/{slug}/review`).
