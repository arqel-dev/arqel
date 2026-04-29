---
layout: home

hero:
  name: Arqel
  text: Admin panels for Laravel
  tagline: Forjado em PHP, renderizado em React. MIT, Inertia-only, sem build de cliente próprio.
  image:
    src: /hero.svg
    alt: Arqel
  actions:
    - theme: brand
      text: Getting Started
      link: /guide/getting-started
    - theme: alt
      text: GitHub
      link: https://github.com/arqel/arqel
    - theme: alt
      text: Conceitos
      link: /guide/what-is-arqel

features:
  - icon: 🧭
    title: Resources declarativos
    details: Declare um Resource em PHP — Arqel deriva index, create, edit, detail e routes automaticamente.
  - icon: ⚛️
    title: React 19 nativo
    details: Renderiza com React 19.2 + Inertia 3 + ShadCN CLI v4 (Base UI). Sem TanStack Query, sem SWR.
  - icon: 🧩
    title: 21 field types
    details: Text, Number, Currency, Select, BelongsTo, HasMany, Date, File, Image, Color, Slug e mais — server-driven, override no cliente quando precisar.
  - icon: 🔐
    title: Auth Laravel-native
    details: Policies, Gates e abilities do Laravel — sem ACL paralela. Field-level e action-level authorization de série.
  - icon: 🪝
    title: Hooks reusáveis
    details: useResource, useArqelForm, useTable, useFlash, useFieldDependencies, useCanAccess — todos tipados.
  - icon: 🛠️
    title: Extensível
    details: Custom fields, custom actions, registry de macros e MCP server pronto para LLMs explorarem o panel.
---
