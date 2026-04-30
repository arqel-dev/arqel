# SKILL.md — arqel/ai (JS)

## Purpose

Pacote React que entrega os componentes UI do Arqel AI. Por enquanto
exporta apenas `<AiTextInput>`, espelhando o PHP
`Arqel\Ai\Fields\AiTextField` (component string `AiTextInput`).

O componente é **apresentacional** mas precisa fazer **uma chamada de
rede explícita** quando o usuário clica em "Generate with AI" — o
backend (`AiGenerateController`) é quem resolve o prompt template
(que **nunca** trafega para o cliente) e devolve `{ text }`. O
contrato de rota canônico é `POST /admin/{resource}/fields/{field}/generate`.

## Key Contracts

```ts
interface AiTextInputProps {
  name: string;
  value: string;
  onChange?: (value: string) => void;
  props: {
    provider?: string | null;
    buttonLabel: string;
    maxLength?: number | null;
    hasContextFields: boolean;
  } | undefined;
  resource?: string;
  field?: string;
  formData?: Record<string, unknown>;
  generateUrl?: string;
  csrfToken?: string;
}
```

Comportamento ao clicar no botão:

1. Resolve URL: `generateUrl` (override) ou
   `/admin/${resource}/fields/${field}/generate`.
2. `fetch(url, { method: 'POST', credentials: 'same-origin', headers:
   { Content-Type: application/json, Accept: application/json,
   X-CSRF-TOKEN: csrfToken ?? '' }, body: JSON.stringify({ formData })
   })`.
3. Sucesso (`response.ok`) → lê `{ text }` do JSON, chama
   `onChange(text)` (ou seta state interno se uncontrolled), troca o
   label para `Regenerate`.
4. Falha → renderiza banner com `Generation failed (HTTP 500).` e
   reabilita o botão.

## Conventions

- TypeScript strict, sem `any` (apenas asserts pontuais comentados em
  `register.tsx` para compatibilidade com a assinatura genérica do
  FieldRegistry).
- Component name canônico: `AiTextInput`. Não renomear — o PHP usa
  essa string em `getComponent()`.
- Lint: Biome (`pnpm lint`); testes: Vitest + Testing Library; build:
  tsup.
- Side-effects ficam isolados em `dist/register.js` para tree-shaking.
- SSR-safe: nada no render path toca `window`/`document`.

## Examples

### Uso direto

```tsx
import { AiTextInput } from '@arqel/ai';

<AiTextInput
  name="description"
  value={form.description}
  onChange={(v) => setForm((f) => ({ ...f, description: v }))}
  props={{
    provider: 'claude',
    buttonLabel: 'Gerar descrição',
    maxLength: 1000,
    hasContextFields: true,
  }}
  resource="products"
  field="description"
  formData={form}
  csrfToken={csrfToken}
/>;
```

### Via FieldRegistry do `@arqel/ui`

```ts
// boot.ts
import '@arqel/ai/register';
```

A partir daí qualquer schema com `component: 'AiTextInput'` (que é
exatamente o que o PHP `AiTextField::getComponent()` retorna) é
renderizado pelo adapter desta lib.

## AiTextInput (AI-007 React)

O componente fecha o slice React do ticket AI-007 — o slice PHP foi
mergeado no batch #32. Renderiza:

- `<textarea>` controlado (ou uncontrolled, se `onChange` for omitido)
  com `maxLength` aplicado quando configurado.
- Botão "Generate with AI" (label do PHP via `props.buttonLabel`); vira
  `Regenerate` após o primeiro sucesso.
- Spinner SVG inline + `disabled` durante a chamada.
- Banner `role="alert"` quando o `fetch` falha (com status code).
- Contador `X / maxLength` quando `maxLength` está presente.

Não toque no `aria-label` do botão sem alinhar com o PHP — testes do
form usam essa string para encontrar o trigger.

## AiTranslateInput (AI-008 React)

Componente React que fecha o slice React do ticket AI-008 — o slice
PHP foi mergeado no batch #33 (`Arqel\Ai\Fields\AiTranslateField`,
component string `AiTranslateInput`).

```ts
interface AiTranslateInputProps {
  name: string;
  value: Record<string, string> | null;
  onChange?: (value: Record<string, string>) => void;
  props: {
    languages: string[];
    defaultLanguage: string;
    autoTranslate: boolean;
    provider?: string | null;
  } | undefined;
  resource?: string;
  field?: string;
  translateUrl?: string;
  csrfToken?: string;
}
```

Render:

- `role="tablist"` com uma `<button role="tab">` por idioma de
  `props.languages`. Tabs com tradução vazia/null carregam
  `data-missing="true"` + um pontinho visual (`Missing translation`).
- Painel ativo: `<textarea>` controlado editando
  `translations[activeLang]`; o `onChange` recebe sempre o objeto
  completo `Record<lang, string>`.
- Botão `Translate from {defaultLanguage}` em cada tab **não-default**:
  `POST` com `targetLanguages: [activeLang]`.
- Botão `Translate all missing` no topo: itera as línguas com
  tradução vazia e dispara um único `POST` com `targetLanguages: missing[]`.
- Loading per-language (`Set<string>`); banner `role="alert"` quando o
  `fetch` falha (HTTP code visível).

Rota canônica: `POST /admin/{resource}/fields/{field}/translate`
(override via `translateUrl`). Body:
`{ sourceLanguage, targetLanguages, sourceText }`. Resposta esperada:
`{ translations: { [lang]: text } }` — mesclada no state e propagada
via `onChange`.

### Uso direto

```tsx
import { AiTranslateInput } from '@arqel/ai';

<AiTranslateInput
  name="title"
  value={form.title}
  onChange={(v) => setForm((f) => ({ ...f, title: v }))}
  props={{
    languages: ['en', 'pt', 'es'],
    defaultLanguage: 'en',
    autoTranslate: false,
    provider: 'claude',
  }}
  resource="posts"
  field="title"
  csrfToken={csrfToken}
/>;
```

## Anti-patterns

- Trazer o prompt template para o cliente — segurança/IP. O backend
  resolve `{fieldName}` placeholders.
- Adicionar TanStack Query / SWR / Inertia router só para esse fetch:
  uma chamada `fetch` simples basta (ADR-016).
- Renomear o component name para algo sem casamento com o PHP — o
  field aponta exatamente para `AiTextInput`.
- Tocar `window`/`document` durante render — quebra SSR.

## Related

- PHP field: `packages/ai/src/Fields/AiTextField.php`
- PHP controller: `packages/ai/src/Http/Controllers/AiGenerateController.php`
- Rota: `packages/ai/routes/web.php` (`arqel.ai.generate`)
- Ticket: `PLANNING/10-fase-3-avancadas.md` → AI-007
- FieldRegistry: `packages-js/ui/src/form/FieldRegistry.tsx`
