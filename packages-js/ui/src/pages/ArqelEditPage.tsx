/**
 * Default Inertia page for `arqel::edit`.
 *
 * Same shape as `ArqelCreatePage` but seeds the form with the
 * server-emitted `record`. Submission posts to PUT `/{slug}/{id}`.
 */

import { useArqelForm } from '@arqel/hooks';
import type { FieldSchema } from '@arqel/types/fields';
import type { FormSchema } from '@arqel/types/forms';
import type { RecordType, ResourceEditProps } from '@arqel/types/resources';
import { router, usePage } from '@inertiajs/react';
import type { FormEvent, JSX } from 'react';
import { FormActions } from '../form/FormActions.js';
import { FormRenderer } from '../form/FormRenderer.js';
import { PageHeader } from '../utility/PageHeader.js';

const FALLBACK_SCHEMA: FormSchema = {
  schema: [],
  columns: 1,
  model: null,
  inline: false,
  disabled: false,
};

interface ArqelFormShape {
  data: Record<string, unknown>;
  setData: (name: string, value: unknown) => void;
  errors: Record<string, string[]>;
  processing: boolean;
}

export default function ArqelEditPage<TRecord extends RecordType = RecordType>(): JSX.Element {
  const page = usePage();
  const props = page.props as unknown as ResourceEditProps<TRecord>;
  const fields = (props.fields ?? []) as FieldSchema[];
  const record = props.record;
  const schema = (props as unknown as { form?: FormSchema }).form ?? FALLBACK_SCHEMA;

  const form = useArqelForm({ fields, record }) as unknown as ArqelFormShape;

  const submit = (e: FormEvent): void => {
    e.preventDefault();
    const id = String((record as { id?: string | number } | undefined)?.id ?? '');
    const slug = props.resource?.slug ?? '';
    router.put(`/${slug}/${id}`, form.data as Record<string, never>, {
      preserveScroll: true,
    });
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title={props.recordTitle ?? `Edit ${props.resource?.label ?? 'record'}`}
        description={props.recordSubtitle ?? null}
      />
      <form onSubmit={submit} className="space-y-6">
        <FormRenderer
          schema={schema}
          fields={fields}
          values={form.data}
          onChange={(name, value) => form.setData(name, value)}
          errors={form.errors}
        />
        <FormActions processing={form.processing} onCancel={() => window.history.back()} />
      </form>
    </div>
  );
}
