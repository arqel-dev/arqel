# Auto-scaling Arqel panels on Laravel Cloud

Admin panels have a **very different** load profile from public-facing
applications: traffic concentrated during business hours, export spikes at
month-end, and long-running AI jobs. This guide covers how to size each
component without waste.

## Scaling components

A standard Arqel app has **four processes** that scale independently:

| Process | Role | Scale metric |
| ------- | ---- | ------------ |
| **Web (PHP-FPM/Octane)** | Inertia routes, table fetches, form submits | RPS + p95 latency |
| **Queue worker** | Bulk Actions, Exports, AI jobs, broadcasts | Queue depth + job duration |
| **Reverb** | WebSocket fan-out (Inertia event broadcasting) | Concurrent connections |
| **Scheduler** | `php artisan schedule:run` | (does not scale — fixed at 1×) |

## Web instances

Simple rule: **1 web instance handles ~50 concurrent admins** on a typical
Arqel (assuming Octane on, Postgres with `pgbouncer`, and Redis for cache).
Without Octane, count on ~20.

| Size | Avg RPS | Web instances | Instance type |
| ---- | ------- | ------------- | ------------- |
| Small (≤10 users) | ≤5 | 1× | `nano` (256 MB / 0.25 vCPU) |
| Medium (10-50) | 5-30 | 2× | `small` (512 MB / 0.5 vCPU) |
| Large (50-200) | 30-100 | 4-6× | `medium` (1 GB / 1 vCPU) |
| XL (200+) | 100+ | 8+ × auto | `large` (2 GB / 2 vCPU) |

**Recommended auto-scale rule** (configure in **Settings → Scaling**):

```yaml
web:
  min: 2
  max: 10
  scale_up_when:
    cpu_average: '> 70% por 2min'
    OR
    p95_latency: '> 800ms por 1min'
  scale_down_when:
    cpu_average: '< 25% por 10min'
```

> Classic admin-panel spike: Monday 09:00 (everyone opens the app).
> Pre-warm to 4× at that time if you know the pattern (`scheduled scaling`).

## Queue workers

Bulk Actions and Exports are the **most common bottleneck** in admin panels. A
worker processes around 10 jobs/s for simple CRUD, but only 0.1 jobs/s for
50k-row XLSX exports.

| Case | Workers | Dedicated queues |
| ---- | ------- | ---------------- |
| Small (no AI, rare exports) | 1× | `default` |
| Medium (occasional AI, daily exports) | 2× | `default`, `exports` |
| Large (heavy AI, hourly exports) | 4× + 2× exports | `default`, `exports`, `ai` |
| XL | 8+ × default + 4× exports + 4× ai | dedicated |

**`cloud.yaml` configuration:**

```yaml
services:
  worker:
    command: php artisan queue:work redis --queue=default,exports --tries=3 --max-time=3600
    instances:
      min: 1
      max: 4
  ai_worker:
    command: php artisan queue:work redis --queue=ai --tries=2 --timeout=600
    instances:
      min: 1
      max: 2
```

Use **separate queues** so a large Export does not block fast mutations like
"Approve order".

### Concurrency on AI jobs

`@arqel-dev/ai` jobs (LLM calls via `ClaudeProvider` or `OpenAIProvider`)
have high latency (2-30s) and per-call cost. Throttle with Laravel's
`RateLimited` middleware:

```php
public function middleware(): array
{
    return [new RateLimited('ai-tier')];
}
```

And configure in `AppServiceProvider`:

```php
RateLimiter::for('ai-tier', fn () => Limit::perMinute(60));
```

## Reverb (WebSocket)

Reverb is **stateful** — each connection sticks to one instance. To scale
horizontally, use **Redis pub/sub** (already enabled in the `cloud:export`
template).

| Concurrent connections | Reverb instances | Memory/instance |
| ---------------------- | ---------------- | --------------- |
| ≤500 | 1× | 512 MB |
| 500-2,000 | 2× | 1 GB |
| 2,000-10,000 | 4× | 2 GB |
| 10,000+ | 8+ × with sticky session | 4 GB |

**Important:** Laravel Cloud's load balancer already handles sticky sessions
via the `_reverb_sid` cookie. Without it, connections drop on rescale.

```yaml
services:
  reverb:
    command: php artisan reverb:start --host=0.0.0.0 --port=8080
    instances:
      min: 1
      max: 4
    sticky_session: true
```

## Database connection pool

Laravel Cloud's Postgres ships with `pgbouncer` in front. Limits:

| Tier | Max connections | Recommended for web/worker |
| ---- | --------------- | -------------------------- |
| Hobby | 25 | 2× web + 1× worker |
| Starter | 100 | 4× web + 2× worker |
| Production | 400 | 10× web + 8× worker |
| Scale | 1000+ | no practical limit |

**Each Octane worker opens a persistent connection.** If you have 4 web
instances × 4 Octane workers = 16 connections from web alone. Add queue
workers (typically 1 per process) and size the Postgres tier accordingly.

> Symptom of an exhausted pool: `SQLSTATE[08006] FATAL: sorry, too many clients
> already`. Immediate fix: bump the Postgres tier. Long-term fix: set
> `database.connections.pgsql.options.persistent = false` on workers (but
> latency suffers).

## Recommendations by application size

### Small — startup or internal tool (≤10 users)

```yaml
web:    { min: 1, max: 1, type: nano }
worker: { min: 1, max: 1, type: nano }
reverb: { min: 1, max: 1, type: nano }
postgres: hobby
redis: hobby
```

Approximate cost: **$15-25/month** (no AI tokens).

### Medium — small/medium business (10-50 users)

```yaml
web:    { min: 2, max: 4, type: small }
worker: { min: 2, max: 4, type: small }
reverb: { min: 1, max: 2, type: small }
postgres: starter
redis: starter
```

Approximate cost: **$80-150/month**.

### Large — SaaS product (50-200 users)

```yaml
web:        { min: 4, max: 8, type: medium }
worker:     { min: 4, max: 8, type: medium }
ai_worker:  { min: 2, max: 4, type: medium }
reverb:     { min: 2, max: 4, type: medium, sticky_session: true }
postgres: production
redis: production
```

Approximate cost: **$400-700/month**.

## Monitoring

Metrics worth alerting on in Laravel Cloud:

- **Web p95 > 1s for 5min** → bump `web.min` or optimize the query.
- **Queue depth > 1000 for 2min** → bump `worker.max`.
- **Reverb dropped connections > 5%** → missing sticky session or slow Redis.
- **Postgres CPU > 80% for 10min** → tier upgrade or missing index.

Integrate with Sentry (recommended) or native Cloud Logs.

## Next steps

- Estimate exact costs for your scenario → [cost-estimation.md](./cost-estimation.md).
- Compare with other platforms → [comparison-other-hosts.md](./comparison-other-hosts.md).
