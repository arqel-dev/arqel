# Design — Fresh Laravel project end-to-end validation of Arqel framework

**Data:** 2026-05-06
**Estado:** Aprovado pelo utilizador (aguarda review do spec escrito antes da implementação)
**Autor:** Claude Opus 4.7 (1M context) com review de Diogo C. Coutinho

---

## Contexto

Após a migração shadcn (commit `cc34a3c`) e o sweep de docs i18n (commits `27b3c5e`, `5a069e4`, `ee24cab`), precisamos validar que o framework Arqel funciona end-to-end num projeto Laravel real, antes de avançar para novas fases de melhorias.

A validação serve dois propósitos:

1. **Defensivo:** garantir que `arqel:install` corre sem erros num skeleton Laravel limpo, que todos os 7 packages PHP + 6 JS integram, e que features-chave da Fase 1 MVP funcionam (Resource CRUD, Form, Table, Actions, Auth, Theme, Command Palette).
2. **Ofensivo:** smoke test da experiência **real** do utilizador final via `composer require arqel-dev/framework` directamente do Packagist, exactamente como aparece nos READMEs.

## Descobertas do brainstorming

Antes de planear, foram descobertos dois problemas que mudam o escopo:

### Drift de nomenclatura `arqel-dev/arqel` vs `arqel-dev/framework`

Modelo correcto:

- **`arqel-dev/arqel`** — repositório GitHub do **monorepo** (URLs, clone, issues, security advisories)
- **`arqel-dev/framework`** — **meta-package** publicado no Packagist (o que utilizadores fazem `composer require`)
- O directório `packages/arqel/` no monorepo é a **fonte** do meta-package — o seu `composer.json` deve ter `name: "arqel-dev/framework"`

Estado actual: 130+ ficheiros misturam os dois nomes em contextos errados. O `packages/arqel/composer.json` declara `name: "arqel-dev/arqel"` (errado); o Packagist tem `arqel-dev/framework` com versões `v0.8.0` e `v0.8.1`. Há evidência (em `.claude/settings.local.json`) de que um sed massivo já foi tentado mas ficou inacabado.

**Implicação:** o teste end-to-end exige primeiro completar este rename, senão `composer require arqel-dev/framework` no projeto teste vai puxar v0.8.1 do Packagist mas o `apps/demo/composer.json` no monorepo continua a referir `arqel-dev/arqel` via path repo, criando dois flows divergentes.

### Estado do Packagist

- `arqel-dev/framework`: `dev-main`, `v0.8.0`, `v0.8.1` ✅
- `arqel-dev/{core,auth,fields,form,actions,nav,table}`: apenas `dev-main` ⚠️

Sub-packages no Packagist têm apenas `dev-main`, não tags. Isto pode quebrar `composer require arqel-dev/framework:^0.8` porque o meta-package faz `self.version` aos sub-packages e o Composer pode não conseguir resolver `^0.8` se cada sub-pacote só tem `dev-main`. Será um dos pontos a verificar na Fase 3.

## Arquitectura — três fases sequenciais

```
Fase 1 — Saneamento naming (no monorepo)
  └─→ Fase 2 — Recriar apps/demo/ (path repos, dentro do monorepo)
       └─→ Fase 3 — Projeto externo via Packagist (~/PhpstormProjects/arqel-test/)
            └─→ [opcional] Tag v0.8.2 + republish se Packagist desactualizado
```

Cada fase termina com critérios verificáveis. Falhas em Fase 2 retornam ao código em `packages/`. Falhas em Fase 3 retornam à decisão de novo release tag.

---

## Fase 1 — Saneamento de naming

### Objectivo

Acabar o rename `arqel-dev/arqel` → `arqel-dev/framework` em todos os contextos onde é referência ao meta-package, mantendo o token `arqel-dev/arqel` apenas em URLs do GitHub.

### Regras de discriminação

Para cada ocorrência de `arqel-dev/arqel`:

