# External validation project (`arqel-test`) + MCP integration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Levar os 10 fixes da branch `feat/e2e-validation` para a `main`, publicar v0.9.1 em Packagist + NPM, e validar end-to-end num projeto Laravel limpo (`~/PhpstormProjects/arqel-test/`) que consome `arqel-dev/framework` via Packagist e `@arqel-dev/mcp-server` via NPM.

**Architecture:** Cinco fases sequenciais — Release pipeline → Bootstrap externo → Cobertura de recursos → Integração MCP → Validação e relatório → Cleanup. Cada fase tem critério de saída verificável; falhas em qualquer ponto retornam ao monorepo para hotfix → bump → repetir.

**Tech Stack:** Laravel 13, PHP 8.3+, Inertia 3, React 19.2, Vite 7, Tailwind v4, Pest 3, Composer 2, pnpm 9+, NPM, MCP Protocol (JSON-RPC), Packagist.

**Spec:** [`docs/superpowers/specs/2026-05-06-external-validation-mcp-integration-design.md`](../specs/2026-05-06-external-validation-mcp-integration-design.md)

---

## File Structure

### Monorepo (`/home/diogo/PhpstormProjects/arqel/`)

- Modify (durante rebase): `apps/demo/composer.lock`, `package.json` versions de cada pacote (resolução de conflitos)
- Modify (Fase 0 bump): todos os `packages/*/composer.json`, `packages-js/*/package.json` (0.9.0 → 0.9.1)
- Create: `docs/superpowers/reports/2026-05-06-e2e-validation-report.md` (Fase 4)
- Delete: `apps/demo-old/` (Fase 5)

### External project (`/home/diogo/PhpstormProjects/arqel-test/`)

- Create (Fase 1): repositório Laravel 13 limpo via `laravel new` + `composer require arqel-dev/framework`
- Create (Fase 2): `app/Models/Post.php`, `app/Models/Category.php`
- Create (Fase 2): migrations `xxxx_create_posts_table.php`, `xxxx_create_categories_table.php`, `xxxx_add_category_id_to_posts.php`
- Create (Fase 2): `database/factories/PostFactory.php`, `database/factories/CategoryFactory.php`
- Create (Fase 2): `database/seeders/PostSeeder.php`, `database/seeders/CategorySeeder.php`
- Create (Fase 2): `app/Arqel/Resources/PostResource.php`, `app/Arqel/Resources/CategoryResource.php`
- Create (Fase 2): `app/Arqel/Widgets/TotalPostsWidget.php`
- Create (Fase 2): `tests/Feature/PostResourceTest.php`, `tests/Feature/CategoryResourceTest.php`
- Create (Fase 3): `.mcp.json` com config do `@arqel-dev/mcp-server`

---

## Convenções partilhadas por todas as tasks

- **Commits**: Conventional Commits + DCO sign-off (`git commit --signoff -m "..."`)
- **Allowed scopes** (commitlint): `core, panel, fields, table, form, actions, auth, cli, nav, tenant, ai, widgets, fields-advanced, audit, export, marketplace, versioning, workflow, realtime, mcp, testing, react, ui, hooks, types, devtools, infra, lcloud, gov, docs, demo, qa, tickets, sprint, deps, release`
- **Never use `git add -A`/`git add .`** — sempre paths específicos para evitar arrastar untracked
- **Never `--no-verify`** — se hook falhar, fix root cause
- **PT-BR em docs**, inglês em código

---

# Fase 0 — Release pipeline

## Task 0.1: Pre-flight checks na branch `feat/e2e-validation`

**Files:**
- Read: `git status`, `git log`, `composer.lock` em `apps/demo/`

- [ ] **Step 1: Verificar branch e working tree**

```bash
cd /home/diogo/PhpstormProjects/arqel
git rev-parse --abbrev-ref HEAD
git status --short
```

Expected: branch `feat/e2e-validation`. Working tree pode ter `apps/demo/composer.lock`, `scripts/remove-packagist-webhooks.sh`, `.claude/scheduled_tasks.lock`, `packages-js/mcp-server/` untracked.

- [ ] **Step 2: Verificar HEAD e divergência da main**

```bash
git fetch origin
git log --oneline feat/e2e-validation ^origin/main | head -20
git rev-parse HEAD
git rev-parse origin/main
```

Expected: 10 commits ahead, HEAD `1f20453` (commit do spec) ou superior se houve commits novos. `origin/main` em `9177f34` ou superior.

- [ ] **Step 3: Decidir sobre untracked**

```bash
git status --short | grep '^??'
```

Se `packages-js/mcp-server/` aparecer untracked: investigar — esses ficheiros JÁ existem em `origin/main` (pasta tracked). Provavelmente é diferença local. NÃO commitar à branch — deixar `git stash --include-untracked` antes do rebase para rebase ficar limpo.

- [ ] **Step 4: Stash dos untracked + working tree changes**

```bash
git stash push --include-untracked -m "pre-rebase-stash"
git status --short
```

Expected: working tree limpo. Stash criado.

- [ ] **Step 5: Commit de checkpoint do estado atual da branch**

Não há mudanças não-commitadas neste ponto (foram stashed). Skip.

## Task 0.2: Rebase `feat/e2e-validation` sobre `origin/main`

**Files:**
- Modify: durante resolução de conflitos — `apps/demo/composer.lock`, `package.json` de pacotes, possivelmente `apps/demo/config/*`

- [ ] **Step 1: Iniciar rebase**

```bash
git rebase origin/main
```

Expected: rebase corre. Provavelmente conflitos em `composer.lock` e/ou `package.json`.

- [ ] **Step 2: Para cada conflito, inspeccionar e resolver**

