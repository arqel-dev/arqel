# 04 — Estrutura do Repositório

> Layout físico do monorepo Arqel. Complementa `02-arquitetura.md` (containers) e `03-adrs.md` (ADR-009 monorepo).

## 1. Visão geral

**Repositório único:** `github.com/arqel-dev/arqel`

Layout top-level:

```
arqel/
├── .github/              # Workflows CI, issue templates, CODEOWNERS
├── apps/                 # Apps não-packages (demo, docs)
│   ├── playground/       # Demo Laravel app com Arqel instalado
│   └── docs/             # Docs site (Nextra ou VitePress)
├── packages/             # Packages Composer (PHP)
│   ├── arqel/            # Meta-package arqel-dev/framework
│   ├── core/             # arqel-dev/core
│   ├── fields/           # arqel-dev/fields
│   ├── table/            # arqel-dev/table
│   ├── form/             # arqel-dev/form
│   ├── actions/          # arqel-dev/actions
│   ├── auth/             # arqel-dev/auth
│   ├── nav/              # arqel-dev/nav
│   ├── tenant/           # arqel-dev/tenant (Fase 2)
│   ├── audit/            # arqel-dev/audit (Fase 2)
│   ├── versioning/       # arqel-dev/versioning (Fase 3)
│   ├── workflow/         # arqel-dev/workflow (Fase 3)
│   ├── realtime/         # arqel-dev/realtime (Fase 3)
│   ├── mcp/              # arqel-dev/mcp (Fase 2)
│   └── testing/          # arqel-dev/testing
├── packages-js/          # Packages npm (TypeScript/React)
│   ├── types/            # @arqel-dev/types
│   ├── react/            # @arqel-dev/react (Inertia bindings)
│   ├── hooks/            # @arqel-dev/hooks
│   ├── ui/               # @arqel-dev/ui (structural components)
│   └── fields/           # @arqel-dev/fields (React field components)
├── registry/             # ShadCN CLI v4 registry (arqel.dev/r/*)
│   ├── ui/               # Atomic UI components para CLI distribution
│   ├── layouts/          # Layouts preset
│   └── themes/           # Theme presets
├── docs-content/         # MDX source para site docs
├── examples/             # Exemplo apps demonstrando features
├── scripts/              # Scripts dev (release, lint, etc.)
├── .editorconfig
├── .gitignore
├── CHANGELOG.md
├── CODE_OF_CONDUCT.md
├── CONTRIBUTING.md
├── LICENSE              # MIT
├── README.md
├── SECURITY.md
├── composer.json        # Root composer (monorepo orchestration)
├── package.json         # Root package.json (pnpm workspace)
├── pnpm-workspace.yaml  # pnpm workspace config
├── phpunit.xml.dist     # Root PHPUnit config (all packages)
├── pest.xml             # Root Pest config
├── tsconfig.base.json   # Shared TS config
├── biome.json           # Biome lint+format
├── .php-cs-fixer.php    # Laravel Pint config
├── phpstan.neon         # Larastan config
└── renovate.json        # Renovate bot config
```

## 2. Convenção de packages

### 2.1 Composer packages

Cada package em `packages/<name>/` tem:

```
packages/<name>/
├── composer.json
├── README.md
├── SKILL.md             # AI agents context
├── src/                 # Namespace Arqel\<Name>\
│   └── ...
├── tests/               # Pest tests
│   ├── Feature/
│   └── Unit/
├── config/              # config files publicáveis
├── database/            # migrations publicáveis
│   └── migrations/
├── resources/           # views Blade (apenas layouts Inertia), lang, assets
│   ├── views/
│   └── lang/
└── routes/              # rotas package-owned (se aplicável)
```

### 2.2 npm packages

Cada package em `packages-js/<name>/` tem:

```
packages-js/<name>/
├── package.json
├── README.md
├── SKILL.md
├── tsconfig.json
├── tsup.config.ts       # Build config (tsup ou tsdown)
├── src/
│   ├── index.ts
│   └── ...
├── tests/               # Vitest
└── dist/                # Build output (gitignored)
```

