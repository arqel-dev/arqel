/**
 * Default Inertia page for `arqel::show`.
 *
 * Read-only render of the record. Re-uses `<FormRenderer>` with
 * every field forced to disabled — keeps a single rendering path
 * for create/edit/show without a parallel "DetailRenderer" stack.
 */

import type { FieldSchema } from '@arqel/types/fields';
import type { FormSchema } from '@arqel/types/forms';
import type { RecordType, ResourceDetailProps } from '@arqel/types/resources';
import { usePage } from '@inertiajs/react';
import type { JSX } from 'react';
import { FormRenderer } from '../form/FormRenderer.js';
import { PageHeader } from '../utility/PageHeader.js';

const FALLBACK_SCHEMA: FormSchema = {
  schema: [],
  columns: 1,
  model: null,
  inline: false,
  disabled: true,
};

export default function ArqelShowPage<TRecord extends RecordType = RecordType>(): JSX.Element {
  const page = usePage();
  const props = page.props as unknown as ResourceDetailProps<TRecord>;
  const fields = (props.fields ?? []) as FieldSchema[];

  // Seed values from the record so FormRenderer has something to
  // display. Detail mode disables every field.
  const values: Record<string, unknown> = {};
  for (const field of fields) {
    const name = field.name;
    if (typeof name === 'string' && props.record !== null && typeof props.record === 'object') {
      values[name] = (props.record as Record<string, unknown>)[name];
    }
  }

  const declaredForm = (props as unknown as { form?: FormSchema }).form;
  const schema: FormSchema = declaredForm ? { ...declaredForm, disabled: true } : FALLBACK_SCHEMA;

  return (
    <div className="space-y-6">
      <PageHeader
        title={props.recordTitle ?? props.resource?.label ?? 'Record'}
        description={props.recordSubtitle ?? null}
      />
      <FormRenderer
        schema={schema}
        fields={fields}
        values={values}
        onChange={() => {
          // No-op — show pages are read-only.
        }}
        errors={{}}
      />
    </div>
  );
}
