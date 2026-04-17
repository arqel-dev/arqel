---
description: Valida que mudanças propostas respeitam os 18 ADRs canônicos
allowed-tools: [Read, Grep]
---

Antes de implementar algo arquitetural, validar contra ADRs em `PLANNING/03-adrs.md`:

## ADRs a considerar (sumário)

1. **ADR-001** — Inertia 3 é única ponte PHP↔React
2. **ADR-002** — Laravel-only (não Symfony, não genérico)
3. **ADR-003** — Eloquent-native (não Repository pattern)
4. **ADR-004** — React 19.2+ como client
5. **ADR-005** — Rejeitar Livewire
6. **ADR-006** — ShadCN CLI v4 hybrid distribution
7. **ADR-007** — Base UI (Radix fork) como primitive default
8. **ADR-008** — Pest 3 para testes PHP
9. **ADR-009** — Monorepo com pnpm workspaces + Composer path repos
10. **ADR-010** — MIT + DCO (não CLA)
11. **ADR-011** — PHP 8.3+ (não suportar 8.2)
12. **ADR-012** — Laravel 12+ (não suportar versões anteriores)
13. **ADR-013** — MCP server a partir da Fase 2
14. **ADR-014** — Filament-compatible patterns onde razoável
15. **ADR-015** — Spatie packages opt-in via suggest
16. **ADR-016** — Inertia props default (não TanStack Query)
17. **ADR-017** — Laravel Policies canônico para authorization
18. **ADR-018** — ServiceProvider auto-discovery

## Processo

Dada a mudança proposta:

1. Ler `PLANNING/03-adrs.md` completo se necessário
2. Verificar se mudança contradiz algum ADR
3. Se contradiz:
   - **PARAR** imediatamente
   - Documentar qual ADR e por quê
   - Pedir confirmação humana antes de prosseguir
   - Se aprovado, propor atualização do ADR (RFC process)
4. Se respeita todos ADRs:
   - Prosseguir com implementação
   - Documentar ADR(s) relevante(s) no PR body

## Casos comuns que exigem check

- Adicionar nova biblioteca JS ou Composer package
- Mudar padrão de authorization
- Introduzir novo pattern de data fetching
- Adicionar suporte a stack não-Laravel
- Mudar estrutura de monorepo
- Adicionar sync com Livewire
- Implementar algo sem testes
