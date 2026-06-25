import type { FieldSchema } from '@arqel-dev/types/fields';
import type { FormSchema, GridEntry } from '@arqel-dev/types/forms';
import { FormRenderer } from '@arqel-dev/ui/form';
import type { JSX } from 'react';

/**
 * Demo surface (GET /admin/grid-form-demo) that mounts a real <FormRenderer>
 * over a single multi-column Grid layout — WITHOUT any Tabs wrapping it. The
 * showcase PostResource create form nests its Grid inside Tabs, conflating the
 * two surfaces; this standalone fixture isolates the Grid so the responsive
 * reflow ({ sm:1, md:2, lg:3 }) can be measured on its own by the Phase-1
 * responsive E2E. Standalone — it only needs `data-testid="grid-form-demo"`
 * visible after login.
 */

const FIELD_NAMES = ['field_a', 'field_b', 'field_c', 'field_d', 'field_e', 'field_f'] as const;

function textField(name: string, label: string): FieldSchema {
  return {
    type: 'text',
    name,
    label,
    component: null,
    required: false,
    readonly: false,
    disabled: false,
    placeholder: null,
    helperText: null,
    defaultValue: '',
    columnSpan: 1,
    live: false,
    liveDebounce: null,
    validation: { rules: [], messages: {}, attribute: null },
    visibility: { create: true, edit: true, detail: true, table: true, canSee: true },
    dependsOn: [],
    props: {},
  };
}

const fields: FieldSchema[] = FIELD_NAMES.map((name, i) =>
  textField(name, `Field ${String.fromCharCode(65 + i)}`),
);

const grid: GridEntry = {
  kind: 'layout',
  type: 'grid',
  component: 'Grid',
  columnSpan: 'full',
  props: { columns: { sm: 1, md: 2, lg: 3 }, gap: '1rem' },
  schema: FIELD_NAMES.map((name) => ({ kind: 'field', name, type: 'text' })),
};

const schema: FormSchema = {
  schema: [grid],
  columns: 1,
  model: null,
  inline: false,
  disabled: false,
};

export default function GridFormDemo(): JSX.Element {
  return (
    <main id="arqel-main" data-testid="grid-form-demo">
      <h1 className="mb-4 text-xl font-semibold">Grid Form Demo</h1>
      <FormRenderer schema={schema} fields={fields} values={{}} onChange={() => {}} />
    </main>
  );
}
