# Deploy guide — Arqel on Laravel Cloud

This guide takes you from **zero to a panel in production** in under 10 minutes.
If a step fails, jump to the [Troubleshooting](#troubleshooting) section at the
end.

---

## Prerequisites

Before you start, make sure you have:

- [x] An account on [Laravel Cloud](https://cloud.laravel.com) (any plan).
- [x] A GitHub account with permission to create repositories.
- [x] PHP 8.3+ and Composer installed locally.
- [x] Arqel CLI: `composer global require arqel-dev/cli`.
- [x] Git configured with `user.email` and `user.name`.

---

## Step 1 — Generate the template via `arqel cloud:export`

The `cloud:export` command (delivered in LCLOUD-001) materializes a Laravel app
**ready for Laravel Cloud** in an empty directory. It includes:

- `cloud.yaml` with services (web, worker, scheduler, Reverb).
- `composer.json` with `arqel-dev/core`, `arqel-dev/auth`, `arqel-dev/fields`, and dependencies.
- `package.json` with Inertia + React 19.2 + Tailwind v4.
- `app/Providers/PanelServiceProvider.php` registering the default panel.
- `routes/arqel.php`, `config/arqel.php`, and `database/migrations/0000_arqel_base.php`.

```bash
arqel cloud:export ./meu-painel --app-name=meu-painel
```

**Expected output:**

```
Exported 47 files to /home/you/meu-painel

Next steps:
  1. Review the generated files in /home/you/meu-painel
  2. Initialize git:
       cd /home/you/meu-painel
       git init
       git add .
       git commit -m 'Initial Arqel app'
  3. Push to GitHub and click "Deploy to Laravel Cloud" in the README.
```

> Tip: `--app-name` accepts `[a-zA-Z][a-zA-Z0-9_-]*`. Use the same value you
> intend to give the service in Laravel Cloud to keep logs and dashboards
> aligned.

---

## Step 2 — Push to GitHub

```bash
cd ./meu-painel
git init
git add .
git commit -m "feat: initial Arqel app" --signoff
git branch -M main
git remote add origin git@github.com:owner/meu-painel.git
git push -u origin main
```

> **Private repository?** No problem — Laravel Cloud asks for authorization
> via a GitHub App during OAuth and only accesses the repositories you
> explicitly grant.

---

## Step 3 — Deploy via `arqel cloud:deploy-link` (recommended)

Starting in Arqel 0.4 (LCLOUD-004), the CLI generates a "Deploy to Laravel Cloud"
link with the query params already filled in:

```bash
arqel cloud:deploy-link owner/meu-painel --region=us-east --name=meu-painel
```

**Output:**

```
Deploy to Laravel Cloud:
https://cloud.laravel.com/deploy?repo=https%3A%2F%2Fgithub.com%2Fowner%2Fmeu-painel&region=us-east&name=meu-painel

(URL copied to clipboard via xclip.)

Next steps:
  1. Make sure the repository is pushed to GitHub.
  2. Open the URL above and authorise Laravel Cloud (GitHub OAuth).
  3. Confirm the import and configure environment variables.
```

Open the URL in your browser. Laravel Cloud will:

1. Ask for authorization from the GitHub App (once per organization).
2. Read `cloud.yaml` and provision automatically:
   - **1× web instance** (PHP-FPM + optional Octane).
   - **1× queue worker** (`php artisan queue:work redis`).
   - **1× scheduler** (`php artisan schedule:run` in cron).
   - **1× Reverb** (`php artisan reverb:start`).
   - **Managed Postgres** (16+).
   - **Managed Redis** (queue + cache + Reverb pub/sub).
3. Compile assets via `npm ci && npm run build`.
4. Run `php artisan key:generate` if `APP_KEY` is empty.

> **Without the CLI?** You can go straight to https://cloud.laravel.com/deploy and
> select the repository manually. The CLI-generated link is only a
> convenience.

### Options supported by `cloud:deploy-link`

| Flag | Default | Description |
| ---- | ------- | ----------- |
| `--region=` | `auto` | Provisioning region (`auto`, `us-east`, `us-west`, `eu-central`, `eu-west`, `ap-southeast`, `sa-east`). |
| `--name=` | (no default) | App name in the dashboard. Accepts `[a-zA-Z][a-zA-Z0-9_-]*`, max 40 chars. |

---

## Step 4 — Configure environment variables

Laravel Cloud injects automatically:

- `DATABASE_URL` — Postgres connection string.
- `REDIS_URL` — Redis connection string.
- `APP_URL` — public URL of the app.
- `APP_ENV=production`.

**You need to configure** (in the panel → Environment):

| Variable | Recommended value | Why |
| -------- | ----------------- | --- |
| `APP_KEY` | (Cloud generates automatically) | Laravel encryption key. |
| `MAIL_MAILER` | `resend` or `postmark` | For password reset / invites. |
| `MAIL_FROM_ADDRESS` | `noreply@seu-dominio.com` | `From:` header in transactional emails. |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` | (generate with `php artisan reverb:install`) | Broadcasting auth. |
| `ARQEL_PANEL_PATH` | `/admin` | Prefix for the panel routes. |
| `ARQEL_AI_OPENAI_KEY` | (optional) | If using `@arqel-dev/ai` with OpenAI. |

> `cloud:export` already produces a `.env.example` with all the placeholders.
> Paste it into the dashboard, fill in values, and click **Save**.

---

## Step 5 — Run migrations

By default, Laravel Cloud **does not run migrations automatically** on the first
deploy (to avoid destroying data on rollbacks). You need to trigger them
manually once:

**Via dashboard** → app → Tasks → New Task:

```
php artisan migrate --force
```

**Via Cloud CLI** (if installed):

```bash
laravel-cloud task run "php artisan migrate --force" --app=meu-painel
```

After the first `migrate`, configure `Auto migrate` in the dashboard
(Settings → Deploy → Run migrations on deploy: ON).

### Initial seed (optional)

If you generated the template with `--with-sample` (reserved for LCLOUD-001
phase 2), run:

```bash
php artisan db:seed --class=ArqelSampleSeeder --force
```

---

## Final verification

Visit `https://meu-painel.laravel.cloud/admin` (or your custom domain).
You should see:

- [x] Arqel login screen (Inertia + Radix UI).
- [x] After login: empty dashboard with side nav.
- [x] DevTools console with no 500/404 errors.
- [x] Connected WebSocket (green badge in the lower corner if Reverb is OK).

---

## Troubleshooting

### Build failure: "Class 'Arqel\Core\Panel' not found"

Cause: corrupted autoload cache after installing a new plugin.
Fix: trigger a deploy with `Clear cache` checked, or run in the dashboard:

```bash
composer dump-autoload --optimize
php artisan optimize:clear
```

### "extension xyz not found" during composer install

Laravel Cloud already ships PHP 8.3 + standard extensions (`pdo`, `mbstring`,
`bcmath`, `gd`, `redis`, `intl`, `zip`). If you need something exotic
(e.g., `imagick`, `ldap`), add it to `cloud.yaml`:

```yaml
services:
  web:
    php:
      extensions:
        - imagick
```

### Migration timeout (≥30s)

Large migrations (e.g., backfilling a new column) can exceed the task limit.
Use **Maintenance Mode** + run via SSH session:

```bash
php artisan down --secret=temp-token
php artisan migrate --force --timeout=600
php artisan up
```

### Reverb does not connect (red badge)

Check `REVERB_HOST` (must be the public domain without `https://`),
`REVERB_PORT=443`, `REVERB_SCHEME=https`. Laravel Cloud terminates TLS at the
edge, so Reverb runs behind the proxy.

### Queue worker stuck

```bash
laravel-cloud task run "php artisan queue:restart"
```

Cloud restarts workers automatically on every deploy, but after changing
`.env` you need to trigger it manually.

### "Permission denied" on `storage/`

Cloud mounts `storage/` as a persistent volume. If you `chmod`'d incorrectly
on the template, run in the SSH Console:

```bash
chmod -R ug+rwX storage bootstrap/cache
```

### Build OK but page returns 502

Check the logs in **Logs → web**. Most common cause: empty `APP_KEY`. Fix:

```bash
laravel-cloud task run "php artisan key:generate --force"
```

After `key:generate`, trigger a redeploy.

---

## Next steps

- Configure auto-scaling for variable traffic → see [auto-scaling.md](./auto-scaling.md).
- Estimate costs before moving production data → see [cost-estimation.md](./cost-estimation.md).
- Evaluate alternatives (Fly.io, Render, AWS) → see [comparison-other-hosts.md](./comparison-other-hosts.md).
