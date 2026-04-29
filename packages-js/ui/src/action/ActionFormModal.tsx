/**
 * `<ActionFormModal>` — modal hosting a `<FormRenderer>` for an Action's
 * companion form (see `arqel/actions` `HasForm`).
 *
 * Action form fields are a flat list — Action FormSchema is built
 * inline from the `ActionFormField[]` since layout components don't
 * apply at this scale. Submit fires `onSubmit(values)`; the parent
 * (typically `useAction`) handles invocation + loading.
 */

import type { ActionSchema, ModalSize } from '@arqel/types/actions';
import type { FieldSchema } from '@arqel/types/fields';
import type { FormSchema } from '@arqel/types/forms';
import { Dialog } from '@base-ui-components/react/dialog';
import { useMemo, useState } from 'react';
import { FormActions } from '../form/FormActions.js';
import { FormRenderer } from '../form/FormRenderer.js';
import { cn } from '../utils/cn.js';
import { Button } from './Button.js';

export interface ActionFormModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  action: ActionSchema;
  fields: FieldSchema[];
  onSubmit: (values: Record<string, unknown>) => void;
  processing?: boolean;
  errors?: Record<string, string[]> | undefined;
  initialValues?: Record<string, unknown>;
}

const SIZE_CLASSES: Record<ModalSize, string> = {
  sm: 'w-[24rem]',
  md: 'w-[32rem]',
  lg: 'w-[44rem]',
  xl: 'w-[56rem]',
  full: 'w-[90vw] h-[90vh]',
};

export function ActionFormModal({
  open,
  onOpenChange,
  action,
  fields,
  onSubmit,
  processing = false,
  errors = {},
  initialValues = {},
}: ActionFormModalProps) {
  const [values, setValues] = useState<Record<string, unknown>>(initialValues);
  const size = action.modalSize ?? 'md';

  const schema: FormSchema = useMemo(
    () => ({
      schema: (action.form ?? []).map((entry) => ({
        kind: 'field' as const,
        name: entry.name,
        type: entry.type,
      })),
      columns: 1,
      model: null,
      inline: false,
      disabled: false,
    }),
    [action.form],
  );

  return (
    <Dialog.Root open={open} onOpenChange={(next) => onOpenChange(next)} modal>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-40 bg-black/40" />
        <Dialog.Popup
          className={cn(
            'fixed left-1/2 top-1/2 z-50 max-w-[90vw] -translate-x-1/2 -translate-y-1/2 overflow-auto',
            'rounded-[var(--radius-arqel)] border border-[var(--color-arqel-border)] bg-[var(--color-arqel-bg)] p-6 shadow-xl outline-none',
            SIZE_CLASSES[size],
          )}
        >
          <Dialog.Title className="text-lg font-semibold">{action.label}</Dialog.Title>
          <form
            className="mt-4 flex flex-col gap-4"
            onSubmit={(e) => {
              e.preventDefault();
              onSubmit(values);
            }}
          >
            <FormRenderer
              schema={schema}
              fields={fields}
              values={values}
              errors={errors}
              onChange={(name, value) => setValues((prev) => ({ ...prev, [name]: value }))}
              disabled={processing}
            />
            <FormActions
              processing={processing}
              submitLabel={action.label}
              extra={
                <Dialog.Close
                  render={
                    <Button type="button" variant="ghost" disabled={processing}>
                      Cancel
                    </Button>
                  }
                />
              }
            />
          </form>
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