## 3. Packages Composer detalhados

### 3.1 `arqel-dev/arqel` (meta-package)

O que o usuário instala: `composer require arqel-dev/framework`.

**Não tem código próprio** — apenas depende dos sub-packages necessários para instalação completa.

> O meta-package vive em **repo separado** `arqel-dev/framework` (não num
> sub-folder do monorepo) porque `type: metapackage` não tem código — só
> declara `require`.

```json
// arqel-dev/framework — composer.json
{
    "name": "arqel-dev/framework",
    "description": "Arqel framework — meta-package that pulls the full stack.",
    "type": "metapackage",
    "license": "MIT",
    "keywords": ["arqel", "laravel", "admin-panel", "inertia", "react", "shadcn"],
    "homepage": "https://arqel.dev",
    "require": {
        "php": "^8.3",
        "arqel-dev/core": "^0.8.0",
        "arqel-dev/auth": "^0.8.0",
        "arqel-dev/fields": "^0.8.0",
        "arqel-dev/form": "^0.8.0",
        "arqel-dev/actions": "^0.8.0",
        "arqel-dev/nav": "^0.8.0",
        "arqel-dev/table": "^0.8.0",
        "inertiajs/inertia-laravel": "^1.0|^2.0|^3.0"
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

### 3.2 `arqel-dev/core`

O coração do framework — contracts, base classes, service provider.

```json
{
    "name": "arqel-dev/core",
    "description": "Core contracts, service provider, and primitives for Arqel.",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.3",
        "laravel/framework": "^12.0|^13.0",
        "inertiajs/inertia-laravel": "^3.0",
        "spatie/laravel-package-tools": "^1.16"
    },
    "autoload": {
        "psr-4": { "Arqel\\Core\\": "src/" }
    }
}
```

Estrutura `packages/core/src/`:

```
src/
├── ArqelServiceProvider.php
├── Arqel.php                         # Facade class
├── Contracts/
│   ├── HasResource.php
│   ├── HasFields.php
│   ├── HasActions.php
│   ├── HasPolicies.php
│   └── Renderable.php
├── Resources/
│   ├── Resource.php                  # Base class abstrata
│   ├── ResourceRegistry.php
│   └── ResourceController.php
├── Http/
│   ├── Controllers/
│   │   ├── ResourceController.php
│   │   ├── ActionController.php
│   │   └── DashboardController.php
│   └── Middleware/
│       ├── HandleArqelInertia.php
│       └── ScopedForPanel.php
├── Panel/
│   ├── Panel.php
│   ├── PanelRegistry.php
│   └── PanelBuilder.php
├── Concerns/                         # Traits
│   ├── HasFieldSchema.php
│   ├── HasActions.php
│   └── ...
├── Commands/
│   ├── InstallCommand.php
│   ├── MakeResourceCommand.php
│   ├── MakeFieldCommand.php
│   ├── MakeActionCommand.php
│   └── PublishCommand.php
├── Facades/
│   └── Arqel.php
└── Support/
    ├── FieldSchemaSerializer.php
    └── InertiaDataBuilder.php
