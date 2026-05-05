# 08 — Fase 1 (MVP): Tickets Detalhados

> Lista completa de tickets para a **Fase 1 (MVP)** do Arqel. Cada ticket tem contexto, descrição técnica, critérios de aceite, dependências e notas de implementação.

## Índice

1. [Visão geral da fase](#1-visão-geral-da-fase)
2. [Preparação e infraestrutura](#2-preparação-e-infraestrutura-infra)
3. [Pacote CORE](#3-pacote-core)
4. [Pacote FIELDS](#4-pacote-fields)
5. [Pacote TABLE](#5-pacote-table)
6. [Pacote FORM](#6-pacote-form)
7. [Pacote ACTIONS](#7-pacote-actions)
8. [Pacote AUTH](#8-pacote-auth)
9. [Pacote NAV](#9-pacote-nav)
10. [Pacotes npm (REACT, UI, FIELDS-JS, HOOKS, TYPES)](#10-pacotes-npm)
11. [Documentação e exemplos](#11-documentação-e-exemplos-docs)
12. [Governança e release](#12-governança-e-release-gov)
13. [Ordem sugerida de execução](#13-ordem-sugerida-de-execução)

## 1. Visão geral da fase

**Objetivo declarado** (ver `07-roadmap-fases.md` §3): entregar um framework Laravel funcional que permita instalar via `composer require arqel-dev/framework`, declarar Resources em PHP, e obter CRUD completo renderizado em React+ShadCN via Inertia — com 20 field types, tabela funcional, formulários, ações básicas e autorização via Laravel Policies.

**Duração:** 4-7 meses com 2-3 devs.

**Total de tickets Fase 1:** ~120, distribuídos:

| Pacote | Tickets | % |
|---|---|---|
| INFRA | ~5 | 4% |
| CORE | ~15 | 13% |
| FIELDS (PHP) | ~22 | 18% |
| TABLE | ~13 | 11% |
| FORM | ~10 | 8% |
| ACTIONS | ~9 | 8% |
| AUTH | ~5 | 4% |
| NAV | ~5 | 4% |
| npm packages (types, react, hooks, ui, fields) | ~25 | 21% |
| DOCS | ~8 | 7% |
| GOV | ~3 | 2% |

**Convenções** (ver `00-index.md` §"Formato de tickets"):

```
### [PACKAGE-###] Título curto e acionável

**Tipo:** feat | chore | docs | test | refactor | infra
**Prioridade:** P0 (blocker) | P1 (crítico) | P2 (normal) | P3 (nice-to-have)
**Estimativa:** XS (<2h) | S (2-8h) | M (1-3d) | L (3-7d) | XL (>1 semana)
**Camada:** php | react | shared | infra | docs
**Depende de:** [TICKET-###]

**Contexto**
Por quê este ticket existe.

**Descrição técnica**
O que implementar.

**Critérios de aceite**
- [ ] Item verificável 1
- [ ] Item verificável 2

**Notas de implementação**
Armadilhas, dicas, referências.
```

---

## 2. Preparação e infraestrutura (INFRA)

### [INFRA-001] Inicialização do monorepo Git

**Tipo:** infra • **Prioridade:** P0 • **Estimativa:** S • **Camada:** infra

**Contexto**

Este é o primeiro ticket absoluto do projeto. Sem o repositório inicializado com a estrutura correta, nenhum outro ticket pode começar. Ver `04-repo-structure.md` para o layout esperado.

**Descrição técnica**

- Criar repositório `github.com/arqel-dev/arqel` (a organização GitHub `arqel` precisa já estar criada — confirmado livre na verificação de naming).
- Inicializar Git com `main` como default branch.
- Criar estrutura de diretórios top-level conforme `04-repo-structure.md` §1:
  - `apps/` (vazio, `.gitkeep`)
  - `packages/` (vazio, `.gitkeep`)
  - `packages-js/` (vazio, `.gitkeep`)
  - `registry/` (vazio, `.gitkeep`)
  - `docs-content/`
  - `examples/`
  - `scripts/`
  - `.github/workflows/`
- Adicionar arquivos top-level:
  - `.gitignore` (padrão Node + PHP + IDE)
  - `.gitattributes` (LF line endings, binary detection)
  - `.editorconfig` (4 spaces PHP, 2 spaces TS/JS/YAML)
  - `LICENSE` (MIT)
  - `README.md` (inicial com badges + instalação placeholder)
  - `CHANGELOG.md` (vazio com cabeçalho "Unreleased")
  - `CODE_OF_CONDUCT.md` (Contributor Covenant 2.1)
  - `SECURITY.md` (política de divulgação responsável)

**Critérios de aceite**

- [ ] Repositório `github.com/arqel-dev/arqel` existe e é público
- [ ] Estrutura top-level de diretórios corresponde a `04-repo-structure.md`
- [ ] `.gitattributes` força LF e marca binários corretamente
- [ ] `.editorconfig` aplica regras de indentação e charset
- [ ] `LICENSE` contém MIT com ano correto e "Arqel Contributors"
- [ ] `README.md` tem seção placeholder para instalação via Composer
- [ ] Commit inicial assinado com DCO (`--signoff`)

**Notas de implementação**

- Usar GitHub CLI: `gh repo create arqel-dev/arqel --public --description "Admin panels for Laravel, forged in PHP, rendered in React."`
- Configurar branch protection em `main` depois de INFRA-005 (CI) estar verde.
- Não commitar nenhum arquivo de pacote ainda — estes vêm nos próximos tickets.

---

### [INFRA-002] Configuração pnpm workspace + Composer path repositories

**Tipo:** infra • **Prioridade:** P0 • **Estimativa:** S • **Camada:** infra • **Depende de:** [INFRA-001]

**Contexto**

O monorepo precisa de orquestração para que pacotes PHP e npm resolvam entre si sem publicação. Ver `04-repo-structure.md` §7.

**Descrição técnica**

- Criar `pnpm-workspace.yaml` na raiz:
  ```yaml
  packages:
    - "packages-js/*"
    - "apps/*"
  ```
- Criar `package.json` raiz com:
  - `"private": true`
  - Scripts: `build`, `test`, `lint`, `typecheck` (que invocam `pnpm -r <script>`)
  - `packageManager: "pnpm@9.x"`
  - `engines: { "node": ">=20.9.0" }`
- Criar `composer.json` raiz com:
  - `"type": "library"` (convenção monorepo)
  - `repositories` com `type: path`, `url: "packages/*"`, `options: { symlink: true }`
  - `require-dev` com todos os pacotes Arqel em `*`
  - `scripts` para `test`, `lint`, `format`
- Criar `.npmrc` com:
  - `strict-peer-dependencies=false` (necessário para React 19 + libs legacy)
  - `auto-install-peers=true`
- Documentar comandos principais no `README.md`.

**Critérios de aceite**

- [ ] `pnpm install` na raiz instala dependências de todos os pacotes JS (mesmo que ainda vazios)
- [ ] `composer install` na raiz resolve pacotes PHP via path repositories
- [ ] Scripts raiz funcionam: `pnpm run build`, `pnpm run test`, `pnpm run lint`
- [ ] `README.md` documenta pré-requisitos (Node ≥20.9, PHP ≥8.3, Composer, pnpm)

**Notas de implementação**

- Testar conexão entre pacotes só será possível depois de CORE-001 (primeiro pacote real) existir.
- `auto-install-peers=true` é crítico — sem isso, React 19 não instala automaticamente.
- Não usar Yarn ou npm — padronizar em pnpm por causa do workspace + performance.

---

### [INFRA-003] Configuração de ferramentas de formatação e lint (PHP e JS)

**Tipo:** infra • **Prioridade:** P0 • **Estimativa:** M • **Camada:** infra • **Depende de:** [INFRA-002]

**Contexto**

Código consistente reduz atrito em PRs e elimina discussões estéreis sobre estilo. Precisamos de Pint (PHP), Biome (JS/TS), e PHPStan/Larastan (análise estática) configurados desde o dia zero.

**Descrição técnica**

Na raiz:

- **Laravel Pint:** criar `.php-cs-fixer.php` (ou `pint.json`) com preset Laravel + regras customizadas:
  - `declare_strict_types`: mandatory
  - `final_class`: enabled
  - `ordered_imports` alphabetical
- **Biome:** criar `biome.json` com:
  - Formatter: 2 spaces, single quotes, trailing commas
  - Linter: regras recomendadas + regras específicas (no-explicit-any: error, no-console: warn)
  - Organize imports: enabled
- **Larastan / PHPStan:** criar `phpstan.neon`:
  - Level 8
  - Paths: `packages/*/src`
  - Excluded: `packages/*/tests`
- **TypeScript:** criar `tsconfig.base.json` compartilhado:
  - `strict: true`
  - `noUncheckedIndexedAccess: true`
  - `exactOptionalPropertyTypes: true`
  - `target: "ES2022"`
  - `module: "ESNext"`
  - `moduleResolution: "bundler"`
- **Git hooks:** configurar Husky + lint-staged para rodar Pint + Biome em pré-commit
- **commitlint:** configurar `@commitlint/config-conventional` para Conventional Commits

**Critérios de aceite**

- [ ] `pnpm run lint` passa sem erros em estado inicial (repo vazio)
- [ ] `vendor/bin/pint --test` executa sem erros
- [ ] `vendor/bin/phpstan analyse` executa sem erros
- [ ] `tsconfig.base.json` pode ser estendido por pacotes via `extends`
- [ ] Pré-commit hook bloqueia commit com código mal formatado
- [ ] Commit com mensagem não-Conventional é rejeitado

**Notas de implementação**

- Biome substituiu ESLint+Prettier no ecossistema React moderno (2025+). Ver documentação em biomejs.dev.
- `noUncheckedIndexedAccess` vai forçar código mais defensivo mas é essencial — muitos bugs em admin panels vêm de acessos a arrays sem verificação.
- Larastan nível 8 pode gerar falsos positivos em Eloquent magic; usar baseline inicial se necessário, mas documentar cada exceção.

---

### [INFRA-004] Configuração do pipeline de CI no GitHub Actions

**Tipo:** infra • **Prioridade:** P0 • **Estimativa:** M • **Camada:** infra • **Depende de:** [INFRA-003]

**Contexto**

CI é non-negociável desde o primeiro commit. Bugs que escapam ao CI contaminam a codebase rapidamente.

**Descrição técnica**

Criar workflows em `.github/workflows/`:

- **`ci.yml`** (rodado em cada PR e push):
  - Job `lint-php`: Pint + Larastan nível 8
  - Job `lint-js`: Biome check
  - Job `typecheck`: `tsc --noEmit` em todos os pacotes JS
  - Job `test-js`: Vitest em todos os pacotes JS
  - Concurrency: cancelar runs anteriores da mesma branch
- **`test-matrix.yml`** (rodado em push para `main` e PRs):
  - Matrix: PHP `[8.3, 8.4]` × Laravel `[12.*, 13.*]` × DB `[mysql, postgres]` × OS `[ubuntu-latest]`
  - Cada job: instala dependências, roda Pest com coverage, upload para Codecov
  - Min coverage: 85% PHP, 80% JS (enforçado — CI falha se abaixo)
- **`security.yml`** (rodado diariamente + em push):
  - CodeQL para JS e PHP
  - Dependabot alerts
  - `composer audit`
  - `pnpm audit`
- **`docs-deploy.yml`** (rodado em push para `main`):
  - Placeholder por enquanto; será completado em ticket DOCS posterior
- **`release.yml`** (acionado por tag):
  - Placeholder; completado em GOV-002

Criar `.github/dependabot.yml`:
- Composer: weekly, grouped
- npm: weekly, grouped
- GitHub Actions: monthly

**Critérios de aceite**

- [ ] PR em draft branch dispara `ci.yml` e todos os jobs passam (em estado inicial vazio)
- [ ] Falha artificial em lint PHP bloqueia merge
- [ ] Falha artificial em tipo TS bloqueia merge
- [ ] Matrix test-matrix.yml roda em todas as combinações PHP/Laravel/DB
- [ ] CodeQL reporta zero findings em código inicial
- [ ] Branch protection em `main` exige CI verde + 1 review

**Notas de implementação**

- Usar `shivammathur/setup-php@v2` para setup PHP (padrão comunidade Laravel).
- Cachear dependências Composer e pnpm agressivamente (economiza 2-5min por run).
- Matrix pode ser reduzido durante desenvolvimento ativo para economizar minutos GitHub Actions, mas restaurado antes de release.

---

### [INFRA-005] Configuração de Renovate Bot + dependency grouping

**Tipo:** infra • **Prioridade:** P1 • **Estimativa:** S • **Camada:** infra • **Depende de:** [INFRA-004]

**Contexto**

Dependências desatualizadas acumulam dívida técnica rapidamente. Dependabot é OK, mas Renovate oferece grouping superior (ex: agrupar todas atualizações React em 1 PR).

**Descrição técnica**

- Instalar Renovate app no repo
- Criar `renovate.json` com:
  - Preset base: `config:recommended`
  - Schedule: `before 5am every Monday`
  - Grupos:
    - `react-monorepo`: agrupa react, react-dom, @types/react, @types/react-dom
    - `inertia-stack`: agrupa inertiajs/inertia-laravel, @inertiajs/react
    - `laravel-stack`: agrupa laravel/framework + core Laravel packages
    - `testing`: agrupa Pest, PHPUnit, Vitest, Playwright
    - `shadcn-registry`: sem grupo (mudanças podem ser breaking)
  - Auto-merge: patches de dev deps apenas
  - Vulnerability alerts: enabled

**Critérios de aceite**

- [ ] Renovate cria PR inicial com dashboard issue
- [ ] `renovate.json` passa validação em `config-validator.renovatebot.com`
- [ ] Grupos funcionam em primeira execução (verificar PRs agrupados)

**Notas de implementação**

- Desativar Dependabot npm/composer para evitar duplicação com Renovate; manter apenas Dependabot Security Updates (que são tratados por equipe security da GitHub).
- Rebase de PRs agrupados pode ficar barulhento; usar `schedule` para limitar janela.

---

## 3. Pacote CORE

### [CORE-001] Esqueleto do pacote `arqel-dev/core` com composer.json e PSR-4

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [INFRA-002]

**Contexto**

Primeiro pacote real do monorepo. Estabelece a convenção para todos os outros pacotes Composer.

**Descrição técnica**

Em `packages/core/`:

- `composer.json`:
  ```json
  {
    "name": "arqel-dev/core",
    "description": "Core contracts, service provider, and primitives for Arqel.",
    "type": "library",
    "license": "MIT",
    "keywords": ["laravel", "admin", "inertia"],
    "homepage": "https://arqel.dev",
    "require": {
      "php": "^8.3",
      "laravel/framework": "^12.0|^13.0",
      "inertiajs/inertia-laravel": "^3.0",
      "spatie/laravel-package-tools": "^1.16"
    },
    "require-dev": {
      "orchestra/testbench": "^10.0",
      "pestphp/pest": "^3.0",
      "pestphp/pest-plugin-laravel": "^3.0",
      "larastan/larastan": "^3.0"
    },
    "autoload": {
      "psr-4": { "Arqel\\Core\\": "src/" }
    },
    "autoload-dev": {
      "psr-4": { "Arqel\\Core\\Tests\\": "tests/" }
    },
    "config": {
      "sort-packages": true,
      "allow-plugins": {
        "pestphp/pest-plugin": true
      }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
  }
  ```
- `README.md` inicial com badge e descrição
- `SKILL.md` esqueleto com estrutura conforme `04-repo-structure.md` §11
- Diretórios: `src/`, `tests/Feature/`, `tests/Unit/`, `config/`
- `phpunit.xml` (compatibilidade Pest) e `pest.xml`
- `.gitattributes` local para excluir `tests/` do dist package

**Critérios de aceite**

- [ ] `composer validate` em `packages/core/` passa sem warnings
- [ ] `composer install` na raiz resolve o pacote via path repository
- [ ] `composer dump-autoload` gera classmap válido
- [ ] `SKILL.md` tem todas as seções: Purpose, Key contracts, Common tasks, Anti-patterns, Links
- [ ] Estrutura de diretórios corresponde a `04-repo-structure.md` §3.2

**Notas de implementação**

- Não inicializar Pest ainda (próximo ticket); aqui só estrutura de diretórios.
- `spatie/laravel-package-tools` é dependência chave — fornece `PackageServiceProvider` que simplifica registration.
- Convenção: PHP namespace espelha o nome do pacote. `arqel-dev/core` → `Arqel\Core\`.

---

### [CORE-002] Implementar `ArqelServiceProvider` com auto-discovery

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [CORE-001]

**Contexto**

ServiceProvider é o ponto de entrada do pacote no Laravel. Decisão ADR-018 determina auto-discovery via `extra.laravel.providers`.

**Descrição técnica**

- Criar `src/ArqelServiceProvider.php` estendendo `Spatie\LaravelPackageTools\PackageServiceProvider`:
  ```php
  <?php

  declare(strict_types=1);

  namespace Arqel\Core;

  use Spatie\LaravelPackageTools\Package;
  use Spatie\LaravelPackageTools\PackageServiceProvider;

  final class ArqelServiceProvider extends PackageServiceProvider
  {
      public function configurePackage(Package $package): void
      {
          $package
              ->name('arqel')
              ->hasConfigFile('arqel')
              ->hasViews('arqel')
              ->hasTranslations()
              ->hasCommands([
                  // CORE-003+
              ])
              ->hasInstallCommand(function ($command) {
                  $command
                      ->publishConfigFile()
                      ->askToStarRepoOnGitHub('arqel-dev/arqel');
              });
      }

      public function packageBooted(): void
      {
          $this->registerResourceRegistry();
          $this->registerPanelRegistry();
          $this->registerFacade();
      }

      protected function registerResourceRegistry(): void
      {
          $this->app->singleton(ResourceRegistry::class);
      }

      protected function registerPanelRegistry(): void
      {
          $this->app->singleton(PanelRegistry::class);
      }

      protected function registerFacade(): void
      {
          $this->app->alias(PanelRegistry::class, 'arqel');
      }
  }
  ```
- Adicionar em `composer.json`:
  ```json
  "extra": {
    "laravel": {
      "providers": ["Arqel\\Core\\ArqelServiceProvider"]
    }
  }
  ```
- Criar `config/arqel.php` inicial (stub):
  ```php
  <?php

  return [
      'path' => '/admin',
      'resources' => [
          'path' => app_path('Arqel/Resources'),
          'namespace' => 'App\\Arqel\\Resources',
      ],
      'auth' => [
          'guard' => 'web',
      ],
  ];
  ```
- Criar stubs de `ResourceRegistry` e `PanelRegistry` (apenas classes vazias — preenchidas em CORE-004 e CORE-005)

**Critérios de aceite**

- [ ] ServiceProvider registra sem erros em app Laravel de teste
- [ ] `php artisan config:publish` (nova sintaxe Laravel 12+) copia `config/arqel.php` para app
- [ ] Facade `Arqel` disponível via `\Arqel\Core\Facades\Arqel`
- [ ] Teste Pest confirma que `app(ResourceRegistry::class)` retorna mesma instância (singleton)
- [ ] `php artisan arqel:install` (placeholder) aparece na lista de comandos

**Notas de implementação**

- `spatie/laravel-package-tools` v1.16+ suporta Laravel 12+. Verificar compatibilidade antes.
- Não usar `$loadMigrationsFrom` em CORE — migrations específicas ficam nos pacotes que as precisam (ex: `arqel-dev/audit`).
- `askToStarRepoOnGitHub` é boa prática de comunidade Laravel.

---

### [CORE-003] Comando Artisan `arqel:install`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [CORE-002]

**Contexto**

Este é o primeiro comando que um usuário executa. Experiência neste ponto define adoção — tem que ser impecável.

**Descrição técnica**

- Criar `src/Commands/InstallCommand.php`:
  ```php
  final class InstallCommand extends Command
  {
      protected $signature = 'arqel:install 
                              {--force : Overwrite existing files}';
      
      protected $description = 'Install Arqel and scaffold starter files';

      public function handle(): int
      {
          $this->displayBanner();
          $this->publishConfig();
          $this->scaffoldDirectories();
          $this->scaffoldProvider();
          $this->scaffoldLayout();
          $this->runMigrations();
          $this->scaffoldFirstResource();
          $this->displaySuccess();
          
          return self::SUCCESS;
      }
      // ...
  }
  ```
- Executar as seguintes operações com output prompts (via Laravel Prompts):
  1. Publicar `config/arqel.php`
  2. Criar `app/Arqel/Resources/` e `app/Arqel/Widgets/`
  3. Criar `app/Providers/ArqelServiceProvider.php` com panel stub (template em `stubs/`)
  4. Criar `resources/js/Pages/Arqel/` (para Inertia pages)
  5. Criar `AGENTS.md` na raiz (template inicial) — ver RF-DX-08
  6. Perguntar se quer rodar `arqel:resource User` imediatamente
  7. Exibir próximos passos (instalar frontend packages, rodar npm install, etc.)
- Criar diretório `stubs/` em `packages/core/stubs/` com:
  - `provider.stub`
  - `panel.stub`
  - `agents.stub`
  - `layout.stub` (layout Blade inicial para Inertia)

**Critérios de aceite**

- [ ] `php artisan arqel:install` em app Laravel fresco completa em <30s
- [ ] Arquivos stub são copiados sem tokens `{{}}` não substituídos
- [ ] `AGENTS.md` contém seções: "Project overview", "Key conventions", "Commands", "Architecture"
- [ ] Flag `--force` sobrescreve arquivos existentes; sem flag, pergunta antes de sobrescrever
- [ ] Output usa cores Laravel Prompts (verde=sucesso, amarelo=warning, etc.)
- [ ] Teste Pest: `Artisan::call('arqel:install')` em Orchestra Testbench completa sem erros

**Notas de implementação**

- Laravel Prompts (`laravel/prompts`) é novo padrão 2025+; usar `confirm()`, `select()`, `info()`, etc.
- Stubs ficam em `packages/core/stubs/` mas podem ser publicados para `stubs/arqel/` na app do usuário via `php artisan stub:publish`.
- **Não** tentar instalar automaticamente pacotes npm — o usuário pode ter lockfile específico. Apenas mostrar comando.

---

### [CORE-004] Implementar `ResourceRegistry` e contract `HasResource`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [CORE-002]

**Contexto**

Registry centraliza lookup de Resources por model ou slug. Padrão Singleton.

**Descrição técnica**

- Criar `src/Contracts/HasResource.php`:
  ```php
  interface HasResource
  {
      public static function getModel(): string;
      public static function getSlug(): string;
      public static function getLabel(): string;
      public static function getPluralLabel(): string;
      public static function getNavigationIcon(): ?string;
      public static function getNavigationGroup(): ?string;
      public static function getNavigationSort(): ?int;
  }
  ```
- Criar `src/Resources/ResourceRegistry.php`:
  ```php
  final class ResourceRegistry
  {
      /** @var array<class-string<HasResource>> */
      private array $resources = [];

      public function register(string $resourceClass): void;
      public function registerMany(array $resourceClasses): void;
      public function discover(string $path, string $namespace): void;
      public function all(): array;
      public function findByModel(string $modelClass): ?string;
      public function findBySlug(string $slug): ?string;
      public function has(string $resourceClass): bool;
      public function clear(): void;
  }
  ```
- Implementar `discover()` que faz scan em diretório usando `Finder`:
  - Reflexiona classes que implementam `HasResource`
  - Registra automaticamente
- Registrar `ResourceRegistry` como singleton no ServiceProvider (já feito em CORE-002)

**Critérios de aceite**

- [ ] `ResourceRegistry::register(UserResource::class)` armazena corretamente
- [ ] `findByModel(User::class)` retorna `UserResource::class`
- [ ] `findBySlug('users')` retorna `UserResource::class`
- [ ] Registrar classe que não implementa `HasResource` lança `InvalidArgumentException`
- [ ] Registrar mesma classe duas vezes é idempotente (não duplica)
- [ ] `discover()` em diretório com 5 Resources encontra todos
- [ ] Testes cobrem: registro, lookup, discovery, clear, edge cases

**Notas de implementação**

- Usar `symfony/finder` (já dep Laravel) para scan.
- Usar `ReflectionClass` para verificar interface implementation — não instanciar.
- Cache de discovery em produção: serializar registry em arquivo, só invalidar em `optimize:clear` ou quando arquivos mudam (verificar `filemtime`).

---

### [CORE-005] Implementar `Panel` e `PanelRegistry`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [CORE-004]

**Contexto**

Panels permitem múltiplos admin UIs numa app (ex: `/admin` para staff, `/customer` para clients). Design de `Panel` como fluent builder.

**Descrição técnica**

- Criar `src/Panel/Panel.php`:
  ```php
  final class Panel
  {
      public function __construct(public readonly string $id) {}

      public function path(string $path): static;
      public function brand(string $name, ?string $logo = null): static;
      public function theme(string $theme): static;
      public function primaryColor(string $color): static;
      public function darkMode(bool $enabled = true): static;
      public function middleware(array $middleware): static;
      public function resources(array $resources): static;
      public function widgets(array $widgets): static;
      public function navigationGroups(array $groups): static;
      public function authGuard(string $guard): static;
      public function tenant(?string $scope = null): static;
      
      // Getters
      public function getPath(): string;
      public function getBrand(): array;
      public function getTheme(): string;
      // ... etc
  }
  ```
- Criar `src/Panel/PanelRegistry.php`:
  ```php
  final class PanelRegistry
  {
      private array $panels = [];
      private ?Panel $currentPanel = null;

      public function panel(string $id): Panel;  // Create or get existing
      public function getCurrent(): ?Panel;
      public function setCurrent(string $id): void;
      public function all(): array;
      public function has(string $id): bool;
  }
  ```
- Criar Facade `src/Facades/Arqel.php`:
  ```php
  final class Arqel extends Facade
  {
      protected static function getFacadeAccessor(): string
      {
          return PanelRegistry::class;
      }
  }
  ```
- Implementar routing: quando panel `admin` é registrado com path `/admin`, auto-gerar rotas:
  - `GET /admin` → dashboard
  - `GET /admin/{resource}` → index
  - `GET /admin/{resource}/create` → create
  - `POST /admin/{resource}` → store
  - `GET /admin/{resource}/{id}` → show
  - `GET /admin/{resource}/{id}/edit` → edit
  - `PUT /admin/{resource}/{id}` → update
  - `DELETE /admin/{resource}/{id}` → destroy

**Critérios de aceite**

- [ ] `Arqel::panel('admin')` cria panel com ID `admin`
- [ ] Fluent API: `Arqel::panel('admin')->path('/backoffice')->brand('Acme')` funciona
- [ ] Múltiplos panels não conflitam em rotas
- [ ] Rotas auto-geradas aparecem em `php artisan route:list`
- [ ] Middleware é aplicado corretamente em cada panel
- [ ] Teste Pest: registrar panel, resolver rota, verificar que controllers respondem

**Notas de implementação**

- Controllers (CORE-006) serão genéricos — 1 controller handle qualquer Resource via route parameter.
- Não implementar auth aqui — middleware é passado pela app (ex: `auth`, `verified`).
- Rotas são nomeadas: `arqel.{panel}.{resource}.{action}` (ex: `arqel.admin.users.index`).

---

### [CORE-006] Implementar `ResourceController` genérico

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** XL • **Camada:** php • **Depende de:** [CORE-005]

**Contexto**

Este é o controller que recebe todas as requests dos Resources. Genérico e polimórfico — resolve Resource via parâmetro de rota.

**Descrição técnica**

- Criar `src/Http/Controllers/ResourceController.php`:
  ```php
  final class ResourceController
  {
      public function __construct(
          private readonly ResourceRegistry $registry,
          private readonly InertiaDataBuilder $dataBuilder,
      ) {}

      public function index(Request $request, string $resource): Response;
      public function create(Request $request, string $resource): Response;
      public function store(Request $request, string $resource): RedirectResponse;
      public function show(Request $request, string $resource, string $id): Response;
      public function edit(Request $request, string $resource, string $id): Response;
      public function update(Request $request, string $resource, string $id): RedirectResponse;
      public function destroy(Request $request, string $resource, string $id): RedirectResponse;
  }
  ```
- Cada método:
  1. Resolve Resource via `$this->registry->findBySlug($resource)` (404 se não existir)
  2. Instancia Resource
  3. Verifica Policy via `$this->authorize(...)` (usar Gate Laravel)
  4. Executa lógica específica (query, store, update, etc.)
  5. Retorna `Inertia::render('arqel::{action}', $data)`
- Implementar `InertiaDataBuilder` em `src/Support/InertiaDataBuilder.php`:
  - `buildIndexData(Resource $resource, Request $request): array`
  - `buildCreateData(Resource $resource, Request $request): array`
  - `buildEditData(Resource $resource, Model $record, Request $request): array`
  - Cada método retorna shape conforme `06-api-react.md` §3
- Lidar com FormRequest auto-gerada ou fallback para validação inline

**Critérios de aceite**

- [ ] `GET /admin/users` renderiza página Inertia com props esperados
- [ ] `POST /admin/users` cria registro e redireciona
- [ ] `PUT /admin/users/1` atualiza registro
- [ ] `DELETE /admin/users/1` deleta registro
- [ ] Policy deny retorna 403
- [ ] Resource inexistente retorna 404
- [ ] ValidationException retorna erros para Inertia (via `back()->withErrors()`)
- [ ] Testes Feature cobrem todos os 7 endpoints com happy path e erros

**Notas de implementação**

- **Crítico:** este controller é o hot path. Otimizações de query (eager loading via Fields) vêm em FIELDS-020.
- Não usar Resource Controllers Laravel (`Route::resource`) — as rotas precisam de parâmetro `{resource}` dinâmico.
- Inertia shared props (auth, tenant, flash) são adicionados automaticamente via middleware (CORE-007).

---

### [CORE-007] Middleware `HandleArqelInertiaRequests`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [CORE-006]

**Contexto**

Shared props em Inertia são injetados via middleware. Ver `06-api-react.md` §2.

**Descrição técnica**

- Criar `src/Http/Middleware/HandleArqelInertiaRequests.php` estendendo `Inertia\Middleware`:
  ```php
  final class HandleArqelInertiaRequests extends Middleware
  {
      public function version(Request $request): ?string
      {
          return parent::version($request) . '-' . config('arqel.version', '0.1.0');
      }

      public function share(Request $request): array
      {
          return array_merge(parent::share($request), [
              'auth' => [
                  'user' => $this->currentUser($request),
                  'can' => $this->globalAbilities($request),
              ],
              'panel' => $this->currentPanel(),
              'tenant' => $this->currentTenant($request),
              'flash' => [
                  'success' => fn () => $request->session()->get('success'),
                  'error' => fn () => $request->session()->get('error'),
                  'info' => fn () => $request->session()->get('info'),
                  'warning' => fn () => $request->session()->get('warning'),
              ],
              'translations' => fn () => $this->translations(),
              'arqel' => [
                  'version' => config('arqel.version'),
              ],
          ]);
      }
      
      // Private methods...
  }
  ```
- Registrar middleware automaticamente no grupo `web` via ServiceProvider quando panels existem
- Gerar tenant como `null` por padrão (implementado em Fase 2)
- Gerar translations com subset baseado em namespace `arqel.*`

**Critérios de aceite**

- [ ] Request GET em rota Arqel inclui shared props completos
- [ ] `auth.user` é null se guest
- [ ] `auth.can` contém global abilities (strings)
- [ ] `flash` exibe mensagens session e é limpo após exibição
- [ ] `translations` contém apenas strings `arqel.*` (não toda `lang/`)
- [ ] Middleware é idempotente (não duplica em re-dispatch)

**Notas de implementação**

- `share()` em Inertia aceita closures para lazy evaluation — usar para `translations` (custosa).
- Tenant `null` é intencional em Fase 1 — estrutura já existe para Fase 2 preencher.
- `version()` invalidar cache client quando Arqel atualiza — partial reload automático.

---

### [CORE-008] Classe base `Resource` abstract

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [CORE-004]

**Contexto**

Classe que o usuário vai estender. API definida em `05-api-php.md` §1.1.

**Descrição técnica**

- Criar `src/Resources/Resource.php` (abstract):
  ```php
  abstract class Resource implements HasResource, HasFields, HasActions
  {
      public static string $model;
      public static ?string $label = null;
      public static ?string $pluralLabel = null;
      public static ?string $slug = null;
      public static ?string $navigationIcon = null;
      public static ?string $navigationGroup = null;
      public static ?int $navigationSort = null;
      public static ?string $recordTitleAttribute = null;
      
      // Lifecycle hooks (override-able)
      protected function beforeCreate(array $data): array { return $data; }
      protected function afterCreate(Model $record): void {}
      protected function beforeUpdate(Model $record, array $data): array { return $data; }
      protected function afterUpdate(Model $record): void {}
      protected function beforeSave(Model $record, array $data): array { return $data; }
      protected function afterSave(Model $record): void {}
      protected function beforeDelete(Model $record): void {}
      protected function afterDelete(Model $record): void {}
      
      // Required implementations
      abstract public function fields(): array;
      
      // Default implementations (override-able)
      public function table(Table $table): Table { /* defaults */ }
      public function form(Form $form): Form { /* defaults based on fields() */ }
      public function indexQuery(): ?Builder { return null; }
      public function recordTitle(Model $record): string { /* default */ }
      public function recordSubtitle(Model $record): ?string { return null; }
      
      // Auto-derived from class name
      public static function getSlug(): string;
      public static function getLabel(): string;
      public static function getPluralLabel(): string;
      public static function getModel(): string;
      public static function getNavigationIcon(): ?string;
      public static function getNavigationGroup(): ?string;
      public static function getNavigationSort(): ?int;
  }
  ```
- Auto-derivation:
  - `getSlug()`: de class name, ex `UserResource` → `users`
  - `getLabel()`: de model class, ex `User` → `User`
  - `getPluralLabel()`: pluralizer Laravel, ex `User` → `Users`
- Contracts em `src/Contracts/`:
  - `HasFields` — requer `fields(): array`
  - `HasActions` — requer `table(Table $table): Table` (que contém actions)
  - `HasPolicies` — opcional, para Resources que declaram policies inline

**Critérios de aceite**

- [ ] Criar Resource subclass com apenas `$model = User::class` e `fields()` compila
- [ ] Auto-derivation produz slug, label, pluralLabel corretamente
- [ ] Override de `$slug = 'team-members'` funciona e é usado
- [ ] Lifecycle hooks são chamados na ordem correta pelo controller
- [ ] Try instanciar sem `$model` definido lança erro claro
- [ ] Testes cobrem: derivation, override, lifecycle, defaults

**Notas de implementação**

- `str()->plural()` do Laravel faz pluralização em inglês. Para i18n, `$pluralLabel` deve ser overridden.
- Lifecycle hooks **não** lançam exceções em default — retornam data unchanged.
- `beforeSave`/`afterSave` são hooks "combo" que rodam em create OU update — útil para lógica compartilhada.

---

### [CORE-009] Comando `arqel:resource` — gerador de Resource

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [CORE-008]

**Contexto**

Developer experience crítico. Um único comando deve produzir Resource utilizável imediatamente.

**Descrição técnica**

- Criar `src/Commands/MakeResourceCommand.php`:
  ```php
  final class MakeResourceCommand extends Command
  {
      protected $signature = 'arqel:resource 
                              {model : The model class name (e.g., User or App\\Models\\User)}
                              {--from-model : Introspect model attributes to generate fields}
                              {--from-migration= : Path to migration file for introspection}
                              {--with-policy : Also generate Policy class}
                              {--force : Overwrite existing file}';
      
      protected $description = 'Generate an Arqel Resource class';
  }
  ```
- Lógica:
  1. Resolve model class (tenta `App\Models\{Model}` se não FQN)
  2. Se `--from-model`:
     - Instancia model vazio
     - Lê `$fillable` e casts
     - Para cada attribute, inferir FieldType (ex: string → `Field::text`, bool → `Field::toggle`, int → `Field::number`, datetime → `Field::dateTime`, belongsTo relation detectado → `Field::belongsTo`)
  3. Se `--from-migration`: parsear migration schema via AST ou regex
  4. Geração de arquivo baseado em stub com substituições
  5. Se `--with-policy`: chama internamente `php artisan make:policy` do Laravel

**Critérios de aceite**

- [ ] `php artisan arqel:resource User` gera `app/Arqel/Resources/UserResource.php`
- [ ] `--from-model` inclui fields inferidos para todos attributes do model
- [ ] `--with-policy` gera `app/Policies/UserPolicy.php` com métodos (viewAny, view, create, update, delete)
- [ ] Model inexistente retorna erro claro
- [ ] Arquivo existente sem `--force` pergunta antes de sobrescrever
- [ ] Resource gerada é imediatamente utilizável (aparece no panel)
- [ ] Testes Pest: cobrir cada variação (sem flags, com --from-model, com --with-policy)

**Notas de implementação**

- Introspection de Eloquent relationships é **hard** — método `getAllAttributes()` não é nativo. Usar `getFillable()` + `getCasts()` + reflection para métodos retornando `BelongsTo`, `HasMany`, etc.
- Stub deve importar `use Arqel\Fields\Field;` corretamente.
- Considerar flag `--dry-run` para preview sem escrita (valor em MCP tools).

---

### [CORE-010] `FieldSchemaSerializer` — serialização de Fields para JSON

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [CORE-008]

**Contexto**

Inertia precisa passar definições de Fields para o React como JSON. Serializer centraliza lógica.

**Descrição técnica**

- Criar `src/Support/FieldSchemaSerializer.php`:
  ```php
  final class FieldSchemaSerializer
  {
      /**
       * @param array<Field> $fields
       * @return array<array<string, mixed>>
       */
      public function serialize(array $fields, ?Model $record = null, ?User $user = null): array
      {
          return collect($fields)
              ->filter(fn (Field $f) => $f->canSee($user, $record))
              ->map(fn (Field $f) => $this->serializeOne($f, $record, $user))
              ->values()
              ->all();
      }

      private function serializeOne(Field $field, ?Model $record, ?User $user): array
      {
          return [
              'type' => $field->getType(),
              'name' => $field->getName(),
              'label' => $field->getLabel(),
              'component' => $field->getComponent(),
              'required' => $field->isRequired(),
              'readonly' => $field->isReadonly() || !$field->canEdit($user, $record),
              'disabled' => $field->isDisabled($record),
              'placeholder' => $field->getPlaceholder(),
              'helperText' => $field->getHelperText(),
              'defaultValue' => $field->getDefault(),
              'validation' => $this->serializeValidation($field),
              'columnSpan' => $field->getColumnSpan(),
              'visibility' => $this->serializeVisibility($field),
              'live' => $field->isLive(),
              'liveDebounce' => $field->getLiveDebounce(),
              'dependsOn' => $field->getDependencies(),
              'props' => $field->getTypeSpecificProps(),
          ];
      }
      
      // ... helpers
  }
  ```
- Shape resultante deve bater com `FieldSchema` TypeScript em `06-api-react.md` §4
- Filtra fields por `canSee` server-side (não serializa o que não se pode ver)
- Evalua closures com contexto (`$record`, `$user`)

**Critérios de aceite**

- [ ] Field simples (`Field::text('name')->required()`) serializa corretamente
- [ ] Field com `canSee(fn() => false)` não aparece em output
- [ ] Field com closure `visibleOnDetail()` é avaliado corretamente conforme context
- [ ] `props` específicas por tipo (select options, belongsTo route) estão presentes
- [ ] Shape JSON é exatamente o definido em `06-api-react.md`
- [ ] Snapshot test: output serializado de conjunto de fields é determinístico

**Notas de implementação**

- Usar snapshot testing (Pest plugin `pest-plugin-snapshot`) para garantir que mudanças no shape são conscientes.
- Validação (`validation.rules`) é convertida em Zod schema **no client** — serializer só passa array de regras Laravel.
- Performance: serializer é chamado por request. Para Resources com 50+ fields, cachear parcialmente.

---

### [CORE-011] Facade `Arqel` e aliases

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** S • **Camada:** php • **Depende de:** [CORE-005]

**Contexto**

Facade pública para acesso conveniente. API em `05-api-php.md` §13.

**Descrição técnica**

- Criar `src/Facades/Arqel.php`:
  ```php
  final class Arqel extends Facade
  {
      protected static function getFacadeAccessor(): string
      {
          return PanelRegistry::class;
      }
      
      /**
       * @method static Panel panel(string $id)
       * @method static ?Panel getCurrentPanel()
       * @method static string getResource(string $modelClass)
       * @method static array getResources()
       * @method static string url(string $name, array $params = [])
       * @method static bool hasPanel(string $id)
       */
  }
  ```
- Registrar alias em `composer.json`:
  ```json
  "extra": {
    "laravel": {
      "aliases": {
        "Arqel": "Arqel\\Core\\Facades\\Arqel"
      }
    }
  }
  ```
- Adicionar métodos `url()`, `getResource()`, etc. em `PanelRegistry` para serem resolvidos via Facade.

**Critérios de aceite**

- [ ] `Arqel::panel('admin')` funciona em código cliente
- [ ] `Arqel::url('users.index')` retorna URL correta
- [ ] Docblock fornece autocomplete em IDE (PhpStorm, VSCode + Intelephense)
- [ ] Facade não precisa de registro manual em `config/app.php` (auto-discovery funciona)

**Notas de implementação**

- Laravel 12+ já não tem `config/app.php` com aliases por padrão — facade discovery acontece via provider.
- Considerar gerar `_ide_helper.php` futuramente para melhor IDE support.

---

### [CORE-012] Layout Inertia base (Blade root template)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [CORE-002]

**Contexto**

Inertia requer um Blade template root que contém o `@inertia` directive. Single page, loaded uma vez.

**Descrição técnica**

- Criar `packages/core/resources/views/app.blade.php`:
  ```blade
  <!DOCTYPE html>
  <html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
  <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title inertia>{{ config('app.name', 'Arqel Admin') }}</title>
      
      <meta name="csrf-token" content="{{ csrf_token() }}">
      
      <!-- Theme flash (previne FOUC dark mode) -->
      <script>
          (function() {
              const theme = localStorage.getItem('arqel-theme') || 'system';
              const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
              const isDark = theme === 'dark' || (theme === 'system' && systemDark);
              if (isDark) document.documentElement.classList.add('dark');
          })();
      </script>
      
      @routes
      @viteReactRefresh
      @vite(['resources/css/app.css', 'resources/js/app.tsx'])
      @inertiaHead
  </head>
  <body class="h-full bg-background text-foreground antialiased">
      @inertia
  </body>
  </html>
  ```
- Publicar view via ServiceProvider para `resources/views/vendor/arqel/app.blade.php` (editável pelo user)
- Configurar Inertia para usar este template como root:
  ```php
  // Em config/arqel.php
  'inertia' => [
      'root_view' => 'arqel::app',
  ],
  ```

**Critérios de aceite**

- [ ] Página Inertia renderiza com template base (HTML válido)
- [ ] CSRF token está presente
- [ ] Theme flash previne FOUC visível (verificar em modo dark)
- [ ] `@routes` (Ziggy) expõe rotas nomeadas ao client
- [ ] Publicação via `arqel:publish --tag=views` funciona
- [ ] Template pode ser customizado sem quebrar

**Notas de implementação**

- `@routes` requer pacote `tightenco/ziggy` — adicionar como suggest no composer.json (opt-in).
- Para Inertia 3 SSR, criar também `app-ssr.blade.php` (configurado em ticket separado).
- `antialiased` em body é convenção Tailwind que melhora rendering fonts.

---

### [CORE-013] Sistema de tradução de mensagens (lang files)

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [CORE-002]

**Contexto**

UI strings precisam ser traduzíveis (RNF-I-01, RNF-I-03). Começar com en e pt-BR.

**Descrição técnica**

- Criar `packages/core/resources/lang/en/` e `packages/core/resources/lang/pt_BR/`
- Para cada locale, arquivos:
  - `messages.php`: strings gerais (create, edit, delete, save, cancel, etc.)
  - `validation.php`: overrides específicos para Arqel (se existirem)
  - `actions.php`: labels de actions padrão (view, edit, delete, restore, etc.)
  - `table.php`: strings de tabela (sorting, filtering, pagination, empty state)
  - `form.php`: strings de formulário (submit, reset, required, etc.)
- Registrar namespace `arqel::` no ServiceProvider
- Estrutura en/messages.php (exemplo):
  ```php
  return [
      'actions' => [
          'create' => 'Create',
          'edit' => 'Edit',
          'delete' => 'Delete',
          'save' => 'Save',
          'cancel' => 'Cancel',
          'back' => 'Back',
      ],
      'confirmation' => [
          'delete' => 'Are you sure you want to delete this?',
          'cannot_undo' => 'This action cannot be undone.',
      ],
      // ...
  ];
  ```
- Todas as strings user-facing em PHP usam `__('arqel::messages.actions.create')`

**Critérios de aceite**

- [ ] `__('arqel::messages.actions.create')` retorna 'Create' (en) / 'Criar' (pt-BR)
- [ ] `php artisan arqel:publish --tag=lang` publica para `lang/vendor/arqel/` no app
- [ ] Todas as strings user-facing no código CORE usam helper `__()`
- [ ] Traduções são incluídas em shared props (via `HandleArqelInertiaRequests`)
- [ ] No React, `t('actions.create')` retorna tradução correta

**Notas de implementação**

- Outros locales (es, fr, de, it, ja, pt-PT) ficam para Fase 2 (RNF-I-02).
- Evitar pluralização complexa em Fase 1 — usar `trans_choice` só onde necessário.
- String IDs devem ser hierárquicos (`actions.create` > `create`) para organização.

---

### [CORE-014] Testes de infraestrutura do pacote CORE

**Tipo:** test • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [CORE-009]

**Contexto**

Core é a fundação. Se falha, tudo falha. Cobertura robusta de testes é investimento alto-ROI.

**Descrição técnica**

- Configurar `tests/Pest.php` com:
  ```php
  uses(\Orchestra\Testbench\TestCase::class)->in('Feature', 'Unit');
  ```
- Criar `tests/TestCase.php` estendendo `Orchestra\Testbench\TestCase`:
  - Override `getPackageProviders()` retornando `[ArqelServiceProvider::class]`
  - Override `defineEnvironment()` para config de DB SQLite in-memory
- Criar tests/Feature/:
  - `InstallCommandTest.php` — cobre `arqel:install`
  - `MakeResourceCommandTest.php` — cobre `arqel:resource`
  - `ResourceControllerTest.php` — cobre CRUD endpoints
  - `ServiceProviderTest.php` — cobre registro e configuração
- Criar tests/Unit/:
  - `ResourceRegistryTest.php`
  - `PanelRegistryTest.php`
  - `ResourceAutoDerivationTest.php`
  - `FieldSchemaSerializerTest.php`
- Meta: ≥ 90% cobertura no pacote CORE (acima do RNF-Q-01 de 85% global)

**Critérios de aceite**

- [ ] `vendor/bin/pest packages/core/tests` passa todos os testes
- [ ] Cobertura >= 90% (gerada via `--coverage`)
- [ ] CI matrix test-matrix.yml executa tests em todas as combinações
- [ ] Zero testes pendente (`->todo()`) em commit merge para main
- [ ] Snapshot tests para shapes JSON (FieldSchemaSerializer)

**Notas de implementação**

- Orchestra Testbench simula app Laravel completa — use `$this->app` para acessar container.
- Testes de Inertia: usar `Inertia::assertInertia()` via pacote `inertiajs/inertia-laravel-testing` (já inclui helpers em Laravel 12+).
- Para testar controllers, criar TestModels (User, Post) em `tests/Fixtures/`.

---

### [CORE-015] SKILL.md do pacote core completo

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** S • **Camada:** docs • **Depende de:** [CORE-010]

**Contexto**

SKILL.md é fonte canônica de contexto para coding agents (Claude Code, Cursor). Também serve como onboarding humano. Ver `04-repo-structure.md` §11.

**Descrição técnica**

Criar `packages/core/SKILL.md`:

```markdown
# Arqel Core — AI Agent Skill

## Purpose
The `arqel-dev/core` package is the foundation of the Arqel admin panel framework. It provides:
- `ArqelServiceProvider` — registers routes, views, commands, middleware
- `ResourceRegistry` — central registry of all Resource classes
- `PanelRegistry` — manages multiple admin panels (e.g., `/admin`, `/customer`)
- `Resource` abstract base class — developers extend this to declare CRUD pages
- `ResourceController` — generic controller handling all CRUD routes
- `FieldSchemaSerializer` — converts PHP Field objects to JSON for Inertia

## Key contracts
- `Arqel\Core\Contracts\HasResource` — implemented by every Resource
- `Arqel\Core\Contracts\HasFields` — requires `fields(): array` method
- `Arqel\Core\Resources\Resource` — abstract class with lifecycle hooks

## Common tasks
- **Register a new panel**: `Arqel::panel('admin')->resources([...])` in ArqelServiceProvider::boot()
- **Generate a Resource**: `php artisan arqel:resource User --from-model --with-policy`
- **Customize CRUD behavior**: override `beforeCreate`, `afterCreate`, etc. in Resource subclass
- **Customize routing**: use `Arqel::panel('admin')->path('/custom')` 

## Anti-patterns
- ❌ Don't register Resources in config files — use Panel fluent API
- ❌ Don't call `ResourceRegistry::register` manually; use Panel->resources([])
- ❌ Don't skip Policies — Arqel authorization is policy-first (ADR-017)
- ❌ Don't use Resource Controllers (Laravel feature) — Arqel's controller is singular and generic

## File structure
packages/core/src/
├── ArqelServiceProvider.php
├── Resources/Resource.php (abstract)
├── Resources/ResourceRegistry.php
├── Panel/Panel.php, PanelRegistry.php
├── Http/Controllers/ResourceController.php
├── Http/Middleware/HandleArqelInertiaRequests.php
├── Support/FieldSchemaSerializer.php, InertiaDataBuilder.php
├── Commands/InstallCommand.php, MakeResourceCommand.php
├── Contracts/HasResource.php, HasFields.php, HasActions.php
└── Facades/Arqel.php

## Links
- Source: packages/core/src/
- Docs: https://arqel.dev/docs/core
- Architecture: /docs/arquitetura.md §4.1
- ADRs: /docs/adrs.md (ADR-001, 002, 005, 018 directly relevant)
```

**Critérios de aceite**

- [ ] SKILL.md existe com todas as seções
- [ ] Code examples são copy-pasteable e funcionais
- [ ] Referencias cruzadas (ADRs, docs internas) são válidas
- [ ] Anti-patterns listam pelo menos 3 erros comuns
- [ ] Links externos (docs, GitHub) são válidos (verificar via lint externo)

**Notas de implementação**

- SKILL.md é lido pelo MCP server (Fase 2) — formato consistente importa.
- Manter SKILL.md atualizado é parte de Definition of Done para tickets que mudam API.

---

### [CORE-016] `arqel:install` instala e configura o frontend automaticamente

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [CORE-003]

**Contexto**

Hoje `arqel:install` faz scaffold PHP completo mas deixa o setup frontend (`pnpm add @arqel-dev/{ui,fields,react,hooks,types}`, configuração de `resources/js/app.tsx`, configuração de Tailwind v4 em `resources/css/app.css`) como steps manuais — o user precisa ler [Getting Started](apps/docs/guide/getting-started.md) e copiar comandos.

Isso é fricção alta para o **primeiro contato** do user com Arqel — exatamente o ponto onde a primeira impressão importa mais. Um dev Laravel sem JS background não sabe escolher entre `pnpm`/`npm`/`yarn`, fica confuso ao editar `app.tsx`/`app.css`, e desiste antes de ver a primeira UI funcional.

Arqel é fundamentalmente Inertia + React (ADR-001) — não há admin sem o lado JS. O install deve refletir isso: **uma única execução** para sair de zero a `php artisan serve && pnpm dev` funcional.

**Descrição técnica**

Estender `Arqel\Core\Commands\InstallCommand` com 4 fases novas após o scaffolding PHP atual:

1. **Detectar package manager** — checar `pnpm-lock.yaml`/`yarn.lock`/`package-lock.json` na ordem; se nenhum existir, perguntar via `select()` Laravel Prompts (default `pnpm`).
2. **Instalar dependências JS** — perguntar via `confirm()` "Instalar pacotes frontend agora?" (default yes) e invocar:
   ```bash
   {pm} add @arqel-dev/react @arqel-dev/ui @arqel-dev/hooks @arqel-dev/fields @arqel-dev/types
   {pm} add -D @inertiajs/react react react-dom @types/react @types/react-dom
   ```
   Via `Symfony\Component\Process\Process` com timeout 300s e output streamado para o terminal. Em falha (rede, lockfile conflict, etc.), exibir warning não-fatal e seguir.
3. **Scaffold `resources/js/app.tsx`** — substituir o conteúdo default de Laravel por:
   ```tsx
   import '@arqel-dev/ui/styles.css';
   import '@arqel-dev/fields/register';
   import { createArqelApp } from '@arqel-dev/react/inertia';

   createArqelApp({
     appName: import.meta.env.VITE_APP_NAME ?? 'Arqel',
     pages: import.meta.glob('./Pages/**/*.tsx'),
   });
   ```
   Stub em `packages/core/stubs/app.tsx.stub`. Idempotente: detecta `import '@arqel-dev/ui/styles.css'` para skip (a menos que `--force`).
4. **Scaffold `resources/css/app.css`** — garantir as duas linhas:
   ```css
   @import 'tailwindcss';
   @import '@arqel-dev/ui/styles.css';
   ```
   Idempotente: skip se ambas já presentes.

Adicionar flag `--no-frontend` para users que querem o scaffold PHP-only (e.g., contribuidores do monorepo, CI smoke tests).

**Critérios de aceite**

- [ ] `php artisan arqel:install` em Laravel fresh completa do zero ao "pnpm dev funcional" sem steps manuais
- [ ] Detection automática de pm; `select()` apenas quando nenhum lockfile existe
- [ ] `--no-frontend` flag funciona (pula fases 1-4 silently)
- [ ] `--force` re-escreve `app.tsx`/`app.css` mesmo quando já configurados
- [ ] Falha de rede no `{pm} add` não mata o comando — emite warning amarelo e continua
- [ ] Stub `app.tsx.stub` substitui `{{ appName }}` por `config('app.name')` quando aplicável
- [ ] Idempotência: rodar 2× sem `--force` não duplica imports
- [ ] Teste Pest: mock `Process` + assert sequência de comandos chamados
- [ ] `getting-started.md` em `apps/docs/guide/` atualizado: passos 3+4 viram "tudo isso é feito automaticamente pelo `arqel:install`"

**Notas de implementação**

- Symfony Process: usar `setTty(false)` em CI (Pest sem TTY); `setTty(Process::isTtySupported())` em dev real.
- O step 3 é destrutivo (sobrescreve `app.tsx`) — sempre `confirm()` antes a menos que `--force`.
- `package.json` do user pode não existir (Laravel novo cria, mas `arqel:install` não pode assumir). Detectar e abortar a fase 2 com warning se ausente.
- Considerar: hint para rodar `pnpm dev` no final do output success — fechamos o loop visualmente.

---

## 4. Pacote FIELDS

### [FIELDS-001] Esqueleto do pacote `arqel-dev/fields`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [CORE-008]

**Contexto**

Pacote separado para isolar concerns e permitir extensão via plugins (RF-F-07, RF-F-08).

**Descrição técnica**

- Estrutura `packages/fields/`:
  - `composer.json` (dep: `arqel-dev/core: self.version`)
  - `src/Field.php` (abstract, movido ou referenciado)
  - `src/FieldFactory.php` (static fluent API)
  - `src/Types/` (diretório — um arquivo por tipo)
  - `src/Concerns/` (traits compartilhados)
  - `src/ValidationBridge.php` (Laravel rules → Zod schema string)
  - `src/FieldServiceProvider.php`
  - `README.md`, `SKILL.md`
  - `tests/`, `pest.xml`

- `composer.json`:
  ```json
  {
    "name": "arqel-dev/fields",
    "description": "Field types for Arqel — declarative, validated, renderable.",
    "require": {
      "php": "^8.3",
      "arqel-dev/core": "self.version"
    },
    "autoload": {
      "psr-4": { "Arqel\\Fields\\": "src/" }
    },
    "extra": {
      "laravel": {
        "providers": ["Arqel\\Fields\\FieldServiceProvider"]
      }
    }
  }
  ```

- `FieldServiceProvider`: registra fields no `FieldFactory` no `boot()`

**Critérios de aceite**

- [ ] `composer require arqel-dev/fields` (path) resolve sem erros
- [ ] `FieldServiceProvider` é discovered
- [ ] Testes iniciais passam (smoke test)
- [ ] SKILL.md esqueleto presente

**Notas de implementação**

- Decisão de design: separar `arqel-dev/fields` de `arqel-dev/core` permite:
  - Usuários instalarem apenas core sem fields (raro, mas possível)
  - Evolução independente de versões
  - Field packages third-party (ex: `acme/arqel-fields-advanced`)

---

### [FIELDS-002] Classe abstrata `Field` base

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [FIELDS-001]

**Contexto**

Base de todos os fields. Define API fluent e estado interno. Ver `05-api-php.md` §2.1.

**Descrição técnica**

Criar `src/Field.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Fields;

use Arqel\Fields\Concerns\HasValidation;
use Arqel\Fields\Concerns\HasVisibility;
use Arqel\Fields\Concerns\HasDependencies;
use Arqel\Fields\Concerns\HasAuthorization;
use Illuminate\Support\Str;

abstract class Field
{
    use HasValidation;
    use HasVisibility;
    use HasDependencies;
    use HasAuthorization;

    protected string $type;
    protected string $component;
    protected string $name;
    protected ?string $label = null;
    protected ?string $placeholder = null;
    protected ?string $helperText = null;
    protected mixed $default = null;
    protected bool $readonly = false;
    protected ?\Closure $disabled = null;
    protected int|string $columnSpan = 1;
    protected bool $dehydrated = true;
    protected bool $live = false;
    protected ?int $liveDebounce = null;
    
    final public function __construct(string $name)
    {
        $this->name = $name;
        $this->label = Str::of($name)->snake()->replace('_', ' ')->title()->toString();
    }

    // Fluent setters (return static for chaining)
    public function label(string $label): static;
    public function placeholder(?string $placeholder): static;
    public function helperText(?string $text): static;
    public function default(mixed $value): static;
    public function readonly(bool $readonly = true): static;
    public function disabled(bool|\Closure $disabled = true): static;
    public function columnSpan(int|string $span): static;
    public function columnSpanFull(): static;
    public function dehydrated(bool|\Closure $dehydrated = true): static;
    public function live(bool $live = true): static;
    public function liveDebounced(int $ms = 300): static;
    public function afterStateUpdated(\Closure $callback): static;
    
    // Getters
    public function getType(): string;
    public function getName(): string;
    public function getLabel(): string;
    public function getComponent(): string;
    public function getPlaceholder(): ?string;
    public function getHelperText(): ?string;
    public function getDefault(): mixed;
    public function isReadonly(): bool;
    public function isDisabled(?Model $record = null): bool;
    public function getColumnSpan(): int|string;
    public function isDehydrated(): bool;
    public function isLive(): bool;
    public function getLiveDebounce(): ?int;
    
    // Subclasses override for type-specific props
    public function getTypeSpecificProps(): array
    {
        return [];
    }
}
```

**Critérios de aceite**

- [ ] Instanciação: `new TextField('name')` funciona (mas uso normal é via factory)
- [ ] Label auto-derivado: `Field::text('first_name')->getLabel()` retorna 'First Name'
- [ ] Fluent API encadeia: `->required()->maxLength(255)->placeholder('...')`
- [ ] Closures em `disabled()` são avaliadas com `$record` context
- [ ] `dehydrated(false)` previne persistência (útil para fields computed)
- [ ] Testes unitários cobrem cada método fluent + getter

**Notas de implementação**

- Trait separation permite reutilização em fields custom (users podem usar traits).
- `afterStateUpdated` callback é serializado como reference (nome) e resolvido server-side no POST — closures não serializam para JSON.
- `columnSpan` aceita int (1-12) ou 'full' para grid layout.

---

### [FIELDS-003] `FieldFactory` static fluent API

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [FIELDS-002]

**Contexto**

API pública para criar fields: `Field::text(...)`, `Field::email(...)`, etc. Espelha convenção Filament (ADR-014).

**Descrição técnica**

Criar `src/FieldFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Fields;

use Arqel\Fields\Types\{
    TextField, TextareaField, NumberField, CurrencyField,
    BooleanField, ToggleField, SelectField, MultiSelectField,
    RadioField, EmailField, UrlField, PasswordField,
    SlugField, DateField, DateTimeField,
    BelongsToField, HasManyField,
    FileField, ImageField, ColorField, HiddenField
};

/**
 * Static factory for creating fields.
 * Note: the class is named `Field` to match the public API `Field::text(...)`.
 */
final class Field
{
    /** @var array<string, class-string<\Arqel\Fields\Field>> */
    protected static array $registry = [];
    /** @var array<string, \Closure> */
    protected static array $macros = [];

    public static function text(string $name): TextField;
    public static function textarea(string $name): TextareaField;
    public static function number(string $name): NumberField;
    public static function currency(string $name): CurrencyField;
    public static function boolean(string $name): BooleanField;
    public static function toggle(string $name): ToggleField;
    public static function select(string $name): SelectField;
    public static function multiSelect(string $name): MultiSelectField;
    public static function radio(string $name): RadioField;
    public static function email(string $name): EmailField;
    public static function url(string $name): UrlField;
    public static function password(string $name): PasswordField;
    public static function slug(string $name): SlugField;
    public static function date(string $name): DateField;
    public static function dateTime(string $name): DateTimeField;
    public static function belongsTo(string $name, string $relatedResource): BelongsToField;
    public static function hasMany(string $name, string $relatedResource): HasManyField;
    public static function file(string $name): FileField;
    public static function image(string $name): ImageField;
    public static function color(string $name): ColorField;
    public static function hidden(string $name): HiddenField;
    
    // Macros (RF-F-08)
    public static function macro(string $name, \Closure $callback): void;
    public static function hasMacro(string $name): bool;
    public static function __callStatic(string $name, array $args);
    
    // Registry (RF-F-07 — custom fields)
    public static function register(string $type, string $fieldClass): void;
}
```

**Critérios de aceite**

- [ ] `Field::text('name')` retorna instância `TextField` com `name = 'name'`
- [ ] Todos os 20 factory methods retornam instâncias corretas
- [ ] `Field::macro('priceBRL', fn($n) => Field::currency($n)->prefix('R$'))` + `Field::priceBRL('price')` funciona
- [ ] `Field::register('customType', CustomFieldClass::class)` permite `Field::customType(...)` via `__callStatic`
- [ ] IDE autocomplete funciona (docblocks ou real methods)
- [ ] Testes cobrem: factory methods, macros, registry, error cases

**Notas de implementação**

- Dilema nomenclatura: classe pública é `Field` mas também há `abstract class Field` (base). Resolver via namespacing ou renomear abstract para `BaseField`. Recomendação: abstract = `Arqel\Fields\Field` (base), factory = aliased via facade ou renomear para `FieldFactory` com alias `Field` via ServiceProvider.
- Macros são registered no runtime em ServiceProvider ou early boot.

---

### [FIELDS-004] `TextField` e variants de input texto

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [FIELDS-003]

**Contexto**

Mais comum dos tipos. Precisa estar sólido antes de tudo.

**Descrição técnica**

Criar fields em `src/Types/`:

- `TextField.php`:
  ```php
  final class TextField extends Field
  {
      protected string $type = 'text';
      protected string $component = 'TextInput';
      
      public function maxLength(int $max): static;
      public function minLength(int $min): static;
      public function pattern(string $regex): static;
      public function autocomplete(string $token): static;
      public function mask(string $pattern): static;  // Formatação visual
      
      public function getTypeSpecificProps(): array
      {
          return array_filter([
              'maxLength' => $this->maxLength ?? null,
              'minLength' => $this->minLength ?? null,
              'pattern' => $this->pattern ?? null,
              'autocomplete' => $this->autocomplete ?? null,
              'mask' => $this->mask ?? null,
          ]);
      }
  }
  ```
- `TextareaField.php`: extends TextField, `rows()`, `cols()`, `component = 'TextareaInput'`
- `EmailField.php`: extends TextField, auto-adds `email` validation rule, `type = 'email'`
- `UrlField.php`: auto-adds `url` rule, `type = 'url'`
- `PasswordField.php`: `type = 'password'`, `component = 'PasswordInput'`, `revealable(bool)`
- `SlugField.php`: `fromField('title')` para auto-geração, `separator('-')`

**Critérios de aceite**

- [ ] `Field::text('name')->maxLength(255)->serialize()` inclui `props.maxLength: 255`
- [ ] `Field::email('email')` auto-inclui regra 'email' em validation
- [ ] `Field::password('pass')->revealable()` inclui `revealable: true` em props
- [ ] `Field::slug('slug')->fromField('title')` inclui `fromField: 'title'` em props
- [ ] Testes cobrem todos os fluent methods e serialização

**Notas de implementação**

- Pattern `fromField` cria dependency em `HasDependencies` trait — slug reacts a mudanças de title.
- Mask client-side será implementado com `react-imask` no pacote React (ver FIELDS-JS).

---

### [FIELDS-005] `NumberField` e `CurrencyField`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [FIELDS-003]

**Contexto**

Numeric types comuns com formatting específico.

**Descrição técnica**

- `NumberField.php`:
  - `min(int|float)`, `max(int|float)`, `step(int|float)`
  - `integer()`: força integer (rule 'integer')
  - `decimals(int)`: casas decimais
  - Default `type = 'number'`, `component = 'NumberInput'`
- `CurrencyField.php`:
  - `prefix(string)`: ex '$', 'R$', '€'
  - `suffix(string)`: ex 'USD'
  - `decimals(int = 2)`
  - `thousandsSeparator(string = ',')`
  - `decimalSeparator(string = '.')`
  - `component = 'CurrencyInput'`

**Critérios de aceite**

- [ ] `Field::number('age')->min(0)->max(120)->integer()` produz rules corretas
- [ ] `Field::currency('price')->prefix('$')->decimals(2)` serializa com props formatação
- [ ] Validation client-side respeita min/max/step
- [ ] Testes cobrem: integer, float, currency formatting

**Notas de implementação**

- PT-BR typicamente usa `R$`, separador milhar `.`, decimal `,`. Criar macro `Field::priceBRL` útil.
- `decimals(2)` aplica cast `decimal:2` automaticamente no Eloquent? Não — Field não controla model casts. User ainda precisa definir em `$casts`.

---

### [FIELDS-006] `BooleanField` e `ToggleField`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [FIELDS-003]

**Contexto**

Duas variants visuais da mesma funcionalidade: checkbox vs switch/toggle.

**Descrição técnica**

- `BooleanField.php`:
  - `type = 'boolean'`, `component = 'Checkbox'`
  - `inline()`: label inline com checkbox vs empilhado
  - Default value tipicamente `false`
- `ToggleField.php`:
  - Extends BooleanField
  - `type = 'toggle'`, `component = 'Toggle'`
  - `onColor(string)`, `offColor(string)` para customização visual
  - `onIcon(string)`, `offIcon(string)` ícones opcionais

**Critérios de aceite**

- [ ] `Field::boolean('is_active')` serializa como checkbox
- [ ] `Field::toggle('is_published')` serializa como toggle com defaults
- [ ] `default(true)` define default corretamente
- [ ] Casts Eloquent `boolean` funciona automaticamente

**Notas de implementação**

- React components diferentes (Checkbox.tsx vs Toggle.tsx) — ambos manipulam boolean state.
- ToggleField é sugar semântico — pode até ser macro ao invés de classe, mas separar ajuda no IDE/docs.

---

### [FIELDS-007] `SelectField` com options estáticas e relationship

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [FIELDS-003]

**Contexto**

Dos fields mais complexos. Suporta options estáticas, dinâmicas (closure), via relationship Eloquent, com busca, criação inline.

**Descrição técnica**

`SelectField.php`:

```php
final class SelectField extends Field
{
    protected string $type = 'select';
    protected string $component = 'SelectInput';
    
    protected array|\Closure|null $options = null;
    protected ?string $optionsRelation = null;
    protected ?string $optionsRelationDisplay = null;
    protected ?\Closure $optionsRelationQuery = null;
    protected bool $searchable = false;
    protected bool $multiple = false;
    protected bool $native = true;
    protected bool $creatable = false;
    protected ?\Closure $createUsing = null;
    
    public function options(array|\Closure $options): static;
    public function optionsRelationship(string $relation, string $display, ?\Closure $query = null): static;
    public function searchable(bool $searchable = true): static;
    public function native(bool $native = true): static;
    public function multiple(bool $multiple = true): static;
    public function createable(bool $create = true): static;
    public function createOptionUsing(\Closure $callback): static;
    public function allowCustomValues(bool $allow = true): static;
    
    public function getTypeSpecificProps(): array
    {
        return [
            'options' => $this->resolveOptions(),
            'searchable' => $this->searchable,
            'multiple' => $this->multiple,
            'native' => $this->native,
            'creatable' => $this->creatable,
            'createRoute' => $this->creatable ? $this->buildCreateRoute() : null,
        ];
    }
    
    private function resolveOptions(): array
    {
        if ($this->options instanceof \Closure) {
            return $this->normalizeOptions(($this->options)());
        }
        if ($this->optionsRelation) {
            $query = \App::make($this->ownerResource::getModel())
                ->{$this->optionsRelation}()
                ->getRelated()
                ->newQuery();
            if ($this->optionsRelationQuery) {
                $query = ($this->optionsRelationQuery)($query);
            }
            return $query->pluck($this->optionsRelationDisplay, 'id')->all();
        }
        return $this->options ?? [];
    }
}
```

`MultiSelectField` e `RadioField` estendem ou configuram SelectField.

**Critérios de aceite**

- [ ] Options estáticas: `Field::select('status')->options(['draft' => 'Draft', 'published' => 'Published'])`
- [ ] Options via closure: `Field::select('cat')->options(fn() => Category::pluck('name', 'id')->all())`
- [ ] Options via relationship: `Field::select('cat')->optionsRelationship('category', 'name')`
- [ ] Searchable: props incluem `searchable: true`, frontend usa combobox
- [ ] Multiple: serializa como array, Eloquent cast `array` funciona
- [ ] Creatable com callback: `createOptionUsing(fn($name) => Category::create(['name' => $name]))` gera endpoint POST
- [ ] Testes cobrem: todos os modos options, search, multiple, creatable

**Notas de implementação**

- `optionsRelationship` requer contexto do owner Resource para introspeção Eloquent. Definir via setter injection no controller quando field é serializado.
- Creatable gera rota POST dinâmica: `/admin/{resource}/fields/{field}/create-option`.
- Performance: options de relationship grande (1000+ records) deve usar server-side search, não client-side.

---

### [FIELDS-008] `BelongsToField` e `HasManyField` (readonly)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [FIELDS-007]

**Contexto**

Integração com Eloquent relationships. Ver `05-api-php.md` §2.3.

**Descrição técnica**

`BelongsToField.php`:

```php
final class BelongsToField extends Field
{
    protected string $type = 'belongsTo';
    protected string $component = 'BelongsToInput';
    
    protected string $relatedResource;
    protected ?string $relationshipName = null;
    protected array $searchColumns = [];
    protected bool $preload = false;
    protected bool $searchable = true;
    protected ?\Closure $optionLabel = null;
    protected ?\Closure $relationQuery = null;
    protected ?Form $createOptionForm = null;
    
    public function __construct(string $name, string $relatedResource)
    {
        parent::__construct($name);
        $this->relatedResource = $relatedResource;
        $this->relationshipName = Str::before($name, '_id') ?: $name;
        $this->searchColumns = [$this->getRecordTitleAttribute()];
    }
    
    public function searchable(bool $searchable = true): static;
    public function preload(bool $preload = true): static;
    public function searchColumns(array $columns): static;
    public function optionLabel(\Closure $callback): static;
    public function relationship(string $relation, string $display, ?\Closure $query = null): static;
    public function createOptionForm(\Closure|Form $form): static;
    
    public function getTypeSpecificProps(): array
    {
        return [
            'relatedResource' => $this->relatedResource,
            'searchRoute' => $this->buildSearchRoute(),
            'searchColumns' => $this->searchColumns,
            'preload' => $this->preload,
            'preloadedOptions' => $this->preload ? $this->loadOptions() : null,
            'optionLabel' => $this->optionLabel ? $this->serializeOptionLabel() : null,
            'createRoute' => $this->createOptionForm ? $this->buildCreateOptionRoute() : null,
        ];
    }
}
```

`HasManyField.php` (Fase 1 = readonly apenas):

- Renderiza tabela inline dos related records
- Não permite criar/editar relacionados em Fase 1 (Fase 2: Repeater)
- `canAdd(bool)`, `canEdit(bool)` stubs para futura expansão

**Critérios de aceite**

- [ ] `Field::belongsTo('author', UserResource::class)` produz props corretos
- [ ] Endpoint de search `/admin/users/fields/author/search?q=...` funciona
- [ ] `preload()` inclui options iniciais em preloadedOptions
- [ ] `optionLabel(fn($u) => "{$u->name} ({$u->email})")` serializa template correto
- [ ] `createOptionForm` expõe endpoint POST
- [ ] HasMany field readonly lista related records corretamente
- [ ] Testes Feature: cobrir search, preload, createOption flow completo

**Notas de implementação**

- Search endpoint usa query builder com `LIKE` por default; para performance, recomendar Laravel Scout em docs.
- `relatedResource` precisa ser class-string — validar em runtime.
- `optionLabel` como closure é serializada para template string (`"{{name}} ({{email}})"`) — alternativa é endpoint dinâmico para label (mais custoso).

---

### [FIELDS-009] `DateField` e `DateTimeField`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [FIELDS-003]

**Contexto**

Datas são complexas por timezone, format, ranges.

**Descrição técnica**

`DateField.php`:

```php
final class DateField extends Field
{
    protected string $type = 'date';
    protected string $component = 'DateInput';
    
    protected string|\Closure|null $minDate = null;
    protected string|\Closure|null $maxDate = null;
    protected string $format = 'Y-m-d';
    protected string $displayFormat = 'd/m/Y';
    protected bool $closeOnSelect = true;
    protected ?string $timezone = null;
    
    public function minDate(string|\Closure $date): static;
    public function maxDate(string|\Closure $date): static;
    public function format(string $format): static;
    public function displayFormat(string $format): static;
    public function closeOnDateSelection(bool $close = true): static;
    public function timezone(string $tz): static;
}
```

`DateTimeField.php`: extends DateField, adiciona:
- `seconds(bool)`: mostrar segundos
- `format = 'Y-m-d H:i:s'` default

**Critérios de aceite**

- [ ] `Field::date('birthday')->maxDate(now())` serializa ISO date
- [ ] Closures em `minDate(fn() => now())` são avaliadas server-side
- [ ] Format `d/m/Y` é respeitado no client
- [ ] Timezone aplicado corretamente (conversão UTC ↔ user TZ)
- [ ] Testes cobrem: validação, conversão TZ, format

**Notas de implementação**

- Usar Carbon internamente (já dep Laravel).
- DatePicker client-side usa `react-day-picker` ou similar (configurado em FIELDS-JS).
- TZ: servidor sempre UTC, client mostra TZ user. Configurável via `timezone()` método.

---

### [FIELDS-010] `FileField` e `ImageField`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [FIELDS-003]

**Contexto**

Upload é crítico em admin panels. Suportar local, S3, GCS via Laravel Flysystem.

**Descrição técnica**

`FileField.php`:

```php
final class FileField extends Field
{
    protected string $type = 'file';
    protected string $component = 'FileInput';
    
    protected string $disk = 'local';
    protected ?string $directory = null;
    protected string $visibility = 'private';
    protected ?int $maxSize = null;  // KB
    protected array $acceptedFileTypes = [];
    protected bool $multiple = false;
    protected bool $reorderable = false;
    protected ?string $strategy = 'direct';  // 'direct' | 'spatie-media-library' | 'presigned'
    
    public function disk(string $disk): static;
    public function directory(string $dir): static;
    public function visibility(string $v): static;
    public function maxSize(int $kb): static;
    public function acceptedFileTypes(array $mimes): static;
    public function multiple(bool $multiple = true): static;
    public function reorderable(bool $reorderable = true): static;
    public function using(string $strategy): static;
    
    public function handleUpload(UploadedFile $file): string;
    public function handleDelete(string $path): void;
}
```

`ImageField.php`: extends FileField com:
- `acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])` default
- `imageCropAspectRatio(string)`: ex '1:1', '16:9'
- `imageResizeTargetWidth(int)`: resize no upload
- `component = 'ImageInput'`

**Critérios de aceite**

- [ ] `Field::file('doc')->disk('s3')->maxSize(5120)` produz props e validação correta
- [ ] `Field::image('avatar')->imageCropAspectRatio('1:1')` inclui crop config
- [ ] Upload endpoint funciona: `POST /admin/{resource}/fields/{field}/upload`
- [ ] Validação mime types no server
- [ ] `multiple()` retorna array de paths
- [ ] `reorderable()` preserva ordem
- [ ] Strategy 'spatie-media-library' requer pacote (via `suggest`)
- [ ] Testes cobrem: upload single/multiple, validation, delete, disk

**Notas de implementação**

- Upload direct: PHP recebe, grava via Storage::put(). Simples mas limita tamanho.
- Presigned URL: gera URL S3, client uploads direto. Complexo mas escalável. (Fase 2 ou 3).
- Spatie Media Library: melhor para features (responsive images, conversions). Opt-in.
- Image crop client-side via react-image-crop ou similar.

---

### [FIELDS-011] `ColorField`, `HiddenField`, `SlugField`

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [FIELDS-003]

**Contexto**

Fields simples mas necessários para completar catálogo de 20 (RF-F-01).

**Descrição técnica**

- `ColorField.php`:
  - `type = 'color'`, `component = 'ColorInput'`
  - `presets(array)`: cores pré-definidas (ex: `['#FF0000', '#00FF00']`)
  - `format(string)`: 'hex', 'rgb', 'hsl'
  - `alpha(bool)`: suporte opacity
- `HiddenField.php`:
  - `type = 'hidden'`, `component = 'HiddenInput'`
  - Renderiza `<input type="hidden">` — útil para passing IDs
  - Não tem label visual
- `SlugField.php` (já esboçado em FIELDS-004):
  - `fromField(string)`: auto-gerar de outro field
  - `separator(string = '-')`
  - `maxLength(int = 60)` default
  - `reservedSlugs(array)`: evitar conflitos
  - `unique(string $modelClass)`: validação Eloquent

**Critérios de aceite**

- [ ] `Field::color('brand_color')->presets(['#000', '#fff'])` props incluem presets
- [ ] `Field::hidden('team_id')` não renderiza label no React
- [ ] `Field::slug('slug')->fromField('title')` reacts a title changes via `live()`
- [ ] Testes cobrem cada tipo

**Notas de implementação**

- ColorField client-side usa `react-colorful` (leve, sem deps).
- SlugField client-side escuta mudanças em `fromField` e atualiza slug — requer dependency tracking (Fase 1 precisa suportar).

---

### [FIELDS-012] `ValidationBridge` — Laravel rules → Zod schema

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [FIELDS-002]

**Contexto**

Source of truth de validação é Laravel (RNF-Q). Client-side Zod é espelho. Bridge traduz.

**Descrição técnica**

Criar `src/ValidationBridge.php`:

```php
final class ValidationBridge
{
    /** @var array<string, \Closure> */
    private static array $rules = [];
    
    public static function register(string $rule, \Closure $translator): void;
    public static function translate(array $rules): string;  // Serialized Zod schema

    // Built-in translators
    public static function bootBuiltins(): void;
}
```

Built-in translators para rules comuns:

```php
// 'required' → '.min(1, { message: "Required" })' após tipo
// 'string' → 'z.string()'
// 'email' → 'z.string().email()'
// 'max:255' → '.max(255)'
// 'min:3' → '.min(3)'
// 'numeric' → 'z.number()'
// 'integer' → 'z.number().int()'
// 'boolean' → 'z.boolean()'
// 'url' → 'z.string().url()'
// 'date' → 'z.string().datetime()'
// 'in:a,b,c' → 'z.enum(["a", "b", "c"])'
// 'nullable' → '.nullable()'
// 'regex:/pattern/' → '.regex(/pattern/)'
// 'unique:users,email' → '.refine(async (val) => !(await checkUnique("users", "email", val)))'
```

Output: string representando Zod schema que pode ser `eval`'d ou construído no client.

**Critérios de aceite**

- [ ] `translate(['required', 'string', 'max:255'])` retorna `'z.string().min(1).max(255)'`
- [ ] `translate(['email', 'nullable'])` retorna `'z.string().email().nullable()'`
- [ ] `translate(['in:draft,published'])` retorna `'z.enum(["draft", "published"])'`
- [ ] Rules custom registradas funcionam
- [ ] Rules desconhecidas não crasham (log warning, skip)
- [ ] Unique rule produz async refinement (server round-trip)
- [ ] Testes cobrem >20 combinações

**Notas de implementação**

- **Alternativa considerada:** não gerar Zod schema, deixar validação só server-side e apresentar erros via Inertia. Rejeitado porque UX inferior (só valida on submit).
- String resultante é parseada no client usando `new Function(schemaStr)` (com confiança — é gerada server-side, não input user).
- Alternativa mais segura: mandar árvore JSON e reconstruir Zod no client. Mais complexo mas safer. Decidir em RFC interno.

---

### [FIELDS-013] Testes do pacote FIELDS (unitários e feature)

**Tipo:** test • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [FIELDS-012]

**Contexto**

Fields é o pacote com mais API surface. Cobertura robusta crítica.

**Descrição técnica**

- Tests/Unit/:
  - `FieldFactoryTest.php`
  - `TextFieldTest.php`, `NumberFieldTest.php`, ..., um por tipo
  - `ValidationBridgeTest.php`
  - `FieldSerializationTest.php` (snapshot tests)
- Tests/Feature/:
  - `BelongsToSearchTest.php` (endpoint search)
  - `FileUploadTest.php`
  - `CreateOptionTest.php`
- Cobertura mínima: 90% (acima do global 85%)
- Snapshot tests de cada tipo serializado (20 snapshots)

**Critérios de aceite**

- [ ] `vendor/bin/pest packages/fields/tests` passa
- [ ] Coverage ≥ 90%
- [ ] 20 snapshots documentam shape JSON de cada field type
- [ ] Feature tests cobrem endpoints (search, upload, create-option)
- [ ] Zero flaky tests (retry 3x estável)

**Notas de implementação**

- Snapshot files em `tests/Snapshots/`.
- Feature tests usam Orchestra Testbench + fixture models.
- Para `FileUploadTest`, usar `UploadedFile::fake()` + `Storage::fake()`.

---

### [FIELDS-014] SKILL.md do pacote fields

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** S • **Camada:** docs • **Depende de:** [FIELDS-013]

**Contexto**

Similar a CORE-015.

**Descrição técnica**

Criar `packages/fields/SKILL.md` seguindo estrutura canônica. Incluir:
- Tabela completa dos 20 field types com exemplo de uso cada um
- Seção "Creating custom fields" com exemplo passo-a-passo
- Seção "Macros" com exemplo real (priceBRL)
- Anti-patterns: "Don't mutate field state directly", "Don't skip validation", "Don't use Field::register in non-ServiceProvider code"

**Critérios de aceite**

- [ ] SKILL.md documentado com todos os 20 types
- [ ] Exemplos copy-pasteáveis
- [ ] Links para ticket de cada tipo no planejamento

**Notas de implementação**

- Manter alinhado com `05-api-php.md` §2 — se divergir, atualizar ambos.

---

### [FIELDS-015] Trait `HasValidation` em Concerns

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [FIELDS-002]

**Contexto**

Trait compartilhada para todos os fields que têm validação (praticamente todos).

**Descrição técnica**

Criar `src/Concerns/HasValidation.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Fields\Concerns;

use Closure;

trait HasValidation
{
    /** @var array<string|object|Closure> */
    protected array $validationRules = [];
    protected array $validationMessages = [];
    protected array $validationAttributes = [];

    public function required(bool|Closure $required = true): static
    {
        $this->addRule('required', $required);
        return $this;
    }

    public function nullable(): static
    {
        $this->addRule('nullable');
        return $this;
    }

    public function rules(array $rules): static
    {
        foreach ($rules as $rule) {
            $this->addRule($rule);
        }
        return $this;
    }

    public function rule(string|object $rule, ?string $message = null): static
    {
        $this->addRule($rule);
        if ($message) {
            $this->validationMessages[$this->normalizeRuleKey($rule)] = $message;
        }
        return $this;
    }

    public function unique(
        ?string $table = null,
        ?string $column = null,
        mixed $ignorable = null,
    ): static {
        // Build Laravel Rule::unique() dynamically
        return $this->rule(new UniqueRuleBuilder($this, $table, $column, $ignorable));
    }

    public function maxLength(int $max): static;
    public function minLength(int $min): static;
    public function requiredIf(string $otherField, mixed $value): static;
    public function requiredWith(string|array $otherFields): static;
    public function requiredWithout(string|array $otherFields): static;
    public function validationAttribute(string $attribute): static;
    public function validationMessage(string $rule, string $message): static;

    public function getValidationRules(): array
    {
        return array_map(
            fn ($rule) => $rule instanceof Closure ? $rule() : $rule,
            $this->validationRules
        );
    }

    public function getValidationMessages(): array;
    public function getValidationAttribute(): ?string;

    protected function addRule(string|object|Closure $rule, bool|Closure $conditional = true): void
    {
        if (is_bool($conditional) && !$conditional) {
            return;
        }
        // Dedupe by normalized key
        $key = $this->normalizeRuleKey($rule);
        $this->validationRules[$key] = $rule;
    }

    private function normalizeRuleKey(string|object|Closure $rule): string;
}
```

**Critérios de aceite**

- [ ] `->required()` adiciona rule 'required' idempotentemente
- [ ] `->required(false)` não adiciona rule
- [ ] `->required(fn ($record) => $record->is_published)` avalia closure no submit
- [ ] `->unique('users', 'email', $this->record)` gera Rule::unique() com ignore
- [ ] `->rules(['min:3', 'max:255'])` adiciona ambas
- [ ] `->validationMessage('required', 'Custom message')` sobrescreve message default
- [ ] Getter retorna rules resolvidas (closures avaliadas)

**Notas de implementação**

- `UniqueRuleBuilder` é helper interno que constrói `Rule::unique()->ignore()` lazy (precisa de `$record` atual).
- Mensagens custom têm precedência sobre defaults Laravel.
- Rules como objetos (ex: `new Password(8)`) precisam ser preservadas, não stringified.

---

### [FIELDS-016] Trait `HasVisibility` em Concerns

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [FIELDS-002]

**Contexto**

Controla em quais contextos (create/edit/detail/table) o field aparece.

**Descrição técnica**

Criar `src/Concerns/HasVisibility.php`:

```php
trait HasVisibility
{
    protected bool $hiddenOnCreate = false;
    protected bool $hiddenOnEdit = false;
    protected bool $hiddenOnDetail = false;
    protected bool $hiddenOnTable = false;
    protected ?Closure $visibleIf = null;
    protected ?Closure $hiddenIf = null;

    public function hidden(bool|Closure $hidden = true): static;
    public function hiddenOnCreate(bool $hidden = true): static;
    public function hiddenOnEdit(bool $hidden = true): static;
    public function hiddenOnDetail(bool $hidden = true): static;
    public function hiddenOnTable(bool $hidden = true): static;
    public function visibleOn(string|array $contexts): static;
    public function hiddenOn(string|array $contexts): static;
    public function visibleIf(Closure $callback): static;
    public function hiddenIf(Closure $callback): static;

    public function isVisibleIn(string $context, ?Model $record = null): bool;
}
```

**Critérios de aceite**

- [ ] `->hiddenOnCreate()` oculta field em create page
- [ ] `->visibleOn(['edit', 'detail'])` mostra só nesses contextos
- [ ] `->visibleIf(fn ($record) => $record->is_admin)` avalia closure com record context
- [ ] `isVisibleIn('create')` retorna bool correto
- [ ] Testes cobrem combinações (visibleIf + hiddenOn, etc.)

**Notas de implementação**

- Contextos válidos: `'create'`, `'edit'`, `'detail'`, `'table'`.
- `visibleIf` e `hiddenIf` não podem ser ambos definidos — avisar com exception clara.

---

### [FIELDS-017] Trait `HasDependencies` — fields reativas

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [FIELDS-002]

**Contexto**

Cobre RF-F-06 (conditional/dependent fields). Essencial para UX moderna.

**Descrição técnica**

Criar `src/Concerns/HasDependencies.php`:

```php
trait HasDependencies
{
    /** @var array<string> */
    protected array $dependencies = [];
    protected ?Closure $afterStateUpdated = null;
    protected ?Closure $resolveOptionsCallback = null;

    public function dependsOn(string|array $fields): static
    {
        $this->dependencies = array_merge(
            $this->dependencies,
            is_array($fields) ? $fields : [$fields]
        );
        return $this;
    }

    public function afterStateUpdated(Closure $callback): static
    {
        $this->afterStateUpdated = $callback;
        return $this;
    }

    public function resolveOptionsUsing(Closure $callback): static
    {
        $this->resolveOptionsCallback = $callback;
        return $this;
    }

    public function getDependencies(): array;
    public function hasDependencies(): bool;
    public function handleDependencyUpdate(array $formState, string $changedField): array;
}
```

E endpoint backend para handling:

- `POST /admin/{resource}/fields/{field}/refresh` — recebe estado form atual, retorna novas options/visibility para o field

**Critérios de aceite**

- [ ] `Field::select('state')->dependsOn(['country_id'])->resolveOptionsUsing(fn($state) => State::where('country_id', $state['country_id'])->pluck('name', 'id'))` funciona
- [ ] Endpoint refresh retorna novas options corretas
- [ ] React faz partial reload quando dependency muda
- [ ] `afterStateUpdated` closure é chamada quando field muda
- [ ] Testes Feature: flow completo de dependent fields

**Notas de implementação**

- No React (ver `06-api-react.md` §9.7), `useFieldDependencies` hook escuta mudanças e faz Inertia partial reload.
- Debounce default: 300ms para evitar thrashing durante digitação.
- Se dependency cycle detectado (A depende B, B depende A), lançar exception em boot.

---

### [FIELDS-018] Trait `HasAuthorization` — canSee/canEdit per-field

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [FIELDS-002]

**Contexto**

Cobre RF-F-09 e RF-AU-03. Field-level authorization server-side.

**Descrição técnica**

Criar `src/Concerns/HasAuthorization.php`:

```php
trait HasAuthorization
{
    protected ?Closure $canSeeCallback = null;
    protected ?Closure $canEditCallback = null;

    public function canSee(Closure $callback): static
    {
        $this->canSeeCallback = $callback;
        return $this;
    }

    public function canEdit(Closure $callback): static
    {
        $this->canEditCallback = $callback;
        return $this;
    }

    public function canBeSeenBy(?Authenticatable $user, ?Model $record = null): bool
    {
        if (!$this->canSeeCallback) {
            return true;
        }
        return (bool) ($this->canSeeCallback)($user, $record);
    }

    public function canBeEditedBy(?Authenticatable $user, ?Model $record = null): bool
    {
        if (!$this->canEditCallback) {
            return true;
        }
        return (bool) ($this->canEditCallback)($user, $record);
    }
}
```

**Critérios de aceite**

- [ ] `->canSee(fn ($user) => $user->isAdmin())` oculta field para não-admins
- [ ] `->canEdit(fn ($user, $record) => $user->id === $record->owner_id)` torna readonly para outros
- [ ] Fields não visíveis não são serializados em resposta Inertia (enforcement server-side)
- [ ] Testes cobrem: happy path + edge cases (user null, record null)

**Notas de implementação**

- **Crítico:** não-seralização é enforcement real — readonly via React é UX only.
- Fields hidden por `canSee` ainda fazem parte do schema em serialization? Não — remover completamente.
- Para debugging: log em dev mode quando field é hidden por authorization.

---

### [FIELDS-019] Eager loading automático baseado em fields

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [FIELDS-008]

**Contexto**

Cobre RF-P-03 e ADR-003 §Consequences. N+1 queries em admin panels são killer de performance.

**Descrição técnica**

- Criar `src/EagerLoadingResolver.php`:
  ```php
  final class EagerLoadingResolver
  {
      /**
       * @param array<Field> $fields
       * @return array<string> Relations to eager load
       */
      public static function resolve(array $fields): array
      {
          $relations = [];
          foreach ($fields as $field) {
              if ($field instanceof BelongsToField) {
                  $relations[] = $field->getRelationshipName();
              }
              if ($field instanceof HasManyField) {
                  $relations[] = $field->getRelationshipName();
              }
          }
          return array_unique($relations);
      }
  }
  ```
- Integrar com `ResourceController::index()`:
  ```php
  $query = $resource->indexQuery() ?? $resource::$model::query();
  $relations = EagerLoadingResolver::resolve($resource->fields());
  if ($relations) {
      $query->with($relations);
  }
  ```
- Suportar column-level eager loading (select specific columns from relation) via `Column::relationship('author')->display('name')` (ver TABLE tickets)

**Critérios de aceite**

- [ ] Resource com 3 belongsTo fields produz query com `->with(['a', 'b', 'c'])`
- [ ] Debug bar: zero N+1 em index page de Resource típico
- [ ] User pode override via `indexQuery()` se quiser eager loading custom
- [ ] Teste: query count em index de 50 records com belongsTo é ≤ 2 (main + eager)

**Notas de implementação**

- Laravel Debugbar mede query count em tests (via `DB::listen`).
- Cuidado: eager loading de `hasMany` em index pode trazer dados massivos — só fazer para Fields declaradas.
- Column-level select (`:id,name`) reduz payload — otimização Fase 2.

---

### [FIELDS-020] Endpoint de search para BelongsToField

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [FIELDS-008]

**Contexto**

BelongsToField searchable precisa endpoint server-side para busca dinâmica.

**Descrição técnica**

- Criar `src/Http/Controllers/FieldSearchController.php`:
  ```php
  final class FieldSearchController
  {
      public function __invoke(
          Request $request,
          string $resource,
          string $field,
      ): JsonResponse {
          $resourceClass = $this->registry->findBySlug($resource);
          abort_if(!$resourceClass, 404);
          
          $resourceInstance = app($resourceClass);
          $fieldInstance = collect($resourceInstance->fields())
              ->firstWhere('name', $field);
          
          abort_if(!$fieldInstance instanceof BelongsToField, 400);
          abort_if(!$fieldInstance->isSearchable(), 403);
          
          $this->authorize('viewAny', $resourceClass::getModel());
          
          $query = $request->input('q', '');
          $results = $fieldInstance->search($query);
          
          return response()->json($results);
      }
  }
  ```
- Registrar rota em Panel:
  - `GET /admin/{resource}/fields/{field}/search`
- Rate limit: 30 req/min (evitar abuse)

**Critérios de aceite**

- [ ] `GET /admin/users/fields/role/search?q=admin` retorna JSON com opções
- [ ] Shape: `[{value: 1, label: "Administrator"}, ...]`
- [ ] Search limita a 20 resultados por default
- [ ] Policy check: user precisa `viewAny` do related model
- [ ] Throttle aplicado
- [ ] Teste: search retorna resultados relevantes para query

**Notas de implementação**

- Caching agressivo faz sentido — same query + same permissions = same result.
- Performance: usar Laravel Scout se disponível; fallback `LIKE` em search columns.

---

### [FIELDS-021] Endpoint de upload para FileField/ImageField

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [FIELDS-010]

**Contexto**

Upload precisa de endpoint dedicado. Fase 1 usa "direct upload" (client → PHP → storage).

**Descrição técnica**

- Criar `src/Http/Controllers/FieldUploadController.php`:
  ```php
  final class FieldUploadController
  {
      public function store(
          Request $request,
          string $resource,
          string $field,
      ): JsonResponse {
          // Resolve resource + field
          $fieldInstance = $this->resolveField($resource, $field);
          abort_if(!$fieldInstance instanceof FileField, 400);
          
          // Validate file
          $request->validate([
              'file' => [
                  'required',
                  'file',
                  'max:' . $fieldInstance->getMaxSize(),
                  'mimes:' . implode(',', $fieldInstance->getMimeTypes()),
              ],
          ]);
          
          // Authorize (create or update policy)
          $this->authorize(
              $request->input('context') === 'edit' ? 'update' : 'create',
              $resourceInstance::getModel()
          );
          
          // Store
          $path = $request->file('file')->store(
              $fieldInstance->getDirectory(),
              $fieldInstance->getDisk()
          );
          
          return response()->json([
              'path' => $path,
              'url' => Storage::disk($fieldInstance->getDisk())->url($path),
              'size' => $request->file('file')->getSize(),
              'originalName' => $request->file('file')->getClientOriginalName(),
          ]);
      }

      public function destroy(Request $request, string $resource, string $field): JsonResponse;
  }
  ```
- Rotas:
  - `POST /admin/{resource}/fields/{field}/upload`
  - `DELETE /admin/{resource}/fields/{field}/upload`

**Critérios de aceite**

- [ ] Upload de arquivo valid retorna 200 com path + URL
- [ ] Upload excedendo max size retorna 422 com mensagem
- [ ] Mime type inválido retorna 422
- [ ] User sem permission recebe 403
- [ ] Delete via DELETE endpoint remove do storage
- [ ] Teste Feature com `UploadedFile::fake()`

**Notas de implementação**

- ImageField adiciona processing opcional (resize, crop) após upload — Fase 2 ou via strategy.
- Para uploads >10MB, considerar chunked upload (Fase 2 via Laravel Uppy ou similar).
- Cleanup: arquivos uploaded mas nunca associados a registro ficam órfãos — job de cleanup em Fase 2.

---

### [FIELDS-022] Macros e field registry runtime

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** S • **Camada:** php • **Depende de:** [FIELDS-003]

**Contexto**

Permite users estenderem catálogo via macros (RF-F-08) e custom fields registrados (RF-F-07).

**Descrição técnica**

- Implementar `Field::macro()` e `Field::register()` em FieldFactory (stubs já em FIELDS-003)
- Criar `ArqelFields::skill()` helper que gera lista de fields disponíveis (builtins + macros + custom) para MCP Fase 2
- Scaffolder `arqel:field` (já tem stub em CORE-003 plumbing):
  - `php artisan arqel:field RichMarkdown`
  - Gera `app/Arqel/Fields/RichMarkdownField.php` com extends `Field`
  - Gera `resources/js/Arqel/Fields/RichMarkdownInput.tsx` stub
  - Adiciona linha em `ArqelServiceProvider`: `Field::register('richMarkdown', RichMarkdownField::class)`

**Critérios de aceite**

- [ ] `Field::macro('priceBRL', fn($n) => Field::currency($n)->prefix('R$')->decimalSeparator(','))` permite `Field::priceBRL('amount')`
- [ ] `Field::register('custom', CustomField::class)` permite `Field::custom(...)`
- [ ] `php artisan arqel:field RichMarkdown` gera arquivos corretos
- [ ] ServiceProvider registration é auto-adicionada quando possível
- [ ] Testes cobrem: macro registration, custom field registration, scaffold

**Notas de implementação**

- Inserção automática em ServiceProvider é frágil — melhor gerar instrução em output do comando para user copiar.
- Macros ficam em memória (não persistem) — precisam ser re-registradas a cada boot.

---

## 5. Pacote TABLE

### [TABLE-001] Esqueleto do pacote `arqel-dev/table`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [CORE-008]

**Contexto**

Pacote de tabelas. Cobre RF-T-01 até RF-T-06 em Fase 1.

**Descrição técnica**

Estrutura `packages/table/`:

- `composer.json` (dep: `arqel-dev/core`)
- `src/Table.php` (main builder)
- `src/Column.php` (abstract base)
- `src/Columns/` (tipos)
- `src/Filters/` (tipos)
- `src/TableQueryBuilder.php` (aplicador de sort/filter/search a Eloquent query)
- `src/TablePaginator.php` (wrapper serializable)
- `src/Concerns/` (traits reutilizáveis)
- `src/TableServiceProvider.php`
- `SKILL.md`, `README.md`
- `tests/`

**Critérios de aceite**

- [ ] composer valida, install resolve
- [ ] ServiceProvider discovered
- [ ] SKILL.md esqueleto

**Notas de implementação**

- Table é pacote "pesado" — 13 tickets. Dividir work entre dois devs viável.

---

### [TABLE-002] Classe `Table` builder fluent

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [TABLE-001]

**Contexto**

API principal de tabela. Ver `05-api-php.md` §1.1 exemplo em Resource::table().

**Descrição técnica**

Criar `src/Table.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Table;

use Arqel\Actions\Action;
use Arqel\Actions\BulkAction;

final class Table
{
    /** @var array<Column> */
    protected array $columns = [];
    /** @var array<Filter> */
    protected array $filters = [];
    /** @var array<Action> */
    protected array $actions = [];
    /** @var array<BulkAction> */
    protected array $bulkActions = [];
    /** @var array<Action> */
    protected array $toolbarActions = [];
    
    protected int $defaultPerPage = 25;
    protected array $perPageOptions = [10, 25, 50, 100];
    protected ?string $defaultSortColumn = null;
    protected string $defaultSortDirection = 'desc';
    protected bool $searchable = true;
    protected bool $selectable = true;
    protected ?string $emptyStateHeading = null;
    protected ?string $emptyStateDescription = null;
    protected ?string $emptyStateIcon = null;
    
    public function columns(array $columns): static;
    public function filters(array $filters): static;
    public function actions(array $actions): static;
    public function bulkActions(array $actions): static;
    public function toolbarActions(array $actions): static;
    public function defaultSort(string $column, string $direction = 'desc'): static;
    public function perPage(int $default, array $options = []): static;
    public function searchable(bool $searchable = true): static;
    public function selectable(bool $selectable = true): static;
    public function emptyState(string $heading, ?string $description = null, ?string $icon = null): static;
    public function striped(bool $striped = true): static;
    public function compact(bool $compact = true): static;
    
    public function getColumns(): array;
    public function getFilters(): array;
    public function getActions(): array;
    public function getBulkActions(): array;
    public function getToolbarActions(): array;
    public function toArray(Resource $resource, Request $request, ?Authenticatable $user = null): array;
}
```

`toArray()` serializa tudo para Inertia conforme `06-api-react.md` §3.1.

**Critérios de aceite**

- [ ] Fluent builder encadeia corretamente
- [ ] `toArray()` produz shape esperado para Inertia
- [ ] Columns, filters, actions, bulkActions todos expostos
- [ ] Default sort aplicado em query quando não há sort no request
- [ ] Testes cobrem fluent API + serialização

**Notas de implementação**

- `toArray()` recebe Resource para contexto (policies, record title, etc.).
- Serialização respeita field-level authorization de columns (canSee).

---

### [TABLE-003] Classe `Column` abstract + tipos básicos

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [TABLE-002]

**Contexto**

Column types: text, number, date, badge, boolean. 8 tipos totais em Fase 1.

**Descrição técnica**

Criar `src/Column.php` (abstract):

```php
abstract class Column
{
    protected string $type;
    protected string $name;
    protected ?string $label = null;
    protected bool $sortable = false;
    protected bool $searchable = false;
    protected bool $copyable = false;
    protected bool $hidden = false;
    protected bool $hiddenOnMobile = false;
    protected ?string $alignment = null;
    protected ?string $width = null;
    protected ?Closure $formatStateUsing = null;
    protected ?Closure $badgeColor = null;
    protected ?Closure $icon = null;
    protected ?Closure $tooltip = null;
    protected ?Closure $url = null;
    protected bool $openUrlInNewTab = false;
    protected ?Closure $canSee = null;
    
    // Static factories in concrete subclasses or via Column::text(), etc.
    
    public function label(string $label): static;
    public function sortable(bool $sortable = true): static;
    public function searchable(bool $searchable = true): static;
    public function copyable(bool $copyable = true): static;
    public function hidden(bool $hidden = true): static;
    public function alignStart(): static;
    public function alignCenter(): static;
    public function alignEnd(): static;
    public function width(string $width): static;
    public function formatStateUsing(Closure $callback): static;
    public function url(Closure|string $url, bool $newTab = false): static;
    public function canSee(Closure $callback): static;
    
    abstract public function toArray(): array;
}
```

Tipos em `src/Columns/`:

- `TextColumn.php`:
  - `limit(int)`: max chars before "..."
  - `wrap()`: allow line wrap
  - `copyable()`: hover → copy button
  - `fontFamily(string)`: monospace para códigos

- `BadgeColumn.php`:
  - `colors(array)`: map value → color ('draft' => 'gray')
  - `icons(array)`: map value → icon

- `BooleanColumn.php`:
  - `trueIcon(string)`, `falseIcon(string)` defaults check/x
  - `trueColor(string)`, `falseColor(string)`

- `DateColumn.php`:
  - `date(string)`: format, default 'Y-m-d'
  - `dateTime(string)`
  - `since()`: format "2 hours ago"

- `NumberColumn.php`:
  - `money(string $currency)`: format as money
  - `suffix(string)`, `prefix(string)`

- `IconColumn.php`:
  - Valor é ícone name
  - `options(array)`: map value → icon

- `ImageColumn.php`:
  - `disk(string)`, `directory(string)`
  - `circular()`, `square()`, `size(int)`
  - Preview em hover

- `RelationshipColumn.php`:
  - `display(string)`: column do related model
  - Implícito `->sortable()` usa join

- `ComputedColumn.php`:
  - `getStateUsing(Closure)`: valor computado runtime
  - Não sortable por default (requires DB-level computation)

**Critérios de aceite**

- [ ] 8 column types implementados
- [ ] `Column::text('name')->sortable()->searchable()` funciona
- [ ] Sort server-side: `?sort=name&direction=asc` aplica
- [ ] Search server-side: `?search=john` aplica LIKE em searchable columns
- [ ] Format callbacks são chamados com `$state` e `$record`
- [ ] Hidden columns não são renderizadas
- [ ] Testes cobrem cada tipo + serialização

**Notas de implementação**

- `Column::text('email')->copyable()` mostra hover button copy no React.
- `RelationshipColumn` requer JOIN para sortable — usar `LEFT JOIN` para não perder registros.
- `ComputedColumn` sortable requer DB expression — documentar limitação.

---

### [TABLE-004] `Filter` classes — Select, DateRange, Text, Ternary

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [TABLE-002]

**Contexto**

Filtros comuns: select (um valor), multi-select, date range, text, ternary (true/false/all).

**Descrição técnica**

Criar `src/Filter.php` (abstract):

```php
abstract class Filter
{
    protected string $type;
    protected string $name;
    protected ?string $label = null;
    protected mixed $default = null;
    protected bool $persist = true;
    protected ?Closure $applyCallback = null;
    protected ?Closure $canSee = null;
    
    public function label(string $label): static;
    public function default(mixed $value): static;
    public function persist(bool $persist = true): static;
    public function apply(Closure $callback): static;
    public function canSee(Closure $callback): static;
    
    abstract public function applyToQuery(Builder $query, mixed $value): Builder;
    abstract public function toArray(): array;
}
```

Tipos em `src/Filters/`:

- `SelectFilter.php`:
  ```php
  public function options(array|Closure $options): static;
  public function optionsRelationship(string $relation, string $display): static;
  ```
  - Renderiza como dropdown
  - Filtra `where($column, $value)` por default

- `MultiSelectFilter.php`: como SelectFilter mas `whereIn()`

- `DateRangeFilter.php`:
  ```php
  public function minDate(string|Closure $date): static;
  public function maxDate(string|Closure $date): static;
  ```
  - Renderiza como date range picker
  - Filtra `whereBetween($column, [$from, $to])`

- `TextFilter.php`:
  - Input texto
  - Filtra `where($column, 'LIKE', "%$value%")`

- `TernaryFilter.php`:
  - 3 estados: true, false, all (null)
  - Renderiza como tri-state toggle ou radio
  - Útil para boolean columns

- `ScopeFilter.php`:
  - Aplica scope Eloquent existente
  - `Filter::make('published')->scope('published')`

**Critérios de aceite**

- [ ] 6 filter types implementados
- [ ] `SelectFilter::make('role_id')->options([1 => 'Admin', 2 => 'User'])` funciona end-to-end
- [ ] Multi URL params: `?filter[role_id][]=1&filter[role_id][]=2`
- [ ] DateRange: `?filter[created_at][from]=2026-01-01&filter[created_at][to]=2026-12-31`
- [ ] Ternary: `?filter[is_active]=true|false|all`
- [ ] Persist em URL (query string)
- [ ] Custom apply callback sobrescreve lógica default
- [ ] Testes Feature: cada filter type

**Notas de implementação**

- Persist em localStorage é UI concern — fora de escopo backend.
- `optionsRelationship` requer contexto Resource — inject via setter no controller.

---

### [TABLE-005] `TableQueryBuilder` — sort/filter/search/pagination

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [TABLE-003, TABLE-004]

**Contexto**

Orquestrador que aplica todos os modificadores de query baseado no Request.

**Descrição técnica**

Criar `src/TableQueryBuilder.php`:

```php
final class TableQueryBuilder
{
    public function __construct(
        private Table $table,
        private Builder $query,
        private Request $request,
    ) {}

    public function build(): LengthAwarePaginator
    {
        $this->applySearch();
        $this->applyFilters();
        $this->applySort();
        $this->applyEagerLoading();
        return $this->paginate();
    }

    private function applySearch(): void
    {
        $search = $this->request->input('search');
        if (!$search || !$this->table->isSearchable()) return;
        
        $searchableColumns = $this->getSearchableColumnNames();
        $this->query->where(function (Builder $q) use ($searchableColumns, $search) {
            foreach ($searchableColumns as $column) {
                $q->orWhere($column, 'LIKE', "%{$search}%");
            }
        });
    }

    private function applyFilters(): void
    {
        $filters = $this->request->input('filter', []);
        foreach ($this->table->getFilters() as $filter) {
            $value = $filters[$filter->getName()] ?? $filter->getDefault();
            if ($value === null || $value === '') continue;
            $filter->applyToQuery($this->query, $value);
        }
    }

    private function applySort(): void
    {
        $sortColumn = $this->request->input('sort', $this->table->getDefaultSortColumn());
        $sortDirection = $this->request->input('direction', $this->table->getDefaultSortDirection());
        if (!$sortColumn) return;
        
        $column = collect($this->table->getColumns())->firstWhere('name', $sortColumn);
        if (!$column || !$column->isSortable()) return;
        
        $this->query->orderBy($sortColumn, $sortDirection);
    }

    private function applyEagerLoading(): void
    {
        $relations = EagerLoadingResolver::resolveForColumns($this->table->getColumns());
        if ($relations) {
            $this->query->with($relations);
        }
    }

    private function paginate(): LengthAwarePaginator
    {
        $perPage = $this->request->input('per_page', $this->table->getDefaultPerPage());
        return $this->query->paginate($perPage)->withQueryString();
    }
}
```

**Critérios de aceite**

- [ ] Search cross-columns funciona (OR)
- [ ] Sort aplicado com validação (prevent SQL injection)
- [ ] Filters aplicadas corretamente
- [ ] Pagination com `withQueryString` preserva filters em links
- [ ] Eager loading adicional de relationship columns
- [ ] Teste: request com todos modificadores simultâneos produz query esperada

**Notas de implementação**

- Validar sort column contra whitelist (apenas columns marcadas sortable).
- Search deve ser case-insensitive em PostgreSQL via `ILIKE`.
- Paginator customizar per-page options via `perPageOptions`.

---

### [TABLE-006] Integração Table com ResourceController

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [TABLE-005, CORE-006]

**Contexto**

ResourceController precisa invocar Table build e passar para Inertia.

**Descrição técnica**

Atualizar `ResourceController::index()`:

```php
public function index(Request $request, string $resource): Response
{
    $resourceClass = $this->registry->findBySlug($resource);
    abort_if(!$resourceClass, 404);
    
    $this->authorize('viewAny', $resourceClass::getModel());
    
    $resourceInstance = app($resourceClass);
    $table = $resourceInstance->table(new Table());
    
    $query = $resourceInstance->indexQuery() ?? $resourceClass::$model::query();
    $paginator = (new TableQueryBuilder($table, $query, $request))->build();
    
    return Inertia::render('arqel::resource-index', [
        'resource' => $this->dataBuilder->resourceMeta($resourceClass),
        'records' => $paginator,
        'columns' => array_map(fn ($c) => $c->toArray(), $table->getColumns()),
        'filters' => array_map(fn ($f) => $f->toArray(), $table->getFilters()),
        'actions' => [
            'row' => $this->serializeActions($table->getActions(), $request->user()),
            'bulk' => $this->serializeActions($table->getBulkActions(), $request->user()),
            'toolbar' => $this->serializeActions($table->getToolbarActions(), $request->user()),
        ],
        'search' => $request->input('search'),
        'sort' => [
            'column' => $request->input('sort', $table->getDefaultSortColumn()),
            'direction' => $request->input('direction', $table->getDefaultSortDirection()),
        ],
        'selectedIds' => [],
        'can' => [
            'create' => Gate::check('create', $resourceClass::getModel()),
            'viewAny' => true,
        ],
    ]);
}
```

**Critérios de aceite**

- [ ] `GET /admin/users` renderiza com props de ResourceIndexProps (6-api-react.md §3.1)
- [ ] Query string `?search=john&sort=name&filter[is_active]=true` é aplicada
- [ ] Response status 200 com shape esperado
- [ ] Policy viewAny deny retorna 403
- [ ] Teste Feature: simular request com todos modificadores

**Notas de implementação**

- `serializeActions` respeita policy per-action.
- `recordTitle` e `recordSubtitle` incluídos em cada record via HTTP resource ou transformer.

---

### [TABLE-007] Ações de linha em tabela (row actions serialização)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [TABLE-006]

**Contexto**

Row actions aparecem em cada linha. Cobre RF-T-03.

**Descrição técnica**

- `Table::actions([...])` aceita array de Actions (do `arqel-dev/actions`)
- Serialização inclui resolução per-record de `authorize` (é chamado para cada row)
- Performance: se 50 rows + 5 actions = 250 authorize calls. Otimizar via Gate caching.

**Critérios de aceite**

- [ ] Actions definidas aparecem em cada row
- [ ] Authorization per-row: action hidden se policy false
- [ ] Ícone + label renderizados
- [ ] Dropdown menu quando >3 actions

**Notas de implementação**

- Detailed action implementation vem no pacote ACTIONS (ver seção 7). Aqui só a integração.

---

### [TABLE-008] Bulk actions em tabela

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [TABLE-007]

**Contexto**

Bulk actions aplicadas a múltiplos registros selecionados. Cobre RF-T-04.

**Descrição técnica**

- Endpoint `POST /admin/{resource}/bulk-actions/{action}` que recebe:
  - `ids`: array de IDs selecionados
  - Extra data do action form (se aplicável)
- Chunking automático para grandes datasets:
  ```php
  Model::whereIn('id', $ids)->chunk(100, function ($records) use ($action) {
      $action->execute($records);
  });
  ```
- Opção de queue para >1000 registros
- Progress tracking básico em Fase 1 (full real-time em Fase 3)

**Critérios de aceite**

- [ ] `POST /admin/users/bulk-actions/activate` com `ids: [1,2,3]` funciona
- [ ] Authorization check: policy `update` para cada registro
- [ ] Chunking: 500 records processados em chunks de 100
- [ ] Failure: se 1 falha, outros continuam (ou transactional conforme action config)
- [ ] Teste: bulk delete de 50 users

**Notas de implementação**

- Transaction wrapping por default (atomic) com opt-out para actions que precisam permissive mode.
- UI feedback: toast "10 users activated" em success.

---

### [TABLE-009] Toolbar actions

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** S • **Camada:** php • **Depende de:** [TABLE-002]

**Contexto**

Actions não associadas a records específicos (ex: "Import CSV", "Settings"). Cobre RF-T-03 (variants).

**Descrição técnica**

- `Table::toolbarActions([...])` aceita array de `ToolbarAction` (especialização de Action)
- ToolbarActions renderizam na top-right do table header
- Primary action (Create) é auto-adicionada se Policy create permite
- Serialização inclui authorize check

**Critérios de aceite**

- [ ] Create action auto-adicionada quando user pode create
- [ ] Custom toolbar actions renderizam na ordem
- [ ] Actions com form modal funcionam
- [ ] Test: import action com form renderiza e submete corretamente

**Notas de implementação**

- Distinguir visualmente primary (Create) de secondary (Import, Export).

---

### [TABLE-010] Search global de tabela

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [TABLE-005]

**Contexto**

Busca simples sobre múltiplas columns searchable. RF-T-06.

**Descrição técnica**

- Já implementado em `TableQueryBuilder::applySearch()` (TABLE-005).
- Adicionar debounce no client-side (300ms, ticket React)
- Placeholder "Search..." ou customizado via `Table::searchPlaceholder(string)`
- Para search avançado em relationships: `Column::text('author.name')->searchable()`

**Critérios de aceite**

- [ ] Search input renderizado no topo da tabela
- [ ] Query `?search=john` retorna resultados LIKE em columns searchable
- [ ] Search em column com dot notation (`author.name`) faz JOIN corretamente
- [ ] Clear button para limpar search
- [ ] Teste: search em 100 records retorna matches corretos

**Notas de implementação**

- Escape LIKE wildcards em user input (`%`, `_`) para prevenir performance issues.
- Fase 2: Laravel Scout integration para full-text search.

---

### [TABLE-011] Pagination com controles per-page

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [TABLE-005]

**Contexto**

Pagination customizável. Cobrir RF-T-01.

**Descrição técnica**

- `Table::perPage(int $default, array $options)` configurar
- Default: 25, options: [10, 25, 50, 100]
- Persist preference per-user em `user.settings` JSON column (opt-in — pode começar com URL apenas)
- Serializar:
  ```json
  {
      "records": {
          "data": [...],
          "current_page": 1,
          "per_page": 25,
          "total": 250,
          "last_page": 10,
          "from": 1,
          "to": 25,
          "links": [...]
      }
  }
  ```

**Critérios de aceite**

- [ ] Mudar per-page atualiza URL e query
- [ ] Navegação entre pages funciona
- [ ] Links paginator preservam search/filter state
- [ ] Teste: pagination de 250 records

**Notas de implementação**

- Laravel paginator já inclui `links` array — usar `withQueryString()` para preservar filter state.

---

### [TABLE-012] Testes do pacote TABLE

**Tipo:** test • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [TABLE-011]

**Descrição técnica**

- Unit tests: Table, Column, Filter classes
- Feature tests: full HTTP request lifecycle (index com search, filter, sort, pagination)
- Coverage meta: 90%
- Edge cases: empty table, single record, 1000+ records, simultaneous filters

**Critérios de aceite**

- [ ] Pest passa em todos os testes
- [ ] Coverage ≥ 90%
- [ ] Performance test: index de 1000 records <500ms

**Notas de implementação**

- Usar factories Eloquent para seed data.
- Benchmark com `pest-plugin-stressless` se disponível.

---

### [TABLE-013] SKILL.md do pacote table

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** S • **Camada:** docs • **Depende de:** [TABLE-012]

**Descrição técnica**

SKILL.md com:
- Exemplos de 8 column types
- Exemplos de 6 filter types
- Seção "Customizing queries" com indexQuery() e TableQueryBuilder hooks
- Anti-patterns: skip sortable flag when adding sort (security), using DB raw sem binding

**Critérios de aceite**

- [ ] SKILL.md completo e validado

**Notas de implementação**

- Alinhar com `05-api-php.md`.

---

---

## 6. Pacote FORM

### [FORM-001] Esqueleto do pacote `arqel-dev/form`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [CORE-008, FIELDS-002]

**Contexto**

Pacote separado para forms — distingue declaração de form (layout + schema) de declaração de fields (tipos).

**Descrição técnica**

Estrutura `packages/form/`:

- `composer.json` (deps: `arqel-dev/core`, `arqel-dev/fields`)
- `src/Form.php` (builder fluent)
- `src/FormSchema.php` (container de fields + layout)
- `src/Layout/` (Section, Fieldset, Grid, Columns, Group, Tabs — Fase 1 sem Wizard)
- `src/FormRequestGenerator.php` (auto-gen FormRequest classes)
- `src/FormServiceProvider.php`
- `SKILL.md`, `tests/`

**Critérios de aceite**

- [ ] composer resolve e valida
- [ ] ServiceProvider discovered
- [ ] SKILL.md esqueleto criado

**Notas de implementação**

- Wizard e Tabs dentro de form ficam para Fase 2 (RF-FM-04, RF-FM-05).
- Pacote depende de FIELDS para reutilizar tipos declarados.

---

### [FORM-002] Classe `Form` builder fluent

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [FORM-001]

**Contexto**

Container principal para declaração de form. API em `05-api-php.md` §1.1 (método `form()`).

**Descrição técnica**

Criar `src/Form.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Form;

use Arqel\Fields\Field;
use Arqel\Form\Layout\Component;

final class Form
{
    /** @var array<Component|Field> */
    protected array $schema = [];
    protected int $columns = 1;
    protected ?string $model = null;
    protected bool $inline = false;
    protected bool $disabled = false;

    /**
     * @param array<Component|Field> $schema
     */
    public function schema(array $schema): static;
    public function columns(int $columns): static;
    public function model(string $model): static;
    public function inline(bool $inline = true): static;
    public function disabled(bool $disabled = true): static;

    public function getSchema(): array;
    public function getColumns(): int;
    public function getFields(): array; // Flatten recursively
    public function getModel(): ?string;

    public function toArray(?Model $record = null, ?Authenticatable $user = null): array;
}
```

O método `toArray()` serializa o schema recursivamente, respeitando authorization e visibility per-field.

**Critérios de aceite**

- [ ] `Form::make()->schema([...])` encadeia corretamente
- [ ] `getFields()` retorna array flat de Field (mesmo dentro de Sections/Grids)
- [ ] `toArray()` produz shape JSON conforme `06-api-react.md` §8.4
- [ ] Testes cobrem fluent API e flattening

**Notas de implementação**

- Flattening recursivo é necessário para validation (precisa de lista simples de fields).
- Schema preservado para rendering (layout components ficam na árvore).

---

### [FORM-003] Layout component `Section`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [FORM-002]

**Contexto**

Section é o componente de layout mais usado: agrupa fields com título, descrição opcional, collapsible.

**Descrição técnica**

Criar `src/Layout/Component.php` (abstract base):

```php
abstract class Component
{
    protected string $type;
    protected string $component;
    protected array $schema = [];
    protected int $columnSpan = 1;
    protected ?Closure $visibleIf = null;
    protected ?Closure $canSee = null;

    public function schema(array $schema): static;
    public function columnSpan(int|string $span): static;
    public function visibleIf(Closure $callback): static;
    public function canSee(Closure $callback): static;

    abstract public function toArray(): array;
}
```

Criar `src/Layout/Section.php`:

```php
final class Section extends Component
{
    protected string $type = 'section';
    protected string $component = 'FormSection';
    protected string $heading;
    protected ?string $description = null;
    protected ?string $icon = null;
    protected bool $collapsible = false;
    protected bool $collapsed = false;
    protected int $columns = 1;
    protected bool $compact = false;
    protected ?string $aside = null;

    public static function make(string $heading): static
    {
        return new static($heading);
    }

    public function description(string $description): static;
    public function icon(string $icon): static;
    public function collapsible(bool $collapsible = true): static;
    public function collapsed(bool $collapsed = true): static;
    public function columns(int $columns): static;
    public function compact(bool $compact = true): static;
    public function aside(bool $aside = true): static;
}
```

**Critérios de aceite**

- [ ] `Section::make('Profile')->description('Basic info')->columns(2)->schema([...])` funciona
- [ ] Collapsible sections preservam estado via React state (não persistente Fase 1)
- [ ] Aside: renderiza description/helper em coluna lateral
- [ ] Nested Sections funcionam (Section dentro de Section)
- [ ] Testes cobrem fluent API e serialização

**Notas de implementação**

- `aside()` útil para forms longos: sidebar com contexto/dicas enquanto campos à direita.
- `compact()` reduz padding vertical — útil em edit inline.

---

### [FORM-004] Layout components `Fieldset`, `Grid`, `Columns`, `Group`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [FORM-003]

**Contexto**

Componentes complementares para construir layouts variados. Cobre RF-FM-03.

**Descrição técnica**

Em `src/Layout/`:

- `Fieldset.php`:
  - Similar a Section mas sem collapsible e com `<fieldset>` semântico HTML
  - `legend(string)` ao invés de `heading()`
  - Útil para agrupamentos semânticos (radio groups, permissions)

- `Grid.php`:
  - Grid responsivo: `columns(array|int)`:
    - `columns(3)` → `grid-cols-3` fixo
    - `columns(['sm' => 1, 'md' => 2, 'lg' => 4])` → responsive
  - `gap(string)`: ex 'gap-4', 'gap-8'

- `Columns.php`:
  - Atalho para Grid com 2 colunas fixas
  - Wrapper semântico para "two-column layout" comum

- `Group.php`:
  - Container invisível (sem border/heading) para agrupar fields logicamente
  - Útil para aplicar `visibleIf` a múltiplos fields
  - `orientation('horizontal'|'vertical')`

**Critérios de aceite**

- [ ] Todos os 4 layout components funcionam com fluent API
- [ ] Grid responsivo serializa breakpoints corretamente
- [ ] `Group::make()->visibleIf(fn ($record) => ...)` aplica a todos children
- [ ] Nested layouts funcionam (Grid dentro de Section, etc.)
- [ ] Testes cobrem cada tipo

**Notas de implementação**

- CSS grid via Tailwind: shape ideal é passar classes prontas pro React (`grid-cols-2 md:grid-cols-4`) via helper.
- Group é "invisível" mas está na árvore — permite condicionar visibility de múltiplos fields com 1 predicate.

---

### [FORM-005] Layout component `Tabs` (base — conteúdo simples)

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [FORM-003]

**Contexto**

Tabs dentro de form. Fase 1 suporta tabs estáticos; reactive tabs (state-based) Fase 2.

**Descrição técnica**

Criar `src/Layout/Tabs.php` e `src/Layout/Tab.php`:

```php
final class Tabs extends Component
{
    protected string $type = 'tabs';
    protected string $component = 'FormTabs';
    /** @var array<Tab> */
    protected array $tabs = [];
    protected ?string $defaultTab = null;
    protected string $orientation = 'horizontal'; // 'vertical' also

    public static function make(): static;
    public function tabs(array $tabs): static;
    public function defaultTab(string $id): static;
    public function vertical(): static;
}

final class Tab extends Component
{
    protected string $type = 'tab';
    protected string $id;
    protected string $label;
    protected ?string $icon = null;
    protected ?int $badge = null;

    public static function make(string $id, string $label): static;
    public function icon(string $icon): static;
    public function badge(int|Closure $count): static;
}
```

**Critérios de aceite**

- [ ] `Tabs::make()->tabs([Tab::make('general', 'General')->schema([...])])` funciona
- [ ] Default tab aplicado no primeiro render
- [ ] Badge dinâmico via closure (ex: validation errors count)
- [ ] Vertical orientation serializa corretamente
- [ ] Testes cobrem API

**Notas de implementação**

- Fase 1: tabs não preservam state ao submeter (perde selected tab). Fase 2 com `<Activity>` React 19.2 preserva.
- Validation errors em tab não-ativo: Fase 2 adiciona badge automático.

---

### [FORM-006] Integração Form com ResourceController (create/edit)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [FORM-002, CORE-006]

**Contexto**

Controller precisa invocar `Resource::form()` (se existir) ou gerar Form default a partir de `fields()`.

**Descrição técnica**

Atualizar `ResourceController::create()` e `::edit()`:

```php
public function create(Request $request, string $resource): Response
{
    $resourceClass = $this->registry->findBySlug($resource);
    abort_if(!$resourceClass, 404);
    
    $this->authorize('create', $resourceClass::getModel());
    
    $resourceInstance = app($resourceClass);
    $form = $this->resolveForm($resourceInstance);
    
    return Inertia::render('arqel::resource-create', [
        'resource' => $this->dataBuilder->resourceMeta($resourceClass),
        'fields' => $this->serializer->serialize($form->getFields(), null, $request->user()),
        'form' => $form->toArray(null, $request->user()),
        'defaults' => $this->resolveDefaults($form->getFields()),
        'errors' => $request->session()->get('errors')?->toArray() ?? [],
        'can' => ['create' => true],
    ]);
}

private function resolveForm(Resource $resource): Form
{
    $form = new Form();
    $resource->form($form);
    
    // If empty schema, fall back to fields() with default Section wrapping
    if (empty($form->getSchema())) {
        $form->schema([
            Section::make('Details')->schema($resource->fields()),
        ]);
    }
    
    return $form;
}
```

E `::store()` / `::update()`:

```php
public function store(Request $request, string $resource): RedirectResponse
{
    $resourceClass = $this->registry->findBySlug($resource);
    abort_if(!$resourceClass, 404);
    
    $this->authorize('create', $resourceClass::getModel());
    
    $resourceInstance = app($resourceClass);
    $form = $this->resolveForm($resourceInstance);
    
    $validated = $this->validateFormData($request, $form->getFields());
    
    // Apply lifecycle hooks
    $validated = $resourceInstance->beforeCreate($validated);
    $validated = $resourceInstance->beforeSave(null, $validated);
    
    // Filter dehydrated fields
    $persisted = $this->filterDehydrated($validated, $form->getFields());
    
    $record = $resourceClass::$model::create($persisted);
    
    $resourceInstance->afterCreate($record);
    $resourceInstance->afterSave($record);
    
    session()->flash('success', __('arqel::messages.created'));
    
    return redirect()->to($this->urlFor($resourceClass, 'edit', $record));
}
```

**Critérios de aceite**

- [ ] Create page renderiza com schema correto
- [ ] Edit page pré-preenche com dados do record
- [ ] Validation falha retorna para back() com errors
- [ ] Lifecycle hooks chamados na ordem correta
- [ ] Dehydrated fields não são persistidos
- [ ] Teste Feature: criar, editar, falhar validação

**Notas de implementação**

- `validateFormData` constrói rules array a partir de Fields e delega a `Validator::make`.
- Mass assignment: usar `$fillable` corretamente ou método `create()` do Eloquent.
- Dehydrated: fields computed ou helpers que não mapeiam a attributes.

---

### [FORM-007] Geração automática de FormRequest classes

**Tipo:** feat • **Prioridade:** P2 • **Estimativa:** M • **Camada:** php • **Depende de:** [FORM-006]

**Contexto**

Cobre RF-FM-09. Para users que querem FormRequest dedicado (ex: custom `prepareForValidation`, `authorize`).

**Descrição técnica**

Criar `src/FormRequestGenerator.php`:

```php
final class FormRequestGenerator
{
    public function generate(string $resourceClass, string $action = 'store'): string;
    public function write(string $resourceClass, string $action, string $targetPath): void;
}
```

Comando Artisan helper:

```php
// Integrated em arqel:resource
php artisan arqel:resource User --with-requests
// Gera: app/Http/Requests/Arqel/StoreUserRequest.php
//        app/Http/Requests/Arqel/UpdateUserRequest.php
```

Conteúdo gerado:

```php
final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', User::class);
    }

    public function rules(): array
    {
        $resource = app(UserResource::class);
        $fields = $resource->fields();
        return (new FieldRulesExtractor())->extract($fields);
    }

    public function messages(): array
    {
        $resource = app(UserResource::class);
        return (new FieldMessagesExtractor())->extract($resource->fields());
    }
}
```

**Critérios de aceite**

- [ ] `arqel:resource User --with-requests` gera 2 arquivos
- [ ] Requests geradas funcionam sem edição manual
- [ ] Controller detecta e usa FormRequest se existir (sobrescreve inline validation)
- [ ] Teste: gerar + validar request em Feature test

**Notas de implementação**

- Requests geradas vivem em `app/Http/Requests/Arqel/` — convenção namespaced.
- Usuário pode editar (ex: adicionar `prepareForValidation`) sem regenerar.
- Regeneração com `--force` sobrescreve.

---

### [FORM-008] Inertia useForm integration (server-side helpers)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [FORM-006]

**Contexto**

Inertia 3 useForm é o helper client. Server precisa expor endpoints idiomáticos.

**Descrição técnica**

- Garantir que responses de form submission seguem padrão Inertia:
  - Success: `redirect()->back()->with('success', '...')` ou `redirect()->route(...)`
  - Validation fail: `back()->withErrors($errors)->withInput()`
- `old()` values preservados em back
- Flash messages injetados via middleware `HandleArqelInertiaRequests`
- Precognition support (RF-FM-10) — será incremental em Fase 2, stub API em Fase 1:
  ```php
  Route::precognitive()->post('/admin/{resource}', ResourceController::class)->action('store');
  ```

**Critérios de aceite**

- [ ] POST com data válida cria record e redireciona
- [ ] POST com data inválida retorna 422 com errors (Inertia converte em back + errors)
- [ ] Inertia `useForm` client-side vê errors corretamente
- [ ] `old()` input preservado em back
- [ ] Precognition endpoint responde 204 No Content para dry-run (stub Fase 1)

**Notas de implementação**

- Inertia 3 expõe `processing`, `errors`, `wasSuccessful` reactively — server só precisa enviar data correta.
- Precognition full em Fase 2: real-time field validation antes de submit.

---

### [FORM-009] Testes do pacote FORM

**Tipo:** test • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [FORM-008]

**Descrição técnica**

- Unit tests: Form, Section, Fieldset, Grid, Columns, Group, Tabs, Tab, FormRequestGenerator
- Feature tests: full create/edit/update flow, validation errors, lifecycle hooks
- Coverage ≥ 90%
- Snapshots para cada layout component serializado

**Critérios de aceite**

- [ ] Pest passa
- [ ] Coverage ≥ 90%
- [ ] Feature tests cobrem happy path + 5+ error paths

**Notas de implementação**

- Testar interação de `dehydrated(false)` com model create é crítico (não persistir field helper).

---

### [FORM-010] SKILL.md do pacote form

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** S • **Camada:** docs • **Depende de:** [FORM-009]

**Descrição técnica**

SKILL.md com:
- Exemplos de cada layout component
- Padrões de composição (form com tabs + sections + grids)
- Como usar FormRequest gerado
- Anti-patterns: side effects em closures schema, circular dependencies em visibleIf

**Critérios de aceite**

- [ ] SKILL.md completo e validado

---

## 7. Pacote ACTIONS

### [ACTIONS-001] Esqueleto do pacote `arqel-dev/actions`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [CORE-008]

**Contexto**

Actions são unidades de operação invocáveis. Cobre RF-A-01 a RF-A-04 em Fase 1.

**Descrição técnica**

Estrutura `packages/actions/`:

- `composer.json` (deps: `arqel-dev/core`, `arqel-dev/fields` para form modals, `arqel-dev/form`)
- `src/Action.php` (base)
- `src/Types/` (RowAction, BulkAction, ToolbarAction, HeaderAction)
- `src/Concerns/` (Confirmable, HasForm, HasAuthorization, HasQueuing — queue stub Fase 1)
- `src/ActionExecutor.php`
- `src/Http/Controllers/ActionController.php`
- `SKILL.md`, `tests/`

**Critérios de aceite**

- [ ] composer resolve e valida
- [ ] ServiceProvider discovered
- [ ] SKILL.md esqueleto

**Notas de implementação**

- Actions queued full (com progress) ficam para Fase 2 (RF-A-06). Fase 1 tem stub.

---

### [ACTIONS-002] Classe `Action` base abstract

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [ACTIONS-001]

**Contexto**

Base de todos os actions. API em `05-api-php.md` §3.

**Descrição técnica**

Criar `src/Action.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Actions;

use Arqel\Actions\Concerns\Confirmable;
use Arqel\Actions\Concerns\HasAuthorization;
use Arqel\Actions\Concerns\HasForm;
use Closure;

abstract class Action
{
    use Confirmable;
    use HasAuthorization;
    use HasForm;

    protected string $name;
    protected ?string $label = null;
    protected ?string $icon = null;
    protected string $color = 'primary';
    protected string $variant = 'default';
    protected ?Closure $action = null;
    protected ?string $url = null;
    protected string $method = 'POST';
    protected ?Closure $visible = null;
    protected ?Closure $disabled = null;
    protected ?string $successNotification = null;
    protected ?string $failureNotification = null;
    protected bool $hidden = false;
    protected ?string $tooltip = null;
    
    final public function __construct(string $name)
    {
        $this->name = $name;
        $this->label = Str::of($name)->snake()->replace('_', ' ')->title()->toString();
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    // Fluent API
    public function label(string $label): static;
    public function icon(string $icon): static;
    public function color(string $color): static;
    public function variant(string $variant): static;
    public function action(Closure $callback): static;
    public function url(Closure|string $url, string $method = 'GET'): static;
    public function visible(Closure $callback): static;
    public function disabled(Closure $callback): static;
    public function hidden(bool $hidden = true): static;
    public function tooltip(string $tooltip): static;
    public function successNotification(string $message): static;
    public function failureNotification(string $message): static;

    // Execution
    public function execute(mixed $record = null, array $data = []): mixed;
    public function canBeExecutedBy(?Authenticatable $user, mixed $record = null): bool;
    
    // Serialization
    public function toArray(?Authenticatable $user = null, mixed $record = null): array;
}
```

Cores válidas (enum informal): `primary`, `secondary`, `destructive`, `success`, `warning`, `info`.

Variants: `default`, `outline`, `ghost`, `destructive`.

**Critérios de aceite**

- [ ] `Action::make('publish')->action(fn ($record) => $record->publish())` executa corretamente
- [ ] `->url('/custom', 'GET')` gera link ao invés de invocação
- [ ] `visible(fn ($record) => $record->can_publish)` oculta dinamicamente
- [ ] Serialização produz shape `ActionSchema` (ver `06-api-react.md` §5)
- [ ] Testes cobrem execution, authorization, visibility, serialization

**Notas de implementação**

- `execute` retorna `mixed` — pode ser redirect, notification, void, etc.
- Action pode ser puro redirect (`url`) ou callback (`action`) — XOR conceitualmente.

---

### [ACTIONS-003] `RowAction`, `BulkAction`, `ToolbarAction`, `HeaderAction`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [ACTIONS-002]

**Contexto**

Especializações de Action com comportamento específico por contexto. Cobre RF-A-01.

**Descrição técnica**

Em `src/Types/`:

- `RowAction.php`:
  ```php
  final class RowAction extends Action
  {
      protected string $type = 'row';
      // Inherited: action() callback receives single $record
  }
  ```

- `BulkAction.php`:
  ```php
  final class BulkAction extends Action
  {
      protected string $type = 'bulk';
      protected bool $deselectAfter = true;
      protected int $chunkSize = 100;
      
      public function deselectRecordsAfterCompletion(bool $deselect = true): static;
      public function chunkSize(int $size): static;
      
      // action() callback receives Collection $records
      public function execute(mixed $records = null, array $data = []): mixed
      {
          // Chunk automatically
          $records->chunk($this->chunkSize)->each(function ($chunk) use ($data) {
              ($this->action)($chunk, $data);
          });
      }
  }
  ```

- `ToolbarAction.php`:
  ```php
  final class ToolbarAction extends Action
  {
      protected string $type = 'toolbar';
      // No record context; standalone button in table toolbar
  }
  ```

- `HeaderAction.php`:
  ```php
  final class HeaderAction extends Action
  {
      protected string $type = 'header';
      // Used on detail page header, receives single $record
  }
  ```

Built-in actions comuns como factory methods em classe `Actions`:

```php
final class Actions
{
    public static function view(): RowAction;
    public static function edit(): RowAction;
    public static function delete(): RowAction; // Pre-configured destructive + confirmation
    public static function restore(): RowAction;
    public static function create(): ToolbarAction;
    public static function deleteBulk(): BulkAction;
}
```

**Critérios de aceite**

- [ ] 4 action types funcionam com contextos corretos
- [ ] `Actions::delete()` pre-configured com destructive + requiresConfirmation
- [ ] Built-in factories retornam instâncias corretas
- [ ] BulkAction chunking funciona com 500+ records
- [ ] Testes cobrem cada tipo

**Notas de implementação**

- `view()`, `edit()` são sugar para `->url(route('show'))`.
- `delete()` precisa integrar com soft delete automaticamente (detecta trait).

---

### [ACTIONS-004] Trait `Confirmable`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [ACTIONS-002]

**Contexto**

Modal de confirmação com variants. Cobre RF-A-03.

**Descrição técnica**

Criar `src/Concerns/Confirmable.php`:

```php
trait Confirmable
{
    protected bool $requiresConfirmation = false;
    protected ?string $modalHeading = null;
    protected ?string $modalDescription = null;
    protected ?string $modalIcon = null;
    protected string $modalColor = 'destructive';
    protected ?string $modalConfirmationRequiresText = null;
    protected string $modalSubmitButtonLabel = 'Confirm';
    protected string $modalCancelButtonLabel = 'Cancel';

    public function requiresConfirmation(bool $required = true): static;
    public function modalHeading(string $heading): static;
    public function modalDescription(string $description): static;
    public function modalIcon(string $icon): static;
    public function modalColor(string $color): static; // 'destructive' | 'warning' | 'info'
    public function modalConfirmationRequiresText(string $text): static;
    public function modalSubmitButtonLabel(string $label): static;
    public function modalCancelButtonLabel(string $label): static;

    public function getConfirmationConfig(): ?array;
}
```

Colors válidas: `destructive`, `warning`, `info`.

**Critérios de aceite**

- [ ] `Action::make('delete')->requiresConfirmation()` serializa com confirmation config
- [ ] `modalConfirmationRequiresText('DELETE')` força user digitar texto
- [ ] Modal colors mapeiam para variantes visuais
- [ ] Labels default traduzíveis via arqel::lang
- [ ] Testes cobrem combinações

**Notas de implementação**

- Enforcement de `requiresText` é UX only — server ainda recebe POST. Validação real é via policies.

---

### [ACTIONS-005] Trait `HasForm` — actions com form modal

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [ACTIONS-002, FIELDS-002]

**Contexto**

Actions que requerem input adicional (ex: transfer ownership com seleção de new owner). Cobre RF-A-02.

**Descrição técnica**

Criar `src/Concerns/HasForm.php`:

```php
trait HasForm
{
    /** @var array<Field> */
    protected array $form = [];
    protected bool $modalWide = false;
    protected string $modalSize = 'md'; // sm, md, lg, xl, full

    public function form(array $fields): static;
    public function modalWide(bool $wide = true): static;
    public function modalSize(string $size): static;

    public function hasForm(): bool;
    public function getFormFields(): array;
    public function getFormValidationRules(): array;

    public function executeWithForm(mixed $record, array $data): mixed;
}
```

Serialização inclui form schema (mesmo formato que form regular, serializado via FieldSchemaSerializer).

**Critérios de aceite**

- [ ] `Action::make('transfer')->form([Field::select('new_owner')...])` serializa form schema
- [ ] Submit do action com form data valida via rules
- [ ] Rejected validation retorna errors para modal
- [ ] Action callback recebe `$data` array com form values
- [ ] Teste Feature: invocar action com form modal

**Notas de implementação**

- Rota de submit: `POST /admin/{resource}/{id}/actions/{action}` (row) ou similar.
- Form data validated antes de invocar callback.

---

### [ACTIONS-006] `ActionController` — endpoint de execução

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [ACTIONS-005]

**Contexto**

Controller único que recebe invocações de actions de qualquer tipo.

**Descrição técnica**

Criar `src/Http/Controllers/ActionController.php`:

```php
final class ActionController
{
    public function invokeRowAction(
        Request $request,
        string $resource,
        string $id,
        string $action,
    ): RedirectResponse|JsonResponse {
        $resourceClass = $this->registry->findBySlug($resource);
        abort_if(!$resourceClass, 404);
        
        $resourceInstance = app($resourceClass);
        $record = $resourceClass::$model::findOrFail($id);
        
        $actionInstance = $this->resolveRowAction($resourceInstance, $action);
        abort_if(!$actionInstance, 404);
        
        // Authorize
        abort_if(!$actionInstance->canBeExecutedBy($request->user(), $record), 403);
        
        // Validate form data if action has form
        $data = [];
        if ($actionInstance->hasForm()) {
            $data = $request->validate($actionInstance->getFormValidationRules());
        }
        
        // Execute
        try {
            $result = $actionInstance->execute($record, $data);
        } catch (\Throwable $e) {
            session()->flash('error', $actionInstance->getFailureNotification() ?? $e->getMessage());
            return back();
        }
        
        // Success notification
        if ($msg = $actionInstance->getSuccessNotification()) {
            session()->flash('success', $msg);
        }
        
        // If action returned redirect, use it; otherwise back
        return $result instanceof RedirectResponse
            ? $result
            : back();
    }

    public function invokeBulkAction(Request $request, string $resource, string $action): RedirectResponse;
    public function invokeToolbarAction(Request $request, string $resource, string $action): RedirectResponse;
    public function invokeHeaderAction(Request $request, string $resource, string $id, string $action): RedirectResponse;
}
```

Rotas:

- `POST /admin/{resource}/{id}/actions/{action}` — row/header
- `POST /admin/{resource}/actions/{action}` — toolbar
- `POST /admin/{resource}/bulk-actions/{action}` — bulk

**Critérios de aceite**

- [ ] Row action invocada executa callback e redireciona
- [ ] Action não encontrada retorna 404
- [ ] Policy deny retorna 403
- [ ] Validation fail retorna 422 (Inertia converte para back + errors)
- [ ] Success notification aparece no flash
- [ ] Bulk action processa records corretamente com chunking
- [ ] Teste Feature: cobrir cada tipo de action

**Notas de implementação**

- Bulk action recebe `ids: [...]` no body.
- Exceções capturadas produzem failureNotification — não log silence.
- Considerar retry logic em Fase 2.

---

### [ACTIONS-007] Integração actions com Table

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [ACTIONS-006, TABLE-007]

**Contexto**

Actions precisam serializar corretamente dentro do shape da table response.

**Descrição técnica**

- `Table::getActions()` retorna RowActions; serialização via `Action::toArray($user, $record)` chamada per-row
- Performance optimization: resolver authorization em bulk quando possível
  ```php
  // Em vez de: authorize() per-row per-action = O(rows × actions)
  // Pre-compute policies once for user's role em middleware ou shared props
  ```
- Actions dropdown UI: se >3 actions, agrupar em dropdown menu client-side

**Critérios de aceite**

- [ ] Row actions aparecem por registro com authorization resolvida
- [ ] Bulk actions aparecem no bulk toolbar quando há selection
- [ ] Toolbar actions aparecem no top do table
- [ ] Test: table com 50 records e 5 actions renderiza em <200ms

**Notas de implementação**

- Ver otimização em FIELDS-018: policies cache por user+model.

---

### [ACTIONS-008] Testes do pacote ACTIONS

**Tipo:** test • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [ACTIONS-007]

**Descrição técnica**

- Unit tests: Action base, Confirmable, HasForm, cada tipo de action
- Feature tests: invocar row/bulk/toolbar/header actions, confirmation flows, form modals, authorization
- Coverage ≥ 90%
- Edge cases: action throws exception, user loses permission mid-flight, record deleted before execution

**Critérios de aceite**

- [ ] Pest passa
- [ ] Coverage ≥ 90%
- [ ] Feature tests cobrem cada tipo + variantes (com/sem confirmation, com/sem form)

**Notas de implementação**

- Mock Queue para ação que tem `->queue()` mesmo que seja stub Fase 1.

---

### [ACTIONS-009] SKILL.md do pacote actions

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** S • **Camada:** docs • **Depende de:** [ACTIONS-008]

**Descrição técnica**

SKILL.md com:
- Exemplos dos 4 tipos de action
- Quando usar cada tipo
- Padrões de confirmation (destructive, warning, info)
- Action com form modal — exemplo completo
- Anti-patterns: performing multiple DB writes sem transaction, side effects sem queue

**Critérios de aceite**

- [ ] SKILL.md completo e validado

---

## 8. Pacote AUTH

### [AUTH-001] Esqueleto do pacote `arqel-dev/auth`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [CORE-008]

**Contexto**

Authorization-layer package. Wraps Laravel Policies + Gate com conveniences. Cobre RF-AU-01 a RF-AU-08.

**Descrição técnica**

Estrutura `packages/auth/`:

- `composer.json` (dep: `arqel-dev/core`)
- `src/PolicyDiscovery.php`
- `src/AbilityRegistry.php`
- `src/ArqelGate.php` (facade wrapper)
- `src/Concerns/AuthorizesRequests.php`
- `src/AuthServiceProvider.php`
- `SKILL.md`, `tests/`

**Critérios de aceite**

- [ ] composer resolve e valida
- [ ] ServiceProvider discovered
- [ ] SKILL.md esqueleto

---

### [AUTH-002] `PolicyDiscovery` — auto-registo de policies

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [AUTH-001]

**Contexto**

Laravel convention é `App\Models\Foo` → `App\Policies\FooPolicy`. Discovery automático (no Laravel 11+ isso já é default). Confirmar + documentar comportamento em Arqel.

**Descrição técnica**

- Criar `src/PolicyDiscovery.php`:
  - Verifica, para cada Resource registrado, se existe Policy correspondente
  - Se não existir, log warning em development (ou exception em production opt-in)
  - Método `autoRegisterPoliciesFor(array $resources): void`
- Integrar com `ArqelServiceProvider::packageBooted()`:
  - Após Resources serem registrados, chamar PolicyDiscovery
- Suportar policy customizada via `$policy` estático em Resource:
  ```php
  class UserResource extends Resource
  {
      public static ?string $policy = CustomUserPolicy::class;
  }
  ```

**Critérios de aceite**

- [ ] `UserResource` com `User` model resolve `UserPolicy` automaticamente
- [ ] Resource sem Policy emite warning (não crash)
- [ ] `$policy` override funciona
- [ ] Teste: cenário com/sem Policy, com override

**Notas de implementação**

- Laravel 11+: Gate resolve policies automaticamente. Nosso trabalho aqui é warning/logging.
- Documentar ownership: quem escreve policy, user ou Arqel. Resposta: user, Arqel só verifica existência.

---

### [AUTH-003] `AbilityRegistry` — catálogo de abilities

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [AUTH-002]

**Contexto**

Global abilities expostas em shared props `auth.can`. Permite client-side rendering conditional sem query DB.

**Descrição técnica**

Criar `src/AbilityRegistry.php`:

```php
final class AbilityRegistry
{
    /** @var array<string> */
    protected array $globalAbilities = [];
    /** @var array<string, Closure> */
    protected array $computedAbilities = [];

    public function registerGlobal(string $ability): void;
    public function registerGlobals(array $abilities): void;
    public function registerComputed(string $ability, Closure $callback): void;
    
    public function resolveForUser(?Authenticatable $user): array;
}
```

Global abilities (resolvidas via Gate::check):

```php
// Em ServiceProvider
Arqel::abilities([
    'viewAdminPanel',
    'manageSettings',
    'exportData',
]);

// Resolve automaticamente Gate::allows('viewAdminPanel') para user atual
```

Integração com `HandleArqelInertiaRequests`:

```php
'auth' => [
    'user' => $user,
    'can' => app(AbilityRegistry::class)->resolveForUser($user),
],
```

**Critérios de aceite**

- [ ] `Arqel::ability('viewAdminPanel')` registra
- [ ] Shared props inclui `auth.can.viewAdminPanel: true|false`
- [ ] Computed abilities chamam closure com user
- [ ] Test: ability registered, resolved, exposed client-side

**Notas de implementação**

- Performance: cache de abilities por user em memória do request (não repetir Gate::check para mesmo par user+ability).

---

### [AUTH-004] `<CanAccess>` server-side rendering + middleware helpers

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [AUTH-003]

**Contexto**

Ponte server-side para authorization check usado em React `<CanAccess>`. Cobre RF-AU-07 (server parte).

**Descrição técnica**

- Helper `can(string $ability, mixed $arguments = null): bool` via Facade ou global function
- Trait `AuthorizesRequests` para controllers Arqel:
  ```php
  trait AuthorizesRequests
  {
      protected function authorizeResource(string $resourceClass, string $action, mixed $record = null): void;
      protected function authorizeAction(Action $action, mixed $record = null): void;
      protected function authorizeField(Field $field, string $operation, mixed $record = null): void;
  }
  ```
- Middleware `EnsureUserCanAccessPanel`:
  - Verifica se user pode acessar panel atual
  - Redirect para 403 ou custom unauthorized page

**Critérios de aceite**

- [ ] `$this->authorizeResource(UserResource::class, 'view', $user)` funciona
- [ ] Middleware bloqueia access sem ability
- [ ] Teste: happy path + deny path

**Notas de implementação**

- Traits mantêm controllers testáveis — `actingAs($user)` cobre cenários.

---

### [AUTH-005] Testes + SKILL.md do pacote AUTH

**Tipo:** test + docs • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php + docs • **Depende de:** [AUTH-004]

**Descrição técnica**

- Unit tests: PolicyDiscovery, AbilityRegistry, AuthorizesRequests trait
- Feature tests: Policy enforcement em resources, actions, fields; middleware behavior
- Coverage ≥ 90%
- SKILL.md: quando usar policies vs gates vs abilities, integração com Spatie Permission, anti-patterns (bypass policies client-side, não verificar em bulk)

**Critérios de aceite**

- [ ] Pest passa, coverage ≥ 90%
- [ ] SKILL.md completo

---

### [AUTH-006] Páginas Inertia-React de login + logout opt-in

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php + react • **Depende de:** [AUTH-002, CORE-007]

**Contexto**

Hoje Arqel não publica `/login`/`/logout`, delegando a Breeze/Jetstream/Fortify. Apps que rodaram só `composer require arqel-dev/framework` (sem `arqel new`) ficam sem essas páginas — quebra de DX comparado a Filament e Nova, que oferecem login pronto out-of-the-box.

Aqui shipamos páginas Inertia-React de login/logout dentro de `arqel-dev/auth`, opt-in via `Panel::configure()->login()`. Compatível com starter kits já instalados (não conflita) e independente deles quando ativo.

**Descrição técnica**

```php
// app/Providers/Filament/AdminPanelProvider.php (estilo Filament para Arqel)
$panel
    ->login()              // ativa /admin/login + /admin/logout
    ->loginUrl('/admin/login')
    ->afterLoginRedirectTo('/admin');
```

PHP:
- `Arqel\Auth\Http\Controllers\LoginController` (single-action invokable + showForm via Inertia render).
- `Arqel\Auth\Http\Controllers\LogoutController` POST.
- `Arqel\Auth\Http\Requests\LoginRequest` com rate-limiting via `RateLimiter` (5 tentativas / minuto / IP).
- `Arqel\Auth\Routes::register($panel)` registra rotas condicionalmente quando `$panel->loginEnabled()`.
- `Panel::configure()->login()/loginUrl()/afterLoginRedirectTo()/withoutDefaultAuth()`.

React (`@arqel-dev/auth` novo pacote npm OU dentro de `@arqel-dev/ui/auth`):
- `<LoginPage />` Inertia page com email + password + remember + submit.
- Validação inline via `useForm()` Inertia.
- "Forgot password?" link condicional (visível se AUTH-008 ativo).
- Estilo casa com painel via tokens compartilhados.

Authentication backend usa `Auth::attempt()` Laravel-native, lê de `App\Models\User` + tabela `users` padrão. Não inventa schema novo.

**Critérios de aceite**

- [ ] `Panel::configure()->login()` ativa rotas
- [ ] `/admin/login` renderiza Inertia React
- [ ] Rate-limiting funcional (5/min/IP)
- [ ] Logout invalida sessão + CSRF rotaciona
- [ ] Compatibilidade preservada com Breeze/Jetstream (não conflita)
- [ ] `Panel::configure()->withoutDefaultAuth()` opt-out limpo
- [ ] Pest feature: login happy path, falha credenciais, rate-limit
- [ ] Vitest: render LoginPage, validation errors, submit

**Notas de implementação**

- Driver Auth padrão Laravel — não inventamos `arqel.auth` driver.
- Reuse `App\Models\User` se existir; senão documentação aponta para criar.

---

### [AUTH-007] Registration opt-in + email verification

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php + react • **Depende de:** [AUTH-006]

**Contexto**

Apps SaaS frequentemente querem self-service signup. Arqel oferece via `Panel::configure()->registration()`, opt-in. Apps internas (admin staff-only, estilo Nova) deixam desligado.

**Descrição técnica**

```php
$panel
    ->registration()        // ativa /admin/register
    ->emailVerification()   // exige verificação antes de acesso
    ->registrationFields(fn () => [
        Field::text('name')->required(),
        Field::email('email')->required()->unique('users'),
        Field::password('password')->required()->confirmed()->min(8),
    ]);
```

PHP:
- `RegisterController` cria `User`, dispara `Registered` event.
- Email verification usa Laravel `MustVerifyEmail` interface — apps que registraram via Breeze/Jetstream já têm; senão documentação ensina add.
- Rate-limit (3 registros/IP/hora).

React:
- `<RegisterPage />` Inertia.
- `<VerifyEmailPage />` para resend link.

**Critérios de aceite**

- [ ] `registration()` ativa rota
- [ ] Email verification trigger funcional (envia email)
- [ ] Validation rules customizáveis via `registrationFields()`
- [ ] Pest: registro happy, email verification, rate-limit
- [ ] Vitest: render Register + Verify, validation errors

---

### [AUTH-008] Forgot password + reset token flow

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php + react • **Depende de:** [AUTH-006]

**Contexto**

Recovery flow completo: request reset link, email com token, reset form, validation expiration.

**Descrição técnica**

```php
$panel
    ->passwordReset()       // ativa /admin/forgot-password + /admin/reset-password/{token}
    ->passwordResetExpirationMinutes(60);
```

PHP:
- `ForgotPasswordController` gera token + envia notification (Laravel `Password::sendResetLink`).
- `ResetPasswordController` valida token + atualiza `password_hash`.
- Schema: tabela `password_reset_tokens` (Laravel default — geralmente já existe).
- Rate-limit (3 requests/IP/hora).

React:
- `<ForgotPasswordPage />` — só email input + submit.
- `<ResetPasswordPage />` — email pré-preenchido (read-only via query param) + nova password + confirm.
- Mensagens de sucesso/erro consistentes.

**Critérios de aceite**

- [ ] `passwordReset()` ativa 2 rotas
- [ ] Email enviado com link válido por 60min (configurável)
- [ ] Reset atualiza password e invalida tokens existentes
- [ ] Pest: full flow request → email → reset → login com nova senha
- [ ] Vitest: render ambas pages, validation

**Notas de implementação**

- AUTH-006/007/008 juntos fecham a lacuna de DX vs Filament/Nova. Após shipados, `composer require arqel-dev/framework` + `php artisan arqel:install` é suficiente; starter kit deixa de ser obrigatório.

---

## 9. Pacote NAV

### [NAV-001] Esqueleto do pacote `arqel-dev/nav`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [CORE-008]

**Contexto**

Navegação declarativa para sidebar. Cobre RF-N-01 a RF-N-07 em Fase 1.

**Descrição técnica**

Estrutura `packages/nav/`:

- `composer.json` (dep: `arqel-dev/core`)
- `src/Navigation.php` (builder)
- `src/NavigationItem.php`
- `src/NavigationGroup.php`
- `src/NavigationRegistry.php`
- `src/BreadcrumbsBuilder.php`
- `src/NavServiceProvider.php`
- `SKILL.md`, `tests/`

**Critérios de aceite**

- [ ] composer resolve e valida
- [ ] ServiceProvider discovered
- [ ] SKILL.md esqueleto

---

### [NAV-002] `NavigationItem` e `NavigationGroup`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [NAV-001]

**Contexto**

Blocos de construção do menu.

**Descrição técnica**

Criar `src/NavigationItem.php`:

```php
final class NavigationItem
{
    protected string $label;
    protected ?string $icon = null;
    protected ?string $url = null;
    protected ?string $routeName = null;
    protected array $routeParams = [];
    protected bool $openInNewTab = false;
    protected ?Closure $visible = null;
    protected ?Closure $badge = null;
    protected ?string $badgeColor = null;
    protected int $sort = 0;
    protected ?string $resourceClass = null; // Se item for Resource-backed

    public static function make(string $label): static;
    public static function resource(string $resourceClass): static; // Auto-fill
    public function icon(string $icon): static;
    public function url(string $url): static;
    public function route(string $name, array $params = []): static;
    public function openInNewTab(bool $new = true): static;
    public function visible(Closure $callback): static;
    public function badge(int|string|Closure $value): static;
    public function badgeColor(string $color): static;
    public function sort(int $sort): static;

    public function isVisibleFor(?Authenticatable $user): bool;
    public function resolveBadge(): int|string|null;
    public function toArray(?Authenticatable $user = null): array;
}
```

Criar `src/NavigationGroup.php`:

```php
final class NavigationGroup
{
    protected string $label;
    protected ?string $icon = null;
    protected array $items = [];
    protected bool $collapsible = true;
    protected bool $collapsed = false;
    protected int $sort = 0;
    protected ?Closure $visible = null;

    public static function make(string $label): static;
    public function icon(string $icon): static;
    public function items(array $items): static;
    public function collapsible(bool $collapsible = true): static;
    public function collapsed(bool $collapsed = true): static;
    public function sort(int $sort): static;
    public function visible(Closure $callback): static;
}
```

**Critérios de aceite**

- [ ] `NavigationItem::make('Dashboard')->icon('home')->url('/admin')` funciona
- [ ] `NavigationItem::resource(UserResource::class)` auto-fill label, icon, url
- [ ] Badge dinâmico via closure (ex: pending count)
- [ ] Visibility condicional por user
- [ ] `NavigationGroup::make('Content')->items([...])` funciona
- [ ] Testes cobrem API

**Notas de implementação**

- `NavigationItem::resource()` é syntactic sugar poderoso — elimina boilerplate.

---

### [NAV-003] `Navigation` builder + auto-registo de Resources

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [NAV-002]

**Contexto**

Builder principal + lógica de auto-registo (RF-N-02).

**Descrição técnica**

Criar `src/Navigation.php`:

```php
final class Navigation
{
    protected array $items = [];
    protected array $groups = [];
    protected bool $autoRegisterResources = true;

    public function autoRegister(bool $auto = true): static;
    public function item(NavigationItem $item): static;
    public function group(string $label, Closure $callback): static;
    public function divider(): static;

    public function build(?Authenticatable $user = null): array;
    
    private function autoRegisterFromResources(array $resources): void
    {
        foreach ($resources as $resourceClass) {
            $group = $resourceClass::getNavigationGroup();
            $item = NavigationItem::resource($resourceClass);
            
            if ($group) {
                $this->ensureGroup($group)->addItem($item);
            } else {
                $this->items[] = $item;
            }
        }
    }
}
```

Uso em `ArqelServiceProvider`:

```php
Arqel::panel('admin')
    ->navigation(function (Navigation $nav) {
        $nav->group('Content', function ($group) {
            $group->items([
                NavigationItem::make('Dashboard')->icon('home')->url('/admin'),
                NavigationItem::resource(PostResource::class),
            ]);
        });
        // Auto-register acontece depois se habilitado
    });
```

**Critérios de aceite**

- [ ] Auto-register default: Resources com `$navigationGroup` definido viram itens na navegação
- [ ] Manual overrides têm precedence sobre auto
- [ ] Sort order respeitado (auto + manual)
- [ ] Visibility filtrada por user ao build
- [ ] Teste: cenário mixed (manual + auto) gera estrutura esperada

**Notas de implementação**

- Build é caching-friendly: mesmo user + mesmo estado = mesmo output.

---

### [NAV-004] `BreadcrumbsBuilder` — breadcrumbs automáticos

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** S • **Camada:** php • **Depende de:** [NAV-003]

**Contexto**

Breadcrumbs a partir da rota Inertia atual. Cobre RF-N-06.

**Descrição técnica**

Criar `src/BreadcrumbsBuilder.php`:

```php
final class BreadcrumbsBuilder
{
    public function buildFor(string $routeName, array $params = []): array
    {
        // Ex: 'arqel.admin.users.edit' → 
        // [['label' => 'Users', 'url' => '/admin/users'], 
        //  ['label' => $record->name, 'url' => null]]
    }
}
```

Integrar com shared props:

```php
'breadcrumbs' => fn () => app(BreadcrumbsBuilder::class)->buildFor(Route::currentRouteName(), Route::current()->parameters()),
```

**Critérios de aceite**

- [ ] `/admin/users` → [Home > Users]
- [ ] `/admin/users/1/edit` → [Home > Users > John Doe (recordTitle) > Edit]
- [ ] Customização via override em Resource
- [ ] Teste: cobrir cenários index, create, edit, show

**Notas de implementação**

- Record title via `Resource::recordTitle($record)` default.
- Home item opcional — config flag.

---

### [NAV-005] Testes + SKILL.md do pacote NAV

**Tipo:** test + docs • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php + docs • **Depende de:** [NAV-004]

**Descrição técnica**

- Unit tests: NavigationItem, NavigationGroup, Navigation, BreadcrumbsBuilder
- Feature tests: rendering de nav com visibility, badge dinâmico, auto-register
- Coverage ≥ 90%
- SKILL.md: exemplos completos, auto-register vs manual, custom items, dividers

**Critérios de aceite**

- [ ] Pest passa, coverage ≥ 90%
- [ ] SKILL.md completo

---


## 10. Pacotes npm

### [TYPES-001] Esqueleto do pacote `@arqel-dev/types`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** react • **Depende de:** [INFRA-002]

**Contexto**

Pacote base de TypeScript types compartilhados por todos os outros pacotes React. Zero dependências de runtime — só types.

**Descrição técnica**

Estrutura `packages-js/types/`:

- `package.json`:
  ```json
  {
    "name": "@arqel-dev/types",
    "version": "0.0.0",
    "type": "module",
    "exports": {
      ".": {
        "types": "./dist/index.d.ts",
        "import": "./dist/index.js"
      },
      "./fields": {
        "types": "./dist/fields.d.ts",
        "import": "./dist/fields.js"
      },
      "./resources": "./dist/resources.js",
      "./tables": "./dist/tables.js",
      "./forms": "./dist/forms.js",
      "./actions": "./dist/actions.js",
      "./inertia": "./dist/inertia.js"
    },
    "files": ["dist"],
    "scripts": {
      "build": "tsup",
      "typecheck": "tsc --noEmit",
      "test": "vitest run"
    }
  }
  ```
- `tsconfig.json` (estende `tsconfig.base.json`)
- `tsup.config.ts` com múltiplos entries, dts generation
- `src/index.ts` (re-exports)
- `src/fields.ts`, `resources.ts`, `tables.ts`, `forms.ts`, `actions.ts`, `inertia.ts`, `utils.ts`
- `README.md`, `SKILL.md`

**Critérios de aceite**

- [ ] `pnpm build` gera `dist/` com `.d.ts` + `.js`
- [ ] Import funciona: `import type { FieldSchema } from '@arqel-dev/types'`
- [ ] Subpath imports funcionam: `import type { FieldSchema } from '@arqel-dev/types/fields'`
- [ ] Zero runtime code (só types)
- [ ] SKILL.md esqueleto

**Notas de implementação**

- `tsup` é mais rápido que `tsc` para build packages. Compila com esbuild.
- Subpath imports evitam importar todo o bundle — tree-shaking friendly.
- Manter `package.json` com `"sideEffects": false` para máximo tree-shaking.

---

### [TYPES-002] Types de Resources, Fields, Tables, Forms, Actions

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** react • **Depende de:** [TYPES-001]

**Contexto**

Types canônicos definidos em `06-api-react.md` §3-7. Este ticket materializa tudo em TypeScript real.

**Descrição técnica**

Em `src/fields.ts`: toda a hierarquia `FieldSchema`, `FieldType` enum, discriminated unions `TextFieldProps`, `SelectFieldProps`, `BelongsToFieldProps`, `ImageFieldProps`, etc.

Em `src/resources.ts`: `RecordType`, `ResourceMeta`, `PaginatedRecords<T>`, `ResourceIndexProps<T>`, `ResourceCreateProps<T>`, `ResourceEditProps<T>`, `ResourceDetailProps<T>`.

Em `src/tables.ts`: `ColumnSchema`, `ColumnType`, `ColumnProps<T>` discriminated union.

Em `src/forms.ts`: `FormSchema`, `LayoutComponent`, `SectionSchema`, `FieldsetSchema`, `GridSchema`, `TabsSchema`.

Em `src/actions.ts`: `ActionSchema`, `ActionColor`, `ActionVariant`, `ConfirmationConfig`.

Em `src/inertia.ts`: `SharedProps` global, extension do Inertia `PageProps`.

**Critérios de aceite**

- [ ] Todos os types de `06-api-react.md` §3-7 estão em TypeScript
- [ ] `strict: true` + `noUncheckedIndexedAccess: true` sem errors
- [ ] Discriminated unions funcionam (narrowing via type guards)
- [ ] Types exportados são documentados com TSDoc
- [ ] Teste: importar tipo e usar em component de teste compila

**Notas de implementação**

- Manter sincronizado com serializer PHP (FIELDS-010) — divergência causa bugs.
- Usar snapshot testing em tests para detectar mudanças não-intencionais no shape.

---

### [TYPES-003] Documentação integração com spatie/laravel-typescript-transformer

**Tipo:** feat + docs • **Prioridade:** P2 • **Estimativa:** M • **Camada:** shared • **Depende de:** [TYPES-002]

**Contexto**

Cobre necessidade em `06-api-react.md` §15. Users podem querer types TypeScript que espelhem seus Eloquent models.

**Descrição técnica**

- Documentar integração com `spatie/laravel-typescript-transformer` em docs
- Exemplo: adicionar `#[TypeScript]` attribute nos models, rodar `php artisan typescript:transform`
- Gera `resources/js/types/generated/records.ts` com interfaces
- Fallback: types generics `RecordType` para quem não quer usar transformer

**Critérios de aceite**

- [ ] Docs cobrem setup do spatie/laravel-typescript-transformer
- [ ] Exemplo em `examples/laravel-typescript-transformer/` funciona end-to-end
- [ ] Sem obrigação — tudo funciona com `RecordType` genérico

**Notas de implementação**

- Integração direta com spatie seria feature de Fase 2. Fase 1 = documentação.

---

### [TYPES-004] Testes e SKILL.md do `@arqel-dev/types`

**Tipo:** test + docs • **Prioridade:** P1 • **Estimativa:** S • **Camada:** react + docs • **Depende de:** [TYPES-002]

**Descrição técnica**

- Tests com Vitest: type-level assertions (usar `expect-type` lib)
- Cada shape importante tem teste de estrutura
- SKILL.md: quando importar cada subpath, padrões de uso, integração com RecordType

**Critérios de aceite**

- [ ] `pnpm test` passa
- [ ] 20+ type-level assertions cobrindo shapes críticos
- [ ] SKILL.md completo

---

### [REACT-001] Esqueleto do pacote `@arqel-dev/react`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** react • **Depende de:** [TYPES-001]

**Contexto**

Pacote que contém Inertia bindings + providers + utilities compartilhadas. Fundação do lado React.

**Descrição técnica**

Estrutura `packages-js/react/`:

- `package.json`:
  ```json
  {
    "name": "@arqel-dev/react",
    "peerDependencies": {
      "@inertiajs/react": "^3.0",
      "react": "^19.2.3",
      "react-dom": "^19.2.3"
    },
    "dependencies": {
      "@arqel-dev/types": "workspace:*"
    }
  }
  ```
- `src/index.ts`
- `src/inertia/` (createArqelApp, resolvePage, layoutResolver)
- `src/providers/` (ArqelProvider, ThemeProvider)
- `src/context/` (PanelContext, ResourceContext, TenantContext stub)
- `src/utils/` (route, translate, serializeFields)
- `tsconfig.json`, `tsup.config.ts`
- `README.md`, `SKILL.md`
- `tests/`

**Critérios de aceite**

- [ ] `pnpm build` gera `dist/` corretamente
- [ ] Peer deps declaradas
- [ ] SKILL.md esqueleto

---

### [REACT-002] `createArqelApp` — bootstrap de Inertia + Arqel

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** react • **Depende de:** [REACT-001]

**Contexto**

Entry point que o user invoca em `resources/js/app.tsx`. Envolve `createInertiaApp` com defaults Arqel.

**Descrição técnica**

Criar `src/inertia/createArqelApp.ts` com signature:

```typescript
export interface ArqelAppOptions {
    title?: (title: string) => string
    pages?: Record<string, () => Promise<unknown>>
    layout?: (page: ReactNode) => ReactNode
    setup?: InertiaAppOptions['setup']
    progress?: boolean | { color?: string; delay?: number }
}

export async function createArqelApp(options: ArqelAppOptions = {}): Promise<void>
```

Internamente chama `createInertiaApp` do Inertia com defaults: title callback, resolve via `resolveArqelPage` (built-in + user pages), setup que envolve com `ArqelProvider`, progress bar com CSS var `--color-primary`.

`resolveArqelPage`: resolve rotas `arqel::*` (built-in pages do pacote UI) e user pages em `resources/js/Pages/`.

**Critérios de aceite**

- [ ] User `import { createArqelApp } from '@arqel-dev/react'; createArqelApp()` funciona
- [ ] Pages default Arqel (`arqel::resource-index`) resolvem
- [ ] User pages em `resources/js/Pages/` resolvem
- [ ] Progress bar mostrada em navegações
- [ ] SSR compatible (hydrateRoot se DOM existe, createRoot senão)
- [ ] Teste: bootstrap em playground

**Notas de implementação**

- Inertia 3 preferred setup usa `createInertiaApp` — não reinventar.
- Progress bar é feature Inertia, apenas config aqui.

---

### [REACT-003] `ArqelProvider` + context providers

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** react • **Depende de:** [REACT-002]

**Contexto**

Context Providers fornecem acesso a panel, resource, tenant, theme em qualquer componente filho.

**Descrição técnica**

`src/providers/ArqelProvider.tsx`: wraps app com ThemeProvider + PanelContext.Provider + FlashToastContainer.

`src/providers/ThemeProvider.tsx`: gerencia tema (`light` | `dark` | `system`) com persistence em localStorage (`arqel-theme`). Atualiza `document.documentElement` classes. SSR-safe: lê localStorage só no mount.

`src/context/PanelContext.tsx`, `ResourceContext.tsx`, `TenantContext.tsx`: simples contexts com types. TenantContext é stub em Fase 1 (sempre `null`).

Hooks exportados: `useTheme()`, `usePanel()`, `useResource()` (este em HOOKS-002 na verdade).

**Critérios de aceite**

- [ ] `<ArqelProvider>` wraps app corretamente
- [ ] Theme persiste via localStorage
- [ ] `useTheme()` retorna state correto em qualquer componente filho
- [ ] System theme respeita `prefers-color-scheme`
- [ ] No FOUC (flash of unstyled content) — theme aplicado antes do primeiro paint
- [ ] Testes com Testing Library cobrem toggle theme

**Notas de implementação**

- FOUC mitigation: `CORE-012` tem script inline no `app.blade.php` que aplica classe antes de React load.
- `FlashToastContainer` será implementado em UI-006.

---

### [REACT-004] Utilities: route(), translate(), serializeFields()

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** react • **Depende de:** [REACT-003]

**Contexto**

Funções utilitárias compartilhadas. `route()` usa Ziggy, `translate()` lê shared props, `serializeFields()` converte JSON de field schema para runtime.

**Descrição técnica**

- `src/utils/route.ts`: wrapper tipado sobre `window.route` (Ziggy). Lança erro claro se Ziggy não disponível.
- `src/utils/translate.ts`: hook `useTranslate()` que retorna função `t(key, replacements)`. Suporta placeholders `:name`.
- `src/utils/serializeFields.ts`: utility para construir form initial state a partir de field schemas (aplica defaults, respeita types).

**Critérios de aceite**

- [ ] `route('arqel.admin.users.index')` retorna URL válida
- [ ] `const t = useTranslate(); t('arqel.actions.create')` funciona
- [ ] Replacements funcionam: `t('hello', { name: 'World' })` → "Hello World"
- [ ] Missing key retorna chave como fallback (não undefined)
- [ ] Testes cobrem cada utility

**Notas de implementação**

- Ziggy é dep opcional — se user não instalou, `route()` lança erro claro.
- Para translate server-rendered, alternativa é gerar tags `__('key')` em Blade e passar via shared props (implementado em CORE-013).

---

### [HOOKS-001] Esqueleto do pacote `@arqel-dev/hooks`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** react • **Depende de:** [REACT-001]

**Contexto**

Todos os hooks reusáveis consolidados num pacote. Ver `06-api-react.md` §9.

**Descrição técnica**

Estrutura `packages-js/hooks/`:

- `package.json` com peer deps React 19.2+, @inertiajs/react
- `src/index.ts`
- `src/useResource.ts`, `useArqelForm.ts`, `useCanAccess.ts`
- `src/useFieldDependencies.ts`, `useTable.ts`, `useAction.ts`
- `src/useFlash.ts`, `useNavigation.ts`, `useBreakpoint.ts`, `useOptimistic.ts`
- SKILL.md, tests/

**Critérios de aceite**

- [ ] `pnpm build` funciona
- [ ] Hooks exportados individualmente (tree-shakeable)
- [ ] SKILL.md esqueleto

---

### [HOOKS-002] `useResource` e `useArqelForm`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** react • **Depende de:** [HOOKS-001]

**Contexto**

Dois hooks centrais para Resource pages. Ver `06-api-react.md` §9.1 e §9.2.

**Descrição técnica**

`src/useResource.ts`: wrapper sobre `usePage()` Inertia com types de Resource.

`src/useArqelForm.ts`: wrapper sobre `useForm` Inertia adicionando:
- `fields`: array de FieldSchema para introspection
- `validate()`: validação client-side via Zod schema reconstruído do array de validation rules
- `validateField(name)`: validação de campo individual
- `clientErrors`: erros de validação client-side (separados dos server errors)

Build Zod schema dinamicamente a partir dos fields — usa ValidationBridge serialized rules (FIELDS-012).

**Critérios de aceite**

- [ ] `useResource<User>()` retorna props tipadas corretamente
- [ ] `useArqelForm(defaults, fields)` integra com Inertia useForm
- [ ] Client-side validation via Zod funciona
- [ ] Testes com Testing Library cobrem submit success + fail

**Notas de implementação**

- Client-side validation é progressive enhancement — não substitui server validation.
- `buildZodSchema` fica em utils internos.

---

### [HOOKS-003] `useCanAccess` e `useFlash`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** react • **Depende de:** [HOOKS-002]

**Descrição técnica**

`src/useCanAccess.ts`: lê `auth.can` dos shared props. Suporta record-level abilities via `record.can[ability]` (serializado server-side per-record quando relevante).

`src/useFlash.ts`: lê `flash` dos shared props. Opcionalmente invoca callback `onMessage(type, message)` quando novas flash messages aparecem — útil para integrar com toast library.

**Critérios de aceite**

- [ ] `useCanAccess('users.create')` retorna boolean correto
- [ ] `useFlash()` fornece mensagens via Inertia shared props
- [ ] `onMessage` callback é chamado quando nova flash aparece
- [ ] Testes cobrem happy path e missing ability

**Notas de implementação**

- **Crítico:** `useCanAccess` é UX only. Enforcement real é server-side (ADR-017).
- Record-level abilities são serializadas server-side em cada record (optimization: só para actions ativas).

---

### [HOOKS-004] `useTable`, `useAction`, `useFieldDependencies`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** react • **Depende de:** [HOOKS-002]

**Descrição técnica**

`src/useTable.ts`: gerencia estado de tabela (filters, sort, selection). Sync opcional com URL via `router.get()` Inertia com `preserveState: true`. Retorna `{sort, setSort, filters, setFilter, clearFilters, selectedIds, toggleSelection, selectAll, clearSelection}`.

`src/useAction.ts`: invoca Action via Inertia `router.visit`. Retorna `{invoke, processing, progress}` (progress é stub Fase 1).

`src/useFieldDependencies.ts`: listener para mudanças em fields com `dependsOn`. Quando source field muda, dispara Inertia partial reload com `only: ['fields.{dependent}.options']` após debounce 300ms.

**Critérios de aceite**

- [ ] `useTable()` sync state com URL
- [ ] Ação invoke redirects ou updates após sucesso
- [ ] Dependency changes disparam partial reload
- [ ] Debounce 300ms em dependency updates
- [ ] Testes cobrem cenários principais

**Notas de implementação**

- Debouncing evita thrashing em typing rápido.
- Selection em useTable poderia persistir entre páginas (Fase 2).

---

### [HOOKS-005] `useNavigation`, `useBreakpoint`, `useOptimistic`

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** react • **Depende de:** [HOOKS-004]

**Descrição técnica**

- `useNavigation()`: lê panel nav de shared props, retorna estrutura renderizável com visibility resolvida
- `useBreakpoint()`: hook Tailwind v4-aware que retorna breakpoint atual (`'sm' | 'md' | 'lg' | 'xl' | '2xl'`). Usa `matchMedia` internamente.
- `useOptimistic()`: wrapper sobre React 19.2 `useOptimistic` para padrões comuns (create, update, delete records com optimistic UI)

**Critérios de aceite**

- [ ] Todos os 3 hooks exportados
- [ ] SSR-safe (não acessam window em render)
- [ ] Testes cobrem cada um

---

### [HOOKS-006] Testes + SKILL.md do `@arqel-dev/hooks`

**Tipo:** test + docs • **Prioridade:** P0 • **Estimativa:** M • **Camada:** react + docs • **Depende de:** [HOOKS-005]

**Descrição técnica**

- Tests com Testing Library + `@testing-library/react-hooks`
- Coverage ≥ 85%
- SKILL.md com exemplos de uso de cada hook, anti-patterns (usar hook fora de Inertia context, não usar types)

**Critérios de aceite**

- [ ] `pnpm test` passa
- [ ] Coverage ≥ 85%
- [ ] SKILL.md completo

---

### [UI-001] Esqueleto do pacote `@arqel-dev/ui`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** react • **Depende de:** [REACT-001, HOOKS-001]

**Contexto**

Pacote de componentes estruturais — AppShell, DataTable, FormRenderer, etc. Componentes atômicos (Button, Input) vêm via ShadCN CLI registry.

**Descrição técnica**

Estrutura `packages-js/ui/`:

- `package.json` com peer deps @arqel-dev/react, @arqel-dev/hooks, @arqel-dev/types, React 19.2+
- Deps runtime:
  - `radix-ui` (Radix UI, ADR-007 — ver update 2026-05)
  - `lucide-react` (icons)
  - `class-variance-authority`, `clsx`, `tailwind-merge`
  - `@tanstack/react-table` (para DataTable)
- `src/` estrutura conforme `04-repo-structure.md` §4.4
- `tsup.config.ts` com múltiplos entries
- CSS: `src/styles/globals.css` com Tailwind v4 imports + CSS vars
- SKILL.md, tests/

**Critérios de aceite**

- [ ] Build funciona
- [ ] CSS é exportado e pode ser importado
- [ ] Peer deps declaradas
- [ ] SKILL.md esqueleto

**Notas de implementação**

- Tailwind v4 syntax (`@import 'tailwindcss';`) — diferente de v3.
- Não embutir Tailwind build — expor CSS vars prontas para usuário usar em seu próprio Tailwind.

---

### [UI-002] AppShell + Sidebar + Topbar + MainContent

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** XL • **Camada:** react • **Depende de:** [UI-001, HOOKS-005]

**Contexto**

Layout top-level do admin panel. Cobre RF-N-07.

**Descrição técnica**

`src/shell/AppShell.tsx`: componente root com 4 variants (`sidebar-left` | `sidebar-right` | `topbar-only` | `full-width`). Aceita slots via props: `sidebar`, `topbar`, `footer`, `children`.

`src/shell/Sidebar.tsx`: renderiza nav items de `useNavigation()`. Features:
- Collapsible groups
- Icons + labels
- Badges dinâmicos (via closure server-side)
- Mobile: overlay drawer (slide-in via Radix UI Dialog)
- Desktop: fixed width (240px default, configurable via CSS var `--sidebar-width`)
- Active state (current page highlighted)
- Keyboard navigation: Tab, Enter, Arrow keys
- Brand/logo section no topo

`src/shell/Topbar.tsx`: 
- Logo/brand
- Search (global) — placeholder Fase 1, ativo em Fase 2 (Command palette)
- User menu (avatar → dropdown com Profile, Settings, Logout)
- Theme toggle (usa `useTheme`)
- Tenant switcher — stub Fase 1

`src/shell/MainContent.tsx`:
- Wrapper com padding responsivo
- Max-width aplicado conforme variant
- Suporta breadcrumbs + page header via slots
- `<Breadcrumbs>` auto-renderizado de shared props

**Critérios de aceite**

- [ ] 4 variants renderizam corretamente
- [ ] Sidebar mobile (overlay) funciona com body scroll lock
- [ ] Theme toggle atualiza DOM
- [ ] User menu abre e fecha com keyboard
- [ ] A11y: navegação via keyboard completa, ARIA roles
- [ ] Responsive breakpoints: sm/md/lg
- [ ] Testes com Testing Library

**Notas de implementação**

- Radix UI tem `Dialog` para mobile drawer.
- Usar `useBreakpoint()` (HOOKS-005) para detectar mobile.

---

### [UI-003] ResourceIndex + DataTable + TableToolbar

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** XL • **Camada:** react • **Depende de:** [UI-002, HOOKS-004]

**Contexto**

Página de listagem com tabela. Core do CRUD. Ver `06-api-react.md` §8.2 e §8.3.

**Descrição técnica**

`src/resource/ResourceIndex.tsx`: componente de página que recebe `ResourceIndexProps<T>` e renderiza:
- Header com título + toolbar actions + create button
- Search input + filters
- DataTable
- Pagination

`src/table/DataTable.tsx`: usa TanStack Table v8. Features:
- Column polymorphism: cada tipo (text, badge, date, boolean, image, icon, computed, relationship) tem renderer
- Row selection via checkboxes com Shift+click range select
- Actions cell (dropdown menu se >3 actions, inline buttons se ≤3)
- Sticky header em scroll
- Empty state customizável (via `Table::emptyState()` serializado)
- Loading state (skeleton rows)
- A11y: ARIA roles, keyboard navigation

`src/table/TableFilters.tsx`: renderiza cada filter type (select, dateRange, text, ternary). Active filters shown como chips com clear individual. "Clear all" button.

`src/table/TablePagination.tsx`: navegação prev/next + per-page selector + total count + range display.

`src/table/TableToolbar.tsx`: container para search + filters. Bulk actions bar visível quando há selection (sticky top).

**Critérios de aceite**

- [ ] ResourceIndex renderiza com 50 records + 8 colunas em <100ms
- [ ] Sort click no header atualiza URL e recarrega via Inertia
- [ ] Filter select atualiza URL com debounce
- [ ] Checkbox selection funciona com Shift+click range
- [ ] Bulk actions mostram count selecionados
- [ ] Pagination navegação preserva filters
- [ ] Empty state mostra customização
- [ ] Keyboard navigation completa (Tab, Enter, Space, Arrows)
- [ ] Testes com Testing Library cobrem interações

**Notas de implementação**

- TanStack Table é headless — dá controle total sobre rendering.
- Row selection usa Sets para O(1) lookup.
- Virtual scrolling para 100k rows fica em Fase 2.

---

### [UI-004] FormRenderer + FieldRenderer + layout components

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** react • **Depende de:** [UI-001, FIELDS-JS-001]

**Contexto**

Renderização de forms. Polimorfismo baseado em field.type/component.

**Descrição técnica**

`src/form/FormRenderer.tsx`: itera sobre `form.schema` e renderiza cada componente via switch em `component.type`. Suporta Section, Fieldset, Grid, Columns, Group, Tabs, e Field (folha).

`src/form/FieldRenderer.tsx`: resolve component via `getFieldComponent(field.component)` (registry) e renderiza com props padrão.

`src/form/FormSection.tsx`: section com heading, description opcional, collapsible, aside layout, columns internas.

`src/form/FormFieldset.tsx`: `<fieldset>` semântico com legend.

`src/form/FormGrid.tsx`: CSS grid responsivo com breakpoints.

`src/form/FormTabs.tsx`: tabs usando Radix UI Tabs primitive. Default tab + navigation. Em Fase 1 não preserva state ao submit.

**Critérios de aceite**

- [ ] Form com 20 fields renderiza corretamente
- [ ] Layout components (Section, Grid, Tabs) funcionam
- [ ] Erros de validation aparecem inline
- [ ] Disabled/readonly state correto
- [ ] Testes cobrem cada layout component

**Notas de implementação**

- FieldRenderer é generic — registry de components resolve polimorficamente (ver FIELDS-JS-005).
- Nested layouts (Grid dentro de Section) funcionam recursivamente.

---

### [UI-005] ActionButton + ActionMenu + ConfirmDialog + ActionFormModal

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** react • **Depende de:** [UI-001, HOOKS-004]

**Contexto**

Componentes de ação. Invocam actions via Inertia.

**Descrição técnica**

`src/action/ActionButton.tsx`: botão que invoca ação. Se `requiresConfirmation` → abre ConfirmDialog. Se `form` → abre ActionFormModal. Senão → invoca diretamente. Loading state durante invocation.

`src/action/ActionMenu.tsx`: dropdown menu para múltiplos actions (>3 usa dropdown, menos usa inline buttons).

`src/action/ConfirmDialog.tsx`: modal com variants (destructive/warning/info). Suporta "type to confirm" onde user deve digitar texto exato. Focus trap, keyboard Escape para cancel, Enter para confirm quando válido.

`src/action/ActionFormModal.tsx`: modal contendo FormRenderer com form da action. Submit invoca action com form data.

**Critérios de aceite**

- [ ] Action simples executa diretamente
- [ ] Action com confirmation abre dialog
- [ ] Action com form abre modal com form
- [ ] ConfirmDialog "type to confirm" valida texto exato
- [ ] Loading state durante invoke
- [ ] A11y: focus management em modals
- [ ] Testes cobrem cada cenário

**Notas de implementação**

- Radix UI Dialog/AlertDialog como base.
- Form modal reutiliza FormRenderer.

---

### [UI-006] CanAccess + FlashContainer + utility components

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** react • **Depende de:** [HOOKS-003]

**Descrição técnica**

`src/auth/CanAccess.tsx`: wrapper conditional baseado em `useCanAccess`. Accepts `ability`, `record?`, `fallback?`.

`src/flash/FlashContainer.tsx`: integra `useFlash` com toast library (sonner ou ShadCN toast). Render-less — toast lib renderiza separadamente.

Outros utilities:
- `Breadcrumbs`: auto-renderiza de shared props
- `PageHeader`: wrapper semântico para título + descrição + actions
- `EmptyState`: layout padronizado para empty states
- `ErrorState`: exibição de erros (404, 403, 500)
- `LoadingSkeleton`: skeleton loader variações

**Critérios de aceite**

- [ ] `<CanAccess ability="...">` renderiza conditional
- [ ] Flash messages aparecem como toasts
- [ ] Testes cobrem utilities

**Notas de implementação**

- Usar `sonner` (lib moderna de toast) ou ShadCN toast (user-owned).

---

### [UI-007] Testes + SKILL.md do `@arqel-dev/ui`

**Tipo:** test + docs • **Prioridade:** P0 • **Estimativa:** L • **Camada:** react + docs • **Depende de:** [UI-006]

**Descrição técnica**

- Tests com Testing Library: cobertura de interações, a11y
- Snapshot tests para rendering
- Coverage ≥ 80% (difícil atingir 90% em UI com muita branching visual)
- SKILL.md completo

**Critérios de aceite**

- [ ] Vitest passa
- [ ] Coverage ≥ 80%
- [ ] SKILL.md completo

---

### [FIELDS-JS-001] Esqueleto do pacote `@arqel-dev/fields` (JS)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** react • **Depende de:** [UI-001]

**Contexto**

Um componente React por field type. Field registry singleton para resolução polimórfica.

**Descrição técnica**

Estrutura `packages-js/fields/`:

- `package.json` com peer deps
- `src/registry.ts`: FieldRegistry singleton
- `src/text/`, `src/number/`, `src/boolean/`, ..., um subdir por categoria
- `src/index.ts`: registra built-in fields em boot
- SKILL.md, tests/

**Critérios de aceite**

- [ ] Build funciona
- [ ] Registry exportado
- [ ] SKILL.md esqueleto

---

### [FIELDS-JS-002] Field components básicos (Text, Number, Boolean)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** react • **Depende de:** [FIELDS-JS-001]

**Descrição técnica**

Em `src/text/`:
- `TextInput.tsx`, `TextareaInput.tsx`, `PasswordInput.tsx` (com revealable toggle), `EmailInput.tsx`, `UrlInput.tsx`

Em `src/number/`:
- `NumberInput.tsx` (com incremental buttons opcional), `CurrencyInput.tsx` (com Intl.NumberFormat display)

Em `src/boolean/`:
- `Checkbox.tsx`, `Toggle.tsx`

Cada component segue interface `FieldComponentProps`:
```typescript
export interface FieldComponentProps<T = unknown> {
    field: FieldSchema
    value: T
    onChange: (value: T) => void
    error?: string
    disabled?: boolean
    readonly?: boolean
    record?: unknown
}
```

Base em ShadCN primitives (Input, Checkbox, Switch) via copy-paste do registry.

**Critérios de aceite**

- [ ] 9 components implementados
- [ ] Cada respeita disabled/readonly/error state
- [ ] A11y: labels associados, aria-invalid em erro
- [ ] Keyboard: Tab, Space (boolean), Enter (submit)
- [ ] Testes cobrem cada

**Notas de implementação**

- `CurrencyInput` usa Intl.NumberFormat para display.
- `PasswordInput` com toggle visibility via button no end.

---

### [FIELDS-JS-003] Field components avançados (Select, BelongsTo, Date, File)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** XL • **Camada:** react • **Depende de:** [FIELDS-JS-002]

**Descrição técnica**

- `select/SelectInput.tsx`: native `<select>` ou Radix UI Combobox (searchable). Props determinam qual.
- `select/MultiSelectInput.tsx`: chips + combobox, multi-select
- `select/RadioGroup.tsx`: radio group inline ou vertical
- `relationship/BelongsToInput.tsx`: async combobox via endpoint search (debounced 300ms)
- `relationship/HasManyReadonly.tsx`: tabela inline readonly dos related records
- `date/DateInput.tsx`: date picker com `react-day-picker`
- `date/DateTimeInput.tsx`: date + time picker
- `file/FileInput.tsx`: drag-drop + button upload com progress
- `file/ImageInput.tsx`: preview + crop via `react-image-crop`

**Critérios de aceite**

- [ ] 9 components implementados
- [ ] Select searchable com combobox (Radix UI)
- [ ] BelongsTo faz debounced search
- [ ] Date picker tem min/max validation
- [ ] File upload shows progress
- [ ] Image crop funciona com aspect ratio
- [ ] Testes cobrem cada

**Notas de implementação**

- BelongsTo cache search results em memória por session.
- Image crop é pesado — considerar lazy loading.

---

### [FIELDS-JS-004] Fields simples (Slug, Color, Hidden)

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** react • **Depende de:** [FIELDS-JS-003]

**Descrição técnica**

- `slug/SlugInput.tsx`: auto-gera de outro field via dependency (escuta form data changes)
- `color/ColorInput.tsx`: color picker com presets (usar `react-colorful`)
- `hidden/HiddenInput.tsx`: renderiza `<input type="hidden">`, sem label visual

**Critérios de aceite**

- [ ] 3 components implementados
- [ ] Slug auto-gera quando source field muda
- [ ] Color picker funcional
- [ ] Hidden não tem label visível

---

### [FIELDS-JS-005] Registry + registro automático de fields built-in

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** react • **Depende de:** [FIELDS-JS-004]

**Descrição técnica**

`src/registry.ts`: singleton `Map<string, ComponentType<FieldComponentProps>>` com:
- `registerField(name, component)`
- `getFieldComponent(name)` → `ComponentType | null`
- `getRegisteredFields()` → `string[]`

`src/index.ts`: boot registra todos os 20 built-in fields.

**Critérios de aceite**

- [ ] Todos os 20 built-in fields registrados em boot
- [ ] `getFieldComponent('TextInput')` retorna component
- [ ] Users podem registrar custom: `registerField('MyField', MyField)`
- [ ] Teste cobre registry

---

### [FIELDS-JS-006] Testes + SKILL.md do `@arqel-dev/fields`

**Tipo:** test + docs • **Prioridade:** P0 • **Estimativa:** M • **Camada:** react + docs • **Depende de:** [FIELDS-JS-005]

**Descrição técnica**

- Tests com Testing Library cobrindo cada field component
- Coverage ≥ 80%
- SKILL.md: como criar custom field, exemplo completo (PHP + React component + register)

**Critérios de aceite**

- [ ] Vitest passa
- [ ] Coverage ≥ 80%
- [ ] SKILL.md completo

---

## 11. Documentação e exemplos (DOCS)

### [DOCS-001] Setup do site de documentação

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** L • **Camada:** docs • **Depende de:** [INFRA-001]

**Contexto**

Site público em `arqel.dev` ou `docs.arqel.dev`. Decisão: Nextra vs VitePress.

**Descrição técnica**

**Recomendação:** **VitePress** — mais alinhado com ecossistema Vite, menor (sem Next.js overhead), bom para docs técnicas.

Estrutura `apps/docs/`:
- `package.json`
- `.vitepress/config.ts`
- `.vitepress/theme/` (custom theme)
- `index.md` (landing)
- `guide/` (getting started, installation, concepts)
- `resources/` (Resource, Fields, Table, Form, Actions)
- `advanced/` (custom fields, macros, multi-tenancy stub)
- `reference/` (API reference)
- `examples/`

Deploy via GitHub Pages ou Cloudflare Pages. Search integrado (Algolia DocSearch ou VitePress built-in).

**Critérios de aceite**

- [ ] Site roda localmente via `pnpm dev`
- [ ] Deploy preview em PR
- [ ] Busca funciona
- [ ] Dark mode toggle
- [ ] Mobile responsive

**Notas de implementação**

- VitePress 1.0+ para features modernas.
- Landing page pode ser custom Vue ou HTML statica.

---

### [DOCS-002] Getting Started (< 10 min)

**Tipo:** docs • **Prioridade:** P0 • **Estimativa:** M • **Camada:** docs • **Depende de:** [DOCS-001]

**Contexto**

Cobre RNF-D-01. Primeiro contato do user com Arqel.

**Descrição técnica**

Página `guide/getting-started.md` com:

1. Prerequisites (PHP 8.3+, Laravel 12+, Node 20.9+, pnpm)
2. Create fresh Laravel project
3. Install Arqel: `composer require arqel-dev/framework` + `php artisan arqel:install`
4. Install frontend: `pnpm add @arqel-dev/react @arqel-dev/ui @arqel-dev/hooks @arqel-dev/fields @arqel-dev/types`
5. Configure `resources/js/app.tsx` com `createArqelApp`
6. Gerar primeiro Resource: `php artisan arqel:resource User --from-model --with-policy`
7. Run: `php artisan serve` + `pnpm dev`
8. Screenshot do resultado

**Critérios de aceite**

- [ ] Steps testáveis passo-a-passo
- [ ] Screenshot/gif demo
- [ ] Timer: dev novo completa em <10 min
- [ ] Links para próximos passos

**Notas de implementação**

- Incluir troubleshooting comum (PHP version, Node version, permissions).

---

### [DOCS-003] Conceitos essenciais: Panels, Resources, Fields, Actions, Auth

**Tipo:** docs • **Prioridade:** P0 • **Estimativa:** L • **Camada:** docs • **Depende de:** [DOCS-002]

**Descrição técnica**

Páginas em `guide/concepts/`:

- `panels.md`: o que é Panel, multi-panel, configuração
- `resources.md`: Resource lifecycle, model binding, overrides
- `fields.md`: catálogo completo, fluent API, validation
- `actions.md`: row vs bulk vs toolbar, confirmation, form modals
- `authorization.md`: Laravel Policies integration, field-level auth

Cada página com:
- Conceito em 1 parágrafo
- Exemplo mínimo
- Exemplo avançado
- Links para API reference

**Critérios de aceite**

- [ ] 5 páginas completas
- [ ] Exemplos funcionais (copy-paste)
- [ ] Links internos funcionam

---

### [DOCS-004] Guia: declarar primeiro CRUD completo

**Tipo:** docs • **Prioridade:** P0 • **Estimativa:** M • **Camada:** docs • **Depende de:** [DOCS-003]

**Descrição técnica**

Tutorial passo-a-passo em `guide/tutorial-first-crud.md`:

1. Cenário: blog com Posts
2. Migration + Model
3. UserResource generated
4. PostResource declarar
5. Fields customizados (TitleField, SlugField, RichText stub, BelongsTo)
6. Table com filters
7. Policy
8. Testar UI
9. Deploy considerations

**Critérios de aceite**

- [ ] Tutorial completo executável
- [ ] Repositório exemplo em `examples/first-crud`
- [ ] Timer: user completa em <30 min

---

### [DOCS-005] API Reference — PHP side

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** L • **Camada:** docs • **Depende de:** [DOCS-001]

**Descrição técnica**

- Gerar API reference a partir de docblocks PHP via `phpdoc` ou `doctum`
- Páginas auto-geradas em `reference/php/`
- Organização por namespace: Resources, Fields, Table, Form, Actions, Auth, Nav

**Critérios de aceite**

- [ ] Todas as classes públicas documentadas
- [ ] Docblocks completos nos source files
- [ ] Search funciona em reference
- [ ] CI regenera em cada push

**Notas de implementação**

- Docblocks: descrever params, return, throws, examples.

---

### [DOCS-006] API Reference — TypeScript side

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** M • **Camada:** docs • **Depende de:** [DOCS-001]

**Descrição técnica**

- Gerar reference via `TypeDoc`
- Páginas em `reference/typescript/`
- Organização por package: types, react, hooks, ui, fields

**Critérios de aceite**

- [ ] Types, interfaces, hooks, components documentados
- [ ] TSDoc comments nos sources
- [ ] Search integrado

---

### [DOCS-007] Migration guides — de Filament e Nova

**Tipo:** docs • **Prioridade:** P2 • **Estimativa:** L • **Camada:** docs • **Depende de:** [DOCS-003]

**Contexto**

Facilitar adoção via comparação side-by-side.

**Descrição técnica**

- `guide/migration/from-filament.md`:
  - API mapping (Field::text em ambos, mas overrides diferentes)
  - O que NÃO migra (Livewire code custom)
  - Scripts opcionais para converter resources
- `guide/migration/from-nova.md`:
  - API mapping
  - Vue → React mental model
- `guide/migration/from-react-admin.md` (dev React que quer Laravel-first):
  - Conceitos Laravel para React devs

**Critérios de aceite**

- [ ] 3 guides completos
- [ ] Code comparison tables
- [ ] Limitações claras

**Notas de implementação**

- Migration guides atraem users mesmo que não migrem realmente — funcionam como "what's different" positioning.

---

### [DOCS-008] AGENTS.md template + MCP docs stub

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** S • **Camada:** docs • **Depende de:** [CORE-003]

**Contexto**

Cobre RF-DX-08. AGENTS.md é gerado em `arqel:install` — mas conteúdo vem daqui.

**Descrição técnica**

Em `apps/docs/guide/agents.md`:

- O que é AGENTS.md
- Quando Arqel gera, o que contém:
  - Project overview
  - Key conventions
  - Commands principais
  - Architecture summary
  - Links para SKILLs dos pacotes
- Como customizar
- MCP stub (Fase 2 full implementation)

**Critérios de aceite**

- [ ] Documentação completa
- [ ] Template `AGENTS.md` reproduzível
- [ ] Links para SKILLs funcionais

---

## 12. Governança e release (GOV)

### [GOV-001] SECURITY.md e processo de disclosure

**Tipo:** docs • **Prioridade:** P0 • **Estimativa:** S • **Camada:** docs • **Depende de:** [INFRA-001]

**Descrição técnica**

`SECURITY.md` na raiz com:
- Suportamos security patches nas últimas 2 minor versions
- Como reportar: `security@arqel.dev` (ou GitHub Security Advisories)
- SLA: resposta em 48h, fix em 14 dias para críticas
- PGP key se aplicável
- Hall of Fame para reporters

**Critérios de aceite**

- [ ] SECURITY.md presente e claro
- [ ] Canal de contato funcional
- [ ] Processo testado com dry-run

---

### [GOV-002] Release pipeline automatizado

**Tipo:** infra • **Prioridade:** P0 • **Estimativa:** L • **Camada:** infra • **Depende de:** [INFRA-004]

**Contexto**

Releases manuais são frágeis e inconsistentes. Automatizar desde dia 1.

**Descrição técnica**

- `scripts/release.mjs`:
  - Aceita `--version=0.1.0 --dry-run` ou `--patch`, `--minor`, `--major`
  - Atualiza todos `composer.json` e `package.json` com nova versão
  - Gera CHANGELOG section a partir de Conventional Commits desde última tag
  - Cria git tag + push
- `.github/workflows/release.yml`:
  - Triggered em tag push `v*`
  - Jobs:
    - Build all JS packages
    - Publish to npm (via NPM_TOKEN secret)
    - Split monorepo → sub-repos Packagist via `splitsh/lite`
    - Create GitHub release com CHANGELOG auto-generated

**Critérios de aceite**

- [ ] `pnpm run release --dry-run` mostra plano sem executar
- [ ] Tag push dispara pipeline corretamente
- [ ] Sub-repos Packagist sync automático
- [ ] npm packages published com correct version
- [ ] GitHub release com notes

**Notas de implementação**

- `splitsh/lite` é tool C simples; alternativa: action `packagist/split`.
- Packagist webhook para atualizar listings automaticamente.

---

### [GOV-003] CONTRIBUTING.md + PR/Issue templates + DCO

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** M • **Camada:** docs • **Depende de:** [INFRA-001]

**Descrição técnica**

`CONTRIBUTING.md` na raiz com:
- Setup dev (clone, install, test)
- Estrutura do monorepo
- Workflow: fork → branch → PR
- Convenção de commits (Conventional Commits)
- DCO sign-off obrigatório (`git commit --signoff`)
- Como escrever tests
- Como documentar

`.github/PULL_REQUEST_TEMPLATE.md` com checklist: testes, lint, coverage, docs, DCO.

`.github/ISSUE_TEMPLATE/`:
- `bug_report.yml`
- `feature_request.yml`
- `question.yml`

Instalar GitHub DCO bot no repo.

**Critérios de aceite**

- [ ] Arquivos todos presentes
- [ ] Templates renderizam no GitHub UI
- [ ] DCO bot ativo no repo

**Notas de implementação**

- GitHub DCO bot disponível em apps.

---

## 13. Ordem sugerida de execução

Devido a dependências entre tickets, a ordem de trabalho é constrained. Segue ordem realista de ataque com paralelização por pessoa.

### Sprint 0: Setup (semana 1-2)

**Tudo sequencial, 1 pessoa:**

1. INFRA-001 → INFRA-002 → INFRA-003 → INFRA-004 → INFRA-005
2. GOV-001 (paralelo)
3. GOV-003 (paralelo)

### Sprint 1-2: CORE fundacional (semana 3-6)

**1 dev PHP full-time:**

1. CORE-001 → CORE-002 → CORE-003
2. CORE-004 → CORE-005 → CORE-006 → CORE-007
3. CORE-008 → CORE-009 → CORE-010
4. CORE-011, CORE-012, CORE-013, CORE-014, CORE-015 (paralelizável)

**1 dev JS em paralelo:**

1. TYPES-001 → TYPES-002 → TYPES-003 → TYPES-004
2. REACT-001 → REACT-002 → REACT-003 → REACT-004

**DOCS-001** começa em paralelo logo que INFRA está pronto.

### Sprint 3-4: Fields + Auth + Nav (semana 7-10)

**1 dev PHP:**

1. FIELDS-001 → FIELDS-002 → FIELDS-003
2. FIELDS-004, 005, 006 (paralelizáveis entre si)
3. FIELDS-007 → FIELDS-008 (belongsTo é complexo)
4. FIELDS-009, 010, 011 (paralelos)
5. FIELDS-012 (ValidationBridge — crítico para forms)
6. FIELDS-015 → 016 → 017 → 018 → 019 → 020 → 021 → 022
7. FIELDS-013 → FIELDS-014

**1 dev PHP segundo (paralelo):**

1. AUTH-001 → AUTH-002 → AUTH-003 → AUTH-004 → AUTH-005 (entregue) → AUTH-006 → AUTH-007 → AUTH-008 (DX-parity com Filament/Nova)
2. NAV-001 → NAV-002 → NAV-003 → NAV-004 → NAV-005

**1 dev JS:**

1. HOOKS-001 → HOOKS-002 → HOOKS-003 → HOOKS-004 → HOOKS-005 → HOOKS-006

### Sprint 5-6: Table + Form + Actions (semana 11-14)

**1 dev PHP:**

1. TABLE-001 → TABLE-002 → TABLE-003 → TABLE-004 → TABLE-005
2. TABLE-006 → TABLE-007 → TABLE-008 → TABLE-009 → TABLE-010 → TABLE-011
3. TABLE-012 → TABLE-013

**1 dev PHP segundo:**

1. FORM-001 → FORM-002 → FORM-003 → FORM-004 → FORM-005
2. FORM-006 → FORM-007 → FORM-008 → FORM-009 → FORM-010
3. ACTIONS-001 → ACTIONS-002 → ACTIONS-003 → ACTIONS-004 → ACTIONS-005
4. ACTIONS-006 → ACTIONS-007 → ACTIONS-008 → ACTIONS-009

**1 dev JS:**

1. UI-001 → UI-002 (AppShell é XL — aloque tempo)
2. FIELDS-JS-001 → FIELDS-JS-002 → FIELDS-JS-003
3. UI-003 (ResourceIndex + DataTable — XL)
4. UI-004 (FormRenderer + FieldRenderer)

### Sprint 7: Polimento e docs (semana 15-16)

**Todos os devs focados em:**

1. UI-005 → UI-006 → UI-007
2. FIELDS-JS-004 → FIELDS-JS-005 → FIELDS-JS-006
3. DOCS-002 → DOCS-003 → DOCS-004 → DOCS-005 → DOCS-006
4. DOCS-007 → DOCS-008

### Sprint 8: Release candidate (semana 17-18)

1. Bug fixes
2. Performance tuning (LCP, bundle size)
3. Pilot user onboarding
4. GOV-002 (release pipeline teste)
5. v0.5.0 beta tag

### Sprint 9: Beta público + feedback (semana 19-24+)

1. Iterate com feedback
2. Dogfood em projeto interno
3. Documentação enriquecida
4. Bug bash

**Saída de Fase 1:** v0.5.0-beta público, ≥10 pilot users, critérios de saída cumpridos (ver `07-roadmap-fases.md` §3.4).

---

## Resumo

**Fase 1 MVP:** ~120 tickets detalhados, 4-7 meses com 2-3 devs.

**Entregas principais:**
- Framework Laravel declarativo funcional
- 20 field types, tabela completa, forms com layouts, 4 tipos de action, policies
- 5 pacotes Composer + 5 pacotes npm publicados
- Site docs + getting started < 10 min
- CI/CD completo + release automation
- Beta público para pilot users

**Próximo documento:** `09-fase-2-essenciais.md` — tickets Fase 2 (multi-tenancy, dashboards, MCP, advanced fields).
