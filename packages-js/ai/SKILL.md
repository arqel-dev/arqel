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

## AiSelectInput (AI-009 React)

Componente React que fecha o slice React do ticket AI-009 — o slice
PHP foi mergeado no batch #34 (`Arqel\Ai\Fields\AiSelectField`,
component string `AiSelectInput`).

```ts
interface AiSelectInputProps {
  name: string;
  value: string | null;
  onChange?: (value: string | null) => void;
  props: {
    options: Record<string, string>;
    classifyFromFields: string[];
    provider?: string | null;
    fallbackOption?: string | null;
    hasContextFields: boolean;
  } | undefined;
  resource?: string;
  field?: string;
  formData?: Record<string, unknown>;
  classifyUrl?: string;
  csrfToken?: string;
}
```

Render:

- `<select>` controlado (uncontrolled fallback se `onChange` for
  omitido) com `<option value="">Select...</option>` mais uma
  `<option key={k} value={k}>{label}</option>` por entry de
  `props.options`.
- Botão `Classify with AI` ao lado do select. `disabled` quando
  `hasContextFields` é `false` (com `title` explicando o motivo —
  exibido como tooltip nativo).
- Loading state: spinner SVG inline + botão `disabled` durante o
  `fetch`.
- Em sucesso (`key !== null`): `onChange(key)` é chamado e um banner
  sutil `Suggested by AI` aparece com botões `Accept` e `Pick another`
  (ambos apenas dismissam o banner — o valor já está aplicado no
  state).
- Em sucesso com `key: null` + `fallbackOption` configurado: aplica o
  fallback e mostra o banner `Used fallback`.
- Em sucesso com `key: null` sem fallback: banner `role="alert"` com
  `Could not classify`.
- Em falha HTTP: banner `role="alert"` com o status code visível.

Rota canônica: `POST /admin/{resource}/fields/{field}/classify`
(override via `classifyUrl`). Body: `{ formData }`. Resposta esperada:
`{ key: string | null, label: string | null }`.

### Uso direto

```tsx
import { AiSelectInput } from '@arqel/ai';

<AiSelectInput
  name="priority"
  value={form.priority}
  onChange={(v) => setForm((f) => ({ ...f, priority: v }))}
  props={{
    options: { low: 'Low', medium: 'Medium', high: 'High' },
    classifyFromFields: ['title', 'body'],
    provider: 'claude',
    fallbackOption: 'low',
    hasContextFields: true,
  }}
  resource="tickets"
  field="priority"
  formData={form}
  csrfToken={csrfToken}
/>;
```

## AiExtractInput (AI-010 React)

Componente React que fecha o slice React do ticket AI-010 — o slice
PHP foi mergeado no batch #35 (`Arqel\Ai\Fields\AiExtractField`,
component string `AiExtractInput`).

```ts
interface AiExtractInputProps {
  name: string;
  value: Record<string, unknown> | null;
  onChange?: (value: Record<string, unknown>) => void;
  props: {
    sourceField: string;
    targetFields: string[];
    buttonLabel: string;
    usingJsonMode: boolean;
    provider?: string | null;
  } | undefined;
  resource?: string;
  field?: string;
  formData?: Record<string, unknown>;
  extractUrl?: string;
  csrfToken?: string;
  onPopulateField?: (targetField: string, value: unknown) => void;
}
```

Render:

- Label `Source: {sourceField}` indicando o campo do formulário usado
  como texto-fonte.
- Botão `Extract with AI` (label vem de `props.buttonLabel`); spinner
  inline + `disabled` durante o `fetch`.
- Empty state `No extraction yet — click button to start` antes da
  primeira extração.
- Após sucesso: `<dl>` preview com cada `targetField: extractedValue`;
  cada entry tem botão `Apply` individual + toolbar `Apply all`.
- `Apply` chama `onPopulateField?.(targetField, value)` quando provido,
  caso contrário emite `onChange({ [target]: value })` ou
  `onChange(extracted)` para `Apply all`.
