---
description: Carrega o próximo ticket da sprint ativa e mostra contexto completo
allowed-tools: [Read, Bash(grep:*), Bash(cat:*)]
---

Ler `docs/tickets/current.md` para identificar o ticket corrente, depois ler o ticket completo do arquivo correspondente em `PLANNING/` (08, 09, 10 ou 11 dependendo da fase).

Mostrar:
1. ID e título do ticket
2. Contexto (por que existe)
3. Descrição técnica completa
4. Critérios de aceite (checkboxes)
5. Notas de implementação
6. Dependências que precisam estar completas
7. ADRs relevantes (verificar `PLANNING/03-adrs.md` para referências)

Depois, verificar:
- As dependências do ticket estão de fato completas?
- Existem arquivos que já deveriam existir de tickets anteriores?
- Há alguma ambiguidade que precise esclarecimento humano?

Se tudo ok, começar implementação seguindo convenções de `CLAUDE.md`.