| Contexto imediato | Acção |
|---|---|
| `composer require arqel-dev/arqel` | → `arqel-dev/framework` |
| `"arqel-dev/arqel": "..."` em campo `require` de composer.json | → `arqel-dev/framework` |
| `name` field de `packages/arqel/composer.json` | → `arqel-dev/framework` |
| Texto solto em docs/READMEs/SKILL.md descrevendo o meta-package | → `arqel-dev/framework` |
| Variable name / comment em código PHP referindo o meta-package | → `arqel-dev/framework` |
| `github.com/arqel-dev/arqel` (URLs HTTPS, SSH, clone, compare, issues) | **manter** |
| `"url": ".../arqel-dev/arqel.git"` em package.json `repository` | **manter** |
| `arqel-dev/arqel/discussions`, `/security`, `/actions/runs` | **manter** |

### Estratégia de execução

Para minimizar risco de editar URLs por engano, usar **três passes**:

**Pass 1 — Ficheiros estruturais críticos** (manual com Read+Edit):

- `packages/arqel/composer.json` (`name`)
- `apps/demo/composer.json` (`require`)
- Todos os `composer.json` em `packages/*/` que possam ter referência cruzada
- Todos os `package.json` em `packages-js/*/` que possam ter referência ao meta-package
- `.github/workflows/release.yml`, `.github/workflows/docs-deploy.yml`
- `package.json` (root)

**Pass 2 — Code/scripts** (manual com Read+Edit):

- `packages/cli/src/Generators/SetupScriptGenerator.php`
- `packages/cli/src/Commands/NewCommand.php`
- `packages/cli/tests/**`
- `packages/core/src/Console/DoctorCommand.php` (se referencia)

**Pass 3 — Docs/markdown em massa** (sed com regex defensivo):

```bash
find . \( -name "*.md" -o -name "*.yml" -o -name "*.yaml" \) \
  -not -path "*/node_modules/*" -not -path "*/vendor/*" \
  -exec sed -i \
    -e 's|composer require arqel-dev/arqel\b|composer require arqel-dev/framework|g' \
    -e 's|composer -d \([^ ][^ ]*\) require arqel-dev/arqel\b|composer -d \1 require arqel-dev/framework|g' \
    -e "s|'arqel-dev/arqel:|'arqel-dev/framework:|g" \
    -e 's|"arqel-dev/arqel":|"arqel-dev/framework":|g' \
    -e 's|`arqel-dev/arqel` é o \*\*meta-package\*\*|`arqel-dev/framework` é o **meta-package**|g' \
    -e 's|`arqel-dev/arqel` is the \*\*meta-package\*\*|`arqel-dev/framework` is the **meta-package**|g' \
    -e 's|`arqel-dev/arqel` meta-package|`arqel-dev/framework` meta-package|g' \
    {} +
```

Os patterns deste sed **não** afectam URLs `github.com/arqel-dev/arqel` (porque o `\b` e os contextos imediatos `composer`, `require`, `meta-package` excluem-no).

### Validação Fase 1

Após os 3 passes, executar:

```bash
grep -rn "arqel-dev/arqel" --include="*.php" --include="*.json" --include="*.md" --include="*.ts" --include="*.tsx" --include="*.yml" --include="*.yaml" \
  | grep -v node_modules | grep -v vendor \
  | grep -vE 'github\.com/arqel-dev/arqel|arqel-dev/arqel\.git|arqel-dev/arqel/discussions|arqel-dev/arqel/security|arqel-dev/arqel/issues|arqel-dev/arqel/actions|arqel-dev/arqel/compare|"url".*arqel-dev/arqel'
```

**Critério de aceitação:** o output desse grep é vazio.

### Commit Fase 1

Um único commit:

```
chore(naming): finalize meta-package rename arqel-dev/arqel → arqel-dev/framework

Completes the partial rename started earlier. arqel-dev/arqel is now reserved
exclusively for GitHub repository URLs (clones, issues, security advisories).
arqel-dev/framework is the canonical Composer package name for the meta-package
that downstream apps install.

Affects: ~140 files across composer.json, package.json, docs (EN/PT-BR/ES),
SKILL.md, CI workflows, and CLI generator code.

Refs: PLANNING/04-repo-structure.md §11 (meta-package name)

Signed-off-by: ...
Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
```

---

## Fase 2 — Recriar `apps/demo/` do zero

### Objectivo

