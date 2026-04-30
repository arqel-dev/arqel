/**
 * `<AiTranslateInput>` — apresentational React component for the PHP
 * `Arqel\Ai\Fields\AiTranslateField` (component string `AiTranslateInput`).
 *
 * Render contract (AI-008):
 *   - Tab list (`role="tablist"`) com um `<button role="tab">` por
 *     idioma configurado em `props.languages`. A tab default vem de
 *     `props.defaultLanguage`.
 *   - Cada tab mostra um indicador visual (`data-missing="true"`)
 *     quando a tradução daquela língua está vazia/null.
 *   - Painel ativo: `<textarea>` controlado que edita
 *     `translations[activeLang]`. Mudanças disparam o `onChange` com o
 *     objeto completo `Record<lang, string>`.
 *   - Botão "Translate from {defaultLanguage}" presente em todas as
 *     tabs **não-default**; faz `POST` com `targetLanguages: [lang]`.
 *   - Botão "Translate all missing" no topo do componente; itera as
 *     línguas com tradução vazia e dispara um único `POST` com o
 *     array completo.
 *   - Loading per-language (`Set<string>`): o botão da tab/lang sendo
 *     traduzida fica `disabled` + spinner inline.
 *   - Banner `role="alert"` quando o `fetch` falha; o resto continua
 *     usável.
 *
 * Network:
 *   - URL = `props.translateUrl` (caller override) ou
 *     `/admin/${resource}/fields/${field}/translate` (default route
 *     name `arqel.ai.translate`).
 *   - Body: `{ sourceLanguage, targetLanguages: string[], sourceText }`.
 *     `sourceText` vem de `translations[defaultLanguage] ?? ''`.
 *   - Response: `{ translations: { [lang]: string } }` é mesclado em
 *     state local + `onChange` callback.
 *
 * SSR-safe: nada no render path toca `window`/`document`; o `fetch`
 * só dispara dentro do click handler (post-mount).
 */

import { type ChangeEvent, type ReactElement, useCallback, useId, useMemo, useState } from 'react';

export interface AiTranslateInputFieldProps {
  languages: string[];
  defaultLanguage: string;
  autoTranslate: boolean;
  provider?: string | null;
}

export type AiTranslateValue = Record<string, string>;

export interface AiTranslateInputProps {
  name: string;
  value: AiTranslateValue | null;
  onChange?: (value: AiTranslateValue) => void;
  props: AiTranslateInputFieldProps | undefined;
  resource?: string;
  field?: string;
  translateUrl?: string;
  csrfToken?: string;
}

interface AiTranslateResponseBody {
  translations?: unknown;
}

function buildTranslateUrl(
  override: string | undefined,
  resource: string | undefined,
  field: string | undefined,
): string | null {
  if (override !== undefined && override !== '') {
    return override;
  }
  if (resource && field) {
    return `/admin/${resource}/fields/${field}/translate`;
  }
  return null;
}

function isStringRecord(input: unknown): input is Record<string, string> {
  if (input === null || typeof input !== 'object') {
    return false;
  }
  for (const v of Object.values(input as Record<string, unknown>)) {
    if (typeof v !== 'string') {
      return false;
    }
  }
  return true;
}

function isMissing(translations: AiTranslateValue, lang: string): boolean {
  const v = translations[lang];
  return v === undefined || v === null || v === '';
}

