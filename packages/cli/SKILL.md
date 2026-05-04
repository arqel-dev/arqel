# SKILL.md — arqel-dev/cli

> Contexto canônico para AI agents trabalhando no pacote `arqel-dev/cli`.

## Purpose

Pacote standalone, instalado via `composer global require arqel-dev/cli`,
expõe o binário `arqel` com subcomandos meta (não rodam dentro de uma
app Arqel — orquestram a criação delas). O comando flagship é `arqel new`,
um scaffolder interactivo entregue por CLI-TUI-001 da Fase 4.

A decisão arquitetural-chave: o comando **gera um script bash/PowerShell
revisável** ao invés de executar `laravel new`/`composer require`
diretamente. Isso preserva testabilidade (zero network, asserts diretos
no texto), dá ao usuário oportunidade de auditar o que será executado,
e segue o mesmo idioma do Filament installer.

## Status

### Entregue (CLI-TUI-001 + CLI-TUI-005 + MKTPLC-005)

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
- Suíte Pest: 23 testes (6 unit `ScriptGeneratorTest` + 6 feature
  `NewCommandTest` + 11 coverage gaps em `Tests\Unit\Coverage\CliCoverageGapsTest`,
  cobrindo nomes inválidos com whitespace/dot/slash/ASCII estendido,
  tenancy=spatie, mcpIntegration, sintaxe PowerShell e regression do
  registry de comandos).
- `Arqel\Cli\Commands\InstallCommand` (`MKTPLC-005`) — signature
  `install {package} {--marketplace-url=} {--no-prompts} {--platform=}
  {--no-installer} {--migrate}`. Faz fetch de metadata via
  `MarketplaceClient` (HTTP fetcher injectável p/ testes), valida
  compat via `CompatibilityChecker` (subset Composer semver: caret,
  tilde, `>=`/`<=`/`>`/`<`/`=`, exato, composto), e gera script
  `arqel-install-{slug}.{sh|ps1}` na CWD via
  `InstallScriptGenerator`. Mesma filosofia do `NewCommand`: o script
  é revisável, nunca executado pelo CLI.
- Suíte Pest agora com **53 testes** (4 unit MarketplaceClient + 10
  unit CompatibilityChecker + 4 unit PluginMetadata + 6 unit
  InstallScriptGenerator + 6 feature InstallCommand, somados aos
  tests pré-existentes).
- PHPStan level max limpo.

### Entregue (LCLOUD-001)

- `Arqel\Cli\Commands\CloudExportCommand` (signature
  `cloud:export {target-dir} {--with-sample} {--app-name=}`):
  copia o template `templates/laravel-cloud/` para o diretório
  destino aplicando substituição de placeholder `{{APP_NAME}}` em
  arquivos textuais. Recusa diretório destino não-vazio; cria
  hierarquia se ausente. Mensagem final imprime instruções de
  `git init`/`commit`/push + indicação de clicar no botão
  "Deploy to Laravel Cloud" do README gerado.
- `Arqel\Cli\Services\TemplateExporter` (`final readonly`): itera
  recursivamente o `sourceDir`, preserva subdiretórios e
  permissões, aplica `strtr()` nos arquivos textuais (allowlist
  de extensões), copia binários (PNG, fontes, etc.) sem alterar.
  Retorna lista ordenada de arquivos escritos. PHPStan max-safe.
- Template `templates/laravel-cloud/`:
  - `cloud.yml` provisionando PHP 8.3 + Postgres 16 + pgvector +
    Redis + Reverb.
  - `cloud.env.example` + `.env.production.example` com vars
    Arqel essenciais.
  - `composer.json` template (`your-org/{{APP_NAME}}`, type
    `project`, deps `arqel-dev/core`, `arqel-dev/panel`, Reverb,
    Inertia).
  - `app/Providers/ArqelServiceProvider.php` stub.
  - `README.md` PT-BR com botão "Deploy to Laravel Cloud",
    pre-flight checklist, custom domain, troubleshooting.
- Suite Pest acrescenta **13 testes** (7 feature
  `CloudExportCommandTest` + 6 unit `TemplateExporterTest`).

### Cloud template export

Permite ao usuário gerar a estrutura de um repo deployável em
Laravel Cloud sem clonar repositório externo:

```bash
# Dentro de qualquer pasta:
arqel cloud:export ./my-arqel-app --app-name=myadmin

# Saída: arquivos copiados, instruções:
#   cd ./my-arqel-app
#   git init && git add . && git commit -m 'Initial Arqel app'
#   git push (para GitHub) e clicar no botão do README.
```

A decisão arquitetural é manter o template **dentro do monorepo
arqel-dev/cli** (em `templates/laravel-cloud/`) ao invés de
`github.com/arqel-dev/laravel-cloud-template` separado: isso evita
um repo desincronizado e permite testar o template no mesmo CI.
Quando o Arqel for promovido a 1.0, podemos espelhar o conteúdo
para um template repo dedicado via splitsh; o command continua
sendo o caminho canônico para gerar a estrutura.

### Entregue (LCLOUD-004 + LCLOUD-005)

- `Arqel\Cli\Commands\CloudDeployLinkCommand` (signature
  `cloud:deploy-link {github-repo} {--region=auto} {--name=}`):
  gera URL canônica `https://cloud.laravel.com/deploy?...` com
  `repo`, `region` e `name` pré-preenchidos. Tenta copiar a URL
  para o clipboard via `pbcopy`/`xclip`/`wl-copy` (defensivo —
  ignora falhas silenciosamente). Sob testes, `ARQEL_CLI_NO_CLIPBOARD=1`
  desliga a tentativa.
