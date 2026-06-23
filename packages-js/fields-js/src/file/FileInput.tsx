/**
 * `<FileInput>` — drag-drop file picker.
 *
 * Phase 1 stores the selected `File` object directly in the form
 * state; the actual upload happens server-side via Inertia's
 * multipart-aware form submission. Progress events are exposed
 * via `useArqelForm`'s `progress` field (Inertia native).
 */

import { useArqelTranslations } from '@arqel-dev/react/utils';
import type { FileFieldSchema } from '@arqel-dev/types/fields';
import type { FieldRendererProps } from '@arqel-dev/ui/form';
import { cn } from '@arqel-dev/ui/utils';
import { useState } from 'react';

export function FileInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as FileFieldSchema;
  const t = useArqelTranslations();
  const hasError = errors !== undefined && errors.length > 0;
  const isDisabled = disabled || f.disabled || f.readonly;
  const [dragOver, setDragOver] = useState(false);

  const file = value instanceof File ? value : null;
  const filename = file?.name ?? (typeof value === 'string' ? value : null);

  const handleFiles = (files: FileList | null) => {
    if (!files || files.length === 0) return;
    onChange(files[0]);
  };

  return (
    <section
      aria-label={t('arqel.fields.file.upload', 'File upload')}
      onDragOver={(e) => {
        e.preventDefault();
        if (!isDisabled) setDragOver(true);
      }}
      onDragLeave={() => setDragOver(false)}
      onDrop={(e) => {
        e.preventDefault();
        setDragOver(false);
        if (!isDisabled) handleFiles(e.dataTransfer.files);
      }}
      className={cn(
        'flex flex-col items-center justify-center gap-2 rounded-sm border-2 border-dashed px-4 py-6 text-sm',
        dragOver ? 'border-primary bg-muted' : 'border-border',
        hasError && 'border-destructive',
        isDisabled && 'opacity-50',
      )}
    >
      {filename ? (
        <span className="font-medium">{filename}</span>
      ) : (
        <span className="text-muted-foreground">
          {t('arqel.fields.file.drop_hint', 'Drag a file here or click to browse')}
        </span>
      )}
      <label className="cursor-pointer text-xs text-primary hover:underline">
        {filename
          ? t('arqel.fields.file.choose_another', 'Choose another file')
          : t('arqel.fields.file.browse', 'Browse')}
        <input
          id={inputId}
          type="file"
          className="sr-only"
          disabled={isDisabled}
          aria-invalid={hasError || undefined}
          aria-describedby={describedBy}
          onChange={(e) => handleFiles(e.target.files)}
        />
      </label>
    </section>
  );
}