export function AiTranslateInput(props: AiTranslateInputProps): ReactElement {
  const {
    name,
    value,
    onChange,
    props: fieldProps,
    resource,
    field,
    translateUrl,
    csrfToken,
  } = props;

  const languages = useMemo<string[]>(() => fieldProps?.languages ?? [], [fieldProps?.languages]);
  const defaultLanguage = fieldProps?.defaultLanguage ?? languages[0] ?? '';

  const reactId = useId();
  const tablistId = `arqel-ai-translate-${reactId}`;

  const [internalValue, setInternalValue] = useState<AiTranslateValue>(value ?? {});
  const isControlled = onChange !== undefined;
  const currentValue: AiTranslateValue = isControlled ? (value ?? {}) : internalValue;

  const initialActive = languages.includes(defaultLanguage)
    ? defaultLanguage
    : (languages[0] ?? defaultLanguage);
  const [activeLang, setActiveLang] = useState<string>(initialActive);

  const [loadingLangs, setLoadingLangs] = useState<Set<string>>(() => new Set());
  const [error, setError] = useState<string | null>(null);

  const applyValue = useCallback(
    (next: AiTranslateValue) => {
      if (isControlled && onChange) {
        onChange(next);
      } else {
        setInternalValue(next);
      }
    },
    [isControlled, onChange],
  );

  const handleTextareaChange = useCallback(
    (event: ChangeEvent<HTMLTextAreaElement>) => {
      const next: AiTranslateValue = { ...currentValue, [activeLang]: event.target.value };
      applyValue(next);
    },
    [activeLang, applyValue, currentValue],
  );

  const setLoadingFor = useCallback((langs: string[], on: boolean): void => {
    setLoadingLangs((prev) => {
      const next = new Set(prev);
      for (const l of langs) {
        if (on) {
          next.add(l);
        } else {
          next.delete(l);
        }
      }
      return next;
    });
  }, []);

  const performTranslate = useCallback(
    async (targetLanguages: string[]): Promise<void> => {
      if (targetLanguages.length === 0) {
        return;
      }
      const url = buildTranslateUrl(translateUrl, resource, field);
      if (url === null) {
        setError('Missing translate URL: provide `translateUrl` or both `resource` and `field`.');
        return;
      }

      setError(null);
      setLoadingFor(targetLanguages, true);

      try {
        const response = await fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken ?? '',
          },
          body: JSON.stringify({
            sourceLanguage: defaultLanguage,
            targetLanguages,
            sourceText: currentValue[defaultLanguage] ?? '',
          }),
        });

        if (!response.ok) {
          setError(`Translation failed (HTTP ${String(response.status)}).`);
          return;
        }

        const body = (await response.json()) as AiTranslateResponseBody;
        const incoming = body.translations;
        if (!isStringRecord(incoming)) {
          setError('Translation failed: invalid response body.');
          return;
        }

        const merged: AiTranslateValue = { ...currentValue, ...incoming };
        applyValue(merged);
      } catch {
        setError('Translation failed: network error.');
      } finally {
        setLoadingFor(targetLanguages, false);
      }
    },
    [
      applyValue,
      csrfToken,
      currentValue,
      defaultLanguage,
      field,
      resource,
      setLoadingFor,
      translateUrl,
    ],
  );

  const handleTranslateOne = useCallback(
    (lang: string) => {
      void performTranslate([lang]);
    },
    [performTranslate],
  );

  const handleTranslateAllMissing = useCallback(() => {
    const missing = languages.filter(
      (lang) => lang !== defaultLanguage && isMissing(currentValue, lang),
    );
    void performTranslate(missing);
  }, [currentValue, defaultLanguage, languages, performTranslate]);

  const missingCount = languages.filter(
    (lang) => lang !== defaultLanguage && isMissing(currentValue, lang),
  ).length;

  const activeText = currentValue[activeLang] ?? '';
  const activeIsLoading = loadingLangs.has(activeLang);

  return (
    <div className="arqel-ai-translate-input" data-arqel-field="aiTranslate" data-field-name={name}>
      <div className="arqel-ai-translate-input__toolbar">
        <button
          type="button"
          onClick={handleTranslateAllMissing}
          disabled={missingCount === 0 || loadingLangs.size > 0}
          className="arqel-ai-translate-input__translate-all"
        >
          Translate all missing
        </button>
      </div>

      <div role="tablist" id={tablistId} className="arqel-ai-translate-input__tabs">
        {languages.map((lang) => {
          const selected = lang === activeLang;
          const missing = isMissing(currentValue, lang);
          return (
            <button
              key={lang}
              type="button"
              role="tab"
              aria-selected={selected}
              aria-controls={`${tablistId}-panel-${lang}`}
              data-missing={missing ? 'true' : 'false'}
              onClick={() => setActiveLang(lang)}
              className="arqel-ai-translate-input__tab"
            >
              <span>{lang}</span>
              {missing ? (
                <span
                  role="img"
                  aria-label="Missing translation"
                  className="arqel-ai-translate-input__missing-dot"
                  data-testid={`missing-dot-${lang}`}
                >
                  •
                </span>
              ) : null}
            </button>
          );
        })}
      </div>

      <div
        role="tabpanel"
        id={`${tablistId}-panel-${activeLang}`}
        className="arqel-ai-translate-input__panel"
      >
        <textarea
          name={`${name}[${activeLang}]`}
          value={activeText}
          onChange={handleTextareaChange}
          rows={6}
          className="arqel-ai-translate-input__textarea"
        />

        {activeLang !== defaultLanguage ? (
          <div className="arqel-ai-translate-input__panel-actions">
            <button
              type="button"
              onClick={() => handleTranslateOne(activeLang)}
              disabled={activeIsLoading}
              className="arqel-ai-translate-input__translate-one"
            >
              {activeIsLoading ? (
                <span
                  role="status"
                  aria-label="Translating"
                  className="arqel-ai-translate-input__spinner"
                >
                  <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                  >
                    <circle
                      cx="12"
                      cy="12"
                      r="10"
                      stroke="currentColor"
                      strokeOpacity="0.25"
                      strokeWidth="4"
                    />
                    <path
                      d="M22 12a10 10 0 0 1-10 10"
                      stroke="currentColor"
                      strokeWidth="4"
                      strokeLinecap="round"
                    />
                  </svg>
                </span>
              ) : null}
              <span>Translate from {defaultLanguage}</span>
            </button>
          </div>
        ) : null}
      </div>

      {error !== null ? (
        <div role="alert" className="arqel-ai-translate-input__error">
          {error}
        </div>
      ) : null}
    </div>
  );
}

export default AiTranslateInput;
