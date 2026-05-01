/**
 * `<AiSelectInput>` — apresentational React component for the PHP
 * `Arqel\Ai\Fields\AiSelectField` (component string `AiSelectInput`).
 *
 * Render contract (AI-009):
 *   - `<select>` controlled (or uncontrolled fallback) with a
 *     placeholder option + one entry per `props.options`,
 *   - "Classify with AI" button next to the select; disabled when
 *     `hasContextFields` is false (with a tooltip explaining why),
 *   - while a request is in-flight the button is disabled and an
 *     inline spinner is rendered,
 *   - on success a banner with `Suggested by AI` is rendered with two
 *     dismiss buttons: `Accept` and `Pick another` (both just hide the
 *     banner — the value is already applied),
 *   - if the response returns `key: null` and `fallbackOption` is
 *     configured, the fallback is applied and a `Used fallback`
 *     banner is shown,
 *   - if the response returns `key: null` and there is no fallback,
 *     an error banner `Could not classify` is rendered.
 *
 * Network:
 *   - URL = `props.classifyUrl` override or
 *     `/admin/${resource}/fields/${field}/classify`.
 *   - `fetch(url, { method: 'POST', credentials: 'same-origin', headers:
 *     { Content-Type, Accept, X-CSRF-TOKEN }, body: JSON.stringify({
 *     formData }) })`. The PHP controller runs the LLM classification
 *     and returns `{ key, label }`.
 *
 * SSR-safe: nothing in the render path touches `window`/`document`.
 */

import { type ChangeEvent, type ReactElement, useCallback, useId, useState } from 'react';

export interface AiSelectInputFieldProps {
  options: Record<string, string>;
  classifyFromFields: string[];
  provider?: string | null;
  fallbackOption?: string | null;
  hasContextFields: boolean;
}

export interface AiSelectInputProps {
  name: string;
  value: string | null;
  onChange?: (value: string | null) => void;
  props: AiSelectInputFieldProps | undefined;
  resource?: string;
  field?: string;
  formData?: Record<string, unknown>;
  classifyUrl?: string;
  csrfToken?: string;
}

interface AiClassifyResponseBody {
  key?: unknown;
  label?: unknown;
}

type SuggestionKind = 'ai' | 'fallback';

const DEFAULT_BUTTON_LABEL = 'Classify with AI';
const NO_CONTEXT_TOOLTIP =
  'No context fields configured. Add `classifyFromFields` to enable AI classification.';

function buildClassifyUrl(
  override: string | undefined,
  resource: string | undefined,
  field: string | undefined,
): string | null {
  if (override !== undefined && override !== '') {
    return override;
  }
  if (resource && field) {
    return `/admin/${resource}/fields/${field}/classify`;
  }
  return null;
}

export function AiSelectInput(props: AiSelectInputProps): ReactElement {
  const {
    name,
    value,
    onChange,
    props: fieldProps,
    resource,
    field,
    formData,
    classifyUrl,
    csrfToken,
  } = props;

  const options = fieldProps?.options ?? {};
  const fallbackOption = fieldProps?.fallbackOption ?? null;
  const hasContextFields = fieldProps?.hasContextFields ?? false;

  const reactId = useId();
  const selectId = `arqel-ai-select-${reactId}`;

  const [internalValue, setInternalValue] = useState<string | null>(value);
  const isControlled = onChange !== undefined;
  const currentValue = isControlled ? value : internalValue;

  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const [suggestion, setSuggestion] = useState<SuggestionKind | null>(null);

  const applyValue = useCallback(
    (next: string | null) => {
      if (isControlled && onChange) {
        onChange(next);
      } else {
        setInternalValue(next);
      }
    },
    [isControlled, onChange],
  );

  const handleSelectChange = useCallback(
    (event: ChangeEvent<HTMLSelectElement>) => {
      const next = event.target.value === '' ? null : event.target.value;
      applyValue(next);
      setSuggestion(null);
    },
    [applyValue],
  );

  const handleClassify = useCallback(async (): Promise<void> => {
    const url = buildClassifyUrl(classifyUrl, resource, field);
    if (url === null) {
      setError('Missing classify URL: provide `classifyUrl` or both `resource` and `field`.');
      return;
    }

    setIsLoading(true);
    setError(null);
    setSuggestion(null);

    try {
      const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken ?? '',
        },
        body: JSON.stringify({ formData: formData ?? {} }),
      });

      if (!response.ok) {
        setError(`Classification failed (HTTP ${String(response.status)}).`);
        return;
      }

      const body = (await response.json()) as AiClassifyResponseBody;
      const key = typeof body.key === 'string' ? body.key : null;

      if (key !== null) {
        applyValue(key);
        setSuggestion('ai');
        return;
      }

      // key === null → try fallback
      if (fallbackOption !== null && fallbackOption !== '') {
        applyValue(fallbackOption);
        setSuggestion('fallback');
        return;
      }

      setError('Could not classify.');
    } catch {
      setError('Classification failed: network error.');
    } finally {
      setIsLoading(false);
    }
  }, [applyValue, classifyUrl, csrfToken, fallbackOption, field, formData, resource]);

  const buttonDisabled = isLoading || !hasContextFields;
  const buttonTitle = !hasContextFields ? NO_CONTEXT_TOOLTIP : undefined;

  return (
    <div className="arqel-ai-select-input" data-arqel-field="aiSelect" data-field-name={name}>
      <select
        id={selectId}
        name={name}
        value={currentValue ?? ''}
        onChange={handleSelectChange}
        className="arqel-ai-select-input__select"
      >
        <option value="">Select...</option>
        {Object.entries(options).map(([key, label]) => (
          <option key={key} value={key}>
            {label}
          </option>
        ))}
      </select>

      <div className="arqel-ai-select-input__actions">
        <button
          type="button"
          onClick={() => {
            void handleClassify();
          }}
          disabled={buttonDisabled}
          aria-label={DEFAULT_BUTTON_LABEL}
          className="arqel-ai-select-input__button"
          {...(buttonTitle !== undefined ? { title: buttonTitle } : {})}
        >
          {isLoading ? (
            <span role="status" aria-label="Classifying" className="arqel-ai-select-input__spinner">
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
          <span>{DEFAULT_BUTTON_LABEL}</span>
        </button>
      </div>

      {suggestion !== null ? (
        <div role="status" className="arqel-ai-select-input__suggestion">
          <span>{suggestion === 'ai' ? 'Suggested by AI' : 'Used fallback'}</span>
          <button
            type="button"
            onClick={() => {
              setSuggestion(null);
            }}
            className="arqel-ai-select-input__suggestion-accept"
          >
            Accept
          </button>
          <button
            type="button"
            onClick={() => {
              setSuggestion(null);
            }}
            className="arqel-ai-select-input__suggestion-pick"
          >
            Pick another
          </button>
        </div>
      ) : null}

      {error !== null ? (
        <div role="alert" className="arqel-ai-select-input__error">
          {error}
        </div>
      ) : null}
    </div>
  );
}

export default AiSelectInput;
