/**
 * `<AiImageInput>` — apresentational React component for the PHP
 * `Arqel\Ai\Fields\AiImageField` (component string `AiImageInput`).
 *
 * Render contract (AI-011):
 *   - File input restrito por `accept={props.acceptedMimes.join(',')}`
 *     com drag-drop area visual (`<label>` clicável que dispara o
 *     `<input type="file">` escondido).
 *   - Preview da imagem após selecionar (img src vem de
 *     `URL.createObjectURL` — só é chamado dentro do change handler,
 *     mantendo o render path SSR-safe).
 *   - Validação client-side: rejeita arquivos > `props.maxFileSize`
 *     (em bytes) com banner de erro; nesse caso o botão "Analyze with
 *     AI" fica `disabled`.
 *   - Botão `Analyze with AI` (label de `props.buttonLabel`); spinner
 *     inline + `disabled` durante o `fetch`. Sem arquivo selecionado o
 *     botão também fica `disabled`.
 *   - Após sucesso: lista preview `<dl>` com `analysis_key: value` por
 *     entry; cada entry tem botão `Apply` individual; toolbar mostra
 *     `Apply all`.
 *   - `Apply` chama `onPopulateField?.(target, value)` usando o
 *     mapeamento `props.populateFields[analysisKey]` quando disponível.
 *     `Apply all` itera todas as analyses que têm mapping.
 *   - Banner `role="alert"` quando o `fetch` falha (mensagem da
 *     response 422 quando disponível).
 *
 * Network:
 *   - URL = `props.analyzeUrl` override ou
 *     `/admin/${resource}/fields/${field}/analyze-image`.
 *   - Body: `{ imageBase64 }` produzido por `FileReader.readAsDataURL`
 *     (data URI completo).
 *   - Resposta: `{ analyses, populateMapping }` (200) ou
 *     `{ message }` (422).
 *
 * SSR-safe: nada no render path toca `window`/`URL`/`FileReader` — só
 * em handlers de evento.
 */

import { Alert, AlertDescription, Badge, Button, Card, CardContent } from '@arqel-dev/ui';
import { type ChangeEvent, type ReactElement, useCallback, useId, useState } from 'react';

export interface AiImageInputFieldProps {
  analyses: string[];
  populateFields: Record<string, string>;
  provider?: string | null;
  acceptedMimes: string[];
  maxFileSize: number;
  buttonLabel: string;
}

export interface AiImageInputProps {
  name: string;
  value: string | null;
  onChange?: (value: string) => void;
  props: AiImageInputFieldProps | undefined;
  resource?: string;
  field?: string;
  analyzeUrl?: string;
  csrfToken?: string;
  onPopulateField?: (targetField: string, value: string) => void;
}

interface AiImageResponseBody {
  analyses?: unknown;
  populateMapping?: unknown;
  message?: unknown;
}

function buildAnalyzeUrl(
  override: string | undefined,
  resource: string | undefined,
  field: string | undefined,
): string | null {
  if (override !== undefined && override !== '') {
    return override;
  }
  if (resource && field) {
    return `/admin/${resource}/fields/${field}/analyze-image`;
  }
  return null;
}

function isStringRecord(input: unknown): input is Record<string, string> {
  if (input === null || typeof input !== 'object' || Array.isArray(input)) {
    return false;
  }
  for (const value of Object.values(input as Record<string, unknown>)) {
    if (typeof value !== 'string') {
      return false;
    }
  }
  return true;
}

function readFileAsDataUrl(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      const result = reader.result;
      if (typeof result === 'string') {
        resolve(result);
        return;
      }
      reject(new Error('FileReader returned non-string result.'));
    };
    reader.onerror = () => {
      reject(reader.error ?? new Error('FileReader error.'));
    };
    reader.readAsDataURL(file);
  });
}