```

### 3.3 `arqel-dev/fields`

Catálogo de field types.

```
packages/fields/src/
├── Field.php                         # Base abstract
├── Concerns/                         # Shared traits
│   ├── HasValidation.php
│   ├── HasVisibility.php
│   ├── HasDependencies.php
│   └── HasAuthorization.php
├── Types/
│   ├── TextField.php
│   ├── TextareaField.php
│   ├── NumberField.php
│   ├── CurrencyField.php
│   ├── BooleanField.php
│   ├── ToggleField.php
│   ├── SelectField.php
│   ├── MultiSelectField.php
│   ├── RadioField.php
│   ├── EmailField.php
│   ├── UrlField.php
│   ├── PasswordField.php
│   ├── SlugField.php
│   ├── DateField.php
│   ├── DateTimeField.php
│   ├── BelongsToField.php
│   ├── HasManyField.php
│   ├── FileField.php
│   ├── ImageField.php
│   ├── ColorField.php
│   └── HiddenField.php
├── FieldFactory.php                  # Static fluent API: Field::text(...)
└── ValidationBridge.php              # Laravel rules → Zod schema
```

### 3.4 `arqel-dev/table`

```
packages/table/src/
├── Table.php                         # Main table builder
├── Column.php                        # Base column
├── Columns/
│   ├── TextColumn.php
│   ├── BadgeColumn.php
│   ├── BooleanColumn.php
│   ├── DateColumn.php
│   ├── ImageColumn.php
│   ├── IconColumn.php
│   ├── ComputedColumn.php
│   └── RelationshipColumn.php
├── Filters/
│   ├── Filter.php
│   ├── SelectFilter.php
│   ├── DateRangeFilter.php
│   ├── TextFilter.php
│   ├── TernaryFilter.php
│   └── QueryBuilderFilter.php        # Visual query builder (Fase 2)
├── Concerns/
│   ├── HasSorting.php
│   ├── HasSearching.php
│   └── HasGrouping.php
└── TableQueryBuilder.php             # Eloquent eager loading detection
```

### 3.5 `arqel-dev/form`

```
packages/form/src/
├── Form.php                          # Main form builder
├── FormSchema.php                    # Ordered fields + layout
├── Layout/
│   ├── Section.php
│   ├── Fieldset.php
│   ├── Grid.php
│   ├── Columns.php
│   ├── Group.php
│   ├── Tabs.php
│   └── Wizard.php                    # Multi-step (Fase 2)
├── Components/
│   ├── Repeater.php                  # Fase 2
│   └── Builder.php                   # Fase 2
└── FormRequestGenerator.php          # Auto-gen FormRequest classes
```

### 3.6 `arqel-dev/actions`

```
packages/actions/src/
├── Action.php                        # Base abstract
├── Types/
│   ├── RowAction.php
│   ├── BulkAction.php
│   ├── ToolbarAction.php
│   ├── HeaderAction.php              # Detail page header
│   └── StandaloneAction.php
├── Concerns/
│   ├── Confirmable.php
│   ├── HasForm.php                   # Actions with form modals
│   ├── HasAuthorization.php
│   └── HasQueuing.php                # Background actions
├── ActionExecutor.php                # Queue integration
└── BulkActionJob.php                 # Laravel Job base
```

### 3.7 `arqel-dev/auth`

```
packages/auth/src/
├── PolicyDiscovery.php               # Auto-register policies
├── AbilityRegistry.php
├── ArqelGate.php                     # Wrapper around Laravel Gate
└── Concerns/
    └── AuthorizesRequests.php
```

### 3.8 `arqel-dev/nav`

```
packages/nav/src/
├── Navigation.php                    # Fluent builder
├── NavigationItem.php
├── NavigationGroup.php
├── NavigationRegistry.php
└── BreadcrumbsBuilder.php
```

### 3.9 `arqel-dev/tenant` (Fase 2)

```
packages/tenant/src/
├── TenantManager.php
├── TenantResolver.php                # Subdomain, path, header, session
├── Concerns/
│   └── BelongsToTenant.php           # Eloquent trait
├── Scopes/
│   └── TenantScope.php               # Eloquent global scope
├── Middleware/
│   └── ResolveTenantMiddleware.php
└── Integrations/
    ├── StanclAdapter.php             # stancl/tenancy
    └── SpatieAdapter.php             # spatie/laravel-multitenancy
```

### 3.10 `arqel-dev/audit` (Fase 2)

```
packages/audit/src/
├── ActivityTracker.php               # Wraps spatie/laravel-activitylog
├── Concerns/
│   └── LogsActivity.php
└── Http/
    └── Controllers/
        └── ActivityLogController.php
