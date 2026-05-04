# SKILL.md — apps/demo

## Purpose

App de demonstração end-to-end do framework Arqel, no formato de um **blog admin**
showcase (estilo Filament/Nova demo). Integra os principais pacotes do monorepo
num único projeto Laravel + Inertia + React, servindo simultaneamente como:

1. **Showcase público** — rodável local pelo time de marketing/visitantes do site.
2. **Smoke test cross-package** — exercita Resource/Field/Workflow/Versioning/AI/Audit no CI.
3. **Exemplo canônico** — base para tutoriais e screenshots da documentação.

## Status

Fase 1 (MVP) — versão `0.8.0-rc.1`. Stub-friendly: implementações mínimas de Panel,
HasWorkflow e Versionable garantem que o demo bootstrappa em CI sem depender de
APIs ainda em flux nos pacotes core. Substituições por `Arqel\Core\Panel\Panel`,
`Arqel\Workflow\Concerns\HasWorkflow` e `Arqel\Versioning\Concerns\Versionable`
acontecem via aliases conforme cada pacote estabilize sua API pública.

## Conventions

- **Resources** declarativos em `app/Arqel/Resources/`. Cada Resource expõe `$slug`,
  `$model` e `fields(): array<int, array<string, mixed>>`. Quando o `FieldFactory`
  do pacote `arqel/fields` for finalizado, migrar de arrays para chamadas
  `Field::text(...)` sem alterar o consumo no provider.
- **Panel registration** em `app/Providers/ArqelServiceProvider.php` (user-land).
  Sempre usa `Panel::configure('admin')->...` — nenhum acesso direto a singleton.
- **Workflow** definido em `app/States/PostStates.php` como mapa puro
  `from => [allowed...]`. Sem dependência de máquina de estado externa.
- **AI endpoints** retornam stubs determinísticos (`arqel-ai-stub`). Em produção
  o controller delega ao provider configurado em `config/arqel-ai.php`.
- **Tests-first**: `tests/Feature/*Test.php` (Pest 3 + Orchestra Testbench) +
  `resources/js/__tests__/*.test.tsx` (Vitest + Testing Library).

## Anti-patterns

- ❌ Não introduzir TanStack Query / SWR — usar Inertia props (ADR-001 / ADR-016).
- ❌ Não duplicar lógica de Resource em controllers ad-hoc; o painel deve gerar
  routes via `Arqel\Core\Panel\Panel` quando disponível. Os controllers atuais
  (`PostListController`, etc.) servem apenas para validar o cenário até lá.
- ❌ Não persistir secrets de provider AI no repo — usar env (`OPENAI_API_KEY`,
  `ANTHROPIC_API_KEY`) lidos por `config/arqel-ai.php`.
- ❌ Não acoplar o seeder à UI (sem chamadas HTTP) — `DemoSeeder` é puramente
  de domínio.

## Examples

```php
// Registro do painel admin (app/Providers/ArqelServiceProvider.php)
Panel::configure('admin')
    ->path('admin')
    ->resources([
        PostResource::class,
        TagResource::class,
        CategoryResource::class,
    ])
    ->login()
    ->registration()
    ->emailVerification()
    ->passwordReset();
```

```php
// Transição de estado (app/States/PostStates.php)
$post->transitionTo(PostStates::REVIEW, PostStates::allowedFrom($post->state));
```

## Related

- `apps/marketplace/` — irmão público (storefront) com mesmas convenções de stack.
- `packages/core` — fonte do `Panel` e `Resource` reais.
- `packages/fields`, `packages/workflow`, `packages/versioning`, `packages/ai`,
  `packages/audit` — pacotes integrados.
- `PLANNING/05-api-php.md` — contratos PHP que este demo consome.
- `PLANNING/08-fase-1-mvp.md` — tickets de origem.
