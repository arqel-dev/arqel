/**
 * `<FormRenderer>` — recursive walker over `FormSchema`.
 *
 * Each `SchemaEntry` dispatches to the matching layout component
 * (`Section`, `Fieldset`, `Grid`, `Tabs`, …) or to `<FieldRenderer>`
 * when it's a leaf. `Tabs` collects child `Tab` entries so the
 * keyboard-accessible `<FormTabs>` can render them as panels.
 *
 * The renderer is *presentational*: state lives in the caller (typically
 * `useArqelForm`). `value(name)` and `setValue(name, value)` resolve
 * field state, while `errors[name]` carries server/client validation
 * messages.
 */

import type { FieldSchema } from '@arqel/types/fields';
import {
  type FieldEntry,
  type FormSchema,
  isFieldEntry,
  isLayoutEntry,
  type LayoutEntry,
  type SchemaEntry,
  type TabEntry,
} from '@arqel/types/forms';
import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';
import { FieldRenderer } from './FieldRenderer.js';
import { FormFieldset } from './FormFieldset.js';
import { FormGrid } from './FormGrid.js';
import { FormSection } from './FormSection.js';
import { type FormTabConfig, FormTabs } from './FormTabs.js';

export interface FormRendererProps {
  schema: FormSchema;
  fields: FieldSchema[];
  values: Record<string, unknown>;
  errors?: Record<string, string[]>;
  onChange: (name: string, value: unknown) => void;
  disabled?: boolean;
  className?: string;
}

export function FormRenderer({
  schema,
  fields,
  values,
  errors = {},
  onChange,
  disabled = false,
  className,
}: FormRendererProps) {
  const fieldsByName = new Map(fields.map((f) => [f.name, f]));

  const renderEntry = (entry: SchemaEntry, key: string): ReactNode => {
    if (isFieldEntry(entry)) {
      return renderFieldEntry(entry, key);
    }
    if (isLayoutEntry(entry)) {
      return renderLayoutEntry(entry, key);
    }
    return null;
  };

  const renderFieldEntry = (entry: FieldEntry, key: string): ReactNode => {
    const field = fieldsByName.get(entry.name);
    if (!field) return null;
    return (
      <FieldRenderer
        key={key}
        field={field}
        value={values[entry.name]}
        errors={errors[entry.name]}
        disabled={disabled || schema.disabled === true}
        onChange={(value) => onChange(entry.name, value)}
      />
    );
  };

  const renderChildren = (entries: SchemaEntry[] | undefined): ReactNode => {
    if (!entries) return null;
    return entries.map((child, i) => renderEntry(child, `${i}`));
  };

  const renderLayoutEntry = (entry: LayoutEntry, key: string): ReactNode => {
    switch (entry.type) {
      case 'section':
        return (
          <FormSection key={key} config={entry.props}>
            {renderChildren(entry.schema)}
          </FormSection>
        );
      case 'fieldset':
        return (
          <FormFieldset key={key} config={entry.props}>
            {renderChildren(entry.schema)}
          </FormFieldset>
        );
      case 'grid':
      case 'columns':
        return (
          <FormGrid
            key={key}
            config={entry.type === 'columns' ? { columns: entry.props.columns } : entry.props}
          >
            {renderChildren(entry.schema)}
          </FormGrid>
        );
      case 'group':
        return (
          <div
            key={key}
            className={cn(
              'flex gap-4',
              entry.props.orientation === 'vertical' ? 'flex-col' : 'flex-row',
            )}
          >
            {renderChildren(entry.schema)}
          </div>
        );
      case 'tabs': {
        const tabs: FormTabConfig[] = (entry.schema ?? [])
          .filter((c): c is TabEntry => isLayoutEntry(c) && c.type === 'tab')
          .map((tab) => ({
            id: tab.props.id,
            label: tab.props.label,
            badge: tab.props.badge,
            content: renderChildren(tab.schema) ?? <span key={`tab-${tab.props.id}-empty`} />,
          }));
        return <FormTabs key={key} config={entry.props} tabs={tabs} />;
      }
      case 'tab':
        // Tabs collect their children directly; lone Tab entries are
        // a no-op to avoid rendering the same content twice.
        return null;
    }
  };

  const columns = schema.columns ?? 1;

  return (
    <div
      data-arqel-form=""
      className={cn('grid gap-4', className)}
      style={
        columns > 1 ? { gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` } : undefined
      }
    >
      {schema.schema.map((entry, i) => renderEntry(entry, `${i}`))}
    </div>
  );
}