Validar `arqel:install` num skeleton Laravel 12 intocado, exercitando todos os 7 packages PHP + 6 JS via path repos. Detectar bugs do instalador, da auto-discovery, do scaffolding antes de chegar ao Packagist.

### Procedimento

1. **Backup:** `mv apps/demo apps/demo-old` (não apagar; manter para diff posterior).
2. **Skeleton novo:** `cd apps && laravel new demo --no-interaction --git=false` (Laravel 12.x, sem auto-init de git porque já estamos num repo).
3. **Path repos + require do meta-package:**
   Editar `apps/demo/composer.json`:
   ```json
   {
     "require": {
       "php": "^8.3",
       "laravel/framework": "^12.0",
       "laravel/tinker": "^2.10.1",
       "arqel-dev/framework": "@dev"
     },
     "repositories": [
       { "type": "path", "url": "../../packages/arqel",   "options": { "symlink": true } },
       { "type": "path", "url": "../../packages/core",    "options": { "symlink": true } },
       { "type": "path", "url": "../../packages/auth",    "options": { "symlink": true } },
       { "type": "path", "url": "../../packages/fields",  "options": { "symlink": true } },
       { "type": "path", "url": "../../packages/form",    "options": { "symlink": true } },
       { "type": "path", "url": "../../packages/actions", "options": { "symlink": true } },
       { "type": "path", "url": "../../packages/nav",     "options": { "symlink": true } },
       { "type": "path", "url": "../../packages/table",   "options": { "symlink": true } }
     ]
   }
   ```
4. **Workspace npm:** adicionar `apps/demo` ao `pnpm-workspace.yaml` (se ainda não estiver) e ao `package.json` root.
5. **Install PHP:** `composer -d apps/demo install`. Esperado: todos os 8 packages resolvem via path repo, `inertiajs/inertia-laravel` vem como peer transitivo.
6. **Run installer:** `php artisan -V && php -d memory_limit=-1 apps/demo/artisan arqel:install`. Esperado: scaffolding completo sem `--force`.
7. **Migrate:** `php apps/demo/artisan migrate`.
8. **Create admin:** `php apps/demo/artisan arqel:make-user --name=Admin --email=admin@arqel.test --password=password`.
9. **Doctor:** `php apps/demo/artisan arqel:doctor` — esperado 0 fails.
10. **Smoke test boot:** `composer -d apps/demo dev` (script que corre `serve + queue + pail + vite`). Esperado: tudo verde.

### Adicionar `PostResource` para exercitar a stack

Após o smoke test do skeleton, adicionar **um** Resource real para exercitar o pipeline completo:

**Migration** (`database/migrations/...create_posts_table.php`):

```php
Schema::create('posts', function (Blueprint $t) {
    $t->id();
    $t->string('title');
    $t->string('slug')->unique();
    $t->text('body')->nullable();
    $t->string('status')->default('draft');  // draft|published|archived
    $t->boolean('featured')->default(false);
    $t->timestamp('published_at')->nullable();
    $t->foreignId('user_id')->constrained();
    $t->timestamps();
});
```

**Model** (`app/Models/Post.php`) com `belongsTo(User::class)` e factory.

**Resource** (`app/Arqel/Resources/PostResource.php`):

```php
final class PostResource extends Resource
{
    public static string $model = Post::class;
    public static ?string $navigationIcon = 'file-text';
    public static ?string $navigationGroup = 'Content';
    public static ?int $navigationSort = 10;

    public function form(): Form
    {
        return Form::make()
            ->columns(2)
            ->schema([
                Section::make('Content')->schema([
                    TextField::make('title')->required(),
                    TextField::make('slug')->required(),
                    TextareaField::make('body')->rows(8),
                ]),
                Section::make('Meta')->schema([
                    SelectField::make('status')->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
                    BooleanField::make('featured'),
                    DateTimeField::make('published_at'),
                ]),
            ]);
    }

    public function table(): Table
    {
        return Table::make()
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                BadgeColumn::make('status')->colors([
                    'success' => 'published',
                    'warning' => 'draft',
                    'destructive' => 'archived',
                ]),
                BooleanColumn::make('featured'),
                DateColumn::make('published_at'),
            ])
            ->filters([
                SelectFilter::make('status')->options([...]),
                TernaryFilter::make('featured'),
            ])
            ->actions([
                Actions::edit(),
                Actions::delete(),
            ])
            ->bulkActions([
                Actions::deleteBulk(),
            ])
            ->defaultSort('published_at', 'desc');
    }
}
```

