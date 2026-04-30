# Guias de migração para Arqel

Bem-vindo aos guias de migração. Esta pasta cobre como portar um admin panel
existente para Arqel, mantendo features paridade onde possível e marcando
explicitamente as quebras de paradigma (sobretudo Livewire → Inertia/React).

> **Escopo atual (v0.8 RC):** narrativa de migração + 25+ mapeamentos de API
> prontos para copiar. Migration scripts (parser de Resource Filament/Nova
> para Arqel) e video walkthroughs ficam para v0.9.

## Guias disponíveis

- [`from-filament.md`](./from-filament.md) — Filament v3 → Arqel
- [`from-nova.md`](./from-nova.md) — Laravel Nova → Arqel

## Árvore de decisão

Antes de escolher um guia, entenda **de onde você vem**:

```
Você está vindo de…
│
├── Filament v3
│   ├── Usa Livewire intensamente em páginas custom?      → ⚠ Veja from-filament.md §"Custom Pages"
│   ├── Multi-tenancy (TenancyForLaravel ou custom)?      → ✓ Mapeável (arqel/tenant)
│   ├── Plugins comunitários pesados (Curator, Shield)?   → ⚠ Avaliar paridade caso a caso
│   └── Features básicas (Resources/Forms/Tables)?        → ✓ from-filament.md cobre 90%
│
├── Laravel Nova
│   ├── Usa Lenses extensivamente?                        → ⚠ Mapear para scoped views
│   ├── Cards/Metrics em dashboards?                      → ✓ arqel/widgets cobre
│   ├── Actions com queue?                                → ✓ Suporta nativo
│   └── Fields custom Vue?                                → ⚠ Reescrever em React
│
└── Greenfield / outro framework
    └── Vá direto para `docs/getting-started.md` (não use estes guias)
```

## Princípios comuns às duas migrações

1. **Não migre tudo de uma vez.** Arqel suporta coexistir com Filament/Nova
   no mesmo Laravel app — diferentes prefixos de rota (`/admin`, `/admin-v2`)
   permitem migração resource-a-resource.
2. **Reaproveite Eloquent Models, Migrations e Policies.** Estes não mudam.
   Arqel é Laravel-native (ADR-001) e respeita Policies/Gates do framework.
3. **Inertia-only.** A ponte PHP↔React é Inertia 3 (ADR-001). Não há
   livewire-like server-rendering. Custom pages = controller + Inertia
   render + componente React.
4. **Final by default.** Classes Arqel são `final` exceto onde extensibilidade
   é design intent (ex.: `AbstractTenantResolver`). Customize via composição,
   não herança.

## Ordem recomendada de migração

Independentemente do framework de origem:

1. Models/Policies/Migrations (não mudam — só audite)
2. Resources mais simples (CRUD raso, sem relacionamentos complexos)
3. Resources com relacionamentos (BelongsTo/HasMany)
4. Resources com lifecycle hooks customizados
5. Dashboards/Widgets/Cards/Metrics
6. Actions (row, bulk, header)
7. Custom pages e fluxos (último — exige reescrita maior)
8. Multi-tenancy (se aplicável — `arqel/tenant` adapters)

## Como contribuir com correções

Encontrou um padrão não coberto? Abra issue no GitHub com label
`docs:migration` e exemplo concreto do código de origem.
