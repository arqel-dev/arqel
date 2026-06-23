/**
 * Default Inertia page for `arqel::create`.
 *
 * Renders a `<FormRenderer>` over the schema/fields the server
 * emits (with `record: null`). Submission goes through Inertia's
 * `useForm` via `useArqelForm` from `@arqel-dev/hooks` — this page is
 * just the layout shell.
 */

import { useArqelForm } from '@arqel-dev/hooks';
import { useArqelTranslations } from '@arqel-dev/react/utils';
import type { FieldSchema } from '@arqel-dev/types/fields';
import type { FormSchema } from '@arqel-dev/types/forms';
import type { ResourceCreateProps } from '@arqel-dev/types/resources';
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
  const t = useArqelTranslations();
  const props = page.props as unknown as ResourceCreateProps;
  const fields = (props.fields ?? []) as FieldSchema[];
  const schema = (props as unknown as { form?: FormSchema }).form ?? FALLBACK_SCHEMA;

  const form = useArqelForm({ fields }) as unknown as ArqelFormShape;

  const submit = (e: FormEvent): void => {
    e.preventDefault();
    const slug = props.resource?.slug ?? '';
    const basePath = props.resource?.panelPath ?? '/admin';
    router.post(`${basePath}/${slug}`, form.data as Record<string, never>, {
      preserveScroll: true,
    });
  };

  // Pre-interpolate the label into the English fallback so the title stays
  // correct even when the `arqel.pages.create` key is absent from the shared
  // dictionary (the hook does not re-interpolate a raw fallback).
  const label = props.resource?.label ?? t('arqel.pages.fallback', 'record');
  const createTitle = t('arqel.pages.create', `Create ${label}`, { label });

  return (
    <div className="space-y-6">
      <PageHeader title={createTitle} description={null} />
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
