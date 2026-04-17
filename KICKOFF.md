# KICKOFF — Arqel

> Instruções passo-a-passo para inicializar o repositório e entregar ao Claude Code.

## Estrutura deste pacote

```
arqel-kickoff/
├── CLAUDE.md                      # ⭐ Contexto mestre p/ Claude Code
├── AGENTS.md                      # ⭐ Padrão agents.md (outros AI agents)
├── README.md                      # Descrição pública do projeto
├── KICKOFF.md                     # Este arquivo
├── .gitignore
├── .claude/
│   ├── settings.json              # Config Claude Code
│   └── commands/                  # Slash commands custom
│       ├── next-ticket.md
│       ├── review-ticket.md
│       ├── sprint-status.md
│       └── adr-check.md
├── scripts/
│   ├── init.sh                    # Setup ambiente
│   ├── next-ticket.sh             # Utility
│   └── pre-commit-check.sh        # Hook de qualidade
├── docs/
│   └── tickets/
│       └── current.md             # ⭐ Ponteiro para ticket ativo
└── PLANNING/                      # 13 docs do planejamento (22k+ linhas)
    ├── 00-index.md
    ├── 01-spec-tecnica.md
    ├── 02-arquitetura.md
    ├── 03-adrs.md
    ├── 04-repo-structure.md
    ├── 05-api-php.md
    ├── 06-api-react.md
    ├── 07-roadmap-fases.md
    ├── 08-fase-1-mvp.md          # ⭐ 123 tickets Fase 1
    ├── 09-fase-2-essenciais.md
    ├── 10-fase-3-avancadas.md
    ├── 11-fase-4-ecossistema.md
    └── 12-processos-qa.md
```

Arquivos com ⭐ são os mais importantes para o Claude Code.

## Passo 1 — Criar repositório local

```bash
# Escolhe onde vais guardar (exemplo: ~/projects)
cd ~/projects

# Cria diretório
mkdir arqel
cd arqel

# Inicializa git
git init

# Copia TODO o conteúdo de arqel-kickoff/ para cá
# (ajusta o caminho de origem conforme onde descarregaste)
cp -r /caminho/para/arqel-kickoff/. .

# Verifica
ls -la
```

Deves ver: `CLAUDE.md`, `AGENTS.md`, `README.md`, `.claude/`, `scripts/`, `docs/`, `PLANNING/`, `.gitignore`.

## Passo 2 — Tornar scripts executáveis

```bash
chmod +x scripts/*.sh
```

## Passo 3 — Primeiro commit

Importante: este commit inicial NÃO precisa seguir Conventional Commits porque ainda não tens o hook instalado. Depois do Claude Code terminar INFRA-001 (que instala hooks), todos os commits seguintes seguirão a convenção.

```bash
git add .
git commit -m "Initial commit: planning package + kickoff docs"
```

## Passo 4 — Criar repositório no GitHub (opcional, recomendado)

```bash
# Se tens gh CLI
gh repo create arqel --public --source=. --remote=origin --push

# Ou manualmente: cria no GitHub.com e depois:
git remote add origin https://github.com/TEU-USER/arqel.git
git branch -M main
git push -u origin main
```

Para a org oficial, precisas verificar disponibilidade:
```bash
# Verificar se org 'arqel' está livre
gh api orgs/arqel 2>&1 | head -3
```

## Passo 5 — Verificar ambiente de desenvolvimento

Antes de entregar ao Claude Code, confirma que o ambiente local está pronto:

```bash
php --version          # Deve ser 8.3+
composer --version
node --version         # Deve ser 20.9+
pnpm --version         # Se não tens: npm i -g pnpm
gh --version           # Para operações GitHub
```

## Passo 6 — Iniciar Claude Code

Abre um terminal no diretório do repo:

```bash
cd ~/projects/arqel

claude
```

O Claude Code vai automaticamente ler `CLAUDE.md` ao iniciar.

## Passo 7 — Primeiro prompt

Cola este prompt exato na primeira mensagem ao Claude Code:

---

```
Olá! Vamos começar a implementação do Arqel.

Já leste CLAUDE.md. Resumindo:

- Estamos no Sprint 0 (Setup inicial), modo Autonomous
- Ponto de partida: INFRA-001 em PLANNING/08-fase-1-mvp.md
- Fase 1 completa tem 123 tickets, totalizando 4-7 meses de trabalho humano
- Regras críticas em CLAUDE.md — ler se dúvida

Por favor:

1. Lê PLANNING/00-index.md para contexto de navegação
2. Lê PLANNING/03-adrs.md (18 ADRs canônicos)
3. Lê PLANNING/04-repo-structure.md (layout do monorepo)
4. Lê o ticket INFRA-001 completo em PLANNING/08-fase-1-mvp.md
5. Começa implementação de INFRA-001

Modo autonomous: avança sem perguntar, exceto nos 8 casos listados em
CLAUDE.md §"Quando PARAR e pedir confirmação humana".

Ao completar cada ticket:
- Roda testes + lint + typecheck
- Commit com Conventional Commits + DCO sign-off
- Atualiza docs/tickets/current.md
- Passa para o próximo ticket automaticamente

Objetivo desta sessão: completar Sprint 0 inteiro (INFRA-001 a INFRA-005
+ GOV-001 + GOV-003).

Começa agora.
```

