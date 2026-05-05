# Encontrar plugins

> Cómo descubrir, evaluar e instalar plugins desde el Arqel Marketplace.

Esta página cubre el lado **consumidor** del marketplace — desarrolladores que quieren extender su admin Arqel con fields, widgets, integraciones o temas mantenidos por la comunidad.

## Caminos de descubrimiento

El marketplace expone seis caminos públicos vía REST (y la UI en `arqel.dev/marketplace` los consume todos):

### 1. Búsqueda por categoría

Categorías raíz por defecto: `fields`, `widgets`, `themes`, `integrations`, `utilities`. Cada una puede tener sub-categorías (relación auto-referenciada `parent_id` en `arqel_plugin_categories`).

```http
GET /api/marketplace/categories?root=1
GET /api/marketplace/categories/widgets/plugins
```

El primero devuelve todas las categorías raíz con `children` eager-loaded. El segundo devuelve plugins publicados de una categoría específica, paginado.

### 2. Búsqueda de texto

La búsqueda libre cubre `name` + `description`:

```http
GET /api/marketplace/plugins?search=calendar&type=widget&per_page=30
```

Parámetros soportados: `type` (enum), `search` (string), `per_page` (clampeado 1-100), `page`. Solo aparecen plugins con `status=published` — drafts, pending y archived son opacos (404 explícito).

### 3. Trending

Top plugins en los últimos 7 días. El score es calculado por `TrendingScoreCalculator`:

```
score = installations_last_7d * 1.0 + recent_positive_reviews_30d * 5.0
```

Las reviews positivas (≥4 estrellas) pesan 5x más que las instalaciones brutas — la señal social vence a los picos de descargas anónimas.

```http
GET /api/marketplace/trending
```

Devuelve los 20 plugins publicados top ordenados por `trending_score` desc. El recálculo es diario (la app host debe agendar `Schedule::command('arqel:marketplace:trending')->daily()`).

### 4. Featured (editor's picks)

Curación manual por el equipo Arqel. Activado vía `POST /admin/plugins/{slug}/feature` (Gate `marketplace.feature`).

```http
GET /api/marketplace/featured
```

Ordenado por `featured_at` desc — los picks más recientes primero.

### 5. Nuevos esta semana

Plugins publicados en los últimos N días (por defecto 7, clampeado 1-90):

```http
GET /api/marketplace/new?days=14
```

Útil para watchlists y newsletters semanales.

### 6. Más populares (de todos los tiempos)

Ranking absoluto por número de instalaciones:

```http
GET /api/marketplace/popular
```

Límite 20. Útil cuando trending es volátil pero quieres "lo que la comunidad realmente ha adoptado".

## Cómo evaluar un plugin

Antes de instalar, abre la página de detalle del plugin (`/marketplace/{slug}`) y revisa cinco señales:

### Descargas

`installations.count()` es el indicador más crudo. Plugins con >1k instalaciones se consideran estables por la mayoría de equipos. Los plugins nuevos pueden ser excelentes pero requieren que leas el código antes de adoptar en producción.

### Reviews + ratings

La relación `arqel_plugin_reviews` almacena estrellas (1-5), comentario y dos contadores: `helpful_count` y `unhelpful_count`. Opciones de orden:

```http
GET /api/marketplace/plugins/{slug}/reviews?sort=helpful
GET /api/marketplace/plugins/{slug}/reviews?sort=recent
GET /api/marketplace/plugins/{slug}/reviews?sort=rating
```

`helpful` (por defecto) ordena por `helpful_count` desc — las reviews que otros usuarios encontraron útiles flotan arriba. Las reviews `pending` (recién creadas) no aparecen en el listado público hasta que pasan la cola de moderación.

El flag `verified_purchaser` indica si el reviewer compró el plugin (solo plugins premium). En plugins gratuitos esta columna siempre es `false`.

### Badges de seguridad

Cada plugin mantiene un historial de scans en `arqel_plugin_security_scans`, ejecutado por `SecurityScanner`. El badge mostrado en la página refleja el último scan:

| Badge | Significado |
|---|---|
| 🟢 **Passed** | Sin hallazgos o solo warnings `low` |
| 🟡 **Flagged** | Hallazgos `high` o `medium` — lee detalles antes de instalar |
| 🔴 **Failed** | Hallazgo `critical` — el plugin fue auto-delisted (`status=archived`) |
| ⏳ **Pending/Running** | Scan en progreso, vuelve más tarde |

