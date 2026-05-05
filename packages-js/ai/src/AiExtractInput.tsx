/**
 * `<AiExtractInput>` — apresentational React component for the PHP
 * `Arqel\Ai\Fields\AiExtractField` (component string `AiExtractInput`).
 *
 * Render contract (AI-010):
 *   - Read-only label `Source: {sourceField}` indicando o campo do
 *     formulário de onde o texto-fonte é lido.
 *   - Botão `Extract with AI` (label vem de `props.buttonLabel`); em
 *     loading o botão fica `disabled` + spinner inline.
 *   - Empty state `No extraction yet — click button to start` antes da
 *     primeira extração.
 *   - Após sucesso: lista preview `<dl>` com cada `targetField:
 *     extractedValue`; cada entry tem botão `Apply` individual e o
 *     toolbar mostra `Apply all`.
 *   - `Apply` chama `onPopulateField?.(targetField, value)` ou
 *     `onChange(extracted)` se `onPopulateField` for ausente.
 *   - Banner `role="alert"` quando o `fetch` falha (com `message` da
 *     response 422 quando disponível).
 *
 * Network:
 *   - URL = `props.extractUrl` override ou
 *     `/admin/${resource}/fields/${field}/extract`.
 *   - Body: `{ sourceText }` lido de `formData?.[sourceField] ?? ''`.
 *   - Resposta: `{ extracted: Record<string, unknown> }` (200) ou
 *     `{ message }` (422).
 *
 * SSR-safe: nada no render path toca `window`/`document`.
 */

import { Alert, AlertDescription, Badge, Button, Card, CardContent, Label } from '@arqel-dev/ui';
import { type ReactElement, useCallback, useId, useState } from 'react';

export interface AiExtractInputFieldProps {
  sourceField: string;
  targetFields: string[];
  buttonLabel: string;
  usingJsonMode: boolean;
  provider?: string | null;
}

export type AiExtractValue = Record<string, unknown>;

export interface AiExtractInputProps {
  name: string;
  value: AiExtractValue | null;
  onChange?: (value: AiExtractValue) => void;
  props: AiExtractInputFieldProps | undefined;
  resource?: string;
  field?: string;
  formData?: Record<string, unknown>;
  extractUrl?: string;
  csrfToken?: string;
  onPopulateField?: (targetField: string, value: unknown) => void;
}

interface AiExtractResponseBody {
  extracted?: unknown;
  message?: unknown;
}

function buildExtractUrl(
  override: string | undefined,
  resource: string | undefined,
  field: string | undefined,
): string | null {
  if (override !== undefined && override !== '') {
    return override;
  }
  if (resource && field) {
    return `/admin/${resource}/fields/${field}/extract`;
  }
  return null;
}

function isPlainRecord(input: unknown): input is Record<string, unknown> {
  return input !== null && typeof input === 'object' && !Array.isArray(input);
}

function coerceSourceText(raw: unknown): string {
  if (typeof raw === 'string') {
    return raw;
  }
  if (raw === null || raw === undefined) {
    return '';
  }
  if (typeof raw === 'number' || typeof raw === 'boolean') {
    return String(raw);
  }
  return '';
}

