# Migrando de Filament

> **TL;DR:** A API declarativa de Resource/Field/Table é quase idêntica. O grande shift é o **bridge**: Livewire → Inertia + React. Custom Livewire components não migram — viram React components.

## Quando faz sentido migrar

- Você precisa de **TypeScript end-to-end** (front + types compartilhados)
- Time prefere React/Inertia a Alpine/Livewire
- Quer evitar lock-in proprietário (Filament v4 tem features paid)
- Equipe já tem expertise React e quer aproveitá-la no admin

## Quando **não** migrar

- Você usa Filament Plugins comerciais críticos (alguns só existem no Filament)
- App é majoritariamente non-admin (Livewire pode estar OK pro caso geral)
- Time é puro PHP sem JS background — Inertia ainda exige React/Vite know-how

## API mapping

### Resource declaration

| Filament | Arqel |
|---|---|
| `class PostResource extends Resource` | `final class PostResource extends Resource` (`final` por convenção) |
| `protected static ?string $model = Post::class;` | `protected static string $model = Post::class;` (não-nullable) |
| `protected static ?string $navigationIcon = 'heroicon-o-newspaper';` | `protected static ?string $navigationIcon = 'newspaper';` (lucide-react ID) |
| `public static function form(Form $form): Form` | `public function form(): Form` (não-static, sem param) |
| `public static function table(Table $table): Table` | `public function table(): Table` |
| `public static function getPages(): array` | (não existe — routes são derivadas automaticamente) |

### Fields

| Filament | Arqel |
|---|---|
| `Forms\Components\TextInput::make('name')` | `Field::text('name')` |
| `->required()` | `->required()` |
| `->maxLength(120)` | `->maxLength(120)` |
| `Forms\Components\Select::make('status')->options([...])` | `Field::select('status')->options([...])` |
| `Forms\Components\Select::make('user_id')->relationship('user', 'name')` | `Field::belongsTo('user_id', UserResource::class)` |
| `Forms\Components\RichEditor::make('body')` | `Field::textarea('body')` (RichText custom em Phase 2) |
| `Forms\Components\Toggle::make('is_active')` | `Field::toggle('is_active')` |
| `Forms\Components\DatePicker::make('published_at')` | `Field::date('published_at')` |
| `Forms\Components\FileUpload::make('cover')->image()` | `Field::image('cover')` |
| `Forms\Components\ColorPicker::make('theme')` | `Field::color('theme')` |
| `Forms\Components\Hidden::make('user_id')` | `Field::hidden('user_id')` |

### Tables

| Filament | Arqel |
|---|---|
| `Tables\Columns\TextColumn::make('title')` | `TextColumn::make('title')` |
| `->sortable()->searchable()` | `->sortable()->searchable()` |
| `Tables\Columns\BadgeColumn::make('status')->colors([...])` | `BadgeColumn::make('status')->colors([...])` |
| `Tables\Columns\IconColumn::make('verified')->boolean()` | `BooleanColumn::make('verified')` |
| `Tables\Filters\SelectFilter::make('status')` | `SelectFilter::make('status')` |
| `Tables\Filters\TernaryFilter::make('is_published')` | `TernaryFilter::make('is_published')` |

### Actions

| Filament | Arqel |
|---|---|
| `Tables\Actions\EditAction::make()` | `Actions::edit()` |
| `Tables\Actions\DeleteAction::make()` | `Actions::delete()` |
| `Tables\Actions\Action::make('publish')` | `RowAction::make('publish')` |
| `->requiresConfirmation()` | `->requiresConfirmation()` |
| `->form([...])` | `->form([...])` |
| `Tables\Actions\BulkActionGroup` | (lista plana — Arqel não tem grouping ainda) |

### Layout

| Filament | Arqel |
|---|---|
| `Forms\Components\Section::make('Title')` | `Section::make('Title')` |
| `->columns(2)` | `->columns(2)` |
| `->collapsible()` | `->collapsible()` |
| `Forms\Components\Tabs::make('Tabs')` | `Tabs::make()` |
| `Forms\Components\Grid::make(['md' => 2, 'lg' => 3])` | `Grid::make()->columns(['md' => 2, 'lg' => 3])` |

### Authorization

| Filament | Arqel |
|---|---|
| Spatie Laravel-Permission integration popular | Laravel Policy/Gate-only ([ADR-017](https://github.com/arqel/arqel/blob/main/PLANNING/03-adrs.md)) |
| `->visible(fn () => auth()->user()->can('publish'))` | `->visible(fn ($record) => auth()->user()->can('publish', $record))` |
| `->disabled(fn () => true)` | `->disabled(true)` ou `->disabled(fn ($record) => $record?->locked)` |

## O que **não** migra

- ❌ **Livewire components custom** — viram React components em `resources/js/Arqel/Fields/` ou `resources/js/Pages/Arqel/`
- ❌ **Filament Plugins paid** (Schedule, Logger, etc.) — não existem no ecosystem Arqel ainda. Workarounds: integrar libs Laravel-native (Schedule via `spatie/laravel-schedule-monitor`, etc.)
- ❌ **`->reactive()`** — em Arqel use `->live()` + `->afterStateUpdated()` (mesma idea, sintaxe levemente diferente)
- ❌ **Filament Notifications** — em Arqel use `successNotification()`/`failureNotification()` em Action, ou `arqel/flash` shared prop

## Estratégia de migração

### Opção A: rewrite incremental

1. Adicione Arqel ao mesmo projeto (`composer require arqel/core`)
2. Configure panel em `/admin-v2` (paralelo ao `/admin` Filament)
3. Migre Resources um a um, comparando lado a lado
4. Quando tudo migrado, swap path e remove Filament

### Opção B: rewrite completo

Para apps com muitas customizações Livewire, rewrite limpo é mais rápido que tentar migrar custom components peça a peça.

## Scripts de conversão

(planejado — sem implementação)

A community pode contribuir com `arqel:from-filament` que faz conversão automática de Resources simples (Field types 1:1, Filters 1:1). PRs welcome em [github.com/arqel/arqel](https://github.com/arqel/arqel).

## Próximos passos

- [Vindo de Nova](/guide/migration/from-nova)
- [Vindo de react-admin](/guide/migration/from-react-admin)
- [Tutorial: primeiro CRUD](/guide/tutorial-first-crud)
