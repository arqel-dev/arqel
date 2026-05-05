# Finding plugins

> How to discover, evaluate, and install plugins from the Arqel Marketplace.

This page covers the **consumer** side of the marketplace — developers who want to extend their Arqel admin with community-maintained fields, widgets, integrations, or themes.

## Discovery paths

The marketplace exposes six public paths via REST (and the UI at `arqel.dev/marketplace` consumes all of them):

### 1. Search by category

Default root categories: `fields`, `widgets`, `themes`, `integrations`, `utilities`. Each can have sub-categories (self-referencing `parent_id` relation in `arqel_plugin_categories`).

```http
GET /api/marketplace/categories?root=1
GET /api/marketplace/categories/widgets/plugins
```

The first returns all root categories with `children` eager-loaded. The second returns published plugins of a specific category, paginated.

### 2. Text search

Free-form search covers `name` + `description`:

```http
GET /api/marketplace/plugins?search=calendar&type=widget&per_page=30
```

Supported parameters: `type` (enum), `search` (string), `per_page` (clamped 1-100), `page`. Only plugins with `status=published` appear — drafts, pending, and archived are opaque (explicit 404).

### 3. Trending

Top plugins over the last 7 days. The score is computed by `TrendingScoreCalculator`:

```
score = installations_last_7d * 1.0 + recent_positive_reviews_30d * 5.0
```

Positive reviews (≥4 stars) weigh 5x more than raw installations — social signal beats anonymous download spikes.

```http
GET /api/marketplace/trending
```

Returns top 20 published plugins ordered by `trending_score` desc. Recompute is daily (the host app must schedule `Schedule::command('arqel:marketplace:trending')->daily()`).

### 4. Featured (editor's picks)

Manual curation by the Arqel team. Toggled via `POST /admin/plugins/{slug}/feature` (Gate `marketplace.feature`).

```http
GET /api/marketplace/featured
```

Ordered by `featured_at` desc — most recent picks first.

### 5. New this week

Plugins published in the last N days (default 7, clamped 1-90):

```http
GET /api/marketplace/new?days=14
```

Useful for watchlists and weekly newsletters.

### 6. Most popular (all-time)

Absolute ranking by installation count:

```http
GET /api/marketplace/popular
```

Limit 20. Useful when trending is volatile but you want "what the community has actually adopted".

## How to evaluate a plugin

Before installing, open the plugin detail page (`/marketplace/{slug}`) and check five signals:

### Downloads

`installations.count()` is the rawest gauge. Plugins with >1k installations are considered stable by most teams. New plugins can be excellent but require you to read the code before adopting in production.

### Reviews + ratings

The `arqel_plugin_reviews` relation stores stars (1-5), comment, and two counters: `helpful_count` and `unhelpful_count`. Sort options:

```http
GET /api/marketplace/plugins/{slug}/reviews?sort=helpful
GET /api/marketplace/plugins/{slug}/reviews?sort=recent
GET /api/marketplace/plugins/{slug}/reviews?sort=rating
```

`helpful` (default) sorts by `helpful_count` desc — reviews other users found useful float to the top. `pending` reviews (newly created) do not appear in the public listing until they pass the moderation queue.

The `verified_purchaser` flag indicates whether the reviewer bought the plugin (premium plugins only). On free plugins this column is always `false`.

### Security badges

Every plugin keeps a scan history in `arqel_plugin_security_scans`, run by `SecurityScanner`. The badge displayed on the page reflects the latest scan:

| Badge | Meaning |
|---|---|
| 🟢 **Passed** | No findings or only `low` warnings |
| 🟡 **Flagged** | `high` or `medium` findings — read details before installing |
| 🔴 **Failed** | `critical` finding — plugin was auto-delisted (`status=archived`) |
| ⏳ **Pending/Running** | Scan in progress, check back later |

Covers vulnerability lookup, license check (allow-list `MIT`, `Apache-2.0`, `BSD-2-Clause`, `BSD-3-Clause`) and (eventually) static analysis for suspicious patterns. Details in [Security best practices](./security-best-practices.md).

### Compatibility constraint

Every plugin declares in its `composer.json`:

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

The constraint follows semver. Before installing, check that `compat.arqel` covers the version you run in production. The `PluginConventionValidator` (run at submission time) already ensures the constraint is valid semver — but matching it against your version is your responsibility.

### Maintainer activity

The detail page lists every release (`arqel_plugin_versions` relation). Plugins with no release in the last 12 months should be treated as _at-risk_, especially if the Arqel framework had major bumps in that period.

## Installation

Installation is a wrapper over Composer + npm that respects the framework's two languages.

### CLI: `arqel install`

(Command delivered in MKTPLC-005, planned in the `Console` of the `arqel-dev/marketplace` package.)

```bash
php artisan arqel:install acme/stripe-card
```

Behind the scenes:

1. Resolves the slug `acme/stripe-card` against the marketplace via `GET /plugins/{slug}` to obtain `composer_package` and `npm_package`.
2. Runs `composer require <composer_package>:<latest_version>`.
3. If an `npm_package` exists, runs `pnpm add -D <npm_package>` in the `apps/admin` workspace (or the configured path).
4. Registers the service provider via `php artisan vendor:publish --tag=plugin-providers` if necessary.
5. Persists the installation in `arqel_plugin_installations` with `anonymized_user_hash` (does not send raw user data).

### Direct Composer

For free plugins you can always bypass the CLI:

```bash
composer require acme/stripe-card
pnpm add -D @acme/arqel-stripe-fields
```

Note that on this path you are **not** counted in the installation statistics and therefore do not influence the plugin's trending score. If you want to support authors you like, prefer `arqel:install`.

### Premium plugins

Premium plugins require purchase + license key:

```http
POST /api/marketplace/plugins/{slug}/purchase           # initiate
POST /api/marketplace/plugins/{slug}/purchase/confirm   # after payment
GET  /api/marketplace/plugins/{slug}/download           # with valid license
```

Details in [Payments & licensing](./payments-and-licensing.md). `arqel:install` runs that flow automatically when it detects `price_cents > 0`, redirecting the user to checkout via `MockPaymentGateway` (default) or `StripeConnectGateway` (future).

## Verifying what is installed

The `arqel:plugin:list` command (delivered in MKTPLC-003) reads metadata via `Composer\InstalledVersions::getInstalledPackagesByType('arqel-plugin')` and prints a table:

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

Add `--validate` to run the `PluginConventionValidator` against each install:

```bash
php artisan arqel:plugin:list --validate
```

Detailed per-plugin output with check results (`composer_type`, `plugin_type_enum`, `compat_semver`, `category_present`, `installation_instructions`, `keywords_present`). Useful when you suspect a plugin has divergent conventions after an upgrade.

## Next steps

- Plugin looks interesting but you want to read the source first? Every plugin has a `github_url` on its detail page.
- Found a bug? Use the "Report issue" link that points straight to the plugin repository's issues.
- Want reviews on your own plugin? See [Publishing plugins](./publishing.md).