function formatBytes(bytes: number): string {
  if (bytes >= 1_048_576) {
    return `${(bytes / 1_048_576).toFixed(1)} MB`;
  }
  if (bytes >= 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`;
  }
  return `${String(bytes)} B`;
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

export function AiImageInput(props: AiImageInputProps): ReactElement {
  const {
    name,
    value,
    onChange,
    props: fieldProps,
    resource,
    field,
    analyzeUrl,
    csrfToken,
    onPopulateField,
  } = props;

  const analyses = fieldProps?.analyses ?? [];
  const populateFields = fieldProps?.populateFields ?? {};
  const acceptedMimes = fieldProps?.acceptedMimes ?? [];
  const maxFileSize = fieldProps?.maxFileSize ?? 0;
  const buttonLabel = fieldProps?.buttonLabel ?? 'Analyze with AI';

  const reactId = useId();
  const inputId = `arqel-ai-image-${reactId}`;

  const [file, setFile] = useState<File | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [results, setResults] = useState<Record<string, string> | null>(null);
  const [populateMapping, setPopulateMapping] = useState<Record<string, string>>(populateFields);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const handleFileChange = useCallback(
    (event: ChangeEvent<HTMLInputElement>) => {
      const next = event.target.files?.[0] ?? null;
      setError(null);
      setResults(null);

      if (next === null) {
        setFile(null);
        setPreviewUrl(null);
        return;
      }

      if (maxFileSize > 0 && next.size > maxFileSize) {
        setError(`File too large: ${formatBytes(next.size)} (max ${formatBytes(maxFileSize)}).`);
        setFile(null);
        setPreviewUrl(null);
        return;
      }

      setFile(next);
      try {
        setPreviewUrl(URL.createObjectURL(next));
      } catch {
        setPreviewUrl(null);
      }
    },
    [maxFileSize],
  );

  const handleAnalyze = useCallback(async (): Promise<void> => {
    if (file === null) {
      return;
    }
    const url = buildAnalyzeUrl(analyzeUrl, resource, field);
    if (url === null) {
      setError('Missing analyze URL: provide `analyzeUrl` or both `resource` and `field`.');
      return;
    }

    setIsLoading(true);
    setError(null);

    try {
      const imageBase64 = await readFileAsDataUrl(file);
      const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken ?? '',
        },
        body: JSON.stringify({ imageBase64 }),
      });

      if (!response.ok) {
        let message: string | null = null;
        try {
          const body = (await response.json()) as AiImageResponseBody;
          if (typeof body.message === 'string' && body.message !== '') {
            message = body.message;
          }
        } catch {
          message = null;
        }
        setError(message ?? `Analysis failed (HTTP ${String(response.status)}).`);
        return;
      }

      const body = (await response.json()) as AiImageResponseBody;
      if (!isStringRecord(body.analyses)) {
        setError('Analysis failed: invalid response body.');
        return;
      }

      setResults(body.analyses);
      if (isStringRecord(body.populateMapping)) {
        setPopulateMapping(body.populateMapping);
      }
      // Promove o data URI ao value do field caso o consumidor controle
      // o value via onChange — útil para quem queira persistir a imagem
      // selecionada como base64 (ou trocar por uma URL upload-side).
      if (onChange !== undefined) {
        onChange(imageBase64);
      }
    } catch {
      setError('Analysis failed: network error.');
    } finally {
      setIsLoading(false);
    }
  }, [analyzeUrl, csrfToken, field, file, onChange, resource]);

  const applyOne = useCallback(
    (analysisKey: string, val: string) => {
      const target = populateMapping[analysisKey];
      if (target === undefined || target === '') {
        return;
      }
      onPopulateField?.(target, val);
    },
    [onPopulateField, populateMapping],
  );

  const applyAll = useCallback(
    (entries: Record<string, string>) => {
      for (const key of Object.keys(entries)) {
        const target = populateMapping[key];
        const val = entries[key];
        if (target === undefined || target === '' || val === undefined) {
          continue;
        }
        onPopulateField?.(target, val);
      }
    },
    [onPopulateField, populateMapping],
  );

  const accept = acceptedMimes.join(',');
  const hasResults = results !== null && Object.keys(results).length > 0;
  const canAnalyze = file !== null && !isLoading;

  return (
    <div
      className="flex flex-col gap-2"
      data-arqel-field="aiImage"
      data-field-name={name}
      data-has-value={value !== null && value !== '' ? 'true' : 'false'}
    >
      <label
        htmlFor={inputId}
        className="flex items-center justify-center w-full min-h-[6rem] rounded-sm border-2 border-dashed border-border bg-muted/40 px-4 py-6 text-sm text-muted-foreground hover:bg-muted cursor-pointer transition-colors"
      >
        <span>
          {file !== null ? file.name : `Click or drop image (${accept !== '' ? accept : 'any'})`}
        </span>
        <input
          id={inputId}
          type="file"
          accept={accept}
          onChange={handleFileChange}
          className="sr-only"
          aria-label="Image file"
        />
      </label>

      {previewUrl !== null ? (
        <Card>
          <CardContent className="p-2 flex items-center justify-center">
            <img
              src={previewUrl}
              alt="Selected preview"
              data-testid="image-preview"
              className="max-h-64 max-w-full rounded-sm"
            />
          </CardContent>
        </Card>
      ) : null}

      <div className="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          onClick={() => {
            void handleAnalyze();
          }}
          disabled={!canAnalyze}
          aria-label={buttonLabel}
        >
          {isLoading ? (
            <span role="status" aria-label="Analyzing">
              <Spinner />
            </span>
          ) : null}
          <span>{buttonLabel}</span>
        </Button>

        {hasResults && results !== null ? (
          <Button
            variant="default"
            size="sm"
            onClick={() => {
              applyAll(results);
            }}
          >
            Apply all
          </Button>
        ) : null}
      </div>

      {hasResults && results !== null ? (
        <Card>
          <CardContent className="p-4">
            <dl className="flex flex-col gap-2">
              {(analyses.length > 0 ? analyses : Object.keys(results)).map((key) => {
                const val = results[key];
                if (val === undefined) {
                  return null;
                }
                const target = populateMapping[key];
                const hasTarget = target !== undefined && target !== '';
                return (
                  <div
                    key={key}
                    className="flex items-center justify-between gap-2 border-b border-border pb-2 last:border-b-0 last:pb-0"
                  >
                    <dt>
                      <Badge variant="outline">{key}</Badge>
                    </dt>
                    <dd className="flex items-center gap-2 flex-1 justify-end">
                      <span
                        className="text-sm text-foreground truncate"
                        data-testid={`analysis-value-${key}`}
                      >
                        {val}
                      </span>
                      {hasTarget ? (
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => {
                            applyOne(key, val);
                          }}
                          aria-label={`Apply ${key}`}
                        >
                          Apply
                        </Button>
                      ) : null}
                    </dd>
                  </div>
                );
              })}
            </dl>
          </CardContent>
        </Card>
      ) : null}

      {error !== null ? (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      ) : null}
    </div>
  );
}

export default AiImageInput;
