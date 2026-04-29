# O que é Arqel?

> **Status:** stub — preenchimento real chega em DOCS-003 (Conceitos essenciais).

Arqel é um framework open-source MIT para construir admin panels em **Laravel 12+/13** com **Inertia 3** e **React 19.2+**. Posiciona-se como alternativa ao Filament e Laravel Nova, com três escolhas opinativas:

- **Server-driven UI**: Resources, Fields, Tables e Forms são declarados em PHP. O front-end React apenas consome JSON serializado pelo servidor.
- **Inertia-only**: nenhuma fetch lib (TanStack Query, SWR) é introduzida no Resource CRUD. Inertia props são o estado default ([ADR-001](https://github.com/arqel/arqel/blob/main/PLANNING/03-adrs.md)).
- **Laravel-native**: Policies, Gates, FormRequest, Eloquent — usados directamente. Sem ACL paralela.

Veja [Getting Started](/guide/getting-started) para instalar em < 10 minutos.
