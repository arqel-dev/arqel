# Expansão da showcase para dogfooding — Design

**Data:** 2026-06-08
**Autor:** Diogo C. Coutinho (+ Claude)
**Objetivo:** Maximizar a superfície de APIs do Arqel exercitadas pela `apps/showcase`, para que o loop autónomo de dogfood encontre bugs nos pacotes ainda não cobertos. Inclui um ambiente Docker isolado (com Reverb/DB reais) usado tanto em dev como no CI.

**Estado de partida (2026-06-08):** o loop convergiu (2 rondas limpas, 89 bugs + 1 hardening corrigidos), mas a showcase só exercita **12/20 pacotes PHP (60%)** e **7/18 JS (39%)**. A convergência significa "sem mais bugs no que é exercitado" — não "framework sem bugs". Esta expansão dá ao loop os 40%/61% intocados.

---

## Restrições (não negociáveis)

- **Não colidir** com os ambientes Docker em uso: `pnthubv2-*` (portas 8080, 5174, 9002, 6380, 9020, 9021), `hub-*` (5173, 5432, 6379, 9010, 9011), `traefik` (80, 443). Tudo no stack do dogfood usa portas dedicadas + project-name `arqel-dogfood`.
- **PT-BR** em docs/comunicação; **inglês** em código/commits.
- **ADRs canónicos** (Inertia-only, Laravel-native, final-by-default, declare strict_types, tests obrigatórios).
- **Worktree isolado**; branch `feat/showcase-dogfood-expansion`.

---

## 1. Ambiente Docker isolado (`arqel-dogfood`)

Stack Docker Compose dedicado em `apps/showcase/`, que fornece tudo o que a showcase expandida precisa, **incluindo realtime real via Laravel Reverb**.

**Ficheiros:**
- `apps/showcase/compose.dogfood.yml` (nome distinto de `docker-compose.yml` para não ser apanhado por automação do utilizador)
- `apps/showcase/docker/` — `Dockerfile` (PHP 8.4-fpm + extensões), `nginx.conf`, `entrypoint.sh`, `php.ini`
- `apps/showcase/.env.dogfood` — env dedicado (não toca no `.env` do utilizador)
- `apps/showcase/dogfood.sh` — wrapper CLI

**Serviços + portas (todas dedicadas):**

| Serviço | Imagem | Host→Container | Container name |
|---|---|---|---|
| `app` (PHP-FPM + nginx) | build local | **8090**→80 | `arqel-dogfood-app` |
| `vite` (HMR) | node:22-alpine | **5180**→5180 | `arqel-dogfood-vite` |
| `db` | postgres:18-alpine | **5433**→5432 | `arqel-dogfood-db` |
| `redis` | redis:7-alpine | **6390**→6379 | `arqel-dogfood-redis` |
| `reverb` (websockets) | build local (reuse app image) | **8091**→8080 | `arqel-dogfood-reverb` |

- **Project name:** `arqel-dogfood` (todos os comandos `docker compose -p arqel-dogfood …`) → `down` nunca apanha outros stacks.
- **Rede:** `arqel-dogfood-net` (bridge isolada).
- **Volumes:** `arqel-dogfood-db-data`, `arqel-dogfood-redis-data` (nomeados, com prefixo).
- **`.env.dogfood`:** `DB_CONNECTION=pgsql`, `DB_HOST=db`, `REDIS_HOST=redis`, `BROADCAST_CONNECTION=reverb`, `QUEUE_CONNECTION=redis`, `REVERB_HOST=reverb`/`REVERB_PORT=8080` (interno) + `VITE_REVERB_HOST=localhost`/`VITE_REVERB_PORT=8091` (browser), `APP_URL=http://localhost:8090`.

**`dogfood.sh` comandos:**
- `up` — `docker compose -p arqel-dogfood -f compose.dogfood.yml up -d --build`
- `down` — idem `down` (só este stack)
- `fresh` — up + `artisan migrate:fresh --seed` dentro do `app`
- `test` — `artisan test` (Pest) dentro do `app`
- `e2e` — `pnpm exec playwright test` apontado a `localhost:8090`
- `logs` / `sh` — conveniência

**Critério de aceite:** `./dogfood.sh fresh` sobe os 5 serviços nas portas dedicadas, migra+seed contra postgres, `curl localhost:8090/admin/login` → 200, Reverb aceita ligação em `:8091`, e nenhum container `pnthubv2-*`/`hub-*`/`traefik` é tocado.

