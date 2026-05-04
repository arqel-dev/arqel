/**
 * `<StateTransition>` — apresentational component for the PHP
 * `Arqel\Workflow\Fields\StateTransitionField` (component string
 * `arqel-dev/workflow/StateTransition`).
 *
 * Render contract (from WF-003):
 *   - large pill displaying `currentState.label` (background colour
 *     pulled from `currentState.color` when it looks like a CSS string,
 *     fallback `bg-gray-200`),
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

import { type ReactElement, useCallback } from 'react';

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
  const pillClass =
    pillStyle === undefined
      ? 'inline-flex items-center rounded-full bg-gray-200 px-4 py-1.5 text-base font-semibold text-gray-900'
      : 'inline-flex items-center rounded-full px-4 py-1.5 text-base font-semibold text-gray-900';

  return (
    <div className="arqel-state-transition" data-name={name}>
      <div className="arqel-state-transition__current">
        {currentState === null ? (
          <span className="text-sm text-gray-500" data-testid="state-transition-empty-state">
            No state assigned.
          </span>
        ) : (
          <span
            className={pillClass}
            style={pillStyle}
            data-testid="state-transition-pill"
            data-state={currentState.name}
          >
            {currentState.label}
          </span>
        )}
      </div>

      <div className="arqel-state-transition__transitions mt-3 flex flex-wrap gap-2">
        {visibleTransitions.length === 0 ? (
          <span className="text-sm text-gray-500" data-testid="state-transition-empty-transitions">
            No transitions available.
          </span>
        ) : (
          visibleTransitions.map((t) => (
            <button
              key={`${t.from}->${t.to}`}
              type="button"
              data-testid="state-transition-button"
              data-transition-from={t.from}
              data-transition-to={t.to}
              data-authorized={t.authorized ? 'true' : 'false'}
              disabled={!t.authorized}
              onClick={() => handleClick(t.from, t.to)}
              className="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-800 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {t.label}
            </button>
          ))
        )}
      </div>

      {showHistory && history.length > 0 ? (
        <ol
          className="arqel-state-transition__history mt-4 space-y-1 text-sm text-gray-600"
          data-testid="state-transition-history"
        >
          {history.map((entry) => (
            <li
              key={`${entry.at}|${entry.from}->${entry.to}|${entry.by ?? ''}`}
              data-testid="state-transition-history-item"
              data-history-from={entry.from}
              data-history-to={entry.to}
            >
              <span className="font-medium">{entry.from}</span>
              {' → '}
              <span className="font-medium">{entry.to}</span>
              <span className="text-gray-400"> ({entry.at}</span>
              {entry.by ? <span className="text-gray-400">{` by ${entry.by}`}</span> : null}
              <span className="text-gray-400">)</span>
            </li>
          ))}
        </ol>
      ) : null}
    </div>
  );
}

export default StateTransition;
