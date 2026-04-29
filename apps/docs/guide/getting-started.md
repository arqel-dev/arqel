# Getting Started

> **Status:** stub — versão completa em DOCS-002.

Este guia cobre a instalação mínima do Arqel num projeto Laravel novo. Tempo alvo: < 10 minutos.

## Pré-requisitos

- PHP 8.3+ (testado em 8.3 e 8.4)
- Laravel 12+ (testado em 12.x e 13.x)
- Node 20.9+ LTS

## Instalar

```bash
composer require arqel/core
php artisan arqel:install
```

```bash
pnpm add @arqel/ui @arqel/fields @arqel/react @arqel/types
```

## Primeiro Resource

```bash
php artisan arqel:resource Post
```

A próxima etapa é declarar fields, tables e actions — veja [Conceitos](/guide/panels).
