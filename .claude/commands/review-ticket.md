---
description: Executa checklist completo de review antes de commit final
allowed-tools: [Read, Bash(vendor/bin/*), Bash(pnpm:*), Bash(git:*), Grep]
---

Executar checklist de review para o ticket corrente:

## Critérios de aceite do ticket

Ler o ticket ativo em `PLANNING/` e verificar cada checkbox:
- [ ] Todos critérios marcados como completos?
- [ ] Funcionalidade manualmente testável funciona?

## Testes

```bash
vendor/bin/pest --coverage
pnpm test
```

Verificar:
- [ ] Todos testes passam
- [ ] Coverage atinge target do pacote (≥90% core, ≥80% JS)
- [ ] Novos testes foram escritos para novo código
- [ ] Edge cases cobertos (null, empty, invalid input)

## Qualidade de código

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse
pnpm lint
pnpm typecheck
```

Verificar:
- [ ] Lint limpo (PHP Pint + ESLint)
- [ ] PHPStan level max sem errors
- [ ] TypeScript strict sem errors
- [ ] Sem `console.log`, `dd()`, `dump()`, `var_dump`, `TODO` sem contexto
- [ ] `declare(strict_types=1);` em todos PHP files novos
- [ ] Classes PHP `final` exceto quando extensível por design

## Convenções Arqel

- [ ] Comentários de código em inglês
- [ ] SKILL.md atualizado se mudou API pública do pacote
- [ ] Docblocks em todas classes/métodos públicos
- [ ] ADRs respeitados (consultar `PLANNING/03-adrs.md` se dúvida)
- [ ] Nenhuma nova dependência sem consulta

## Git

- [ ] `git status` mostra apenas arquivos esperados
- [ ] Commit message segue Conventional Commits
- [ ] DCO sign-off presente (`git commit --signoff`)
- [ ] Referência ao ticket no commit body

## Se tudo ✅

Fazer commit e atualizar `docs/tickets/current.md` com próximo ticket.

Se algo falhar, corrigir antes de commit.
