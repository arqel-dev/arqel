/**
 * `<StateTransition>` — apresentational component for the PHP
 * `Arqel\Workflow\Fields\StateTransitionField` (component string
 * `arqel-dev/workflow/StateTransition`).
 *
 * Render contract (from WF-003):
 *   - large pill (Badge) displaying `currentState.label` (background colour
 *     pulled from `currentState.color` when it looks like a CSS string,
 *     fallback to a state-derived Badge variant),
 *   - list of buttons, one per transition; authorized → enabled,
 *     non-authorized → shown only when `showDescription = true` and
 *     rendered disabled,
 *   - optional ordered `<ol>` timeline when `showHistory && history.length`,
 *   - empty states "No state assigned." / "No transitions available.".
 *
 * Click behaviour:
 *   - if `onTransition` is provided, it is invoked with `(from, to)`,
 *   - otherwise a `CustomEvent('arqel:state-transition')` is dispatched
 *     on `document` carrying `{ from, to, name, recordId }` so an
 *     Inertia consumer can intercept and call `router.post(...)`.
 *
 * Note: this component performs **no** network I/O — fetch/Inertia
 * coupling lives in higher layers.
 */

import { Badge, Button, type badgeVariants } from '@arqel-dev/ui';
import type { VariantProps } from 'class-variance-authority';
import { type ReactElement, useCallback } from 'react';

type BadgeVariant = NonNullable<VariantProps<typeof badgeVariants>['variant']>;

export interface StateTransitionCurrentState {
  name: string;
  label: string;
  color?: string | null;
  icon?: string | null;
}

export interface StateTransitionEntry {
  from: string;
  to: string;
  label: string;
  authorized: boolean;
}

export interface StateTransitionHistoryEntry {
  from: string;
  to: string;
  at: string;
  by?: string | null;
}

export interface StateTransitionFieldProps {
  currentState: StateTransitionCurrentState | null;
  transitions: StateTransitionEntry[];
  history: StateTransitionHistoryEntry[];
  showDescription: boolean;
  showHistory: boolean;
  transitionsAttribute: string;
}

export interface StateTransitionRecord {
  id?: number | string;
}

export interface StateTransitionProps {
  name: string;
  value: unknown;
  props: StateTransitionFieldProps;
  record?: StateTransitionRecord;
  csrfToken?: string;
  onTransition?: (from: string, to: string) => void;
}

export const STATE_TRANSITION_EVENT = 'arqel:state-transition';

export interface StateTransitionEventDetail {
  from: string;
  to: string;
  name: string;
  recordId: number | string | undefined;
}

/**
 * Returns true when the colour string is a plausible CSS colour value
 * (hex, rgb/rgba, hsl/hsla, or a CSS named colour). We keep the check
 * conservative — anything dubious falls back to the default class.
 */
function isCssColor(value: string): boolean {
  const trimmed = value.trim();
  if (trimmed.length === 0) return false;
  if (/^#[0-9a-fA-F]{3,8}$/.test(trimmed)) return true;
  if (/^(rgb|rgba|hsl|hsla)\s*\(/i.test(trimmed)) return true;
  // CSS named colour (letters, optional dashes); be permissive.
  if (/^[a-zA-Z]+(-[a-zA-Z]+)*$/.test(trimmed)) return true;
  return false;
}

/**
 * Maps a workflow state name to a Badge variant. Common naming
 * conventions across PHP workflow definitions are recognised; anything
 * unknown falls back to `outline`.
 */
function badgeVariantForState(stateName: string): BadgeVariant {
  const normalized = stateName.toLowerCase();
  if (normalized.includes('draft') || normalized.includes('pending')) {
    return 'secondary';
  }
  if (normalized.includes('published') || normalized.includes('approved')) {
    return 'default';
  }
  if (normalized.includes('rejected') || normalized.includes('archived')) {
    return 'destructive';
  }
  return 'outline';
}

export function StateTransition({
  name,
  props,
  record,
  onTransition,
}: StateTransitionProps): ReactElement {
  const { currentState, transitions, history, showDescription, showHistory } = props;

  const handleClick = useCallback(
    (from: string, to: string) => {
      if (onTransition) {
        onTransition(from, to);
        return;
      }
      if (typeof document !== 'undefined') {
        const detail: StateTransitionEventDetail = {
          from,
          to,
          name,
          recordId: record?.id,
        };
        document.dispatchEvent(new CustomEvent(STATE_TRANSITION_EVENT, { detail }));
      }
    },
    [name, onTransition, record?.id],
  );

  const visibleTransitions = showDescription
    ? transitions
    : transitions.filter((t) => t.authorized);

  const pillColor = currentState?.color;
  const pillStyle =
    typeof pillColor === 'string' && isCssColor(pillColor)
      ? { backgroundColor: pillColor }
      : undefined;

  return (
    <div className="arqel-state-transition" data-name={name}>
      <div className="arqel-state-transition__current">
        {currentState === null ? (
          <span
            className="text-sm text-muted-foreground"
            data-testid="state-transition-empty-state"
          >
            No state assigned.
          </span>
        ) : (
          <Badge
            variant={badgeVariantForState(currentState.name)}
            className="px-4 py-1.5 text-base font-semibold"
            style={pillStyle}
            data-testid="state-transition-pill"
            data-state={currentState.name}
          >
            {currentState.label}
          </Badge>
        )}
      </div>

      <div className="arqel-state-transition__transitions mt-3 flex flex-wrap gap-2">
        {visibleTransitions.length === 0 ? (
          <span
            className="text-sm text-muted-foreground"
            data-testid="state-transition-empty-transitions"
          >
            No transitions available.
          </span>
        ) : (
          visibleTransitions.map((t) => (
            <Button
              key={`${t.from}->${t.to}`}
              type="button"
              variant="outline"
              size="sm"
              data-testid="state-transition-button"
              data-transition-from={t.from}
              data-transition-to={t.to}
              data-authorized={t.authorized ? 'true' : 'false'}
              disabled={!t.authorized}
              onClick={() => handleClick(t.from, t.to)}
            >
              {t.label}
            </Button>
          ))
        )}
      </div>

      {showHistory && history.length > 0 ? (
        <ol
          className="arqel-state-transition__history mt-4 space-y-1 text-sm text-muted-foreground"
          data-testid="state-transition-history"
        >
          {history.map((entry) => (
            <li
              key={`${entry.at}|${entry.from}->${entry.to}|${entry.by ?? ''}`}
              data-testid="state-transition-history-item"
              data-history-from={entry.from}
              data-history-to={entry.to}
            >
              <span className="font-medium text-foreground">{entry.from}</span>
              {' → '}
              <span className="font-medium text-foreground">{entry.to}</span>
              <span className="text-muted-foreground"> ({entry.at}</span>
              {entry.by ? <span className="text-muted-foreground">{` by ${entry.by}`}</span> : null}
              <span className="text-muted-foreground">)</span>
            </li>
          ))}
        </ol>
      ) : null}
    </div>
  );
}

export default StateTransition;
