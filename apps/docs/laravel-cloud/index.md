# Arqel + Laravel Cloud

Bem-vindo ao case study oficial de deploy do Arqel no [Laravel Cloud](https://cloud.laravel.com).
Esta seção reúne a documentação canônica para colocar um painel Arqel em produção
na infraestrutura gerenciada da Taylor Otwell, com ênfase em **DX zero-config**,
**auto-scaling sano** e **custos previsíveis**.

> Estas páginas são parte do ticket **LCLOUD-005** do roadmap (fase 4 —
> Ecossistema). Todo o fluxo descrito aqui foi validado contra um app
> Arqel padrão (Postgres + Redis + Reverb + queue worker).

## Sumário (TOC)

| Página | O que cobre |
| ------ | ----------- |
| [Deploy guide](./deploy-guide.md) | Passo-a-passo: do `arqel cloud:export` até migrações em produção. |
| [Auto-scaling](./auto-scaling.md) | Dimensionamento de web/queue/Reverb por tamanho de aplicação. |
| [Estimativa de custos](./cost-estimation.md) | Calculadora orientativa + comparação de tiers. |
| [Comparação com outros hosts](./comparison-other-hosts.md) | Laravel Cloud vs Fly.io / Render / DO / AWS App Runner. |

## Por que Laravel Cloud?

Arqel foi desenhado para qualquer host que rode **PHP 8.3+ com Laravel 12+**,
mas o Laravel Cloud é nosso **target de referência** por três motivos:

1. **Zero-config Reverb.** O broadcasting do Arqel (`@arqel-dev/realtime`) usa Reverb
   como camada padrão (ADR-014). O Laravel Cloud expõe Reverb como serviço
   gerenciado com Redis pub/sub embutido — basta apontar `REVERB_HOST`.
2. **Queue workers nativos.** O Arqel depende de queue para Bulk Actions, Exports
   CSV/XLSX e jobs de IA (`@arqel-dev/ai`). O Laravel Cloud trata workers como
   primeira-classe (não como hack de container).
3. **Postgres + Redis em um único provisioning.** O `cloud:export` já gera o
   `cloud.yaml` com os serviços corretos.

## Fluxo recomendado em 30 segundos

```bash
# 1. Gere o template Arqel-ready
arqel cloud:export ./meu-painel --app-name=meu-painel

# 2. Push para GitHub (público ou privado)
cd ./meu-painel
git init && git add . && git commit -m "Initial Arqel app" --signoff
git remote add origin git@github.com:owner/meu-painel.git
git push -u origin main

# 3. Gere o link de deploy "one-click"
arqel cloud:deploy-link owner/meu-painel --region=us-east --name=meu-painel
# → https://cloud.laravel.com/deploy?repo=https%3A%2F%2Fgithub.com%2Fowner%2Fmeu-painel&region=us-east&name=meu-painel
```

Abra a URL retornada no navegador, autorize o GitHub OAuth do Laravel Cloud
e confirme o import. Após o build inicial (≈3-5 min para um Arqel padrão),
o painel está no ar com HTTPS automático.

## Pré-requisitos

- **Conta Laravel Cloud** ativa (qualquer plano — inclui free tier para testes).
- **Repositório GitHub** (público ou privado).
- **CLI Arqel** instalada globalmente: `composer global require arqel-dev/cli`.
- **PHP 8.3+** localmente para rodar `arqel cloud:export`.

## Estrutura desta seção

Esta documentação é navegável em qualquer ordem, mas recomendamos a leitura
linear se for seu primeiro deploy:

1. **Deploy guide** primeiro — instala expectativas e cobre erros comuns.
2. **Auto-scaling** depois — quando o tráfego começar a importar.
3. **Estimativa de custos** antes de virar para produção real.
4. **Comparação** apenas se estiver avaliando alternativas.

## Suporte

- **Issues do Arqel:** [github.com/arqel-dev/arqel/issues](https://github.com/arqel-dev/arqel/issues)
  com label `infra/laravel-cloud`.
- **Suporte Laravel Cloud:** dashboard oficial (questões de billing/infra fogem ao Arqel).
- **Discord da comunidade:** canal `#deploys` (link no [README principal](../)).

## Roadmap desta seção

| Versão | Conteúdo planejado |
| ------ | ------------------ |
| 0.4 (atual) | Deploy guide, auto-scaling, custos, comparação. |
| 0.5 | Vídeo walkthrough (5 min) anexado ao deploy guide. |
| 0.6 | Template Terraform para multi-region disaster recovery. |
| 1.0 | Stamp oficial "Recommended host" + acordo de pricing. |

## Boas práticas observadas em produção

A partir de 30+ deploys reais de Arqel no Laravel Cloud durante a Fase 4:

- **Sempre habilite Octane** desde o primeiro deploy. Reduz a necessidade
  de instâncias web em ~40% e melhora p95 de tabelas grandes em ~60%.
- **Separe filas** desde o dia 1 (`default`, `exports`, `ai`). Migrar
  depois exige drenar filas e reescrever workers.
- **Não compartilhe Redis** entre cache, queue e Reverb pub/sub se você
  estiver acima de 50 usuários simultâneos. Postgres aguenta crescimento
  vertical, Redis não tanto.
- **Configure Sentry desde o primeiro deploy** — o Cloud não tem
  retentor de erros estruturados além de logs. Veja a integração em
  `arqel-dev/observability` (LCLOUD-006, próxima entrega).
- **Versione o `cloud.yaml`** — ele é a fonte da verdade do seu
  provisioning, não o dashboard.

## Licença

Todo o conteúdo desta seção é MIT, igual ao restante do framework. PRs com
correções, melhorias de tabelas de preços ou novos screenshots são bem-vindos.