**Seeder:** factory cria 25 posts com mix de status, feature flag aleatório, datas spread.

### Critérios de aceitação Fase 2

| # | Camada | Verificação |
|---|---|---|
| 1 | Boot | `serve` + `vite` sem erros, sem warnings PHPStan |
| 2 | Login | `/admin/login` renderiza com layout `login-04`; autentica `admin@arqel.test/password`; redirect para `/admin` |
| 3 | Sidebar | Menu mostra "System > Users" + "Content > Posts" auto-registado |
| 4 | Index | `/admin/posts` mostra DataTable com 25 posts, sort por `title` ascendente, sort por `published_at` descendente; search "draft" filtra |
| 5 | Filter | `SelectFilter` "status=published" filtra correctamente; `TernaryFilter` "featured=true" |
| 6 | Pagination | Default 25 per page; clicar "next" carrega via Inertia partial reload sem reload completo |
| 7 | Create | `/admin/posts/create` renderiza Form com 2 columns, 2 sections; submit com `title` vazio mostra erro `:attribute is required` |
| 8 | Edit | `/admin/posts/{id}/edit` pre-popula campos; submit persiste |
| 9 | Delete | RowAction "Delete" mostra modal `Confirmable`; confirma → record removido |
| 10 | Bulk | Seleccionar 3 rows, "Delete selected", confirma → 3 records removidos via chunking |
| 11 | Theme | Toggle light/dark via Command Palette; persiste em `localStorage` |
| 12 | Cmd+K | Abre `CommandPaletteController` em `GET /admin/commands?q=`; navega `nav:posts` para `/admin/posts` |
| 13 | Doctor | `arqel:doctor` retorna `0 fails` |
| 14 | Auth gate | Logout redirect para `/admin/login`; user não-admin (Gate::denies('viewAdminPanel')) recebe 403 |
| 15 | Tests | `composer -d apps/demo test` (Pest) verde — pelo menos os 2 tests gerados pelo skeleton + 1 PostResource feature test |

### Iteração: bugs encontrados → fix em packages/

Quando um critério falha, debug → fix em `packages/<pkg>/src/...` → `composer -d apps/demo dump-autoload` (path repo symlink propaga automaticamente) → re-run. Cada bug-fix recebe commit próprio com Conventional + DCO + ref ao critério `(2/15 ❌ → ✅)`.

### Cleanup pós-validação

Quando os 15 critérios estão verdes:

1. Diff `apps/demo` vs `apps/demo-old`: portar para `apps/demo` qualquer customização útil que existia no antigo (e.g., seeders extra, README local).
2. `rm -rf apps/demo-old`.
3. Commit final `chore(demo): recreate from fresh Laravel 12 + full PostResource showcase`.

---

## Fase 3 — Smoke test externo via Packagist

### Objectivo

Validar a experiência exacta do utilizador final: `composer require arqel-dev/framework` directo do Packagist, sem path repos, num projeto fora do monorepo.

### Procedimento

1. **Skeleton externo:**
   ```bash
   cd ~/PhpstormProjects
   laravel new arqel-test
   cd arqel-test
   git init  # repo próprio para histórico do teste
   git add -A && git commit -m "chore: initial Laravel 12 skeleton" --signoff
   ```

2. **Install Arqel via Packagist:**
   ```bash
   composer require "arqel-dev/framework:^0.8"
   ```
   Esperado: resolve via Packagist público, puxa transitively core/auth/fields/form/actions/nav/table + inertiajs/inertia-laravel.
   **Risco conhecido:** se `^0.8` falhar a resolver porque sub-packages só têm `dev-main`, fallback para `composer require arqel-dev/framework:dev-main` e abrir issue de release pipeline (Fase 3 falha → ver remediation abaixo).

