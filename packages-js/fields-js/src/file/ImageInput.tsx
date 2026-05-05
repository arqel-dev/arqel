/**
 * `<ImageInput>` — image picker with preview.
 *
 * Phase 1 ships preview-only (URL or local `URL.createObjectURL`).
 * Crop / aspect-ratio editor lands in Phase 2 with `react-image-crop`.
 */

import type { ImageFieldSchema } from '@arqel-dev/types/fields';
import type { FieldRendererProps } from '@arqel-dev/ui/form';
import { cn } from '@arqel-dev/ui/utils';
import { useEffect, useState } from 'react';

export function ImageInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as ImageFieldSchema;
  const hasError = errors !== undefined && errors.length > 0;
  const isDisabled = disabled || f.disabled || f.readonly;

  const [previewUrl, setPreviewUrl] = useState<string | null>(null);

  useEffect(() => {
    if (value instanceof File) {
      const url = URL.createObjectURL(value);
      setPreviewUrl(url);
      return () => URL.revokeObjectURL(url);
    }
    if (typeof value === 'string' && value.length > 0) {
      setPreviewUrl(value);
      return undefined;
    }
    setPreviewUrl(null);
    return undefined;
  }, [value]);

  return (
    <div className="flex flex-col gap-2">
      {previewUrl && (
        <img
          src={previewUrl}
          alt="Preview"
          className={cn('max-h-40 w-auto rounded-sm border border-border')}
        />
      )}
      <label
        className={cn(
          'inline-flex w-fit cursor-pointer items-center gap-2 rounded-sm border border-border px-3 py-1.5 text-sm',
          'hover:bg-muted',
          isDisabled && 'cursor-not-allowed opacity-50',
        )}
      >
        {previewUrl ? 'Replace image' : 'Choose image'}
        <input
          id={inputId}
          type="file"
          accept="image/*"
          className="sr-only"
          disabled={isDisabled}
          aria-invalid={hasError || undefined}
          aria-describedby={describedBy}
          onChange={(e) => {
            const file = e.target.files?.[0];
            if (file) onChange(file);
          }}
        />
      </label>
    </div>
  );
}
