# SKILL.md — arqel/cli

> Contexto canônico para AI agents trabalhando no pacote `arqel/cli`.

## Purpose

Pacote standalone, instalado via `composer global require arqel/cli`,
expõe o binário `arqel` com subcomandos meta (não rodam dentro de uma
app Arqel — orquestram a criação delas). O comando flagship é `arqel new`,
um scaffolder interactivo que cobre o ticket CLI-TUI-001 da Fase 4.

A decisão arquitetural-chave: o comando **gera um script bash/PowerShell
revisável** ao invés de executar `laravel new`/`composer require`
diretamente. Isso preserva testabilidade (zero network, asserts diretos
no texto), dá ao usuário oportunidade de auditar o que será executado,
e segue o mesmo idioma do Filament installer.

## Status

### Entregue (CLI-TUI-001)

- Scaffold do pacote: `composer.json` com `bin/arqel`, deps mínimas
  (`symfony/console ^7.0`, `laravel/prompts ^0.3`).
- `Arqel\Cli\Application` registrando os comandos.
- `Arqel\Cli\Commands\NewCommand` com signature
  `new {name} {--starter=breeze} {--tenancy=none} {--first-resource=}
  {--dark-mode} {--mcp} {--no-prompts} {--platform=}`.
  Modo interactivo usa `Laravel\Prompts` (text/select/confirm); modo
  não-interactivo (`--no-prompts`) lê tudo das flags.
- `Arqel\Cli\Generators\SetupScriptGenerator` (`final readonly`):
  isola a lógica de renderização para Bash e PowerShell. Validação
  de nome no construtor (`/^[a-zA-Z][a-zA-Z0-9_-]*$/`).
- Testes Pest: 6 unit (`ScriptGeneratorTest`) + 6 feature
  (`NewCommandTest` via `Symfony\Console\Tester\CommandTester`).

### Por chegar

- **CLI-TUI-002** — Resource generator interactivo
  (`arqel resource:make`), com previews de fields/columns.
- **CLI-TUI-003** — Camada Ink-equivalente (rich UI com TUI completa,
  provavelmente via `chewie` ou wrapper próprio sobre Prompts).
- **CLI-TUI-005** — Polish final de docs + SKILL + cookbook
  e empacotamento para Packagist.

## Conventions

- Todos os arquivos PHP iniciam com `declare(strict_types=1)`.
- Classes `final` por default; `final readonly` para value objects /
  geradores puros (`SetupScriptGenerator`).
- O binário `bin/arqel` é PHP standalone (não Laravel Artisan).
  Resolve `vendor/autoload.php` em duas posições (instalação global
  vs clone local).
- Detecção de plataforma: `PHP_OS_FAMILY === 'Windows'` → PowerShell,
  caso contrário Bash. Pode ser forçado via `--platform=bash|powershell`
  (essencial para testes determinísticos).
- O script gerado **nunca** é executado pelo CLI; o usuário roda
  manualmente. Mensagem final imprime o comando a digitar.

## Anti-patterns

- **Não** executar `laravel new` / `composer require` diretamente do
  comando. Isso quebra testabilidade, esconde a sequência do usuário,
  e introduz dependência de network nos testes.
- **Não** depender de `arqel/core`. Este pacote é meta — roda fora de
  qualquer app Arqel. Dep cíclica seria pior ainda quando `arqel/core`
  evoluir.
- **Não** usar `dd()`, `var_dump()`, `Symfony\…\OutputInterface::write`
  com cores hard-coded. Confiar nos tags `<info>`/`<error>` do Symfony
  Console que respeitam `--no-ansi`.

## Examples

Modo não-interactivo (CI / scripts):

```bash
arqel new my-admin --no-prompts --starter=breeze --tenancy=stancl --mcp
# => arqel-setup-my-admin.sh gerado na CWD; revisar e rodar com bash.
```

Modo interactivo (humano):

```bash
arqel new my-admin
# Prompts guiam starter / tenancy / first resource / dark mode / mcp.
```

## Related

- `PLANNING/11-fase-4-ecossistema.md` §CLI-TUI — escopo completo
  da family de comandos.
- `PLANNING/03-adrs.md` ADR-001 (Inertia-only) — explica porque o
  scaffolder não precisa colher escolhas de fetch lib.
- `arqel/core` — comandos Artisan que **rodam dentro** da app
  (`arqel:install`, `arqel:doctor`, `arqel:resource`). Não confundir
  com este pacote.