3. **Run installer:**
   ```bash
   php artisan arqel:install
   php artisan migrate
   php artisan arqel:make-user --name=Test --email=test@arqel.test --password=password
   ```

4. **Boot + smoke test:**
   ```bash
   composer dev   # ou php artisan serve + npm run dev
   ```

5. **Manual checks browser:**
   - `/admin/login` carrega CSS/JS sem 404 em assets
   - Login com `test@arqel.test/password` autentica
   - `/admin/users` mostra 1 user (o criado pelo `make-user`)
   - Theme toggle funciona
   - Cmd+K abre command palette

### Critérios de aceitação Fase 3

| # | Verificação |
|---|---|
| 1 | `composer require arqel-dev/framework:^0.8` resolve sem erros (ou fallback documentado para `dev-main`) |
| 2 | `arqel:install` corre sem editar manualmente nada no skeleton |
| 3 | Login flow funciona end-to-end no browser (Inertia + Vite + Tailwind v4 + shadcn) |
| 4 | UserResource (scaffolded pelo install) renderiza index + edit |
| 5 | Theme toggle persiste em refresh |

### Remediation se Fase 3 falhar

**Caso A — `composer require arqel-dev/framework:^0.8` não resolve:**

Sub-packages no Packagist só têm `dev-main`. Solução: tagguear v0.8.2 no monorepo, propagar via splitsh/lite, esperar Packagist hook actualizar. Procedimento:

```bash
# No monorepo (~/PhpstormProjects/arqel)
cd /home/diogo/PhpstormProjects/arqel
# Garantir que main está limpo e Fase 1+2 estão merged
git tag -a v0.8.2 -m "release: v0.8.2 — naming saneamento + demo refresh"
git push origin v0.8.2
# splitsh/lite (CI workflow .github/workflows/release.yml) propaga aos sub-repos
# Aguardar ~2-5 min para Packagist refresh
# Re-tentar Fase 3 do início
```

User deu autorização explícita ("3. taguear automaticamente") para este passo durante o brainstorming.

**Caso B — `arqel:install` falha em projeto externo mas funcionou em apps/demo:**

Diferenças possíveis:
- Path repo vs Packagist: package install vs symlink (alguns assets podem não chegar)
- `provider.stub` referencia algo em `packages/arqel/` que não existe via Packagist
- `composer.json` extra do meta-package está incompleto

Debug: comparar `vendor/arqel-dev/framework/composer.json` no projeto externo com `packages/arqel/composer.json` no monorepo. Fix → tag patch → re-test.

**Caso C — Assets 404 (Inertia/Vite):**

Provavelmente `vite.config.ts.stub` não publica algo. Comparar com `apps/demo/vite.config.ts` da Fase 2.

### Cleanup Fase 3

`~/PhpstormProjects/arqel-test/` fica como git repo próprio para histórico de futuras iterações de smoke test. Não é apagado.

---

## Componentes que serão criados / modificados

### Novos ficheiros

- `~/PhpstormProjects/arqel-test/` (projeto externo, git repo próprio)
- `apps/demo/app/Models/Post.php`
- `apps/demo/app/Arqel/Resources/PostResource.php`
- `apps/demo/database/migrations/...create_posts_table.php`
- `apps/demo/database/factories/PostFactory.php`
- `apps/demo/database/seeders/PostSeeder.php`
- `apps/demo/tests/Feature/PostResourceTest.php`

### Ficheiros modificados (Fase 1)

~140 ficheiros — `composer.json`, `package.json`, docs `*.md`, SKILL.md, CI YAML, CLI generators PHP. Detalhe completo no commit Fase 1.

### Ficheiros modificados (Fase 2)

- `apps/demo/composer.json` (path repos)
- `pnpm-workspace.yaml` (se necessário)
- Possíveis fixes em `packages/<pkg>/src/...` conforme bugs aparecem

### Ficheiros temporários

- `apps/demo-old/` — backup, removido após Fase 2

---

## Sequência de execução e checkpoints