```

### 3.11 `arqel-dev/versioning` (Fase 3)

```
packages/versioning/src/
├── Versionable.php                   # Trait
├── Version.php                       # Model
├── VersionManager.php
└── RestoreAction.php
```

### 3.12 `arqel-dev/workflow` (Fase 3)

Wraps `spatie/laravel-model-states`.

```
packages/workflow/src/
├── WorkflowManager.php
├── Concerns/
│   └── HasWorkflow.php
└── Components/
    └── StateTransitionField.php
```

### 3.13 `arqel-dev/realtime` (Fase 3)

```
packages/realtime/src/
├── RealtimeServiceProvider.php
├── Channels/
│   ├── ResourceChannel.php
│   └── ActionProgressChannel.php
├── Events/
│   ├── ResourceUpdated.php
│   ├── ActionStarted.php
│   ├── ActionProgress.php
│   └── ActionCompleted.php
└── Broadcasting/
    └── ReverbIntegration.php
```

### 3.14 `arqel-dev/mcp` (Fase 2)

```
packages/mcp/src/
├── McpServer.php                     # Main server
├── Tools/
│   ├── ListResourcesTool.php
│   ├── DescribeResourceTool.php
│   ├── ListFieldsTool.php
│   ├── GenerateResourceTool.php
│   └── RunTestTool.php
├── Resources/                        # MCP "resources" (different concept)
│   ├── ArqelSkillResource.php
│   └── ConfigResource.php
├── Prompts/
│   ├── MigrationPrompt.php
│   └── ReviewResourcePrompt.php
└── Commands/
    └── ServeMcpCommand.php           # php artisan arqel:mcp
```

### 3.15 `arqel-dev/testing`

```
packages/testing/src/
├── ArqelTestCase.php                 # Extends Orchestra TestCase
├── Concerns/
│   ├── CreatesResources.php
│   ├── AssertsArqelPages.php
│   └── InteractsWithFields.php
├── Helpers/
│   ├── actingAsAdmin.php
│   ├── visitResource.php
│   └── submitForm.php
└── Fixtures/
    └── TestModels/                   # User, Post, etc. para tests
```

## 4. Packages npm detalhados

### 4.1 `@arqel-dev/types`

Types TypeScript partilhados.

```
packages-js/types/src/
├── index.ts
├── resources.ts                      # ResourceMeta, RecordType, etc.
├── fields.ts                         # FieldSchema, FieldType, etc.
├── tables.ts
├── forms.ts
├── actions.ts
├── nav.ts
├── inertia.ts                        # SharedProps, PageProps
└── utils.ts
```

`package.json`:

```json
{
    "name": "@arqel-dev/types",
    "version": "0.0.0",
    "type": "module",
    "exports": {
        ".": {
            "types": "./dist/index.d.ts",
            "import": "./dist/index.js"
        }
    },
    "types": "./dist/index.d.ts",
    "main": "./dist/index.js",
    "files": ["dist"],
    "peerDependencies": {}
}
```

### 4.2 `@arqel-dev/react`

Inertia bindings + utilities.

```
packages-js/react/src/
├── index.ts
├── inertia/
│   ├── createInertiaApp.ts           # Wraps Inertia's createInertiaApp
│   ├── resolvePage.ts
│   └── layoutResolver.ts
├── providers/
│   ├── ArqelProvider.tsx             # Root context provider
│   └── ThemeProvider.tsx
├── context/
│   ├── PanelContext.tsx
│   ├── ResourceContext.tsx
│   └── TenantContext.tsx
└── utils/
    ├── route.ts
    ├── translate.ts
    └── serializeFields.ts
