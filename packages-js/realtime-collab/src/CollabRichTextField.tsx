import { type ReactElement, useCallback, useEffect, useRef, useState } from 'react';
import * as Y from 'yjs';
import { encodeUpdate } from './encoders';
import { useYjsCollab } from './useYjsCollab';

export interface CollabRichTextFieldProps {
  modelType: string;
  modelId: string | number;
  field: string;
  /**
   * URL relativa para snapshot persistence (POST com `{state, version}`).
   */
  persistUrl?: string;
  /**
   * Debounce em ms antes do POST (default 2000).
   */
  debounceMs?: number;
  /**
   * Initial value antes da hidratação.
   */
  defaultValue?: string;
  className?: string;
  placeholder?: string;
}

const DEFAULT_DEBOUNCE_MS = 2000;

/**
 * Componente básico de rich text colaborativo via Yjs.
 *
 * Renderiza um `<textarea>` controlled cujo conteúdo é mantido em
 * sync com um `Y.Text`. Persiste snapshots no servidor com debounce.
 *
 * Integração ProseMirror/TipTap fica deferida — este scaffold
 * estabelece o contrato de sync; consumidores avançados podem
 * substituir o textarea por bindings y-prosemirror.
 */
export function CollabRichTextField(props: CollabRichTextFieldProps): ReactElement {
  const {
    modelType,
    modelId,
    field,
    persistUrl,
    debounceMs = DEFAULT_DEBOUNCE_MS,
    defaultValue = '',
    className,
    placeholder,
  } = props;

  const collab = useYjsCollab({
    modelType,
    modelId,
    field,
    ...(persistUrl !== undefined ? { persistUrl } : {}),
  });

  const [value, setValue] = useState<string>(defaultValue);
  const versionRef = useRef<number>(0);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Mantém o textarea em sync com o Y.Text remoto.
  useEffect(() => {
    const onUpdate = (): void => {
      setValue(collab.text.toString());
    };
    collab.text.observe(onUpdate);
    setValue(collab.text.toString() || defaultValue);
    return () => {
      collab.text.unobserve(onUpdate);
    };
  }, [collab.text, defaultValue]);

  const persistSnapshot = useCallback((): void => {
    if (typeof persistUrl !== 'string' || persistUrl === '') {
      return;
    }
    if (typeof fetch !== 'function') {
      return;
    }
    const update = Y.encodeStateAsUpdate(collab.doc);
    const body = JSON.stringify({
      state: encodeUpdate(update),
      version: versionRef.current,
    });
    fetch(persistUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body,
      credentials: 'same-origin',
    })
      .then((r) => (r.ok ? r.json() : null))
      .then((payload: unknown) => {
        if (
          payload !== null &&
          typeof payload === 'object' &&
          typeof (payload as { version?: unknown }).version === 'number'
        ) {
          versionRef.current = (payload as { version: number }).version;
        }
      })
      .catch(() => {
        // best-effort persist
      });
  }, [collab.doc, persistUrl]);

  const handleChange = (event: React.ChangeEvent<HTMLTextAreaElement>): void => {
    const next = event.target.value;
    setValue(next);

    // Replace Y.Text content
    collab.doc.transact(() => {
      collab.text.delete(0, collab.text.length);
      collab.text.insert(0, next);
    });

    if (timerRef.current !== null) {
      clearTimeout(timerRef.current);
    }
    timerRef.current = setTimeout(persistSnapshot, debounceMs);
  };

  // SSR-safe placeholder when running on server
  if (typeof window === 'undefined') {
    return (
      <textarea
        className={className}
        placeholder={placeholder}
        defaultValue={defaultValue}
        readOnly
      />
    );
  }

  return (
    <textarea
      className={className}
      placeholder={placeholder}
      value={value}
      onChange={handleChange}
      data-collab-status={collab.status}
    />
  );
}
