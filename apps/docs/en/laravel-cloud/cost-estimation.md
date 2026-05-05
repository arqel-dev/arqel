# Cost estimation — Arqel on Laravel Cloud

> **Disclaimer:** the prices below are **indicative** and reflect the public
> Laravel Cloud pricing as of **April 2026**. Always confirm up-to-date values
> at [cloud.laravel.com/pricing](https://cloud.laravel.com/pricing) before
> committing to a budget. This page is part of the LCLOUD-005 case study.

## Cost structure

An Arqel app in production pays for **five categories**:

1. **Compute** (web + workers + Reverb) — per instance hour.
2. **Database** (managed Postgres) — per monthly tier.
3. **Cache/Queue/PubSub** (managed Redis) — per monthly tier.
4. **Egress** (outbound bandwidth) — per GB.
5. **Arqel-specific add-ons:**
   - AI tokens (`@arqel-dev/ai` with OpenAI/Anthropic).
   - Reverb connections (included in compute, but affects sizing).
   - S3-compatible storage for `FileField` uploads.

## Reference Laravel Cloud table (April 2026)

| Resource | Tier | Approx. price/month |
| -------- | ---- | ------------------- |
| Web instance `nano` (256 MB) | — | $5 |
| Web instance `small` (512 MB) | — | $12 |
| Web instance `medium` (1 GB) | — | $24 |
| Web instance `large` (2 GB) | — | $48 |
| Postgres Hobby | 256 MB / 1 GB storage | $0 (free tier) |
| Postgres Starter | 1 GB / 10 GB | $20 |
| Postgres Production | 4 GB / 50 GB | $80 |
| Postgres Scale | 16 GB / 200 GB | $300 |
| Redis Hobby | 25 MB | $0 |
| Redis Starter | 256 MB | $15 |
| Redis Production | 1 GB | $50 |
| Egress | — | $0.10/GB (after the first 100 GB free) |

Add-ons outside Cloud:

| Add-on | Approx. cost |
| ------ | ------------ |
| OpenAI GPT-4o (input) | $2.50 / 1M tokens |
| OpenAI GPT-4o (output) | $10 / 1M tokens |
| Anthropic Claude Sonnet 4.7 (input) | $3 / 1M tokens |
| Anthropic Claude Sonnet 4.7 (output) | $15 / 1M tokens |
| S3-compatible storage (B2/R2) | ~$5 / 100 GB |

## Calculator — three scenarios

### Small — internal tool (≤10 users)

| Item | Quantity | Monthly |
| ---- | -------- | ------- |
| Web `nano` × 1 | 24×7 | $5 |
| Worker `nano` × 1 | 24×7 | $5 |
| Reverb `nano` × 1 | 24×7 | $5 |
| Postgres Hobby | — | $0 |
| Redis Hobby | — | $0 |
| Egress (~5 GB) | — | $0 |
| **Cloud total** | | **$15** |
| AI tokens (rare, ~100k tokens) | | $1 |
| **Total** | | **~$16/month** |

### Medium — B2B SaaS (10-50 users, occasional AI)

| Item | Quantity | Monthly |
| ---- | -------- | ------- |
| Web `small` × 2 | auto 1-3 | $24-36 |
| Worker `small` × 2 | auto 1-3 | $24-36 |
| Reverb `small` × 1 | 24×7 | $12 |
| Postgres Starter | — | $20 |
| Redis Starter | — | $15 |
| Egress (~50 GB) | — | $0 |
| **Cloud total** | | **~$95-120** |
| AI tokens (~5M input + 1M output) | | $22 |
| **Total** | | **~$120-145/month** |

### Large — public product (50-200 users, heavy AI)

| Item | Quantity | Monthly |
| ---- | -------- | ------- |
| Web `medium` × 4 | auto 4-8 | $96-192 |
| Worker `medium` × 4 | auto 4-8 | $96-192 |
| AI worker `medium` × 2 | auto 2-4 | $48-96 |
| Reverb `medium` × 2 | sticky | $48 |
| Postgres Production | — | $80 |
| Redis Production | — | $50 |
| Egress (~500 GB) | — | $40 |
| **Cloud total** | | **~$460-700** |
| AI tokens (~50M input + 15M output) | | $350 |
| S3 storage (200 GB) | — | $10 |
| **Total** | | **~$820-1,060/month** |

## Arqel-specific cost optimizations

1. **Cache table queries.** `@arqel-dev/table` uses `cache()->remember()` by
   default for `getRecords()` queries. Bump the TTL to 60s on low-volatility
   tables — drops 70% of Postgres queries.
2. **AI prompt caching.** Use Anthropic prompt caching (5 min TTL) to reduce
   90% of input-token cost on long conversations. Already default in
   `ClaudeProvider`.
3. **Exports on a low-priority queue.** Run the `exports` queue on separate
   `nano` workers — cheap and does not compete with web for CPU.
4. **Octane.** Enable in `cloud.yaml` (`octane: true`). Reduces web-instance
   need by ~40%.
5. **Postgres connection pooling with pgbouncer.** Already default — do not
   disable.

## Quick comparison with competitors

For the **Medium** scenario above (~$120/month on Laravel Cloud):

| Platform | Equivalent cost | Setup effort |
| -------- | --------------- | ------------ |
| **Laravel Cloud** | **$120** | Zero (`cloud:export`) |
| Fly.io | ~$95 | Medium (write `fly.toml`) |
| Render | ~$110 | Low (Blueprint) |
| DigitalOcean App Platform | ~$80 | Medium (App Spec) |
| AWS App Runner + RDS | ~$140 | High (CDK or Terraform) |
| Heroku | ~$200 | Low |

> Details in [comparison-other-hosts.md](./comparison-other-hosts.md).

## When it is not worth it

Laravel Cloud is expensive **if**:

- You have ≤3 users and everything fits on a $6 DigitalOcean droplet.
- Your app is **stateless and write-heavy** — Lambda + RDS comes out cheaper.
- You need regions outside the supported set (especially Africa/Oceania
  beyond `ap-southeast`).

In those cases, consider [Fly.io](https://fly.io) or self-host on
DigitalOcean with Forge.

## Next steps

- Review per-component sizing → [auto-scaling.md](./auto-scaling.md).
- Detailed comparison with other hosts → [comparison-other-hosts.md](./comparison-other-hosts.md).
