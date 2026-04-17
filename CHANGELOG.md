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
- Planejamento completo em `PLANNING/` (13 documentos, 328 tickets)
- Convenções operacionais em `CLAUDE.md` e `AGENTS.md`

[Unreleased]: https://github.com/arqel/arqel/compare/HEAD...HEAD