```

### 4.3 `@arqel-dev/hooks`

React hooks.

```
packages-js/hooks/src/
├── index.ts
├── useResource.ts                    # Inertia page data → typed Resource
├── useArqelForm.ts                   # Wraps Inertia useForm
├── useCanAccess.ts                   # Authorization helper
├── useFieldDependencies.ts           # dependsOn handling
├── useTable.ts                       # Table state (filters, sort, selection)
├── useAction.ts                      # Action invocation
├── useFlash.ts                       # Inertia flash messages
├── useNavigation.ts
├── useBreakpoint.ts
└── useOptimistic.ts                  # React 19.2 helper
```

### 4.4 `@arqel-dev/ui`

Structural components.

```
packages-js/ui/src/
├── index.ts
├── shell/
│   ├── AppShell.tsx
│   ├── Sidebar.tsx
│   ├── Topbar.tsx
│   ├── MainContent.tsx
│   └── Footer.tsx
├── resource/
│   ├── ResourceIndex.tsx             # Table page
│   ├── ResourceCreate.tsx
│   ├── ResourceDetail.tsx
│   └── ResourceEdit.tsx
├── table/
│   ├── DataTable.tsx
│   ├── TableHeader.tsx
│   ├── TableRow.tsx
│   ├── TableFilters.tsx
│   ├── TablePagination.tsx
│   └── TableToolbar.tsx
├── form/
│   ├── FormRenderer.tsx
│   ├── FieldRenderer.tsx
│   ├── FormSection.tsx
│   └── FormActions.tsx
├── action/
│   ├── ActionModal.tsx
│   ├── ConfirmDialog.tsx
│   └── ActionProgressToast.tsx
├── widgets/                          # Fase 2
│   ├── StatCard.tsx
│   ├── ChartCard.tsx
│   └── TableCard.tsx
├── dashboard/                        # Fase 2
│   └── DashboardGrid.tsx
├── auth/
│   └── CanAccess.tsx                 # <CanAccess ability="users.create">
├── flash/
│   ├── FlashContainer.tsx
│   └── FlashToast.tsx
└── styles/
    └── globals.css                   # Tailwind base imports
```

### 4.5 `@arqel-dev/fields`

Field React components (uma por field type).

```
packages-js/fields/src/
├── index.ts
├── registry.ts                       # FieldRegistry singleton
├── text/
│   ├── TextInput.tsx
│   ├── TextareaInput.tsx
│   ├── PasswordInput.tsx
│   ├── EmailInput.tsx
│   └── UrlInput.tsx
├── number/
│   ├── NumberInput.tsx
│   └── CurrencyInput.tsx
├── boolean/
│   ├── Checkbox.tsx
│   └── Toggle.tsx
├── select/
│   ├── SelectInput.tsx
│   ├── MultiSelectInput.tsx
│   └── RadioGroup.tsx
├── date/
│   ├── DateInput.tsx
│   └── DateTimeInput.tsx
├── relationship/
│   ├── BelongsToInput.tsx
│   └── HasManyReadonly.tsx
├── file/
│   ├── FileInput.tsx
│   └── ImageInput.tsx
├── slug/
│   └── SlugInput.tsx
├── color/
│   └── ColorInput.tsx
└── hidden/
    └── HiddenInput.tsx
```

## 5. Registry ShadCN CLI v4

Distribuição de componentes atómicos via `https://arqel.dev/r/<name>.json`.

```
registry/
├── ui/                               # Atomic components
│   ├── button.json                   # → arqel.dev/r/button.json
│   ├── input.json
│   ├── select.json
│   ├── dialog.json
│   ├── command.json                  # Command palette
│   ├── toast.json
│   ├── dropdown-menu.json
│   ├── badge.json
│   ├── card.json
│   ├── table.json                    # Base table primitives
│   └── ...
├── layouts/
│   ├── admin-shell.json              # Full AppShell preset
│   ├── centered-form.json
│   └── dashboard-grid.json
└── themes/
    ├── arqel-default.json            # Default Arqel theme
    ├── arqel-dark.json
    └── arqel-minimal.json
```

Cada arquivo `.json` segue shadcn registry schema.

## 6. Apps

### 6.1 `apps/playground/`

Laravel 12 fresh install + Arqel + sample Resources. Usado para:
- E2E tests com Playwright
- Demo público em playground.arqel.dev
- Smoke test de release

