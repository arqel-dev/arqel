# Estimación de costos — Arqel en Laravel Cloud

> **Disclaimer:** los precios a continuación son **indicativos** y reflejan el
> pricing público de Laravel Cloud a **abril de 2026**. Confirma siempre los valores
> actualizados en [cloud.laravel.com/pricing](https://cloud.laravel.com/pricing) antes de
> comprometerte a un presupuesto. Esta página forma parte del case study LCLOUD-005.

## Estructura de costos

Una app Arqel en producción paga por **cinco categorías**:

1. **Compute** (web + workers + Reverb) — por hora-instancia.
2. **Base de datos** (Postgres gestionado) — por tier mensual.
3. **Cache/Queue/PubSub** (Redis gestionado) — por tier mensual.
4. **Egress** (ancho de banda saliente) — por GB.
5. **Add-ons específicos de Arqel:**
   - Tokens IA (`@arqel-dev/ai` con OpenAI/Anthropic).
   - Conexiones Reverb (incluidas en compute, pero afectan el sizing).
   - Storage S3-compatible para uploads de `FileField`.

## Tabla de referencia Laravel Cloud (abril 2026)

| Recurso | Tier | Precio aprox./mes |
| -------- | ---- | ------------------- |
| Instancia web `nano` (256 MB) | — | $5 |
| Instancia web `small` (512 MB) | — | $12 |
| Instancia web `medium` (1 GB) | — | $24 |
| Instancia web `large` (2 GB) | — | $48 |
| Postgres Hobby | 256 MB / 1 GB storage | $0 (free tier) |
| Postgres Starter | 1 GB / 10 GB | $20 |
| Postgres Production | 4 GB / 50 GB | $80 |
| Postgres Scale | 16 GB / 200 GB | $300 |
| Redis Hobby | 25 MB | $0 |
| Redis Starter | 256 MB | $15 |
| Redis Production | 1 GB | $50 |
| Egress | — | $0.10/GB (después de los primeros 100 GB gratis) |

Add-ons fuera de Cloud:

| Add-on | Costo aprox. |
| ------ | ------------ |
| OpenAI GPT-4o (input) | $2.50 / 1M tokens |
| OpenAI GPT-4o (output) | $10 / 1M tokens |
| Anthropic Claude Sonnet 4.7 (input) | $3 / 1M tokens |
| Anthropic Claude Sonnet 4.7 (output) | $15 / 1M tokens |
| Storage S3-compatible (B2/R2) | ~$5 / 100 GB |

## Calculadora — tres escenarios

### Small — herramienta interna (≤10 usuarios)

| Ítem | Cantidad | Mensual |
| ---- | -------- | ------- |
| Web `nano` × 1 | 24×7 | $5 |
| Worker `nano` × 1 | 24×7 | $5 |
| Reverb `nano` × 1 | 24×7 | $5 |
| Postgres Hobby | — | $0 |
| Redis Hobby | — | $0 |
| Egress (~5 GB) | — | $0 |
| **Total Cloud** | | **$15** |
| Tokens IA (raros, ~100k tokens) | | $1 |
| **Total** | | **~$16/mes** |

### Medium — SaaS B2B (10-50 usuarios, IA ocasional)

| Ítem | Cantidad | Mensual |
| ---- | -------- | ------- |
| Web `small` × 2 | auto 1-3 | $24-36 |
| Worker `small` × 2 | auto 1-3 | $24-36 |
| Reverb `small` × 1 | 24×7 | $12 |
| Postgres Starter | — | $20 |
| Redis Starter | — | $15 |
| Egress (~50 GB) | — | $0 |
| **Total Cloud** | | **~$95-120** |
| Tokens IA (~5M input + 1M output) | | $22 |
| **Total** | | **~$120-145/mes** |

### Large — producto público (50-200 usuarios, IA pesada)

| Ítem | Cantidad | Mensual |
| ---- | -------- | ------- |
| Web `medium` × 4 | auto 4-8 | $96-192 |
| Worker `medium` × 4 | auto 4-8 | $96-192 |
| AI worker `medium` × 2 | auto 2-4 | $48-96 |
| Reverb `medium` × 2 | sticky | $48 |
| Postgres Production | — | $80 |
| Redis Production | — | $50 |
| Egress (~500 GB) | — | $40 |
| **Total Cloud** | | **~$460-700** |
| Tokens IA (~50M input + 15M output) | | $350 |
| Storage S3 (200 GB) | — | $10 |
| **Total** | | **~$820-1,060/mes** |

## Optimizaciones de costo específicas de Arqel

1. **Cachea queries de tabla.** `@arqel-dev/table` usa `cache()->remember()` por
   defecto en queries de `getRecords()`. Sube el TTL a 60s en tablas de baja
   volatilidad — recorta 70% de las queries Postgres.
2. **Prompt caching IA.** Usa el prompt caching de Anthropic (5 min TTL) para reducir
   el 90% del costo de input-token en conversaciones largas. Ya por defecto en
   `ClaudeProvider`.
3. **Exports en una queue de baja prioridad.** Corre la queue `exports` en workers
   `nano` separados — barato y no compite con el web por CPU.
4. **Octane.** Habilita en `cloud.yaml` (`octane: true`). Reduce la necesidad de
   instancias web ~40%.
5. **Pooling de conexiones Postgres con pgbouncer.** Ya por defecto — no
   lo deshabilites.

## Comparación rápida con competidores

Para el escenario **Medium** anterior (~$120/mes en Laravel Cloud):

| Plataforma | Costo equivalente | Esfuerzo de setup |
| -------- | --------------- | ------------ |
| **Laravel Cloud** | **$120** | Cero (`cloud:export`) |
| Fly.io | ~$95 | Medio (escribir `fly.toml`) |
| Render | ~$110 | Bajo (Blueprint) |
| DigitalOcean App Platform | ~$80 | Medio (App Spec) |
| AWS App Runner + RDS | ~$140 | Alto (CDK o Terraform) |
| Heroku | ~$200 | Bajo |

> Detalles en [comparison-other-hosts.md](./comparison-other-hosts.md).

## Cuándo no merece la pena

Laravel Cloud es caro **si**:

- Tienes ≤3 usuarios y todo cabe en un droplet DigitalOcean de $6.
- Tu app es **stateless y write-heavy** — Lambda + RDS sale más barato.
- Necesitas regiones fuera del set soportado (especialmente África/Oceanía
  más allá de `ap-southeast`).

En esos casos, considera [Fly.io](https://fly.io) o self-host en
DigitalOcean con Forge.

## Próximos pasos

- Revisa el sizing por componente → [auto-scaling.md](./auto-scaling.md).
- Comparación detallada con otros hosts → [comparison-other-hosts.md](./comparison-other-hosts.md).
