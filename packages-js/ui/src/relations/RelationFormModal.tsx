/**
 * `<RelationFormModal>` — create/edit a related record from within a
 * `RelationManagerPanel` tab. Reuses `<FormRenderer>` for the field list
 * (a flat schema built from `relation.fields`, mirroring how
 * `<ActionFormModal>` derives a `FormSchema` from a flat `ActionFormField[]`)
 * and submits via Inertia `router.post`/`router.put`.
 *
 * On success the modal closes and calls `onSuccess` (if given) so the
 * caller can refresh the relation's table — records now come from
 * `RelationManagerPanel`'s own `fetch()` to `RelationController::index()`
 * (Task 13a), not from an Inertia `relations` prop, so a partial reload
 * would no longer do anything useful here; `ResourceEditTabs` uses
 * `onSuccess` to bump that panel's `refreshKey`.
 */

import { useArqelTranslations } from '@arqel-dev/react/utils';
import type { FieldSchema } from '@arqel-dev/types/fields';
import type { FormSchema } from '@arqel-dev/types/forms';
import type { RelationManagerProps } from '@arqel-dev/types/relations';
import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { FormActions } from '../form/FormActions.js';
import { FormRenderer, type FormRendererProps } from '../form/FormRenderer.js';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '../shadcn/ui/dialog.js';

export interface RelationFormModalProps {
  open: boolean;
  onClose(): void;
  relation: RelationManagerProps;
  parentSlug: string;
  parentId: string | number;
  basePath?: string;
  recordId?: string | number;
  initialValues?: Record<string, unknown>;
  onSuccess?: () => void;
}

export function RelationFormModal({
  open,
  onClose,
  relation,
  parentSlug,
  parentId,
  basePath = '/admin',
  recordId,
  initialValues = {},
  onSuccess,
}: RelationFormModalProps) {
  const t = useArqelTranslations();
  const [values, setValues] = useState<Record<string, unknown>>(initialValues);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [processing, setProcessing] = useState(false);

  const fields = relation.fields as FieldSchema[];

  const schema: FormSchema = useMemo(
    () => ({
      schema: fields.map((field) => ({
        kind: 'field' as const,
        name: field.name,
        type: field.type,
      })),
      columns: 1,
      model: null,
      inline: false,
      disabled: false,
    }),
    [fields],
  );

  if (!open) return null;

  const base = `${basePath}/${parentSlug}/${parentId}/relations/${relation.slug}`;
  const url = recordId ? `${base}/${recordId}` : base;
  const method = recordId ? 'put' : 'post';

  const submit = () => {
    setProcessing(true);
    router[method](url, values as Record<string, never>, {
      preserveScroll: true,
      onSuccess: () => {
        setProcessing(false);
        onSuccess?.();
        onClose();
      },
      onError: (formErrors: Record<string, string>) => {
        setProcessing(false);
        setErrors(
          Object.fromEntries(Object.entries(formErrors).map(([key, message]) => [key, [message]])),
        );
      },
    });
  };

  // `FormRenderer` itself is submit-agnostic (state lives in the caller);
  // the wrapping `<form onSubmit>` below drives real submission, mirroring
  // `ArqelEditPage`/`ActionFormModal`. The stray `onSubmit` in this typed
  // props bag is inert for the real component but lets a test double
  // (which renders a plain submit button) exercise the same `submit`.
  const formRendererProps: FormRendererProps & { onSubmit: () => void } = {
    schema,
    fields,
    values,
    errors,
    onChange: (name, value) => setValues((prev) => ({ ...prev, [name]: value })),
    disabled: processing,
    onSubmit: submit,
  };

  return (
    <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
      <DialogContent className="overflow-auto sm:max-w-[32rem]">
        <DialogHeader>
          <DialogTitle>{relation.label}</DialogTitle>
        </DialogHeader>
        <form
          className="flex flex-col gap-4"
          onSubmit={(e) => {
            e.preventDefault();
            submit();
          }}
        >
          <FormRenderer {...formRendererProps} />
          <FormActions
            processing={processing}
            submitLabel={t('form.save', 'Save')}
            onCancel={onClose}
          />
        </form>
      </DialogContent>
    </Dialog>
  );
}