```
apps/playground/
├── app/
│   ├── Models/                       # Post, User, Category, ...
│   ├── Arqel/Resources/              # UserResource, PostResource
│   └── Policies/
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/js/
│   ├── app.tsx                       # Entry point Inertia + Arqel
│   ├── components/ui/                # ShadCN components (user-owned)
│   └── pages/
├── routes/
│   └── web.php
├── composer.json                     # Depends on path:../../packages/*
├── package.json                      # Depends on workspace:../packages-js/*
└── vite.config.ts
```

### 6.2 `apps/docs/`

Site público de documentação.

```
apps/docs/
├── app/                              # Next.js App Router
├── content/                          # MDX content
├── package.json
└── next.config.mjs
```

Ou alternativamente VitePress — decisão em ticket DOCS-001.

## 7. Orquestração do monorepo

### 7.1 pnpm workspace

```yaml
# pnpm-workspace.yaml
packages:
  - "packages-js/*"
  - "apps/*"
```

### 7.2 Composer path repositories

```json
// Root composer.json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/*",
            "options": { "symlink": true }
        }
    ],
    "require-dev": {
        "arqel-dev/core": "*",
        "arqel-dev/fields": "*",
        "arqel-dev/table": "*"
    }
}
```

### 7.3 Script de release

`scripts/release.mjs`:

```
1. Bump version em todos composer.json e package.json (coordinated)
2. Git tag vX.Y.Z
3. Split monorepo para sub-repos Packagist (git subtree push)
4. Publish npm packages (pnpm publish --recursive)
5. Generate CHANGELOG via conventional-commits
6. Create GitHub release
```

Packagist split via tool como `splitsh/lite` ou action equivalente.

## 8. CI/CD layout

`.github/workflows/`:

```
├── ci.yml                            # PR checks (lint, test, types)
├── test-matrix.yml                   # PHP 8.3, 8.4 × Laravel 12, 13 × MySQL, Postgres
├── e2e.yml                           # Playwright against playground
├── release.yml                       # Tag push → release
├── docs-deploy.yml                   # Deploy docs.arqel.dev
└── security.yml                      # CodeQL + dependency scanning
```

## 9. Versioning

**Lockstep versioning:** todos os packages Arqel partilham a mesma versão.

Ex: `v0.3.0` → todos os Composer e npm packages ficam em `0.3.0`.

Vantagens:
- Simples de raciocinar
- Composer constraints triviais (`arqel-dev/core: self.version`)
- Matches Laravel model

Desvantagens:
- Bump de um package força bump de todos (OK — releases mensais)

## 10. Convenções de arquivos

- **Namespaces PHP:** `Arqel\<Subpackage>\` (ex: `Arqel\Fields\Types\TextField`)
- **TypeScript modules:** paths absolutos via `tsconfig.paths`: `@arqel-dev/ui`, `@arqel-dev/hooks`, etc.
- **Indentação:** 4 spaces PHP, 2 spaces TS/JS/YAML/JSON
- **Line endings:** LF (enforced via `.gitattributes`)
- **Encoding:** UTF-8
- **Max line length:** 120 chars (guide, não hard)

## 11. SKILL.md por package

Cada package (Composer e npm) tem `SKILL.md` no root:

```markdown
# <Package Name> — AI Agent Skill

## Purpose
One paragraph explaining what the package does.

## Key contracts
- `Arqel\Fields\Field::text(string $name)` — fluent API entry point
- ...

## Common tasks
- Adding a new field type → see X
- Customizing field rendering → see Y

## Anti-patterns
- Don't X
- Don't Y

## Links
- Source: packages/fields/src/
- Docs: https://arqel.dev/docs/fields
- MCP tool: mcp__arqel__generate_field
```

Este arquivo é lido por Claude Code, Cursor via MCP e por developers. Fonte canónica de contexto AI.

## 12. Próximos documentos

- **`05-api-php.md`** — contratos PHP detalhados (Resource, Field, Action classes).
- **`06-api-react.md`** — contratos TypeScript e componentes React.
