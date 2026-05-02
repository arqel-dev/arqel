# Política de Privacidade — Arqel DevTools Extension

> **Status:** vigente • **Versão:** 1.0 • **Data efetiva:** 2026-05-01 • **Aplicável a:** `@arqel/devtools-extension` (Chrome, Firefox, Edge)

A extensão **Arqel DevTools** foi desenhada com privacidade-first como princípio de design. Esta política descreve, de forma transparente, exatamente o que a extensão faz com dados do usuário — a resposta curta é: **nada sai da máquina do usuário, em nenhuma circunstância, sem ação explícita.**

---

## 1. Resumo executivo

| Pergunta | Resposta |
|---|---|
| A extensão coleta dados pessoais? | **Não.** |
| A extensão envia dados para servidores externos? | **Não.** |
| A extensão usa analytics, telemetria, ou crash reporting? | **Não.** |
| A extensão lê cookies de outros sites? | **Não.** |
| A extensão lê histórico de navegação? | **Não.** |
| A extensão funciona offline? | **Sim.** Totalmente local. |
| Onde os dados ficam armazenados? | Apenas no `chrome.storage.local` do próprio navegador, no dispositivo do usuário. |

---

## 2. O que a extensão faz

A `@arqel/devtools-extension` é uma ferramenta de debug para desenvolvedores Arqel. Ela adiciona um painel ao DevTools do navegador que mostra:

- **Resources detectados** na página atual (lista de classes Resource registradas no painel Arqel ativo).
- **Inertia props** atuais e histórico de navegação Inertia (somente da aba ativa, somente em modo dev).
- **Eventos do React DevTools bridge** para componentes Arqel (Field, Form, Table).
- **Performance markers** dos hooks Arqel (`useArqelForm`, `useArqelTable`).

Tudo isso vem **da própria página inspecionada**, via hook injetado **apenas quando** `window.__ARQEL_DEV__ === true` — uma flag que só é setada quando a aplicação está em modo `development` e o desenvolvedor explicitamente importa `@arqel/react/dev`.

---

## 3. O que a extensão NÃO faz

- **Não coleta** nenhum dado pessoal: nome, email, IP, user-agent, geolocalização, cookies, tokens, senhas — nada.
- **Não envia** requisições HTTP para servidores Arqel ou terceiros. **Zero network calls** por padrão.
- **Não usa** Google Analytics, Mixpanel, Sentry, PostHog, ou qualquer ferramenta de telemetria.
- **Não acessa** abas que não tenham a flag `__ARQEL_DEV__` setada.
- **Não persiste** dados entre sessões além do que o usuário explicitamente "favoritou" para inspecionar (e mesmo isso fica em `chrome.storage.local`, nunca remoto).
- **Não lê** o conteúdo de outras extensões instaladas.
- **Não modifica** o DOM da página inspecionada (read-only).

---

## 4. Permissões solicitadas no manifest

| Permissão | Justificativa |
|---|---|
| `devtools` | Necessária para registrar painel customizado no DevTools. |
| `storage` | Salvar preferências locais (tema, painel ativo) via `chrome.storage.local`. |
| `scripting` (opcional) | Injetar hook bridge **somente** em páginas com `__ARQEL_DEV__` flag. |

A extensão **não solicita**: `tabs`, `history`, `cookies`, `webRequest`, `<all_urls>` host permissions, `nativeMessaging`, `identity`, ou qualquer permissão que dê acesso a conteúdo cross-origin.

---

## 5. Modo DEV-only — proteção em camadas

A injeção do bridge só acontece quando **todas** estas condições são verdadeiras:

1. A aplicação importou `@arqel/react/dev` (subpath separado, removido do bundle de produção via tree-shaking).
2. `process.env.NODE_ENV === 'development'`.
3. `window.__ARQEL_DEV__ === true` (setado explicitamente pelo dev).

Em **produção**, mesmo com a extensão instalada, ela permanece inerte — o painel do DevTools mostra a mensagem "Nenhuma aplicação Arqel detectada nesta aba".

---

## 6. Compartilhamento de dados

**A extensão não compartilha dados.** Não há terceiros, não há SDK embutido de analytics, não há "anonymized usage statistics opt-in". O modelo é binário: **zero coleta**.

Se em uma versão futura considerarmos adicionar telemetria opcional (e.g., para entender features mais usadas), faremos:

- Anúncio em changelog **antes** do release.
- Opt-in explícito (default: desligado).
- Atualização desta política com nova versão e data efetiva.
- Documentação clara dos campos coletados.

Atualmente isso **não existe e não está planejado para v0.8.x**.

---

## 7. Cookies, local storage, e tracking

A extensão **não usa cookies**. O único storage é `chrome.storage.local`, contendo apenas:

- `arqel_devtools_theme` — `"light" | "dark"` (preferência visual).
- `arqel_devtools_active_panel` — string identificando aba aberta por último.

Esses dados **nunca saem do dispositivo**.

---

## 8. Segurança

- A extensão é open-source (MIT) — código auditável em `https://github.com/arqel/arqel/tree/main/packages-js/devtools-extension`.
- Builds são reproduzíveis via `pnpm build`.
- Source maps são publicados junto ao Web Store package, satisfazendo o requisito da Mozilla AMO de código revisável quando há minificação.
- Sem dependências de runtime — apenas React (já presente no DevTools host).

---

## 9. Como remover a extensão

Em qualquer momento, sem perda de dados (já que não há dados):

- **Chrome/Edge**: `chrome://extensions` → encontrar "Arqel DevTools" → "Remover".
- **Firefox**: `about:addons` → "Extensões" → engrenagem ao lado de "Arqel DevTools" → "Remover".

O `chrome.storage.local` da extensão é apagado automaticamente pelo navegador na desinstalação.

---

## 10. Crianças

A extensão é uma ferramenta de desenvolvimento profissional. Não é direcionada a menores de 13 anos e não coleta dados que permitam identificar idade.

---

## 11. Mudanças nesta política

Qualquer alteração material será publicada em:

- Este arquivo (com bump de versão e nova data efetiva).
- `CHANGELOG.md` raiz do monorepo.
- Release notes do Web Store.

A versão anterior fica preservada via git history.

---

## 12. Contato

- **Issues**: `https://github.com/arqel/arqel/issues`
- **Email**: `security@arqel.dev` (somente para questões de privacidade/segurança).
- **Disclosure responsável**: ver `SECURITY.md` na raiz do monorepo.

---

**Última revisão:** 2026-05-01 • **Próxima revisão prevista:** com release v0.9.0 ou se houver mudança material.
