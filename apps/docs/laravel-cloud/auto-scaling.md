# Auto-scaling de painéis Arqel no Laravel Cloud

Painéis admin têm um perfil de carga **muito diferente** de aplicações
voltadas ao público: tráfego concentrado em horário comercial, picos
de exportação no fim de mês e long-running jobs de IA. Este guia cobre
como dimensionar cada componente sem desperdício.

## Componentes que escalam

Um app Arqel padrão tem **quatro processos** que escalam independentemente:

| Processo | O que faz | Métrica de escala |
| -------- | --------- | ----------------- |
| **Web (PHP-FPM/Octane)** | Inertia routes, table fetches, form submits | RPS + p95 latency |
| **Queue worker** | Bulk Actions, Exports, AI jobs, broadcasts | Queue depth + job duration |
| **Reverb** | WebSocket fan-out (Inertia event broadcasting) | Connections concorrentes |
| **Scheduler** | `php artisan schedule:run` | (não escala — fica fixo em 1×) |

## Web instances

A regra simples: **1 web instance aguenta ~50 admins concorrentes** num
Arqel típico (assumindo Octane ligado, Postgres com `pgbouncer` e Redis
para cache). Sem Octane, contar com ~20.

| Tamanho | RPS médio | Web instances | Tipo de instância |
| ------- | --------- | ------------- | ----------------- |
| Small (≤10 usuários) | ≤5 | 1× | `nano` (256 MB / 0.25 vCPU) |
| Medium (10-50) | 5-30 | 2× | `small` (512 MB / 0.5 vCPU) |
| Large (50-200) | 30-100 | 4-6× | `medium` (1 GB / 1 vCPU) |
| XL (200+) | 100+ | 8+ × auto | `large` (2 GB / 2 vCPU) |

**Auto-scale rule recomendado** (configurar em **Settings → Scaling**):

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

> Pico clássico de painel admin: segunda-feira 09h00 (todos abrem o app).
> Pre-warm para 4× nesse horário se você conhece o padrão (`scheduled scaling`).

## Queue workers

Bulk Actions e Exports são o **bottleneck mais comum** em painéis. Um worker
processa em média 10 jobs/s para CRUD simples, mas só 0.1 jobs/s para
Exports XLSX de 50k linhas.

| Caso | Workers | Filas dedicadas |
| ---- | ------- | --------------- |
| Small (sem AI, exports raros) | 1× | `default` |
| Medium (AI ocasional, exports diários) | 2× | `default`, `exports` |
| Large (AI heavy, exports horários) | 4× + 2× exports | `default`, `exports`, `ai` |
| XL | 8+ × default + 4× exports + 4× ai | dedicadas |

**Configuração no `cloud.yaml`:**

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

Use **filas separadas** para que um Export grande não trave mutations
rápidas como "Aprovar pedido".

### Concurrency em IA jobs

Jobs do `@arqel/ai` (LLM calls via `ClaudeProvider` ou `OpenAIProvider`)
têm latência alta (2-30s) e custo por chamada. Limite com Laravel
`RateLimited` middleware:

```php
public function middleware(): array
{
    return [new RateLimited('ai-tier')];
}
```

E configure no `AppServiceProvider`:

```php
RateLimiter::for('ai-tier', fn () => Limit::perMinute(60));
```

## Reverb (WebSocket)

Reverb é **stateful** — cada conexão fica presa a uma instância. Para escalar
horizontalmente, use **Redis pub/sub** (já vem habilitado no template
do `cloud:export`).

| Conexões concorrentes | Reverb instances | Memória/instância |
| --------------------- | ---------------- | ----------------- |
| ≤500 | 1× | 512 MB |
| 500-2.000 | 2× | 1 GB |
| 2.000-10.000 | 4× | 2 GB |
| 10.000+ | 8+ × com sticky session | 4 GB |

**Importante:** o load balancer do Laravel Cloud já trata sticky sessions
com cookie `_reverb_sid`. Sem isso, conexões caem ao reescalar.

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

Postgres do Laravel Cloud já vem com `pgbouncer` à frente. Limites:

| Tier | Max connections | Recomendado para web/worker |
| ---- | --------------- | --------------------------- |
| Hobby | 25 | 2× web + 1× worker |
| Starter | 100 | 4× web + 2× worker |
| Production | 400 | 10× web + 8× worker |
| Scale | 1000+ | sem limite prático |

**Cada Octane worker abre uma conexão persistente.** Se você tem 4 web
instances × 4 workers Octane = 16 conexões só de web. Some queue workers
(geralmente 1 por processo) e dimensione o tier do Postgres.

> Sintoma de pool exausto: `SQLSTATE[08006] FATAL: sorry, too many clients
> already`. Solução imediata: subir tier do Postgres. Solução de longo
> prazo: ajustar `database.connections.pgsql.options.persistent = false`
> em workers (mas piora latência).

## Recomendações por tamanho de aplicação

### Small — startup ou ferramenta interna (≤10 usuários)

```yaml
web:    { min: 1, max: 1, type: nano }
worker: { min: 1, max: 1, type: nano }
reverb: { min: 1, max: 1, type: nano }
postgres: hobby
redis: hobby
```

Custo aproximado: **$15-25/mês** (sem AI tokens).

### Medium — empresa pequena/média (10-50 usuários)

```yaml
web:    { min: 2, max: 4, type: small }
worker: { min: 2, max: 4, type: small }
reverb: { min: 1, max: 2, type: small }
postgres: starter
redis: starter
```

Custo aproximado: **$80-150/mês**.

### Large — produto SaaS (50-200 usuários)

```yaml
web:        { min: 4, max: 8, type: medium }
worker:     { min: 4, max: 8, type: medium }
ai_worker:  { min: 2, max: 4, type: medium }
reverb:     { min: 2, max: 4, type: medium, sticky_session: true }
postgres: production
redis: production
```

Custo aproximado: **$400-700/mês**.

## Monitoramento

Métricas que valem alarme no Laravel Cloud:

- **Web p95 > 1s por 5min** → aumentar `web.min` ou otimizar query.
- **Queue depth > 1000 por 2min** → aumentar `worker.max`.
- **Reverb dropped connections > 5%** → falta sticky session ou Redis lento.
- **Postgres CPU > 80% por 10min** → upgrade tier ou índice faltando.

Integre com Sentry (recomendado) ou Cloud Logs nativos.

## Próximos passos

- Estime os custos exatos do seu cenário → [cost-estimation.md](./cost-estimation.md).
- Compare com outras plataformas → [comparison-other-hosts.md](./comparison-other-hosts.md).
