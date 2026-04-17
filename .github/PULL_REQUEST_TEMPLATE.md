<!--
Obrigado pelo PR! 🙌

Antes de submeter, confirma o checklist. PRs que não cumprem são devolvidos.
Mais contexto em CONTRIBUTING.md.
-->

## Resumo

<!-- Uma frase clara: o que este PR entrega e porquê. -->

## Ticket

<!-- Referência obrigatória ao ticket de PLANNING/08-*.md (ou fase posterior). -->
Closes: `[PACKAGE-###]`

## Tipo de mudança

- [ ] 🐛 Bug fix (não-breaking que corrige issue)
- [ ] ✨ Nova feature (não-breaking que adiciona funcionalidade)
- [ ] 💥 Breaking change (fix/feature que quebra API pública)
- [ ] 📝 Docs-only
- [ ] ♻️ Refactor (sem mudança funcional)
- [ ] 🧪 Tests-only
- [ ] 🔧 Infra/chore

## Como foi testado

<!-- Descreve testes que correste + cenários manuais (se UI). -->

- [ ] Testes automatizados (Pest e/ou Vitest)
- [ ] Testado manualmente em `apps/playground/` (se aplicável)
- [ ] E2E Playwright (se toca no flow end-to-end)

## Checklist

- [ ] Todos os critérios de aceite do ticket ✅
- [ ] Testes escritos e **passam** localmente
- [ ] Coverage target atingido (90% core PHP, 80% core JS)
- [ ] `pnpm run lint` limpo (Biome)
- [ ] `vendor/bin/pint --test` limpo
- [ ] `pnpm run typecheck` limpo
- [ ] `composer run analyse` limpo (PHPStan level max)
- [ ] Sem `console.log`, `dd()`, `dump()`, `var_dump()` deixados
- [ ] Documentação actualizada (SKILL.md do package, se API mudou)
- [ ] Commits seguem **Conventional Commits** (`feat(pkg): …`)
- [ ] Todos os commits têm **DCO sign-off** (`Signed-off-by: …`)
- [ ] Referência ao ticket no body dos commits
- [ ] **ADRs respeitados** (ver `PLANNING/03-adrs.md`)

## Breaking changes

<!-- Se marcaste "Breaking change" acima, descreve: -->
<!-- - O que quebra -->
<!-- - Caminho de migração para users -->
<!-- - Se criaste/atualizaste ADR -->

## Screenshots / recordings

<!-- Se é mudança de UI, cola imagem ou link para recording (Loom, etc.). -->

## Notas para reviewers

<!-- Hotspots, áreas de dúvida, decisões deliberadas que merecem olhar extra. -->
