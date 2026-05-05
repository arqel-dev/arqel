# Auto-scaling de paneles Arqel en Laravel Cloud

Los paneles admin tienen un perfil de carga **muy distinto** al de las
aplicaciones públicas: tráfico concentrado en horario laboral, picos de
exports a fin de mes y jobs IA de larga duración. Esta guía cubre cómo dimensionar
cada componente sin desperdicio.

## Componentes que escalan

Una app Arqel estándar tiene **cuatro procesos** que escalan independientemente:

| Proceso | Rol | Métrica de scale |
| ------- | ---- | ------------ |
| **Web (PHP-FPM/Octane)** | Rutas Inertia, fetches de tabla, form submits | RPS + latencia p95 |
| **Queue worker** | Bulk Actions, Exports, jobs IA, broadcasts | Profundidad de queue + duración del job |
| **Reverb** | WebSocket fan-out (broadcasting de eventos Inertia) | Conexiones concurrentes |
| **Scheduler** | `php artisan schedule:run` | (no escala — fijo en 1×) |

## Instancias web

Regla simple: **1 instancia web atiende ~50 admins concurrentes** en una
Arqel típica (asumiendo Octane on, Postgres con `pgbouncer` y Redis para cache).
Sin Octane, cuenta con ~20.

| Tamaño | RPS promedio | Instancias web | Tipo de instancia |
| ---- | ------- | ------------- | ------------- |
| Small (≤10 usuarios) | ≤5 | 1× | `nano` (256 MB / 0.25 vCPU) |
| Medium (10-50) | 5-30 | 2× | `small` (512 MB / 0.5 vCPU) |
| Large (50-200) | 30-100 | 4-6× | `medium` (1 GB / 1 vCPU) |
| XL (200+) | 100+ | 8+ × auto | `large` (2 GB / 2 vCPU) |

**Regla de auto-scale recomendada** (configura en **Settings → Scaling**):

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

> Pico clásico de admin-panel: lunes 09:00 (todos abren la app).
> Pre-warm a 4× a esa hora si conoces el patrón (`scheduled scaling`).

## Queue workers

Bulk Actions y Exports son el **cuello de botella más común** en paneles admin. Un
worker procesa alrededor de 10 jobs/s en CRUD simple, pero solo 0.1 jobs/s en
exports XLSX de 50k filas.

| Caso | Workers | Queues dedicadas |
| ---- | ------- | ---------------- |
| Small (sin IA, exports raros) | 1× | `default` |
| Medium (IA ocasional, exports diarios) | 2× | `default`, `exports` |
| Large (IA pesada, exports horarios) | 4× + 2× exports | `default`, `exports`, `ai` |
| XL | 8+ × default + 4× exports + 4× ai | dedicadas |

**Configuración `cloud.yaml`:**

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

Usa **queues separadas** para que un Export grande no bloquee mutaciones rápidas como
"Approve order".

### Concurrencia en jobs IA

Los jobs de `@arqel-dev/ai` (llamadas LLM vía `ClaudeProvider` u `OpenAIProvider`)
tienen alta latencia (2-30s) y costo por llamada. Throttlea con el middleware
`RateLimited` de Laravel:

```php
public function middleware(): array
{
    return [new RateLimited('ai-tier')];
}
```

Y configura en `AppServiceProvider`:

```php
RateLimiter::for('ai-tier', fn () => Limit::perMinute(60));
```

## Reverb (WebSocket)

Reverb es **stateful** — cada conexión queda pegada a una instancia. Para escalar
horizontalmente, usa **Redis pub/sub** (ya habilitado en el template
`cloud:export`).

| Conexiones concurrentes | Instancias Reverb | Memoria/instancia |
| ---------------------- | ---------------- | --------------- |
| ≤500 | 1× | 512 MB |
| 500-2,000 | 2× | 1 GB |
| 2,000-10,000 | 4× | 2 GB |
| 10,000+ | 8+ × con sticky session | 4 GB |

**Importante:** el load balancer de Laravel Cloud ya maneja sticky sessions
vía la cookie `_reverb_sid`. Sin ello, las conexiones se caen al reescalar.

```yaml
services:
  reverb:
    command: php artisan reverb:start --host=0.0.0.0 --port=8080
    instances:
      min: 1
      max: 4
    sticky_session: true
```

## Pool de conexiones a la base de datos

El Postgres de Laravel Cloud viene con `pgbouncer` por delante. Límites:

| Tier | Máx. conexiones | Recomendado para web/worker |
| ---- | --------------- | -------------------------- |
| Hobby | 25 | 2× web + 1× worker |
| Starter | 100 | 4× web + 2× worker |
| Production | 400 | 10× web + 8× worker |
| Scale | 1000+ | sin límite práctico |

**Cada worker Octane abre una conexión persistente.** Si tienes 4 instancias web
× 4 workers Octane = 16 conexiones solo del web. Suma queue workers
(típicamente 1 por proceso) y dimensiona el tier de Postgres en consecuencia.

> Síntoma de pool agotado: `SQLSTATE[08006] FATAL: sorry, too many clients
> already`. Fix inmediato: subir tier de Postgres. Fix a largo plazo: poner
> `database.connections.pgsql.options.persistent = false` en los workers (pero
> sufre la latencia).

## Recomendaciones por tamaño de aplicación

### Small — startup o herramienta interna (≤10 usuarios)

```yaml
web:    { min: 1, max: 1, type: nano }
worker: { min: 1, max: 1, type: nano }
reverb: { min: 1, max: 1, type: nano }
postgres: hobby
redis: hobby
```

Costo aproximado: **$15-25/mes** (sin tokens IA).

### Medium — pequeña/mediana empresa (10-50 usuarios)

```yaml
web:    { min: 2, max: 4, type: small }
worker: { min: 2, max: 4, type: small }
reverb: { min: 1, max: 2, type: small }
postgres: starter
redis: starter
```

Costo aproximado: **$80-150/mes**.

### Large — producto SaaS (50-200 usuarios)

```yaml
web:        { min: 4, max: 8, type: medium }
worker:     { min: 4, max: 8, type: medium }
ai_worker:  { min: 2, max: 4, type: medium }
reverb:     { min: 2, max: 4, type: medium, sticky_session: true }
postgres: production
redis: production
```

Costo aproximado: **$400-700/mes**.

## Monitoring

Métricas que vale la pena alertar en Laravel Cloud:

- **Web p95 > 1s durante 5min** → sube `web.min` u optimiza la query.
- **Profundidad de queue > 1000 durante 2min** → sube `worker.max`.
- **Conexiones Reverb caídas > 5%** → falta sticky session o Redis lento.
- **Postgres CPU > 80% durante 10min** → upgrade de tier o índice faltante.

Integra con Sentry (recomendado) o Cloud Logs nativos.

## Próximos pasos

- Estima costos exactos para tu escenario → [cost-estimation.md](./cost-estimation.md).
- Compara con otras plataformas → [comparison-other-hosts.md](./comparison-other-hosts.md).