---

## 2. Models / migrations / seeders novos

Três models novos, cada um desbloqueando uma família de APIs para o loop. Idempotentes como os atuais (`firstOrCreate`).

**`Order`** (`app/Models/Order.php`) — **state machine rica + soft-deletes**
- Traits: `HasWorkflow` (estados: `pending → paid → shipped → delivered`, + `cancelled` a partir de vários), `SoftDeletes`, `LogsActivity`, `BelongsToTenant`.
- Migration `create_orders_table`: id, tenant_id FK, reference, customer_name, total (decimal), state (default pending), `deleted_at`, timestamps.
- Seed: ~20 orders distribuídas por estados + alguns soft-deleted.

**`MediaAsset`** (`app/Models/MediaAsset.php`) — **file/image upload (disco local)**
- Migration `create_media_assets_table`: id, tenant_id FK, title, file_path, mime, size, timestamps.
- Disco `public` (storage local, sem S3/MinIO → zero-serviços para uploads).
- Seed: 2-3 ficheiros de exemplo copiados para storage.

**`Attachment`** (`app/Models/Attachment.php`) — **morph polimórfico + HasMany nested**
- Migration `create_attachments_table`: id, `attachable_type`/`attachable_id` (morphs, **com `enforceMorphMap`** ligado para exercitar a família morph-class), label, url, timestamps.
- Relação `morphMany` em Post e Order.
- `Relation::enforceMorphMap([...])` no `AppServiceProvider` — exercita o caminho morph-alias (audit/versioning/workflow já corrigidos para isto; valida que continua correto sob a app).

**Comment-inline** — o `Comment` já existe; passa a ser editável inline no PostResource via Repeater de relação (HasMany nested form).

---

## 3. Enriquecer Resources PHP (reusa models existentes)

**PostResource** — actions custom + form layouts + table avançada + versioning UI
- **Actions:** `RowAction::make('publish')` com `requiresConfirmation` + `successNotification`; `RowAction::make('change_status')->form([SelectField…])` (modal); `BulkAction::make('archive')->chunkSize(50)`; uma action com `->authorize(fn)` e `->disabled(fn($r)=>…)`.
- **Form:** `Tabs` (Content / SEO / Meta) + `Grid` responsivo (`['sm'=>1,'md'=>2]`) + `Group` com `visibleIf(fn($r)=>…)`; Comments inline (Repeater de relação).
- **Table:** `ComputedColumn('word_count')->getStateUsing(…)`, `RelationshipColumn(author.name)`, `TextColumn->togglable()->hiddenByDefault()`, `QueryBuilderFilter` com TextConstraint/NumberConstraint.
- **Versioning UI:** drawer com `<VersionTimeline>` + `<VersionDiff>` (endpoint REST já existe).

**TicketResource** — workflow a sério
- `StateTransitionField` na form + `->showHistory()`; transições com **guards de autorização** (deny-when-undefined já default); inline-edit `SelectColumn(status)` + `ToggleColumn`.

**OrderResource** (novo) — soft-delete + state machine
- Table com filtro de trashed + `RestoreAction` (bulk + row); state badges; `StateTransitionField`.

**MediaResource** (novo) — uploads
- Form com `ImageField->multiple()` + `FileField`; table com `ImageColumn`.

---

## 4. Frontend wiring (`app.tsx` + layout)

Liga os 6 pacotes JS não-cobertos:
- **i18n:** `I18nProvider` + `<LocaleSwitcher>` (en/pt_BR, config já existe) + `SetLocaleMiddleware` no kernel PHP.
- **theme:** `ThemeProvider` (defaultTheme=system) + `<ThemeToggle>` no Topbar + `preventFlashScript()` no Blade (anti-FOUC).
- **a11y:** `<SkipLink>` no AppShell + um modal custom com `useFocusTrap`/`useAnnounce`.
- **realtime:** `setupEcho` (→ Reverb do Docker) + `<ConnectionStatusBanner>` + `useFallbackPolling`; lado PHP um Resource com `BroadcastsResourceUpdates` + presence channel.
- **versioning/workflow UI:** componentes React acima, montados nos drawers/forms.

---

## 5. Testes