Estratégia geral:
- **`composer.lock`**: aceitar versão da `main` (`git checkout --theirs <file>`) e regenerar com `composer update --lock` no final, OU resolver manualmente preservando entries dos packages locais. Se conflito grande, regenerar via:
  ```bash
  cd apps/demo
  rm composer.lock
  composer update --lock --no-scripts
  cd ../..
  git add apps/demo/composer.lock
  ```
- **`package.json` versions**: main avançou para 0.9.0; manter 0.9.0 (será bumpado para 0.9.1 na Task 0.4):
  ```bash
  git checkout --theirs packages/*/composer.json packages-js/*/package.json 2>/dev/null
  git add packages/*/composer.json packages-js/*/package.json 2>/dev/null
  ```
- **Outros ficheiros**: avaliar caso a caso. Se `apps/demo/config/*` mudou em main e na branch, normalmente as mudanças da branch (fixes do walkthrough) prevalecem.

Expected: cada `git status` mostra os ficheiros como `UU`. Após resolver, `git add <file>` e `git rebase --continue`.

- [ ] **Step 3: Continuar rebase até ao fim**

```bash
git rebase --continue
```

Repetir Step 2 + Step 3 até rebase completar. Se em algum ponto o estado for irrecuperável: `git rebase --abort` e parar — reportar ao user antes de qualquer destructive action.

Expected: `Successfully rebased and updated refs/heads/feat/e2e-validation.`

- [ ] **Step 4: Recuperar untracked do stash**

```bash
git stash pop
git status --short
```

Expected: untracked voltam (não devem entrar em commits subsequentes).

- [ ] **Step 5: Verificar build sanity local**

```bash
pnpm install
pnpm typecheck
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress 2>&1 | tail -20
```

Expected: typecheck/lint/phpstan limpos. Se algum falhar, é porque rebase introduziu conflito semântico — debugar.

- [ ] **Step 6: Rodar testes**

```bash
vendor/bin/pest --parallel 2>&1 | tail -30
pnpm test 2>&1 | tail -30
```

Expected: tudo passa. Se algo falhar, NÃO push — fix primeiro.

- [ ] **Step 7: Não commitar — rebase já produziu o estado correcto**

Skip commit.

## Task 0.3: Fast-forward `main`

**Files:** N/A (apenas operações git)

- [ ] **Step 1: Confirmar com user antes de push**

A política do monorepo permite push directo a `main` (não há PR review obrigatório). MAS antes de push, **PARAR** e mostrar ao user:

```bash
git log --oneline origin/main..feat/e2e-validation | head -15
```

Pedir confirmação: "Vou fast-forward `main` com estes 10 commits. Confirma?"

Expected: user confirma.

- [ ] **Step 2: Checkout main e fast-forward**

```bash
git checkout main
git pull --ff-only origin main
git merge --ff-only feat/e2e-validation
```

Expected: `Fast-forward.` sem merge commit.

- [ ] **Step 3: Push main**

```bash
git push origin main
```

Expected: push aceite. Se for rejeitado por hook server-side, parar e reportar.

## Task 0.4: Bump versão 0.9.0 → 0.9.1

**Files:**
- Modify: todos os `packages/*/composer.json` e `packages-js/*/package.json`

- [ ] **Step 1: Localizar script de bump existente**

```bash
ls scripts/ 2>/dev/null | grep -i -E 'bump|release|version'
cat package.json | grep -A 5 '"scripts"'
```

Expected: descobrir como o bump 0.8.1 → 0.9.0 anterior foi feito. Provavelmente via script custom ou edit manual + commit `chore(release): bump`.

- [ ] **Step 2: Aplicar bump**

Se houver script (e.g., `scripts/bump-version.sh 0.9.1`), usar. Caso contrário, edit manual:

```bash
# Listar todos os ficheiros relevantes
find packages packages-js -maxdepth 2 -name 'composer.json' -o -name 'package.json' | grep -v node_modules
```

Para cada ficheiro, atualizar `"version": "0.9.0"` → `"version": "0.9.1"`. Em `packages-js/*/package.json` também actualizar dependências internas que referenciem `"@arqel-dev/<pkg>": "0.9.0"` → `"0.9.1"`. Em `packages/arqel/composer.json` (meta-package) actualizar `require` se usar version pinning.

- [ ] **Step 3: Verificar mudanças**

```bash
git diff --stat
```

Expected: ~15-20 ficheiros tocados (todos com bump consistente).

- [ ] **Step 4: Commit do bump**

```bash
git add packages/*/composer.json packages-js/*/package.json
git commit --signoff -m "$(cat <<'EOF'
chore(release): bump 0.9.0 -> 0.9.1

Bumps all PHP packages and JS workspaces from 0.9.0 to 0.9.1
in preparation for tag v0.9.1 publishing the e2e-validation
fixes (filter wiring, ColumnBase shape, auth Inertia middleware,
SelectFilter options normalization).
EOF
)"
```

- [ ] **Step 5: Push do bump**

```bash
git push origin main
```

## Task 0.5: Tag e publicação v0.9.1

**Files:** N/A (apenas operações git)

- [ ] **Step 1: Criar tag**

```bash
git tag -a v0.9.1 -m "v0.9.1 - e2e-validation walkthrough fixes"
```

- [ ] **Step 2: Push da tag**

```bash
git push origin v0.9.1
```

Expected: CI dispara workflow de release (subtree split + npm publish).

- [ ] **Step 3: Acompanhar CI**

```bash
gh run watch --exit-status
```

Expected: workflow `release.yml` (ou equivalente) corre com sucesso. Se falhar, **PARAR** e reportar ao user — sem v0.9.1 nada avança.

- [ ] **Step 4: Verificar Packagist**

```bash
sleep 30  # Packagist webhook pode levar ~30s
curl -s https://repo.packagist.org/p2/arqel-dev/framework.json | grep -o '"version":"[^"]*"' | head -5
```

