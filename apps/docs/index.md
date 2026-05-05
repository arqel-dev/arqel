---
layout: home

hero:
  name: Arqel
  text: Admin panels for Laravel, forged in PHP, rendered in React.
  tagline: An open-source MIT framework for building admin panels with Laravel 12 + Inertia 3 + React 19 + shadcn UI.
  image:
    src: /logo.svg
    alt: Arqel
  actions:
    - theme: brand
      text: Get started
      link: /guide/getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/arqel-dev/arqel

features:
  - icon: 🐘
    title: Laravel-native
    details: Uses Policies, FormRequest, Eloquent and Gate as-is. No DSL to learn — your existing Laravel knowledge transfers directly.
  - icon: ⚛️
    title: Inertia + React 19
    details: Single-page experience without writing fetch boilerplate. PHP declares the schema, React renders it via Inertia.
  - icon: 🎨
    title: shadcn UI (Radix)
    details: Pre-styled with the new-york shadcn registry. Customizable by copy-paste, accessible by default, dark mode included.
  - icon: 🤖
    title: AI-native
    details: First-class MCP server, AI fields and AGENTS.md scaffolding. Build admin panels that play well with your LLM tooling.
  - icon: 📡
    title: Realtime ready
    details: Collaborative editing with Yjs + Laravel Reverb baked in. Multiple admins editing the same record sees live cursors.
  - icon: 🔌
    title: One-line install
    details: composer require arqel-dev/framework + php artisan arqel:install. Provider, middleware, Vite config — all auto-scaffolded.
---
