# SKILL.md — arqel-dev/cli-ink

## Purpose

`@arqel-dev/cli-ink` é um **CLI Node/TypeScript** baseado em [Ink](https://github.com/vadimdemedes/ink) + React 19 que entrega UIs ricas no terminal para apps Arqel: dashboard ao vivo com widgets, navegador read-only de Resources e tail de logs com highlight por nível.

Complementa o pacote PHP standalone `arqel-dev/cli` (`packages/cli`, comandos `arqel new/install/cloud:export`). Aqui o foco é **observabilidade interativa**, não scaffolding.

## Status

**Entregue (CLI-TUI-003 MVP)**:
- Scaffold do pacote npm (`tsup` + `vitest` + `ink-testing-library`).
- 3 modos: `dashboard`, `resources`, `logs`.
- Main menu navegável (j/k/setas, enter, esc/q).
- Hook `useDataSource<T>` com polling + injeção de `readFile`/`fileExists` para testes.
- Hook `useNavigableList` reutilizável.
- 17+ testes (Vitest).

**Por chegar (follow-up tickets)**:
- Comando `arqel:cli:export-data` no lado PHP que produz os manifestos JSON.
- Integração HTTP/WebSocket real com app Laravel (sem ler arquivos).
- Modo Artisan interactive (rodar comandos via TUI).
- Customização de tema (cores, glyphs).
- Suporte a Windows Terminal nativo (testes manuais; package já é cross-platform por design).

## Key Contracts

### CLI binário

```bash
arqel-ink                              # Main menu interativo
arqel-ink dashboard --data-dir=./.arqel-data
arqel-ink resources --data-dir=./.arqel-data
arqel-ink logs storage/logs/laravel.log --follow
```

Flag `--data-dir` ou env `ARQEL_DATA_DIR` aponta para um diretório com manifestos JSON:
- `dashboard.json` — `{queriesPerSec, activeUsers, errors, aiTokens}`.
- `resources.json` — `Array<{slug, label, count, description?}>`.

### Decisão arquitetural — fonte de dados

O CLI Ink **NUNCA conecta direto em DB ou HTTP**. Ele lê manifestos JSON gerados por um comando PHP (deferido). Razões:

1. **Testabilidade** — fácil mockar via injeção de `readFile`.
2. **Sem credenciais** — não precisa de DB password/API token no CLI.
3. **Cross-platform** — `fs.readFileSync` funciona em mac/linux/windows.
4. **Decoupling** — o app Laravel decide o que expor; o CLI só renderiza.

Seu app gera os manifestos via comando agendado (cron) ou manual (`php artisan arqel:cli:export-data`). Ver follow-up.

### React API exportada

```ts
import { Dashboard, ResourceBrowser, LogTailer, MainMenu, useDataSource, useNavigableList } from '@arqel-dev/cli-ink';
```

Os componentes são **apresentacionais puros** — recebem `dataDir`/`filePath` e fazem I/O via fs. Para customizar a fonte, passe `ioOverrides={{ readFile, fileExists }}`.

## Conventions

- **TS strict** (`noUncheckedIndexedAccess`, `exactOptionalPropertyTypes`).
- **ESM only** (`"type": "module"`); shebang via tsup banner.
- **Cross-platform**: `path.join` sempre; nada de shell-specific.
- **Sem dependências surpresa**: `ink`, `react`, `meow`, `chalk` apenas.
- Testes em `src/**/__tests__/*.test.tsx` via `ink-testing-library` (env `node`).

## Examples

**Embed Dashboard em outra app Ink**:

```tsx
import { render } from 'ink';
import { Dashboard } from '@arqel-dev/cli-ink';

render(<Dashboard dataDir="/var/arqel/manifests" pollMs={2000} />);
```

**Hook standalone**:

```tsx
const { data, loading, error } = useDataSource<MyShape>('/path/to.json', { pollMs: 500 });
```

## Anti-patterns

- ❌ Conectar direto em DB ou HTTP do app Laravel — sempre via JSON manifest.
- ❌ Adicionar fetch libraries (axios, fetch, ws) — fora do escopo.
- ❌ Side effects em componentes apresentacionais — I/O deve passar pelos hooks injetáveis.
- ❌ Quebrar Ink convention — não usar DOM globals; é Node + terminal.

## Related

- ADR-001 (Inertia-only) — não se aplica aqui (CLI Node, sem PHP).
- `packages/cli` — CLI PHP standalone (scaffolding).
- `PLANNING/11-fase-4-ecossistema.md` §CLI-TUI-003 — spec original.
