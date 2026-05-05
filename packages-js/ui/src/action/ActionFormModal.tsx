/**
 * `<ActionFormModal>` — modal hosting a `<FormRenderer>` for an Action's
 * companion form (see `arqel-dev/actions` `HasForm`).
 *
 * Action form fields are a flat list — Action FormSchema is built
 * inline from the `ActionFormField[]` since layout components don't
 * apply at this scale. Submit fires `onSubmit(values)`; the parent
 * (typically `useAction`) handles invocation + loading.
 */

import type { ActionSchema, ModalSize } from '@arqel-dev/types/actions';
import type { FieldSchema } from '@arqel-dev/types/fields';
import type { FormSchema } from '@arqel-dev/types/forms';
import { useMemo, useState } from 'react';
import { FormActions } from '../form/FormActions.js';
import { FormRenderer } from '../form/FormRenderer.js';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '../shadcn/ui/dialog.js';
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
  sm: 'sm:max-w-[24rem]',
  md: 'sm:max-w-[32rem]',
  lg: 'sm:max-w-[44rem]',
  xl: 'sm:max-w-[56rem]',
  full: 'sm:max-w-[90vw] h-[90vh]',
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
    <Dialog open={open} onOpenChange={(next) => onOpenChange(next)}>
      <DialogContent className={cn('overflow-auto', SIZE_CLASSES[size])}>
        <DialogHeader>
          <DialogTitle>{action.label}</DialogTitle>
        </DialogHeader>
        <form
          className="flex flex-col gap-4"
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
              <DialogClose asChild>
                <Button type="button" variant="ghost" disabled={processing}>
                  Cancel
                </Button>
              </DialogClose>
            }
          />
        </form>
      </DialogContent>
    </Dialog>
  );
}