---

## O que vai acontecer

O Claude Code vai:

1. **Ler o planejamento** (primeiros 5-10 minutos de sessão)
2. **Criar estrutura do monorepo** (INFRA-001):
   - `package.json` raiz com pnpm workspaces config
   - `composer.json` raiz com path repositories
   - Pastas vazias para `packages/`, `packages-js/`, `apps/`
3. **Configurar TypeScript + Vite + tsup** (INFRA-002):
   - `tsconfig.base.json` strict
   - `vite.config.ts` configurações
4. **Setup CI GitHub Actions** (INFRA-003):
   - `.github/workflows/ci.yml` com matrix
5. **Release pipeline** (INFRA-004):
   - `scripts/release.mjs`
   - `.github/workflows/release.yml`
6. **Pre-commit hooks** (INFRA-005):
   - Husky + lint-staged
7. **SECURITY.md** (GOV-001)
8. **CONTRIBUTING.md** (GOV-003)

Tempo estimado: **2-4 horas de Claude Code** (equivalente a ~2 semanas de desenvolvimento humano).

## Monitoramento durante execução

Podes deixar o Claude Code correr em autonomous. Ele só vai parar para:

1. Custos financeiros (setup de serviços pagos)
2. Decisões de segurança ambíguas
3. Dependências novas não planejadas
4. Falhas inexplicáveis reprodutíveis
5. Conflitos com ADRs

Enquanto corre, podes verificar progresso:

```bash
# Em outro terminal
cd ~/projects/arqel
git log --oneline                # Ver commits feitos
cat docs/tickets/current.md      # Ver ticket ativo
```

## Slash commands disponíveis

Durante a sessão Claude Code:

- `/next-ticket` — Carrega próximo ticket com contexto
- `/review-ticket` — Executa checklist pré-commit
- `/sprint-status` — Relatório de progresso
- `/adr-check` — Valida mudança contra ADRs

## Se algo correr mal

**Claude Code bloqueou esperando resposta:**
- Ler a pergunta com atenção
- Responder baseado em `PLANNING/` quando possível
- Se for decisão tua pessoal (budget, domain names, etc.), decidir

**Claude Code cometeu erro:**
- Pedir para reverter com `git reset --hard HEAD~1` (antes de push)
- Ou `git revert` (se já push)
- Explicar o que esperavas

**Claude Code está lento:**
- Normal em Sprint 0 — há muita leitura inicial
- Depois de caught up no planning, fica mais rápido

**Testes falham em CI:**
- Claude Code deve auto-diagnosticar e corrigir
- Se não consegue após 3 tentativas, vai parar e pedir ajuda

## Marcos esperados

| Marco | Tempo estimado | Indicador |
|---|---|---|
| INFRA-001 completo | ~30 min | `pnpm install` funciona, monorepo estruturado |
| Sprint 0 completo | 2-4 horas | CI verde, release pipeline testável |
| CORE-001 → CORE-005 | 4-8 horas | Primeiras classes do pacote core |
| Primeiro Resource renderizando | ~1-2 dias | Playground funcional com CRUD básico |

## Próximos passos após Sprint 0

Quando Sprint 0 estiver completo, podes:

1. Revisar o trabalho feito: `git log --oneline` e ler alguns commits
2. Testar manualmente: `pnpm test:all` deve passar
3. Dizer ao Claude Code: "Continua com Sprint 1 (CORE foundational)"

O Claude Code vai seguir o plano em `PLANNING/08-fase-1-mvp.md` §13 (ordem sugerida de execução).

## Dúvidas?

Se tiveres dúvidas sobre qualquer ticket ou decisão, consulta primeiro:

1. `CLAUDE.md` — regras operacionais
2. `PLANNING/03-adrs.md` — decisões canônicas
3. `PLANNING/01-spec-tecnica.md` — requisitos originais
4. `PLANNING/12-processos-qa.md` — processos

O planejamento foi feito para auto-suficiência. 90% das decisões de implementação já estão documentadas.

---

**Boa construção! 🚀**