Expected: `"version":"0.9.1"` aparece. Se não, verificar webhook (`scripts/remove-packagist-webhooks.sh` é hint — pode ter sido removido propositadamente; nesse caso fazer trigger manual via Packagist UI ou re-criar webhook).

- [ ] **Step 5: Verificar NPM**

```bash
npm view @arqel-dev/mcp-server@0.9.1 version
npm view @arqel-dev/types@0.9.1 version
npm view @arqel-dev/ui@0.9.1 version
```

Expected: cada comando retorna `0.9.1`.

- [ ] **Step 6: Critério de saída — registry resolution test**

Em diretório temp:
```bash
cd /tmp && mkdir resolve-test && cd resolve-test
echo '{"require":{"arqel-dev/framework":"^0.9.1"}}' > composer.json
composer install --dry-run 2>&1 | tail -10
cd .. && rm -rf resolve-test
```

Expected: composer reporta resolução bem-sucedida da árvore. Se falhar (e.g., sub-packages sem tag), **PARAR** e investigar release pipeline.

# Fase 1 — Bootstrap do projeto externo

## Task 1.1: Criar projeto Laravel limpo

**Files:**
- Create: `~/PhpstormProjects/arqel-test/` (Laravel 13 skeleton completo)

- [ ] **Step 1: Verificar pré-requisitos**

```bash
which laravel && laravel --version
which php && php --version
which composer && composer --version
which pnpm && pnpm --version
which npx && npx --version
```

Expected: todos disponíveis. PHP 8.3+, composer 2.x, pnpm 9+, laravel installer presente.

- [ ] **Step 2: Criar projeto**

```bash
cd ~/PhpstormProjects
laravel new arqel-test --no-interaction --git --branch=main 2>&1 | tail -10
```

Expected: projeto criado em `~/PhpstormProjects/arqel-test/`. Já vem com `git init` e commit inicial. Se a flag `--branch` não for suportada, omitir.

- [ ] **Step 3: Verificar baseline**

```bash
cd ~/PhpstormProjects/arqel-test
git log --oneline | head -3
ls
```

Expected: 1 commit inicial; estrutura Laravel padrão (`app/`, `config/`, `database/`, etc.).

## Task 1.2: Instalar `arqel-dev/framework` via Packagist

**Files:**
- Modify: `composer.json` (root do `arqel-test`)
- Create: `composer.lock`, `vendor/`

- [ ] **Step 1: Composer require**

```bash
cd ~/PhpstormProjects/arqel-test
composer require arqel-dev/framework:^0.9.1 2>&1 | tail -20
```

Expected: composer baixa `arqel-dev/framework` + sub-packages (core, auth, fields, form, actions, nav, table) versão 0.9.1. Sem erros de resolução.

- [ ] **Step 2: Verificar instalação**

```bash
composer show arqel-dev/framework | head -10
ls vendor/arqel-dev/
```

Expected: framework + 7 sub-packages presentes em `vendor/arqel-dev/`.

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit --signoff -m "$(cat <<'EOF'
chore: install arqel-dev/framework v0.9.1

Pulls the framework meta-package and sub-packages from Packagist
without path repos, exactly as a downstream user would.
EOF
)"
```

## Task 1.3: Rodar `arqel:install`

**Files:**
- Modify: `bootstrap/app.php`, `routes/web.php`, `app/Providers/AppServiceProvider.php` (qualquer mudança feita pelo install)
- Create: `app/Arqel/Resources/UserResource.php` (vem out-of-box), `resources/js/app.tsx`, configurações shadcn, etc.

- [ ] **Step 1: Inspeccionar comando install**

```bash
cd ~/PhpstormProjects/arqel-test
php artisan list arqel
php artisan arqel:install --help
```

Expected: comando existe; lista flags disponíveis (provavelmente `--force`, talvez `--no-shadcn` ou similar).

- [ ] **Step 2: Executar install**

```bash
php artisan arqel:install 2>&1 | tee /tmp/arqel-install.log
```

Expected: sucesso. Output deve mencionar middleware registrado, panel registrado, stubs copiados, `shadcn init` corrido. Se algum prompt interactivo aparecer (e.g., "package manager?"), responder pnpm.

- [ ] **Step 3: Diagnosticar problemas comuns**

Se erro tipo "shadcn command not found": instalar `pnpm add -D shadcn-cli` ou seguir output do install.
Se erro de "node version": confirmar `node -v` ≥ 20.9.

- [ ] **Step 4: Verificar mudanças no projeto**

```bash
git status --short | head -30
```

Expected: muitos ficheiros novos/modificados (`resources/js/`, `tsconfig.json`, `vite.config.ts`, `package.json`, etc.).

## Task 1.4: Migrate + criar admin user + build

- [ ] **Step 1: Configurar SQLite**

```bash
cd ~/PhpstormProjects/arqel-test
touch database/database.sqlite
# Confirmar .env tem DB_CONNECTION=sqlite
grep DB_CONNECTION .env
```

Expected: `DB_CONNECTION=sqlite`. Se não, editar `.env`.

- [ ] **Step 2: Rodar migrations**

```bash
php artisan migrate --force 2>&1 | tail -10
```

Expected: tabelas criadas (users, posts não — Post ainda não foi adicionado; posts vem na Fase 2).

- [ ] **Step 3: Criar admin user**

```bash
php artisan arqel:make-user --email=admin@arqel.test --password=password --name="Admin" 2>&1 | tail -5
```

Expected: user criado. Se flags forem diferentes, ajustar via `--help`.

- [ ] **Step 4: pnpm install + build**

```bash
pnpm install 2>&1 | tail -10
pnpm run build 2>&1 | tail -10
```

Expected: build gera `public/build/` sem erros.

- [ ] **Step 5: Doctor**

```bash
php artisan arqel:doctor 2>&1 | tail -20
```

Expected: zero warnings. Se aparecer warning, anotar para o relatório da Fase 4.

- [ ] **Step 6: Smoke test browser-style via curl**

Iniciar servidor:
```bash
php artisan serve --port=8765 &
SERVER_PID=$!
sleep 2
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8765/admin/login
kill $SERVER_PID
```

Expected: HTTP 200 na rota de login.

- [ ] **Step 7: Commit**

```bash
git add -- . ':!database/database.sqlite' ':!.env'
git commit --signoff -m "$(cat <<'EOF'
feat: arqel:install bootstrap

