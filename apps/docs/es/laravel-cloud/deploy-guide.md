# Guía de deploy — Arqel en Laravel Cloud

Esta guía te lleva desde **cero hasta un panel en producción** en menos de 10 minutos.
Si un paso falla, salta a la sección [Troubleshooting](#troubleshooting) al
final.

---

## Prerrequisitos

Antes de empezar, asegúrate de tener:

- [x] Una cuenta en [Laravel Cloud](https://cloud.laravel.com) (cualquier plan).
- [x] Una cuenta GitHub con permiso para crear repositorios.
- [x] PHP 8.3+ y Composer instalados localmente.
- [x] Arqel CLI: `composer global require arqel-dev/cli`.
- [x] Git configurado con `user.email` y `user.name`.

---

## Paso 1 — Generar el template vía `arqel cloud:export`

El comando `cloud:export` (entregado en LCLOUD-001) materializa una app Laravel
**lista para Laravel Cloud** en un directorio vacío. Incluye:

- `cloud.yaml` con servicios (web, worker, scheduler, Reverb).
- `composer.json` con `arqel-dev/core`, `arqel-dev/auth`, `arqel-dev/fields` y dependencias.
- `package.json` con Inertia + React 19.2 + Tailwind v4.
- `app/Providers/PanelServiceProvider.php` registrando el panel por defecto.
- `routes/arqel.php`, `config/arqel.php` y `database/migrations/0000_arqel_base.php`.

```bash
arqel cloud:export ./meu-painel --app-name=meu-painel
```

**Salida esperada:**

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

> Tip: `--app-name` acepta `[a-zA-Z][a-zA-Z0-9_-]*`. Usa el mismo valor que
> tengas pensado dar al servicio en Laravel Cloud para mantener logs y dashboards
> alineados.

---

## Paso 2 — Push a GitHub

```bash
cd ./meu-painel
git init
git add .
git commit -m "feat: initial Arqel app" --signoff
git branch -M main
git remote add origin git@github.com:owner/meu-painel.git
git push -u origin main
```

> **¿Repositorio privado?** Sin problema — Laravel Cloud pide autorización
> mediante una GitHub App durante el OAuth y solo accede a los repositorios que
> le concedas explícitamente.

---

## Paso 3 — Deploy vía `arqel cloud:deploy-link` (recomendado)

A partir de Arqel 0.4 (LCLOUD-004), la CLI genera un link "Deploy to Laravel Cloud"
con los query params ya rellenos:

```bash
arqel cloud:deploy-link owner/meu-painel --region=us-east --name=meu-painel
```

**Salida:**

```
Deploy to Laravel Cloud:
https://cloud.laravel.com/deploy?repo=https%3A%2F%2Fgithub.com%2Fowner%2Fmeu-painel&region=us-east&name=meu-painel

(URL copied to clipboard via xclip.)

Next steps:
  1. Make sure the repository is pushed to GitHub.
  2. Open the URL above and authorise Laravel Cloud (GitHub OAuth).
  3. Confirm the import and configure environment variables.
```

Abre la URL en el browser. Laravel Cloud va a:

1. Pedir autorización de la GitHub App (una vez por organización).
2. Leer `cloud.yaml` y provisionar automáticamente:
   - **1× instancia web** (PHP-FPM + Octane opcional).
   - **1× queue worker** (`php artisan queue:work redis`).
   - **1× scheduler** (`php artisan schedule:run` en cron).
   - **1× Reverb** (`php artisan reverb:start`).
   - **Postgres gestionado** (16+).
   - **Redis gestionado** (queue + cache + Reverb pub/sub).
3. Compilar assets vía `npm ci && npm run build`.
4. Ejecutar `php artisan key:generate` si `APP_KEY` está vacío.

> **¿Sin la CLI?** Puedes ir directo a https://cloud.laravel.com/deploy y
> seleccionar el repositorio manualmente. El link generado por la CLI es solo
> conveniencia.

### Opciones soportadas por `cloud:deploy-link`

| Flag | Default | Descripción |
| ---- | ------- | ----------- |
| `--region=` | `auto` | Región de provisioning (`auto`, `us-east`, `us-west`, `eu-central`, `eu-west`, `ap-southeast`, `sa-east`). |
| `--name=` | (sin default) | Nombre de la app en el dashboard. Acepta `[a-zA-Z][a-zA-Z0-9_-]*`, máx. 40 chars. |

---

## Paso 4 — Configurar variables de entorno

Laravel Cloud inyecta automáticamente:

- `DATABASE_URL` — connection string de Postgres.
- `REDIS_URL` — connection string de Redis.
- `APP_URL` — URL pública de la app.
- `APP_ENV=production`.

**Necesitas configurar** (en el panel → Environment):

| Variable | Valor recomendado | Por qué |
| -------- | ----------------- | --- |
| `APP_KEY` | (Cloud genera automáticamente) | Clave de encriptación de Laravel. |
| `MAIL_MAILER` | `resend` o `postmark` | Para password reset / invites. |
| `MAIL_FROM_ADDRESS` | `noreply@seu-dominio.com` | Header `From:` en emails transaccionales. |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` | (genera con `php artisan reverb:install`) | Auth de broadcasting. |
| `ARQEL_PANEL_PATH` | `/admin` | Prefijo para las rutas del panel. |
| `ARQEL_AI_OPENAI_KEY` | (opcional) | Si usas `@arqel-dev/ai` con OpenAI. |

> `cloud:export` ya produce un `.env.example` con todos los placeholders.
> Pégalo en el dashboard, rellena los valores y haz click en **Save**.

---

## Paso 5 — Ejecutar migraciones

Por defecto, Laravel Cloud **no ejecuta migraciones automáticamente** en el primer
deploy (para evitar destruir datos en rollbacks). Necesitas dispararlas
manualmente una vez:

**Vía dashboard** → app → Tasks → New Task:

```
php artisan migrate --force
```

**Vía Cloud CLI** (si está instalada):

```bash
laravel-cloud task run "php artisan migrate --force" --app=meu-painel
```

Tras el primer `migrate`, configura `Auto migrate` en el dashboard
(Settings → Deploy → Run migrations on deploy: ON).

### Seed inicial (opcional)

Si generaste el template con `--with-sample` (reservado para LCLOUD-001
fase 2), ejecuta:

```bash
php artisan db:seed --class=ArqelSampleSeeder --force
```

---

## Verificación final

Visita `https://meu-painel.laravel.cloud/admin` (o tu dominio personalizado).
Deberías ver:

- [x] Pantalla de login de Arqel (Inertia + Radix UI).
- [x] Tras login: dashboard vacío con side nav.
- [x] Consola de DevTools sin errores 500/404.
- [x] WebSocket conectado (badge verde en la esquina inferior si Reverb está OK).

---

## Troubleshooting

### Build failure: "Class 'Arqel\Core\Panel' not found"

Causa: cache de autoload corrompido tras instalar un nuevo plugin.
Fix: dispara un deploy con `Clear cache` marcado, o ejecuta en el dashboard:

```bash
composer dump-autoload --optimize
php artisan optimize:clear
```

### "extension xyz not found" durante composer install

Laravel Cloud ya viene con PHP 8.3 + extensiones estándar (`pdo`, `mbstring`,
`bcmath`, `gd`, `redis`, `intl`, `zip`). Si necesitas algo exótico
(ej., `imagick`, `ldap`), añádelo a `cloud.yaml`:

```yaml
services:
  web:
    php:
      extensions:
        - imagick
```

### Migration timeout (≥30s)

Las migraciones grandes (ej., backfill de una nueva columna) pueden exceder el límite de la task.
Usa **Maintenance Mode** + ejecuta vía sesión SSH:

```bash
php artisan down --secret=temp-token
php artisan migrate --force --timeout=600
php artisan up
```

### Reverb no conecta (badge rojo)

Revisa `REVERB_HOST` (debe ser el dominio público sin `https://`),
`REVERB_PORT=443`, `REVERB_SCHEME=https`. Laravel Cloud termina TLS en el
edge, así que Reverb corre detrás del proxy.

### Queue worker atascado

```bash
laravel-cloud task run "php artisan queue:restart"
```

Cloud reinicia los workers automáticamente en cada deploy, pero tras cambiar
`.env` necesitas dispararlo manualmente.

### "Permission denied" en `storage/`

Cloud monta `storage/` como volumen persistente. Si hiciste `chmod` incorrectamente
en el template, ejecuta en la SSH Console:

```bash
chmod -R ug+rwX storage bootstrap/cache
```

### Build OK pero la página devuelve 502

Revisa los logs en **Logs → web**. Causa más común: `APP_KEY` vacío. Fix:

```bash
laravel-cloud task run "php artisan key:generate --force"
```

Tras `key:generate`, dispara un redeploy.

---

## Próximos pasos

- Configura auto-scaling para tráfico variable → mira [auto-scaling.md](./auto-scaling.md).
- Estima costos antes de mover datos de producción → mira [cost-estimation.md](./cost-estimation.md).
- Evalúa alternativas (Fly.io, Render, AWS) → mira [comparison-other-hosts.md](./comparison-other-hosts.md).
