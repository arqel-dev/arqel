# Changelog

Todas as alterações notáveis a este projeto são documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/),
e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [Unreleased]

### Added

- Estrutura inicial do monorepo (INFRA-001)
- Orquestração pnpm workspace + Composer path repositories (INFRA-002)
- Toolchain de lint/format (Pint, PHPStan, Biome) + git hooks (Husky + lint-staged + commitlint) (INFRA-003)
- Pipeline CI/CD em GitHub Actions (ci, test-matrix, security, docs-deploy, release) + Dependabot grouping (INFRA-004)
- Renovate Bot config com grouping completo + Dependabot reduzido a github-actions (INFRA-005)
- Política de segurança expandida (SLAs, disclosure process, âmbito) (GOV-001)
- Guia de contribuição + PR template + Issue templates (bug/feature/question) (GOV-003)
- Esqueleto do pacote `arqel/core` com composer.json, PSR-4, README, SKILL, phpunit/pest configs (CORE-001)
- `Arqel\Core\ArqelServiceProvider` com auto-discovery, registos singleton (`ResourceRegistry`, `PanelRegistry`), facade `Arqel`, `config/arqel.php`, comando `arqel:install` e suite Pest com Orchestra Testbench (CORE-002)
- Comando `arqel:install` com Laravel Prompts: publica `config/arqel.php`, faz scaffold de `app/Arqel/Resources`, `app/Arqel/Widgets`, `resources/js/Pages/Arqel`, gera `App\Providers\ArqelServiceProvider`, `resources/views/arqel/layout.blade.php` e `AGENTS.md` (RF-DX-08). Stubs em `packages/core/stubs/`. Flag `--force` sobrescreve sem prompt (CORE-003)
- Contract `Arqel\Core\Contracts\HasResource` (7 métodos estáticos: model, slug, label, plural, navigation icon/group/sort) e `Arqel\Core\Resources\ResourceRegistry` com `register`, `registerMany`, `discover` (PSR-4 + Symfony Finder), `findByModel`, `findBySlug`, `has`, `clear` e `all`. Idempotente; `InvalidArgumentException` em classes que não implementem o contract (CORE-004)
- Planejamento completo em `PLANNING/` (13 documentos, 328 tickets)
- Convenções operacionais em `CLAUDE.md` e `AGENTS.md`

[Unreleased]: https://github.com/arqel/arqel/compare/HEAD...HEAD