Runs arqel:install + migrate + make-user + build. Smoke-tests
the admin login route returns 200 OK. Establishes baseline
before adding Post + Category resources.
EOF
)"
```

# Fase 2 — Cobertura de recursos

## Task 2.1: Adicionar `Post` model + migration + factory + seeder

**Files:**
- Create: `app/Models/Post.php`
- Create: `database/migrations/2026_05_06_000001_create_posts_table.php`
- Create: `database/factories/PostFactory.php`
- Create: `database/seeders/PostSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Criar migration**

```bash
cd ~/PhpstormProjects/arqel-test
php artisan make:migration create_posts_table
```

Editar o ficheiro gerado para conter:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->text('body')->nullable();
            $t->string('status')->default('draft');
            $t->boolean('featured')->default(false);
            $t->timestamp('published_at')->nullable();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

- [ ] **Step 2: Criar Post model**

```bash
# Sobrescrever app/Models/Post.php
```

Conteúdo:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'body', 'status', 'featured', 'published_at', 'user_id',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 3: Criar PostFactory**

`database/factories/PostFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
final class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = fake()->sentence(rand(3, 8));

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(4),
            'body' => fake()->paragraphs(rand(2, 5), true),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'featured' => fake()->boolean(20),
            'published_at' => fake()->optional(0.7)->dateTimeBetween('-1 year'),
            'user_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
        ];
    }
}
```

- [ ] **Step 4: Criar PostSeeder**

`database/seeders/PostSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        Post::factory()->count(25)->create();
    }
}
```

- [ ] **Step 5: Atualizar DatabaseSeeder**

Modificar `database/seeders/DatabaseSeeder.php` para chamar `PostSeeder`:

```php
public function run(): void
{
    $this->call(PostSeeder::class);
}
```

- [ ] **Step 6: Migrate e seed**

```bash
php artisan migrate 2>&1 | tail -5
php artisan db:seed 2>&1 | tail -5
```

