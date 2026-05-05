/**
 * `<AiTextInput>` — apresentational React component for the PHP
 * `Arqel\Ai\Fields\AiTextField` (component string `AiTextInput`).
 *
 * Render contract (AI-007):
 *   - `<textarea>` displaying the current value (controlled if
 *     `onChange` is provided, otherwise uncontrolled with internal
 *     state seeded by `value`),
 *   - "Generate with AI" button (label from `props.buttonLabel`,
 *     fallback `Generate with AI`); after a successful generation the
 *     button label becomes `Regenerate`,
 *   - while a request is in-flight the button is disabled and shows a
 *     small inline spinner,
 *   - on failure a banner with a generic message + HTTP status code is
 *     rendered; the button re-enables so the user can retry,
 *   - optional `X / maxLength` counter when `props.maxLength` is set.
 *
 * Network:
 *   - URL = `props.generateUrl` (caller override) or
 *     `/admin/${resource}/fields/${field}/generate` (default route name
 *     `arqel.ai.generate`).
 *   - `fetch(url, { method: 'POST', credentials: 'same-origin', headers:
 *     { Content-Type, Accept, X-CSRF-TOKEN }, body: JSON.stringify({
 *     formData }) })`. The PHP controller resolves the prompt template
 *     server-side and returns `{ text }`.
 *
 * SSR-safe: nothing in the render path touches `window`/`document`;
 * the network call only fires from the click handler (post-mount).
 */

import { Alert, AlertDescription, Button, Textarea } from '@arqel-dev/ui';
import { type ChangeEvent, type ReactElement, useCallback, useId, useState } from 'react';

export interface AiTextInputFieldProps {
  provider?: string | null;
  buttonLabel: string;
  maxLength?: number | null;
  hasContextFields: boolean;
}

export interface AiTextInputProps {
  name: string;
  value: string;
  onChange?: (value: string) => void;
  props: AiTextInputFieldProps | undefined;
  resource?: string;
  field?: string;
  formData?: Record<string, unknown>;
  generateUrl?: string;
  csrfToken?: string;
}

interface AiGenerateResponseBody {
  text?: unknown;
}

const DEFAULT_BUTTON_LABEL = 'Generate with AI';
const REGENERATE_LABEL = 'Regenerate';

function buildGenerateUrl(
  override: string | undefined,
  resource: string | undefined,
  field: string | undefined,
): string | null {
  if (override !== undefined && override !== '') {
    return override;
  }
  if (resource && field) {
    return `/admin/${resource}/fields/${field}/generate`;
  }
  return null;
}

function Spinner(): ReactElement {
  return (
    <svg
      width="14"
      height="14"
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
      className="animate-spin"
    >
      <circle cx="12" cy="12" r="10" stroke="currentColor" strokeOpacity="0.25" strokeWidth="4" />
      <path
        d="M22 12a10 10 0 0 1-10 10"
        stroke="currentColor"
        strokeWidth="4"
        strokeLinecap="round"
      />
    </svg>
  );
}

export function AiTextInput(props: AiTextInputProps): ReactElement {
  const {
    name,
    value,
    onChange,
    props: fieldProps,
    resource,
    field,
    formData,
    generateUrl,
    csrfToken,
  } = props;

  const buttonLabel = fieldProps?.buttonLabel ?? DEFAULT_BUTTON_LABEL;
  const maxLength = fieldProps?.maxLength ?? null;

  const reactId = useId();
  const textareaId = `arqel-ai-text-${reactId}`;

  const [internalValue, setInternalValue] = useState<string>(value);
  const isControlled = onChange !== undefined;
  const currentValue = isControlled ? value : internalValue;

  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const [hasGenerated, setHasGenerated] = useState<boolean>(false);

  const handleTextareaChange = useCallback(
    (event: ChangeEvent<HTMLTextAreaElement>) => {
      const next = event.target.value;
      if (isControlled && onChange) {
        onChange(next);
      } else {
        setInternalValue(next);
      }
    },
    [isControlled, onChange],
  );

  const applyGeneratedText = useCallback(
    (next: string) => {
      if (isControlled && onChange) {
        onChange(next);
      } else {
        setInternalValue(next);
      }
    },
    [isControlled, onChange],
  );

  const handleGenerate = useCallback(async (): Promise<void> => {
    const url = buildGenerateUrl(generateUrl, resource, field);
    if (url === null) {
      setError('Missing generate URL: provide `generateUrl` or both `resource` and `field`.');
      return;
    }

    setIsLoading(true);
    setError(null);

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
        setError(`Generation failed (HTTP ${String(response.status)}).`);
        return;
      }

      const body = (await response.json()) as AiGenerateResponseBody;
      const text = typeof body.text === 'string' ? body.text : '';
      applyGeneratedText(text);
      setHasGenerated(true);
    } catch {
      setError('Generation failed: network error.');
    } finally {
      setIsLoading(false);
    }
  }, [applyGeneratedText, csrfToken, field, formData, generateUrl, resource]);

  const triggerLabel = hasGenerated ? REGENERATE_LABEL : buttonLabel;
  const charCount = currentValue.length;

  return (
    <div className="flex flex-col gap-2" data-arqel-field="aiText" data-field-name={name}>
      <Textarea
        id={textareaId}
        name={name}
        value={currentValue}
        onChange={handleTextareaChange}
        {...(maxLength !== null ? { maxLength } : {})}
        rows={6}
      />

      {maxLength !== null ? (
        <div className="text-xs text-muted-foreground self-end" aria-live="polite">
          {charCount} / {maxLength}
        </div>
      ) : null}

      <div className="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          onClick={() => {
            void handleGenerate();
          }}
          disabled={isLoading}
          aria-label={triggerLabel}
        >
          {isLoading ? (
            <span role="status" aria-label="Generating">
              <Spinner />
            </span>
          ) : null}
          <span>{triggerLabel}</span>
        </Button>
      </div>

      {error !== null ? (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      ) : null}
    </div>
  );
}

export default AiTextInput;