Cubre lookup de vulnerabilidades, license check (allow-list `MIT`, `Apache-2.0`, `BSD-2-Clause`, `BSD-3-Clause`) y (eventualmente) análisis estático para patrones sospechosos. Detalles en [Buenas prácticas de seguridad](./security-best-practices.md).

### Constraint de compatibilidad

Cada plugin declara en su `composer.json`:

```json
{
  "extra": {
    "arqel": {
      "plugin-type": "field-pack",
      "compat": {
        "arqel": "^1.0"
      }
    }
  }
}
```

El constraint sigue semver. Antes de instalar, revisa que `compat.arqel` cubra la versión que ejecutas en producción. El `PluginConventionValidator` (ejecutado en el momento de submission) ya garantiza que el constraint es semver válido — pero hacer match contra tu versión es responsabilidad tuya.

### Actividad del maintainer

La página de detalle lista cada release (relación `arqel_plugin_versions`). Plugins sin release en los últimos 12 meses deben tratarse como _en riesgo_, especialmente si el framework Arqel tuvo bumps mayores en ese periodo.

## Instalación

La instalación es un wrapper sobre Composer + npm que respeta los dos lenguajes del framework.

### CLI: `arqel install`

(Comando entregado en MKTPLC-005, planeado en el `Console` del paquete `arqel-dev/marketplace`.)

```bash
php artisan arqel:install acme/stripe-card
```

Por detrás:

1. Resuelve el slug `acme/stripe-card` contra el marketplace vía `GET /plugins/{slug}` para obtener `composer_package` y `npm_package`.
2. Ejecuta `composer require <composer_package>:<latest_version>`.
3. Si existe un `npm_package`, ejecuta `pnpm add -D <npm_package>` en el workspace `apps/admin` (o el path configurado).
4. Registra el service provider vía `php artisan vendor:publish --tag=plugin-providers` si es necesario.
5. Persiste la instalación en `arqel_plugin_installations` con `anonymized_user_hash` (no envía datos crudos del usuario).

### Composer directo

Para plugins gratuitos siempre puedes saltarte la CLI:

```bash
composer require acme/stripe-card
pnpm add -D @acme/arqel-stripe-fields
```

Ten en cuenta que por esta vía **no** cuentas en las estadísticas de instalación y por tanto no influyes en el trending score del plugin. Si quieres apoyar a autores que te gustan, prefiere `arqel:install`.

### Plugins premium

Los plugins premium requieren compra + license key:

```http
POST /api/marketplace/plugins/{slug}/purchase           # inicia
POST /api/marketplace/plugins/{slug}/purchase/confirm   # tras pago
GET  /api/marketplace/plugins/{slug}/download           # con licencia válida
```

Detalles en [Pagos y licencias](./payments-and-licensing.md). `arqel:install` ejecuta ese flujo automáticamente cuando detecta `price_cents > 0`, redirigiendo al usuario al checkout vía `MockPaymentGateway` (por defecto) o `StripeConnectGateway` (futuro).

## Verificar lo que está instalado

El comando `arqel:plugin:list` (entregado en MKTPLC-003) lee metadata vía `Composer\InstalledVersions::getInstalledPackagesByType('arqel-plugin')` e imprime una tabla:

```bash
php artisan arqel:plugin:list

+-------------------------+---------+-------------+--------------+-----------+
| Name                    | Version | Plugin Type | Category     | Status    |
+-------------------------+---------+-------------+--------------+-----------+
| acme/stripe-card        | 1.2.0   | field-pack  | integrations | installed |
| beta/markdown-editor    | 0.4.1   | field-pack  | fields       | installed |
| gamma/slack-notify      | 2.0.3   | integration | integrations | installed |
+-------------------------+---------+-------------+--------------+-----------+
```

Añade `--validate` para ejecutar el `PluginConventionValidator` contra cada instalación:

```bash
php artisan arqel:plugin:list --validate
```

Salida detallada por plugin con resultados de checks (`composer_type`, `plugin_type_enum`, `compat_semver`, `category_present`, `installation_instructions`, `keywords_present`). Útil cuando sospechas que un plugin tiene convenciones divergentes después de un upgrade.

## Próximos pasos

- ¿El plugin parece interesante pero quieres leer el código primero? Cada plugin tiene un `github_url` en su página de detalle.
- ¿Encontraste un bug? Usa el enlace "Report issue" que apunta directo a los issues del repositorio del plugin.
- ¿Quieres reviews en tu propio plugin? Mira [Publicar plugins](./publishing.md).
