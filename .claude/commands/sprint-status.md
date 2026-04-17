---
description: Mostra progresso da sprint atual e métricas da fase
allowed-tools: [Read, Bash(grep:*), Bash(wc:*), Bash(git:*)]
---

Gerar relatório do estado da sprint:

## Sprint atual

Ler `docs/tickets/current.md` e identificar:
- Sprint corrente (Sprint 0, 1, 2, etc.)
- Ticket ativo
- Tickets completados na sprint
- Tickets restantes

## Fase

- Fase ativa (1 MVP, 2 Essenciais, 3 Avançadas, 4 Ecossistema)
- Progresso total da fase: X/Y tickets (Z%)
- Target release: v0.5-beta, v0.8-rc, v1.0, v1.x

## Git stats

```bash
git log --oneline --since="7 days ago" | wc -l       # Commits última semana
git log --oneline | wc -l                            # Total commits
git log --format="%ae" | sort -u                     # Contributors
```

## Testes

Se houver testes:
```bash
vendor/bin/pest --compact                            # Status PHP tests
pnpm test --run --reporter=minimal                   # Status JS tests
```

## Próximo ticket após atual

Preview do próximo ticket na queue (só título + ID).

## Recomendação

Baseado no progresso:
- Se ticket atual está perto de completar → sugerir correr `/review-ticket`
- Se sprint está quase completa → mencionar critérios de saída
- Se houver bloqueios → listar claramente

Output em formato markdown legível.
