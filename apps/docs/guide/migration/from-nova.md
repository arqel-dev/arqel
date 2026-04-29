# Migrando de Laravel Nova

> **TL;DR:** Você troca **Vue + Inertia** por **React + Inertia**. A philosophy é parecida (Resource declarativo PHP), mas Arqel é MIT (Nova é paid) e React 19 substitui Vue 3.

## Quando faz sentido migrar

- Time fluente em React (não Vue)
- Quer **TypeScript strict** end-to-end (Nova tem types parciais)
- Não quer pagar licença Nova
- App precisa de feature que Nova ainda não suporta (ex: Tabs nativos com badge dinâmico)

## Quando **não** migrar

- Você usa Nova Tools/Cards comerciais customizados
- Time é fluente Vue e prefere manter
- Você já paga Nova e tudo funciona bem

## Mental shift: Vue → React

| Vue (Nova) | React (Arqel) |
|---|---|
| `<template>` + `<script>` SFC | JSX/TSX |
| `ref()`, `reactive()` | `useState`, `useReducer` |
| `v-model` | `value={...} onChange={...}` (controlled) |
| `computed` | `useMemo` |
| `watch` | `useEffect` |
| Vue Router | (não aplicável — Inertia routing) |
| Pinia/Vuex | React Context + Inertia shared props |

## API mapping

### Resource declaration

| Nova | Arqel |
|---|---|
| `class Post extends Resource` | `final class PostResource extends Resource` |
| `public static $model = 'App\\Models\\Post';` | `protected static string $model = Post::class;` |
| `public static $title = 'title';` | `protected static string $recordTitleAttribute = 'title';` |
| `public static $search = ['title', 'body'];` | (configurado per-column via `->searchable()` em TextColumn) |
| `public function fields(NovaRequest $request)` | `public function fields(): array` |

### Fields

| Nova | Arqel |
|---|---|
| `Text::make('Name')` | `Field::text('name')` (sempre lowercase no key, label auto-derivado) |
| `Text::make('Name')->rules('required', 'max:120')` | `Field::text('name')->required()->maxLength(120)` |
| `Select::make('Status')->options([...])` | `Field::select('status')->options([...])` |
| `BelongsTo::make('User')` | `Field::belongsTo('user_id', UserResource::class)` |
| `HasMany::make('Comments')` | `Field::hasMany('comments', CommentResource::class)` |
| `Boolean::make('Active')` | `Field::boolean('is_active')` |
| `Date::make('Published At')` | `Field::date('published_at')` |
| `DateTime::make('Created At')` | `Field::dateTime('created_at')` |
| `File::make('Document')` | `Field::file('document')` |
| `Image::make('Cover')` | `Field::image('cover')` |
| `Number::make('Price')` | `Field::number('price')` |
| `Currency::make('Price')` | `Field::currency('price')` (com prefix/separators configuráveis) |
| `Slug::make('Slug')` | `Field::slug('slug')->fromField('title')` |
| `Color::make('Theme')` | `Field::color('theme')` |
| `Hidden::make('user_id')` | `Field::hidden('user_id')` |

### Visibility

| Nova | Arqel |
|---|---|
| `->onlyOnIndex()` | `->visibleOn(['table'])` |
| `->onlyOnDetail()` | `->visibleOn(['detail'])` |
| `->onlyOnForms()` | `->visibleOn(['create', 'edit'])` |
| `->hideFromIndex()` | `->hiddenOnTable()` |
| `->hideWhenCreating()` | `->hiddenOnCreate()` |
| `->canSee(fn () => $user->is_admin)` | `->canSee(fn ($user) => $user?->is_admin)` |

### Actions

| Nova | Arqel |
|---|---|
| `class PublishPost extends Action` | `RowAction::make('publish')->action(fn ($record) => ...)` |
| `public function fields(): array` (na Action) | `->form([Field::textarea('reason')])` |
| `public $confirmText, $confirmButtonText` | `->modalDescription()`, `->modalSubmitLabel()` |

Nova trata Actions como classes próprias; Arqel usa builder fluente — bem menos boilerplate.

### Authorization

| Nova | Arqel |
|---|---|
| Policy convention bate (1:1 com Laravel Policies) | (mesmo) |
| `Resource::authorizable()` | (default true; Policy override conforme habitual) |
| `->canSee(fn ($request) => ...)` | `->canSee(fn ($user, $record) => ...)` (signature diferente) |

### Filters & Lenses

| Nova | Arqel |
|---|---|
| Filter classe própria | `SelectFilter::make()`, `DateRangeFilter::make()`, etc. (builder) |
| Lens (custom view) | (não existe) — para custom views, override Inertia page em `Pages/Arqel/Index.tsx` |

### Tools, Cards, Dashboards

| Nova | Arqel |
|---|---|
| Tools | (não existe — usar Resource custom com sua Inertia page) |
| Cards no dashboard | Widgets (registrar em `Panel::widgets([...])`) |
| Custom Dashboard | Phase 2 — full Dashboard API ainda em construção |

## O que **não** migra

- ❌ **Vue components custom** — recriar em React
- ❌ **Nova Tools paid/community** — quase nenhum tem equivalente Arqel
- ❌ **`Trend`, `Value`, `Partition` cards** — Phase 2 introduz Widget abstraction
- ❌ **Lenses** — não há equivalente direct; refactor para custom Inertia page

## Estratégia de migração

1. **Mapping spreadsheet:** liste todos seus Resources Nova + features usadas. Marque cada uma com Arqel-equivalent (acima) ou ❌ (rewrite needed)
2. **Migration paralela:** Arqel em `/admin` (Nova permanece em `/nova` até feature parity)
3. **User testing por audience:** alguns Resources antes de outros, conforme o time confirma usability
4. **Decommission Nova:** quando 100% migrado, `composer remove laravel/nova` + drop `/nova` route

## Próximos passos

- [Vindo de Filament](/guide/migration/from-filament)
- [Vindo de react-admin](/guide/migration/from-react-admin)
- [Tutorial: primeiro CRUD](/guide/tutorial-first-crud)
