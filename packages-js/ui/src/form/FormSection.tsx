/**
 * `<FormSection>` — heading + optional description + collapsible body.
 *
 * Renders a SectionEntry. Aside layout (heading on the left, body on the
 * right) ships behind the `aside` prop so wide forms can read like docs.
 */

import type { SectionProps } from '@arqel-dev/types/forms';
import { type ReactNode, useState } from 'react';
import { cn } from '../utils/cn.js';

export interface FormSectionProps {
  config: SectionProps;
  children: ReactNode;
  className?: string;
}

export function FormSection({ config, children, className }: FormSectionProps) {
  const [open, setOpen] = useState(!config.collapsed);
  const collapsible = config.collapsible === true;

  const heading = (
    <div className="flex items-start gap-2">
      <div className="flex-1">
        <h3 className="text-sm font-semibold">{config.heading}</h3>
        {config.description && (
          <p className="text-xs text-[var(--color-arqel-muted-fg)]">{config.description}</p>
        )}
      </div>
      {collapsible && (
        <button
          type="button"
          aria-expanded={open}
          onClick={() => setOpen((v) => !v)}
          className="text-sm text-[var(--color-arqel-muted-fg)] hover:underline"
        >
          {open ? 'Hide' : 'Show'}
        </button>
      )}
    </div>
  );

  const body = open && (
    <div
      className="grid gap-4"
      style={{ gridTemplateColumns: `repeat(${config.columns ?? 1}, minmax(0, 1fr))` }}
    >
      {children}
    </div>
  );

  return (
    <section
      data-arqel-section=""
      className={cn(
        'rounded-[var(--radius-arqel)] border border-[var(--color-arqel-border)] bg-[var(--color-arqel-bg)]',
        config.compact ? 'p-3' : 'p-4',
        className,
      )}
    >
      {config.aside ? (
        <div className="grid gap-4 md:grid-cols-[16rem_1fr]">
          <div>{heading}</div>
          <div>{body}</div>
        </div>
      ) : (
        <>
          <div className="mb-3">{heading}</div>
          {body}
        </>
      )}
    </section>
  );
}