Expected: posts table criada, 25 posts inseridos.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Post.php database/migrations/*posts* database/factories/PostFactory.php database/seeders/PostSeeder.php database/seeders/DatabaseSeeder.php
git commit --signoff -m "feat: add Post model, migration, factory, seeder"
```

## Task 2.2: Escrever PostResourceTest (TDD)

**Files:**
- Create: `tests/Feature/PostResourceTest.php`

- [ ] **Step 1: Detectar Pest vs PHPUnit**

```bash
cd ~/PhpstormProjects/arqel-test
ls tests/Pest.php 2>/dev/null && echo "PEST" || echo "PHPUNIT"
```

Se PEST, ajustar test syntax abaixo. Default no plano: PHPUnit (Laravel 13 ainda usa).

- [ ] **Step 2: Criar test**

`tests/Feature/PostResourceTest.php` (PHPUnit syntax):

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin-test@arqel.test',
        ]);
    }

    public function test_lists_posts_on_the_resource_index(): void
    {
        Post::factory()->count(3)->create(['user_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->get('/admin/posts')
            ->assertOk();
    }

    public function test_renders_the_create_form_for_posts(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/posts/create')
            ->assertOk();
    }

    public function test_persists_a_new_post_via_post_admin_posts(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/posts', [
                'title' => 'Test post',
                'slug' => 'test-post',
                'status' => 'draft',
                'featured' => false,
                'user_id' => $this->admin->id,
            ])
            ->assertRedirect();

        $this->assertTrue(Post::where('slug', 'test-post')->exists());
    }

    public function test_rejects_creation_without_required_title(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/posts', ['slug' => 'no-title', 'status' => 'draft'])
            ->assertSessionHasErrors('title');
    }
}
```

- [ ] **Step 3: Rodar test (deve falhar)**

```bash
php artisan test --filter=PostResourceTest 2>&1 | tail -20
```

Expected: tests falham com 404 (rotas `/admin/posts` ainda não existem porque PostResource não foi registrado).

- [ ] **Step 4: NÃO commitar ainda — vamos implementar PostResource na próxima task**

## Task 2.3: Implementar PostResource

**Files:**
- Create: `app/Arqel/Resources/PostResource.php`

- [ ] **Step 1: Criar PostResource**

`app/Arqel/Resources/PostResource.php` — copiar literalmente de `apps/demo/app/Arqel/Resources/PostResource.php` no monorepo (já validado no walkthrough). Usar exactamente este conteúdo:

```php
<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Post;
use Arqel\Actions\Actions;
use Arqel\Core\Resources\Resource;
use Arqel\Fields\FieldFactory as Field;
use Arqel\Fields\Types\BooleanField;
use Arqel\Fields\Types\DateTimeField;
use Arqel\Fields\Types\HiddenField;
use Arqel\Fields\Types\TextareaField;
use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Form\Layout\Section;
use Arqel\Table\Columns\BadgeColumn;
use Arqel\Table\Columns\BooleanColumn;
use Arqel\Table\Columns\DateColumn;
use Arqel\Table\Columns\TextColumn;
use Arqel\Table\Filters\SelectFilter;
use Arqel\Table\Filters\TernaryFilter;
use Arqel\Table\Table;

final class PostResource extends Resource
{
    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    public static string $model = Post::class;

    public static ?string $slug = 'posts';
    public static ?string $label = 'Post';
    public static ?string $pluralLabel = 'Posts';
    public static ?string $navigationIcon = 'file-text';
    public static ?string $navigationGroup = 'Content';
    public static ?int $navigationSort = 10;
    public static ?string $recordTitleAttribute = 'title';

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            (new TextField('title'))->required(),
            Field::slug('slug'),
            new TextareaField('body'),
            Field::select('status')->options([
                'draft' => 'Draft',
                'published' => 'Published',
                'archived' => 'Archived',
            ]),
            new BooleanField('featured'),
            new DateTimeField('published_at'),
            (new HiddenField('user_id'))->default(fn () => auth()->id()),
        ];
    }

    public function form(): Form
    {
        return Form::make()
            ->columns(2)
            ->model(Post::class)
            ->schema([
                Section::make('Content')
                    ->columns(2)
                    ->schema([
                        (new TextField('title'))->required()->columnSpan('full'),
                        Field::slug('slug')->fromField('title')->columnSpan('full'),
                        (new TextareaField('body'))->columnSpan('full'),
                        (new HiddenField('user_id'))->default(fn () => auth()->id()),
                    ]),
                Section::make('Meta')
                    ->columns(2)
                    ->schema([
                        Field::select('status')->options([
                            'draft' => 'Draft',
                            'published' => 'Published',
                            'archived' => 'Archived',
                        ]),
                        (new BooleanField('featured'))->inline(),
                        (new DateTimeField('published_at'))->columnSpan('full'),
                    ]),
            ]);
    }

    public function table(): Table
    {
        return (new Table)
            ->columns([
                TextColumn::make('title')->sortable()->searchable()->limit(80),
                BadgeColumn::make('status')->colors([
                    'draft' => 'gray',
                    'published' => 'green',
                    'archived' => 'yellow',
                ]),
                BooleanColumn::make('featured'),
                DateColumn::make('published_at')->sortable()->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'archived' => 'Archived',
                ]),
                TernaryFilter::make('featured'),
            ])
            ->defaultSort('published_at', 'desc')
            ->searchable()
            ->selectable()
            ->actions([Actions::edit(), Actions::delete()])
            ->bulkActions([Actions::deleteBulk()]);
    }
}
```

- [ ] **Step 2: Rodar tests novamente**

```bash
php artisan test --filter=PostResourceTest 2>&1 | tail -20
```

Expected: 4 tests pass.

- [ ] **Step 3: Smoke browser**

```bash
php artisan serve --port=8765 &
SERVER_PID=$!
sleep 2
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8765/admin/posts -H "Cookie: $(cat /tmp/cookie 2>/dev/null)" || true
kill $SERVER_PID
```

(Acesso real autenticado fica para Fase 4 walkthrough manual.)

- [ ] **Step 4: Commit**

```bash
git add app/Arqel/Resources/PostResource.php tests/Feature/PostResourceTest.php
git commit --signoff -m "feat: add PostResource showcase + feature tests"
```

## Task 2.4: Adicionar `Category` model + migration + factory

**Files:**
- Create: `app/Models/Category.php`
- Create: `database/migrations/xxxx_create_categories_table.php`
- Create: `database/migrations/xxxx_add_category_id_to_posts.php`
- Create: `database/factories/CategoryFactory.php`
- Create: `database/seeders/CategorySeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `app/Models/Post.php` (adicionar `category()` relation)

- [ ] **Step 1: Criar migration `categories`**

```bash
php artisan make:migration create_categories_table
```

Editar:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

- [ ] **Step 2: Criar migration `add_category_id_to_posts`**

```bash
php artisan make:migration add_category_id_to_posts_table
```

Editar:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $t) {
            $t->foreignId('category_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $t) {
            $t->dropForeign(['category_id']);
            $t->dropColumn('category_id');
        });
    }
};
```

- [ ] **Step 3: Criar Category model**

`app/Models/Category.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class Category extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'description'];

    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            if (empty($category->slug) && ! empty($category->name)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
```

- [ ] **Step 4: Atualizar Post model**

Editar `app/Models/Post.php`:
- Adicionar `category_id` ao `$fillable`
- Adicionar relação `category()`:

```php
public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(Category::class);
}
```

- [ ] **Step 5: Criar CategoryFactory**

`database/factories/CategoryFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.Str::random(3),
            'description' => fake()->sentence(),
        ];
    }
}
```

- [ ] **Step 6: Criar CategorySeeder**

`database/seeders/CategorySeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::factory()->count(5)->create();

        Post::all()->each(function (Post $post) use ($categories) {
            $post->update(['category_id' => $categories->random()->id]);
        });
    }
}
```

- [ ] **Step 7: Atualizar DatabaseSeeder**

```php
public function run(): void
{
    $this->call(PostSeeder::class);
    $this->call(CategorySeeder::class);
}
```

- [ ] **Step 8: Migrate fresh + seed**

```bash
php artisan migrate:fresh --seed 2>&1 | tail -10
```

Expected: tabelas criadas, posts e categories seeded, posts ligados a categorias.

- [ ] **Step 9: Commit**

```bash
git add app/Models/Category.php app/Models/Post.php database/migrations/*categories* database/migrations/*add_category_id* database/factories/CategoryFactory.php database/seeders/CategorySeeder.php database/seeders/DatabaseSeeder.php
git commit --signoff -m "feat: add Category model + relate Post belongsTo Category"
```

## Task 2.5: Escrever CategoryResourceTest (TDD)

**Files:**
- Create: `tests/Feature/CategoryResourceTest.php`

- [ ] **Step 1: Criar test**

`tests/Feature/CategoryResourceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_lists_categories_on_the_resource_index(): void
    {
        Category::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get('/admin/categories')
            ->assertOk();
    }

    public function test_renders_the_create_form_for_categories(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/categories/create')
            ->assertOk();
    }

    public function test_persists_a_new_category(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/categories', [
                'name' => 'Tech',
                'slug' => 'tech',
                'description' => 'Tech posts',
            ])
            ->assertRedirect();

        $this->assertTrue(Category::where('slug', 'tech')->exists());
    }

    public function test_rejects_creation_without_required_name(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/categories', ['slug' => 'no-name'])
            ->assertSessionHasErrors('name');
    }
}
```

- [ ] **Step 2: Rodar (deve falhar)**

```bash
php artisan test --filter=CategoryResourceTest 2>&1 | tail -10
```

Expected: 404 (CategoryResource ainda não existe).

## Task 2.6: Implementar CategoryResource

**Files:**
- Create: `app/Arqel/Resources/CategoryResource.php`

- [ ] **Step 1: Criar resource**

`app/Arqel/Resources/CategoryResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Category;
use Arqel\Actions\Actions;
use Arqel\Core\Resources\Resource;
use Arqel\Fields\FieldFactory as Field;
use Arqel\Fields\Types\TextareaField;
use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Form\Layout\Section;
use Arqel\Table\Columns\TextColumn;
use Arqel\Table\Table;

final class CategoryResource extends Resource
{
    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    public static string $model = Category::class;

    public static ?string $slug = 'categories';
    public static ?string $label = 'Category';
    public static ?string $pluralLabel = 'Categories';
    public static ?string $navigationIcon = 'tag';
    public static ?string $navigationGroup = 'Content';
    public static ?int $navigationSort = 20;
    public static ?string $recordTitleAttribute = 'name';

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            (new TextField('name'))->required(),
            Field::slug('slug'),
            new TextareaField('description'),
        ];
    }

    public function form(): Form
    {
        return Form::make()
            ->columns(2)
            ->model(Category::class)
            ->schema([
                Section::make('Details')
                    ->columns(2)
                    ->schema([
                        (new TextField('name'))->required()->columnSpan('full'),
                        Field::slug('slug')->fromField('name')->columnSpan('full'),
                        (new TextareaField('description'))->columnSpan('full'),
                    ]),
            ]);
    }

    public function table(): Table
    {
        return (new Table)
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('slug')->searchable(),
                TextColumn::make('posts_count')->label('Posts')->sortable(),
            ])
            ->defaultSort('name', 'asc')
            ->searchable()
            ->selectable()
            ->actions([Actions::edit(), Actions::delete()])
            ->bulkActions([Actions::deleteBulk()]);
    }
}
```

- [ ] **Step 2: Rodar tests**

```bash
php artisan test --filter=CategoryResourceTest 2>&1 | tail -10
```

Expected: 4 tests pass.

- [ ] **Step 3: Smoke completo**

```bash
php artisan test 2>&1 | tail -15
```

Expected: todos os feature tests passam.

- [ ] **Step 4: Commit**

```bash
git add app/Arqel/Resources/CategoryResource.php tests/Feature/CategoryResourceTest.php
git commit --signoff -m "feat: add CategoryResource + feature tests"
```

## Task 2.7: Adicionar widget StatCard "Total Posts"

**Files:**
- Create: `app/Arqel/Widgets/TotalPostsWidget.php`
- Modify: registo de panel/widgets (depende da convenção do framework — verificar via `arqel:doctor` ou docs)

- [ ] **Step 1: Inspeccionar como widgets são registrados**

```bash
cd ~/PhpstormProjects/arqel-test
ls app/Arqel/ 2>/dev/null
grep -r "widget" config/ app/Providers/ 2>/dev/null | head -10
php artisan list arqel | grep -i widget
```

Expected: descobrir convenção. Provavelmente `app/Arqel/Widgets/` é auto-discovered, ou registado em `AppServiceProvider`/`config/arqel.php`.

- [ ] **Step 2: Criar widget**

`app/Arqel/Widgets/TotalPostsWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Arqel\Widgets;

use App\Models\Post;
use Arqel\Core\Widgets\StatCard;

final class TotalPostsWidget extends StatCard
{
    protected ?string $label = 'Total Posts';

    public function getValue(): string
    {
        return (string) Post::count();
    }
}
```

(Se o symbol `Arqel\Core\Widgets\StatCard` não existir — verificar via `composer show arqel-dev/core` e ajustar import. Se não houver widget API exposta, **PARAR** e abrir ticket: "widgets API not available in v0.9.1" — skip esta task ao invés de fabricar API.)

- [ ] **Step 3: Verificar registo**

Se auto-discovery: `php artisan arqel:doctor` deve listar o widget.
Se manual: registar em `config/arqel.php` ou `AppServiceProvider::boot`.

- [ ] **Step 4: Commit (ou skip se widget API ausente)**

```bash
git add app/Arqel/Widgets/TotalPostsWidget.php
git commit --signoff -m "feat: add TotalPostsWidget StatCard on dashboard"
```

# Fase 3 — Integração MCP

## Task 3.1: Inspeccionar resolução de projeto do MCP server

**Files:** read-only

- [ ] **Step 1: Ler `resolve-project.ts`**

```bash
cat /home/diogo/PhpstormProjects/arqel/packages-js/mcp-server/src/laravel/resolve-project.ts
```

Expected: descobrir env var ou heurística (provavelmente `ARQEL_PROJECT_PATH`, `LARAVEL_PROJECT_PATH`, ou `process.cwd()`).

- [ ] **Step 2: Anotar a env var correcta para o `.mcp.json`**

Anotar nome exacto. Default no plano: `ARQEL_PROJECT_PATH`. Se for outro, substituir nos passos subsequentes.

## Task 3.2: Criar `.mcp.json` no projeto externo

**Files:**
- Create: `~/PhpstormProjects/arqel-test/.mcp.json`

- [ ] **Step 1: Criar config**

`.mcp.json`:

```json
{
  "mcpServers": {
    "arqel": {
      "command": "npx",
      "args": ["-y", "@arqel-dev/mcp-server@0.9.1"],
      "env": {
        "ARQEL_PROJECT_PATH": "/home/diogo/PhpstormProjects/arqel-test"
      }
    }
  }
}
```

(Substituir `ARQEL_PROJECT_PATH` se Task 3.1 indicar outro nome.)

- [ ] **Step 2: Verificar `npx` consegue baixar o pacote**

```bash
npx -y @arqel-dev/mcp-server@0.9.1 --help 2>&1 | head -10
```

Expected: ajuda imprime ou processo arranca em modo MCP stdio (vai esperar input — Ctrl+C). Se erro de "package not found": Fase 0 falhou no NPM publish.

- [ ] **Step 3: Commit**

```bash
git add .mcp.json
git commit --signoff -m "chore: add .mcp.json for @arqel-dev/mcp-server v0.9.1"
```

## Task 3.3: Smoke das 7 tools MCP

**Files:** N/A (apenas validação)

- [ ] **Step 1: Iniciar Claude Code apontando para `arqel-test`**

```bash
cd ~/PhpstormProjects/arqel-test
claude
```

(Ou via UI/IDE — qualquer cliente MCP funciona.)

- [ ] **Step 2: Validar que MCP arqel está conectado**

No Claude Code, verificar via UI ou:
```
/mcp
```

Expected: `arqel` server listado como connected. Se falhar: ver logs do MCP server (`npx ... 2>/tmp/mcp.log`).

- [ ] **Step 3: Smoke `search_docs`**

Pedir: "Use `mcp__arqel__search_docs` to search for 'field types'".
Expected: retorna chunks relevantes.

- [ ] **Step 4: Smoke `get_adr`**

Pedir: "Use `mcp__arqel__get_adr` for ADR 001".
Expected: retorna ADR-001 (Inertia-only).

- [ ] **Step 5: Smoke `get_api_reference`**

Pedir: "Use `mcp__arqel__get_api_reference` for `Arqel\\Core\\Resources\\Resource`".
Expected: retorna excerto da API.

- [ ] **Step 6: Smoke `list_resources`**

Pedir: "Use `mcp__arqel__list_resources`".
Expected: retorna `PostResource`, `CategoryResource`, `UserResource`.

- [ ] **Step 7: Smoke `describe_resource`**

Pedir: "Use `mcp__arqel__describe_resource` with slug 'posts'".
Expected: retorna shape canónico (fields, table, form, actions).

- [ ] **Step 8: Smoke `generate_resource`**

Pedir: "Use `mcp__arqel__generate_resource` to create a `TagResource` for a `Tag` model with `name` and `slug` fields".
Expected: retorna ficheiros para criar (ou cria-os directamente). Verificar que `php artisan migrate` corre sem erro e `/admin/tags` renderiza.

- [ ] **Step 9: Smoke `generate_field`**

Pedir: "Use `mcp__arqel__generate_field` for a `color` ColorField on PostResource".
Expected: retorna snippet inseríble.

- [ ] **Step 10: Anotar resultado de cada smoke** para o relatório da Fase 4. Não há commit nesta task — é validação read-mostly. Se `generate_resource` criou ficheiros, esses entram em commit separado:

```bash
git add app/Arqel/Resources/TagResource.php app/Models/Tag.php database/migrations/*tags*
git commit --signoff -m "feat: add TagResource via mcp__arqel__generate_resource smoke"
```

# Fase 4 — Validação e relatório

## Task 4.1: Walkthrough manual dos 15 critérios

**Files:** N/A (validação browser)

- [ ] **Step 1: Iniciar dev server**

```bash
cd ~/PhpstormProjects/arqel-test
php artisan serve --port=8000 &
pnpm run dev
```

(Em terminais separados; ou `php artisan serve & npm run dev`.)

- [ ] **Step 2: Para cada critério (1-15), testar e anotar pass/fail**

Lista de critérios (do spec):
1. Login com credenciais válidas (`admin@arqel.test` / `password`)
2. Login com credenciais inválidas exibe erro
3. Index `/admin/posts` lista records paginados
4. SelectFilter `status=published` filtra a lista
5. TernaryFilter `featured` filtra a lista
6. Paginação navega entre páginas
7. Form de create renderiza (`/admin/posts/create`)
8. Validation errors exibidas no submit inválido
9. Edit pré-popula dados (`/admin/posts/{id}/edit`)
10. Delete exibe modal de confirmação
11. Bulk delete funciona
12. Theme toggle (dark/light) via Cmd+K
13. Command Palette abre via Cmd+K e navega
14. Search global filtra a lista
15. Sort por coluna funciona

Anotar resultado num scratch file (`/tmp/walkthrough-results.txt`) com formato:
```
1. PASS
2. PASS
3. FAIL — Notes: ...
...
```

- [ ] **Step 3: Repetir para `/admin/categories`** (mesmos critérios aplicáveis).

## Task 4.2: Escrever relatório

**Files:**
- Create: `/home/diogo/PhpstormProjects/arqel/docs/superpowers/reports/2026-05-06-e2e-validation-report.md`

- [ ] **Step 1: Criar diretório se não existir**

```bash
cd /home/diogo/PhpstormProjects/arqel
mkdir -p docs/superpowers/reports
```

- [ ] **Step 2: Escrever relatório**

Conteúdo (template):

```markdown
# E2E validation report — Arqel framework v0.9.1

**Data:** 2026-05-06
**Versão testada:** v0.9.1 (Packagist + NPM)
**Ambiente:** ~/PhpstormProjects/arqel-test (Laravel 13, PHP 8.x, SQLite)
**Spec:** docs/superpowers/specs/2026-05-06-external-validation-mcp-integration-design.md

## Resumo executivo

[1 paragraph: passou tudo / falhou X / status final]

## Critério-por-critério (Fase 2 walkthrough)

| # | Critério | PostResource | CategoryResource | Notas |
|---|----------|--------------|------------------|-------|
| 1 | Login válido | PASS | — | |
| 2 | Login inválido erro | PASS | — | |
| 3 | Index lista | PASS | PASS | |
| 4 | SelectFilter | PASS | N/A | |
| 5 | TernaryFilter | PASS | N/A | |
| 6 | Paginação | PASS | PASS | |
| 7 | Create form | PASS | PASS | |
| 8 | Validation errors | PASS | PASS | |
| 9 | Edit pré-popula | PASS | PASS | |
| 10 | Delete modal | PASS | PASS | |
| 11 | Bulk delete | PASS | PASS | |
| 12 | Theme toggle | PASS | — | |
| 13 | Command Palette | PASS | — | |
| 14 | Search global | PASS | PASS | |
| 15 | Sort por coluna | PASS | PASS | |

## Smoke MCP (Fase 3)

| Tool | Resultado | Notas |
|------|-----------|-------|
| search_docs | PASS | |
| get_adr | PASS | |
| get_api_reference | PASS | |
| list_resources | PASS | |
| describe_resource | PASS | |
| generate_resource | PASS | TagResource gerada e funcional |
| generate_field | PASS | |

## Bugs descobertos

[Para cada bug: descrição, severidade, ticket criado em PLANNING/]

## Performance observations

- Tempo `composer require arqel-dev/framework`: X seconds
- Tempo `arqel:install`: X seconds
- Tempo `pnpm run build` (cold): X seconds
- Tempo primeira request `/admin`: X ms
- Tamanho `vendor/arqel-dev/`: X MB

## Conclusão

[Pronto para Fase 2 do roadmap / bloqueado em X / X bugs minor não bloqueantes]
```

Preencher com dados reais da Task 4.1 e 3.3.

- [ ] **Step 3: Commit no monorepo**

```bash
cd /home/diogo/PhpstormProjects/arqel
git add docs/superpowers/reports/2026-05-06-e2e-validation-report.md
git commit --signoff -m "docs(docs): add e2e validation report for v0.9.1"
git push origin main
```

# Fase 5 — Cleanup

## Task 5.1: Remover `apps/demo-old`

**Files:**
- Delete: `apps/demo-old/`

- [ ] **Step 1: Confirmar que `apps/demo-old/` existe e é seguro remover**

```bash
cd /home/diogo/PhpstormProjects/arqel
ls apps/demo-old/ 2>/dev/null | head -5
git log --oneline -- apps/demo-old/ | head -5
```

Expected: existe, tem apenas commits de backup. Pode ser removido.

- [ ] **Step 2: Remover**

```bash
rm -rf apps/demo-old/
```

- [ ] **Step 3: Verificar `.gitignore`**

```bash
git diff .gitignore
```

Se `.gitignore` foi modificado durante o walkthrough, restaurar partes que façam sentido. Se não há mudanças, skip.

- [ ] **Step 4: Commit**

```bash
git add -- apps/demo-old/ .gitignore
git status --short
git commit --signoff -m "chore(demo): remove demo-old backup"
git push origin main
```

## Task 5.2: Atualizar `docs/tickets/current.md`

**Files:**
- Modify: `docs/tickets/current.md`

- [ ] **Step 1: Ler estado actual**

```bash
cat docs/tickets/current.md
```

- [ ] **Step 2: Atualizar para próxima sprint**

Editar para apontar ao próximo ticket (provavelmente abertura da Fase 2 do roadmap, conforme `PLANNING/09-fase-2-essenciais.md`).

- [ ] **Step 3: Commit**

```bash
git add docs/tickets/current.md
git commit --signoff -m "chore(tickets): advance current.md to phase 2 kickoff"
git push origin main
```

---

## Self-review

**1. Spec coverage:**
- Fase 0 release pipeline → Tasks 0.1-0.5 ✅
- Fase 1 bootstrap → Tasks 1.1-1.4 ✅
- Fase 2 cobertura (Post + Category + User + nav + widget + tests) → Tasks 2.1-2.7 ✅
- Fase 3 MCP → Tasks 3.1-3.3 ✅
- Fase 4 validação + relatório → Tasks 4.1-4.2 ✅
- Fase 5 cleanup → Tasks 5.1-5.2 ✅
- UserResource: vem out-of-the-box, não precisa de task dedicada (validado nos walkthrough criteria)

**2. Placeholder scan:**
- "Verificar via `arqel:doctor` ou docs" em Task 2.7 — fix: adicionei comando concreto para inspeccionar
- "ajustar Pest syntax" em 2.2 — aceitável (depende do detect)
- Task 2.7 explicitamente diz "PARAR e abrir ticket" se widget API ausente — guarda apropriada, não placeholder

**3. Type consistency:**
- `PostResource::$slug = 'posts'` consistente com URL `/admin/posts` em todos os lugares
- `CategoryResource::$slug = 'categories'` consistente com URL `/admin/categories`
- `Post::$model = Post::class`, `Post belongsTo User + Category`, `Category hasMany Posts` — coerente
- Migration `add_category_id_to_posts` adiciona FK depois da migration original — ordem temporal correcta (criar categories table antes de adicionar FK em posts requereria ordem específica de timestamps; corrigi ao garantir que `xxxx_create_categories_table` < `xxxx_add_category_id_to_posts`)

**Plano completo e auto-consistente.**

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-06-external-validation-mcp-integration.md`. Two execution options:

1. **Subagent-Driven (recommended)** — eu despacho um subagent fresco por task, review entre tasks, iteração rápida
2. **Inline Execution** — executar tasks nesta sessão usando executing-plans, batch execution com checkpoints

Qual escolhes?
