/**
 * `<FormTabs>` — minimal tabs implementation (Phase 1).
 *
 * UI-002+ wires Base UI Tabs primitive properly; this Phase 1 version
 * is keyboard-accessible (Arrow keys + Home/End) but doesn't yet support
 * lazy mounting or controlled mode.
 */

import type { TabsProps } from '@arqel/types/forms';
import { type ReactElement, type ReactNode, useId, useRef, useState } from 'react';
import { cn } from '../utils/cn.js';

export interface FormTabConfig {
  id: string;
  label: string;
  badge?: number | undefined;
  content: ReactNode;
}

export interface FormTabsProps {
  config: TabsProps;
  tabs: FormTabConfig[];
  className?: string;
}

export function FormTabs({ config, tabs, className }: FormTabsProps): ReactElement | null {
  const [active, setActive] = useState(config.defaultTab ?? tabs[0]?.id ?? null);
  const baseId = useId();
  const refs = useRef<(HTMLButtonElement | null)[]>([]);

  if (tabs.length === 0 || active === null) return null;
  const activeIndex = Math.max(
    0,
    tabs.findIndex((t) => t.id === active),
  );
  const activeTab = tabs[activeIndex];
  if (!activeTab) return null;

  const focus = (i: number) => {
    const target = tabs[i];
    if (!target) return;
    setActive(target.id);
    refs.current[i]?.focus();
  };

  const onKeyDown = (e: React.KeyboardEvent<HTMLButtonElement>) => {
    if (e.key === 'ArrowRight') {
      e.preventDefault();
      focus((activeIndex + 1) % tabs.length);
    } else if (e.key === 'ArrowLeft') {
      e.preventDefault();
      focus((activeIndex - 1 + tabs.length) % tabs.length);
    } else if (e.key === 'Home') {
      e.preventDefault();
      focus(0);
    } else if (e.key === 'End') {
      e.preventDefault();
      focus(tabs.length - 1);
    }
  };

  return (
    <div className={className}>
      <div
        role="tablist"
        aria-orientation={config.orientation}
        className="flex gap-1 border-b border-[var(--color-arqel-border)]"
      >
        {tabs.map((tab, i) => {
          const selected = tab.id === active;
          return (
            <button
              key={tab.id}
              ref={(el) => {
                refs.current[i] = el;
              }}
              type="button"
              role="tab"
              id={`${baseId}-tab-${tab.id}`}
              aria-controls={`${baseId}-panel-${tab.id}`}
              aria-selected={selected}
              tabIndex={selected ? 0 : -1}
              onClick={() => setActive(tab.id)}
              onKeyDown={onKeyDown}
              className={cn(
                '-mb-px border-b-2 px-3 py-2 text-sm transition-colors',
                selected
                  ? 'border-[var(--color-arqel-primary)] font-medium text-[var(--color-arqel-fg)]'
                  : 'border-transparent text-[var(--color-arqel-muted-fg)] hover:text-[var(--color-arqel-fg)]',
              )}
            >
              {tab.label}
              {tab.badge !== undefined && tab.badge !== null && (
                <span className="ml-1 rounded-full bg-[var(--color-arqel-muted)] px-1.5 text-xs">
                  {tab.badge}
                </span>
              )}
            </button>
          );
        })}
      </div>
      <div
        role="tabpanel"
        id={`${baseId}-panel-${activeTab.id}`}
        aria-labelledby={`${baseId}-tab-${activeTab.id}`}
        className="pt-4"
      >
        {activeTab.content}
      </div>
    </div>
  );
}
