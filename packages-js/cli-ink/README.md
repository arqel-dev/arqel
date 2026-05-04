# @arqel-dev/cli-ink

> Rich terminal UI para apps Arqel — dashboard live, resource browser e log tailer construídos com [Ink](https://github.com/vadimdemedes/ink) + React 19.

## Instalação

```bash
npm install -g @arqel-dev/cli-ink
# ou
pnpm add -g @arqel-dev/cli-ink
```

Requer **Node.js ≥ 20.9** (LTS). Suporta macOS, Linux e Windows (cmd / PowerShell / Windows Terminal).

## Uso rápido

```bash
# Menu interativo
arqel-ink

# Dashboard (lê ./.arqel-data/dashboard.json)
arqel-ink dashboard --data-dir=./.arqel-data

# Browser de resources (lê resources.json)
arqel-ink resources

# Tail de log com highlight ERROR/WARN/INFO/DEBUG
arqel-ink logs storage/logs/laravel.log --follow
```

A flag `--data-dir` aceita também variável de ambiente `ARQEL_DATA_DIR`.

## Atalhos de teclado

| Tecla | Ação |
| --- | --- |
| ↑ / k | Subir |
| ↓ / j | Descer |
| Enter | Selecionar |
| Esc / q | Sair / cancelar |

## Manifestos esperados

O CLI lê arquivos JSON gerados por um comando PHP no app (em desenvolvimento — ver SKILL.md "Por chegar"). Formato:

**`dashboard.json`**:
```json
{ "queriesPerSec": 145, "activeUsers": 23, "errors": 2, "aiTokens": 8400 }
```

**`resources.json`**:
```json
[
  { "slug": "users", "label": "Users", "count": 1240, "description": "App users." }
]
```

Exemplos completos em `tests/fixtures/.arqel-data/`.

## Cross-platform

- **macOS / Linux**: funciona out-of-the-box em qualquer terminal moderno.
- **Windows**: usar Windows Terminal, PowerShell 7+ ou cmd. Cores ANSI são suportadas. Paths com espaços precisam de aspas.

Status detalhado e roadmap em [SKILL.md](./SKILL.md).

## Licença

MIT.