function formatPreviewValue(value: unknown): string {
  if (value === null || value === undefined) {
    return '';
  }
  if (typeof value === 'string') {
    return value;
  }
  if (typeof value === 'number' || typeof value === 'boolean') {
    return String(value);
  }
  try {
    return JSON.stringify(value);
  } catch {
    return String(value);
  }
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

export function AiExtractInput(props: AiExtractInputProps): ReactElement {
  const {
    name,
    value,
    onChange,
    props: fieldProps,
    resource,
    field,
    formData,
    extractUrl,
    csrfToken,
    onPopulateField,
  } = props;

  const sourceField = fieldProps?.sourceField ?? '';
  const targetFields = fieldProps?.targetFields ?? [];
  const buttonLabel = fieldProps?.buttonLabel ?? 'Extract with AI';

  const reactId = useId();
  const previewId = `arqel-ai-extract-${reactId}`;

  const [extracted, setExtracted] = useState<AiExtractValue | null>(value);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const applyAll = useCallback(
    (next: AiExtractValue) => {
      if (onPopulateField !== undefined) {
        for (const key of Object.keys(next)) {
          onPopulateField(key, next[key]);
        }
        return;
      }
      onChange?.(next);
    },
    [onChange, onPopulateField],
  );

  const applyOne = useCallback(
    (target: string, val: unknown) => {
      if (onPopulateField !== undefined) {
        onPopulateField(target, val);
        return;
      }
      onChange?.({ [target]: val });
    },
    [onChange, onPopulateField],
  );

  const handleExtract = useCallback(async (): Promise<void> => {
    const url = buildExtractUrl(extractUrl, resource, field);
    if (url === null) {
      setError('Missing extract URL: provide `extractUrl` or both `resource` and `field`.');
      return;
    }

    const sourceText = coerceSourceText(
      formData !== undefined && sourceField !== '' ? formData[sourceField] : '',
    );

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
        body: JSON.stringify({ sourceText }),
      });

      if (!response.ok) {
        let message: string | null = null;
        try {
          const body = (await response.json()) as AiExtractResponseBody;
          if (typeof body.message === 'string' && body.message !== '') {
            message = body.message;
          }
        } catch {
          message = null;
        }
        setError(message ?? `Extraction failed (HTTP ${String(response.status)}).`);
        return;
      }

      const body = (await response.json()) as AiExtractResponseBody;
      if (!isPlainRecord(body.extracted)) {
        setError('Extraction failed: invalid response body.');
        return;
      }

      setExtracted(body.extracted);
    } catch {
      setError('Extraction failed: network error.');
    } finally {
      setIsLoading(false);
    }
  }, [csrfToken, extractUrl, field, formData, resource, sourceField]);

  const hasExtraction = extracted !== null && Object.keys(extracted).length > 0;

  return (
    <div className="flex flex-col gap-2" data-arqel-field="aiExtract" data-field-name={name}>
      <div>
        <Label className="text-muted-foreground">Source: {sourceField}</Label>
      </div>

      <div className="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          onClick={() => {
            void handleExtract();
          }}
          disabled={isLoading}
          aria-label={buttonLabel}
        >
          {isLoading ? (
            <span role="status" aria-label="Extracting">
              <Spinner />
            </span>
          ) : null}
          <span>{buttonLabel}</span>
        </Button>

        {hasExtraction && extracted !== null ? (
          <Button
            variant="default"
            size="sm"
            onClick={() => {
              applyAll(extracted);
            }}
          >
            Apply all
          </Button>
        ) : null}
      </div>

      {hasExtraction && extracted !== null ? (
        <Card>
          <CardContent className="p-4">
            <dl id={previewId} className="flex flex-col gap-2">
              {(targetFields.length > 0 ? targetFields : Object.keys(extracted)).map((target) => {
                const val = extracted[target];
                return (
                  <div
                    key={target}
                    className="flex items-center justify-between gap-2 border-b border-border pb-2 last:border-b-0 last:pb-0"
                  >
                    <dt>
                      <Badge variant="outline">{target}</Badge>
                    </dt>
                    <dd className="flex items-center gap-2 flex-1 justify-end">
                      <span
                        className="text-sm text-foreground truncate"
                        data-testid={`extract-value-${target}`}
                      >
                        {formatPreviewValue(val)}
                      </span>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                          applyOne(target, val);
                        }}
                        aria-label={`Apply ${target}`}
                      >
                        Apply
                      </Button>
                    </dd>
                  </div>
                );
              })}
            </dl>
          </CardContent>
        </Card>
      ) : (
        <p className="text-sm text-muted-foreground italic">
          No extraction yet — click button to start.
        </p>
      )}

      {error !== null ? (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      ) : null}
    </div>
  );
}

export default AiExtractInput;