- Banner `role="alert"` quando o `fetch` falha; usa `message` da
  response 422 quando disponível, senão fallback genérico com HTTP
  code.

Rota canônica: `POST /admin/{resource}/fields/{field}/extract`
(override via `extractUrl`). Body: `{ sourceText }` (lido de
`formData?.[sourceField]`). Resposta esperada:
`{ extracted: Record<string, unknown> }` (200) ou `{ message }` (422).

### Uso direto

```tsx
import { AiExtractInput } from '@arqel/ai';

<AiExtractInput
  name="contact"
  value={form.contact}
  onChange={(v) => setForm((f) => ({ ...f, contact: v }))}
  props={{
    sourceField: 'rawText',
    targetFields: ['name', 'email', 'phone'],
    buttonLabel: 'Extract contact info',
    usingJsonMode: true,
    provider: 'claude',
  }}
  resource="contacts"
  field="contact"
  formData={form}
  csrfToken={csrfToken}
  onPopulateField={(target, value) =>
    setForm((f) => ({ ...f, [target]: value }))
  }
/>;
```

## AiImageInput (AI-011 React)

Componente React que fecha o slice React do ticket AI-011 — o slice
PHP foi mergeado no batch #36 (`Arqel\Ai\Fields\AiImageField`,
component string `AiImageInput`).

```ts
interface AiImageInputProps {
  name: string;
  value: string | null;
  onChange?: (value: string) => void;
  props: {
    analyses: string[];
    populateFields: Record<string, string>;
    provider?: string | null;
    acceptedMimes: string[];
    maxFileSize: number;
    buttonLabel: string;
  } | undefined;
  resource?: string;
  field?: string;
  analyzeUrl?: string;
  csrfToken?: string;
  onPopulateField?: (targetField: string, value: string) => void;
}
```

Render:

- `<input type="file">` escondido + `<label>` clicável formando o
  drop-zone visual; `accept` é o `props.acceptedMimes.join(',')`.
- Preview da imagem após selecionar (via `URL.createObjectURL`, só
  chamado dentro do change handler — render path SSR-safe).
- Validação client-side: arquivos maiores que `props.maxFileSize`
  (em bytes) viram banner `role="alert"` + botão fica `disabled`.
- Botão `Analyze with AI` (label vem de `props.buttonLabel`); spinner
  inline + `disabled` durante o `fetch`. Sem arquivo selecionado o
  botão também fica `disabled`.
- Após sucesso: `<dl>` preview com cada `analysis_key: value`; cada
  entry com mapping em `populateFields` mostra botão `Apply`
  individual; toolbar mostra `Apply all` que itera todas as analyses
  com mapping.
- `Apply` chama `onPopulateField?.(target, value)` usando o
  mapeamento `populateFields[analysisKey]` (ou `populateMapping`
  vindo da response, que tem precedência se presente).
- Banner `role="alert"` quando o `fetch` falha; usa `message` da
  response 422 quando disponível, senão fallback genérico com HTTP
  code.

Rota canônica: `POST /admin/{resource}/fields/{field}/analyze-image`
(override via `analyzeUrl`). Body: `{ imageBase64 }` (data URI
completo, produzido por `FileReader.readAsDataURL`). Resposta
esperada: `{ analyses: Record<string,string>, populateMapping:
Record<string,string> }` (200) ou `{ message }` (422).

### Uso direto

```tsx
import { AiImageInput } from '@arqel/ai';

<AiImageInput
  name="cover"
  value={form.cover}
  onChange={(v) => setForm((f) => ({ ...f, cover: v }))}
  props={{
    analyses: ['alt_text', 'tags'],
    populateFields: { alt_text: 'cover_alt', tags: 'cover_tags' },
    provider: 'claude',
    acceptedMimes: ['image/jpeg', 'image/png', 'image/webp'],
    maxFileSize: 10_485_760,
    buttonLabel: 'Analyze with AI',
  }}
  resource="posts"
  field="cover"
  csrfToken={csrfToken}
  onPopulateField={(target, value) =>
    setForm((f) => ({ ...f, [target]: value }))
  }
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
