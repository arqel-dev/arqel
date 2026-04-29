/**
 * Default Inertia page for `arqel::create`.
 *
 * Renders a `<FormRenderer>` over the schema/fields the server
 * emits (with `record: null`). Submission goes through Inertia's
 * `useForm` via `useArqelForm` from `@arqel/hooks` — this page is
 * just the layout shell.
 */

import { useArqelForm } from '@arqel/hooks';
import type { FieldSchema } from '@arqel/types/fields';
import type { FormSchema } from '@arqel/types/forms';
import type { ResourceCreateProps } from '@arqel/types/resources';
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

export default function ArqelCreatePage(): JSX.Element {
  const page = usePage();
  const props = page.props as unknown as ResourceCreateProps;
  const fields = (props.fields ?? []) as FieldSchema[];
  const schema = (props as unknown as { form?: FormSchema }).form ?? FALLBACK_SCHEMA;

  const form = useArqelForm({ fields }) as unknown as ArqelFormShape;

  const submit = (e: FormEvent): void => {
    e.preventDefault();
    const slug = props.resource?.slug ?? '';
    router.post(`/${slug}`, form.data as Record<string, never>, {
      preserveScroll: true,
    });
  };

  return (
    <div className="space-y-6">
      <PageHeader title={`Create ${props.resource?.label ?? 'record'}`} description={null} />
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