- **Feature (Pest)** por área nova: actions (publish/change-status/archive/authorize), form-layouts (tabs/grid/visibleIf), table-avançada (computed/relationship/query-builder), workflow (transição+guard+history), soft-delete (trashed filter+restore), uploads (store+validation), morph (enforceMorphMap round-trip), i18n (locale switch+cookie).
- **E2E (Playwright)** novos specs: `05-actions`, `06-form-layouts`, `07-table-advanced`, `08-workflow`, `09-soft-delete`, `10-uploads`, `11-i18n`, `12-theme`, `13-realtime` (banner via log/Reverb), `14-versioning-ui`.
- Princípio: cada feature nova ganha um teste que falha se a feature for removida. Tudo corre dentro do container (`./dogfood.sh test` / `./dogfood.sh e2e`).

---

## 6. CI usa o Docker (ajuste aprovado)

O job `apps/showcase` no `.github/workflows/ci.yml` (atualmente SQLite + `php artisan serve`, broadcast=log) passa a usar o `compose.dogfood`:
- Substituir os steps "Setup showcase .env + SQLite", "Migrate + seed", "Smoke-check server boots (serve)" e "Run E2E" por:
  - `docker compose -p arqel-dogfood -f apps/showcase/compose.dogfood.yml up -d --build`
  - aguardar health (`curl localhost:8090/admin/login` → 200; Reverb ready)
  - `./dogfood.sh fresh` (migrate+seed contra postgres)
  - `pnpm exec playwright test` apontado a `localhost:8090`
  - on-failure: `docker compose -p arqel-dogfood logs` + upload report
  - sempre: `docker compose -p arqel-dogfood down -v`
- **Vantagem:** o CI passa a exercitar postgres + redis + **Reverb real** (realtime no caminho real, não só log). Os outros jobs (test-matrix PHP×Laravel, Vitest unit) ficam inalterados.
- **Risco mitigado:** o runner do GitHub é limpo (sem colisão de portas); o `-p arqel-dogfood` + `down -v` garante teardown.

---

## 7. Re-corrida do loop (Round 22+)

- **`dogfood-seen.json`:** mantém as 96 sigs (não re-reportar o corrigido). Os clusters de detecção são **atualizados** para apontar às novas superfícies: actions-custom, form-layouts, table-avançada, workflow-UI, soft-delete, uploads, morph-app-level, i18n, theme, a11y, realtime.
- Lançar **Round 22** contra a cobertura alargada — espera-se nova vaga de bugs nos pacotes antes intocados. Mesmo pipeline (detect→verify→issue→TDD-fix→PR→merge-CLEAN), mesmo critério de paragem (2 rondas limpas consecutivas).
- Stub AI mantém-se (zero-gasto de LLM); o "ambiente Docker fornece tudo" aplica-se a infra (DB/redis/reverb), **não** a LLM/pagamentos reais.

---

## Cobertura resultante

| | Antes | Depois |
|---|---|---|
| PHP | 12/20 (60%) | ~18/20 (90%) |
| JS | 7/18 (39%) | ~15/18 (83%) |

**Fica de fora (justificado):** `cli` (não-web), `marketplace` (tem `apps/marketplace` próprio), `devtools-extension` (extensão de browser).

---

## Sequência de implementação (fases, cada uma verde antes da seguinte)

1. Ambiente Docker (`compose.dogfood.yml` + `docker/` + `.env.dogfood` + `dogfood.sh`).
2. Models + migrations + seeders novos (Order, MediaAsset, Attachment + Comment-inline) + `enforceMorphMap`.
3. Enriquecer Resources PHP (PostResource, TicketResource) + novos (OrderResource, MediaResource).
4. Frontend wiring (i18n, theme, a11y, realtime, versioning/workflow UI).
5. Testes Feature + E2E (todos via container).
6. Migrar o job CI da showcase para o `compose.dogfood`.
7. Atualizar clusters do loop + lançar Round 22.

---

## Riscos e mitigações

- **Colisão de portas Docker** → portas dedicadas (8090/5180/5433/6390/8091) + project-name `arqel-dogfood` + `down -v` isolado.
- **CI mais lento (Docker build)** → cache de layers + reuse da app image para o reverb.
- **Uploads em CI** → disco `public` local (sem S3/MinIO).
- **Reverb flaky em CI** → health-check com retry; fallback-polling cobre o caminho degradado; banner testado via ambos.
- **Round 22 pode encontrar muitos bugs** → é o objetivo; o pipeline já provou escalar (21 rondas, 89 bugs).