- `Arqel\Cli\Services\DeployLinkBuilder` (`final readonly`): valida
  `owner/name` (regex GitHub-compat), allowlist de regiões
  (`auto|us-east|us-west|eu-central|eu-west|ap-southeast|sa-east`),
  validação de `name` (`[a-zA-Z][a-zA-Z0-9_-]*`, ≤40 chars).
  Usa `http_build_query` com `PHP_QUERY_RFC3986` para escape
  determinístico.
- Suíte Pest acrescenta **13 testes** (8 unit `DeployLinkBuilderTest`
  + 5 feature `CloudDeployLinkCommandTest`).
- Decisão arquitetural: o ticket original sugeria integração via
  Laravel Cloud API + GitHub OAuth, mas a API pública estável ainda
  não existe (abr/2026). A entrega aqui é o link "one-click" que o
  Laravel Cloud já reconhece + docs explicando o fluxo manual.
- Docs case study em `apps/docs/laravel-cloud/`: `README.md` (índice),
  `deploy-guide.md` (passo-a-passo end-to-end), `auto-scaling.md`
  (recomendações por tamanho), `cost-estimation.md` (calculadora
  e tiers), `comparison-other-hosts.md` (Fly/Render/DO/AWS).
- VitePress sidebar atualizada (`apps/docs/.vitepress/config.ts`)
  com grupo "Laravel Cloud" no nav e sidebar `/laravel-cloud/`.

```bash
arqel cloud:deploy-link arqel-dev/laravel-cloud-template
# => https://cloud.laravel.com/deploy?repo=https%3A%2F%2Fgithub.com%2Farqel%2Flaravel-cloud-template&region=auto
arqel cloud:deploy-link owner/meu-painel --region=us-east --name=meu-painel
```

### Por chegar

- **CLI-TUI-002** — Resource generator interactivo. Reside em `arqel-dev/core`
  como `php artisan arqel:resource:make` (Artisan command, não comando
  do binário global). **Não confundir com este pacote.**
- **CLI-TUI-003** — Camada Ink-equivalente (rich UI com TUI completa,
  provavelmente via `chewie` ou wrapper próprio sobre Prompts).
- **CLI-TUI-004** — Doctor command (`arqel doctor`) verificando versões
  de PHP/Node/Composer.
- Empacotamento final para Packagist (publicação 0.1.0).

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
- Validação de `appName` é compartilhada entre `NewCommand` (defesa
  rasa, mensagem amigável) e `SetupScriptGenerator` (defesa profunda,
  exceção com regex documentado).

## Anti-patterns

- **Não** executar `laravel new` / `composer require` diretamente do
  comando. Isso quebra testabilidade, esconde a sequência do usuário,
  e introduz dependência de network nos testes.
- **Não** depender de `arqel-dev/core`. Este pacote é meta — roda fora de
  qualquer app Arqel. Dep cíclica seria pior ainda quando `arqel-dev/core`
  evoluir.
- **Não** usar `dd()`, `var_dump()`, `Symfony\…\OutputInterface::write`
  com cores hard-coded. Confiar nos tags `<info>`/`<error>` do Symfony
  Console que respeitam `--no-ansi`.
- **Não** aceitar nomes de app com whitespace, dot, slash, ou caracteres
  ASCII estendidos. Causaria diretórios mal-formados ou paths quebrados
  no `cd`/`Set-Location` gerado.

## Examples

### Instalação global

```bash
composer global require arqel-dev/cli
# Garantir que ~/.composer/vendor/bin está no PATH:
export PATH="$PATH:$HOME/.composer/vendor/bin"
arqel --version
```

### Geração não-interactiva (CI / scripts)

```bash
arqel new my-admin --no-prompts --starter=breeze --tenancy=stancl --mcp
# => arqel-setup-my-admin.sh gerado na CWD; revisar e rodar com bash.
bash arqel-setup-my-admin.sh
```

### Geração interactiva (humano)

```bash
arqel new my-admin
# Prompts guiam starter / tenancy / first resource / dark mode / mcp.
```

### Instalação de plugin do marketplace

```bash
# Dentro de uma app Arqel já scaffolded:
arqel install acme/arqel-stripe-fields --no-prompts
# => arqel-install-acme-arqel-stripe-fields.sh gerado na CWD.
# Revisar e rodar:
bash arqel-install-acme-arqel-stripe-fields.sh
```

O script contém `composer require`, `npm install` (se aplicável),
`php artisan {plugin}:install` (se o plugin declara `installerCommand`)
e opcionalmente `php artisan migrate` (`--migrate`). Antes de gerar,
o CLI valida `compat.arqel` do plugin contra a versão atual do CLI
(subset semver: `^1.0`, `~2.5`, `>=1.0`, etc.).

### Forçando plataforma (útil em testes / cross-OS)

```bash
arqel new my-admin --no-prompts --platform=powershell
# => arqel-setup-my-admin.ps1 mesmo em Linux, para auditar o script Windows.
```

## Related

- `PLANNING/11-fase-4-ecossistema.md` §CLI-TUI — escopo completo
  da family de comandos.
- `PLANNING/03-adrs.md` ADR-001 (Inertia-only) — explica porque o
  scaffolder não precisa colher escolhas de fetch lib.
- `arqel-dev/core` — comandos Artisan que **rodam dentro** da app
  (`arqel:install`, `arqel:doctor`, `arqel:resource`). Não confundir
  com este pacote.