1. **Fase 1** (≈30 min): rename mecânico + 1 commit
2. **Checkpoint 1:** review do commit Fase 1; user aprova ou pede adjustes
3. **Fase 2** (≈3-4h): recriar demo + PostResource + 15 critérios
4. **Checkpoint 2:** review da árvore de commits Fase 2; user smoke-testa demo no browser
5. **Fase 3** (≈30 min se v0.8.1 do Packagist serve, ≈2h se precisa de tag v0.8.2)
6. **Checkpoint 3:** user confirma que ~/PhpstormProjects/arqel-test/ funciona; spec considerada cumprida

Cada checkpoint é uma **paragem natural**. Falhas em qualquer fase fazem rollback do trabalho da fase (git reset) e re-planeamento, não cascade para a fase seguinte.

---

## Riscos e mitigações

| Risco | Probabilidade | Mitigação |
|---|---|---|
| Sed da Fase 1 edita URL `github.com/arqel-dev/arqel` por engano | Baixa | Pass 3 do sed usa patterns que requerem `composer`, `require`, `meta-package`, ou `:` à volta — não aciona em URLs. Validação final via grep com exclusão explícita de URLs |
| `composer require arqel-dev/framework` no Packagist não resolve `^0.8` | Média | Plano de remediation Caso A (tag v0.8.2 manualmente, user autorizou) |
| `apps/demo` recriado perde customização única do antigo | Baixa | `mv` em vez de `rm`, diff manual antes de remover `apps/demo-old/` |
| Bug em `arqel:install` impede a Fase 2 inteira | Baixa | Cada passo é idempotente; flag `--force` re-roda. Se persistente, fix em `packages/core/src/Console/InstallCommand.php` é **trabalho dentro do escopo** (regra de autonomous mode: refactors necessários para ticket → fazer sem perguntar) |
| Tempo total > 1 dia | Média | Os 3 checkpoints permitem parar entre fases. Não há ticket de PLANNING a depender disto cronologicamente |
| Stack JS quebra em Vite 7 + React 19.2 + Tailwind v4 + shadcn no Fase 3 | Média | Já validado em apps/demo (Fase 2); Fase 3 só falha se Packagist v0.8.1 estiver desactualizado vs monorepo (→ Caso A remediation) |

---

## O que está fora do escopo

- **Não vamos fazer** `CategoryResource`, `RoleResource`, ou qualquer Resource adicional além do `PostResource`. Stretch goal para sprint seguinte.
- **Não vamos exercitar** `arqel-dev/widgets`, `arqel-dev/marketplace`, `arqel-dev/audit`, `arqel-dev/ai`, `arqel-dev/realtime`, ou outros packages fora do core 8. Estes são Fase 2/3/4 do roadmap, não Fase 1 MVP.
- **Não vamos** publicar uma nova release no Packagist a menos que Fase 3 Caso A o exija.
- **Não vamos** fazer breaking changes em APIs. Se descobrirmos um bug que requer breaking change, paramos e perguntamos (autonomous mode rule §2).
- **Não vamos** mexer em `apps/docs/`. A documentação foi acabada de actualizar; este é um exercício de validação de runtime.

---

## Anti-patterns a evitar

- ❌ Apagar `apps/demo` antes de validar a recreate
- ❌ Push direto sem run de `pnpm test` + `vendor/bin/pest` por commit
- ❌ Usar `--no-verify` para skipar Husky pre-commit hooks
- ❌ Commitar `apps/demo-old/` ao git
- ❌ Editar URLs `github.com/arqel-dev/arqel` durante o saneamento
- ❌ Tagguear `v0.8.2` antes de Fase 1 + Fase 2 estarem mergeadas em main

---

## Referências

- `CLAUDE.md` — modo autonomous, convenções
- `PLANNING/04-repo-structure.md` §11 — meta-package + estrutura de pacotes
- `PLANNING/08-fase-1-mvp.md` — tickets MVP (referência cruzada)
- `PLANNING/12-processos-qa.md` — coverage targets, release pipeline
- `packages/core/SKILL.md` — `arqel:install` pipeline 11 passos
- `packages/arqel/SKILL.md` — meta-package contract
- Commit `cc34a3c` — shadcn migration (referência do estado pós-migração)
- Commits `27b3c5e`, `5a069e4`, `ee24cab` — i18n docs sweep recente
