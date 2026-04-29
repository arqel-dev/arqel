/**
 * `useArqelOptimistic` — thin wrapper over React 19's `useOptimistic`.
 *
 * Renamed (`useArqelOptimistic`) to avoid colliding with React's hook when
 * both are imported in the same file. Reducer signature mirrors React's.
 */

import { useOptimistic } from 'react';

export function useArqelOptimistic<TState, TAction>(
  state: TState,
  reducer: (current: TState, action: TAction) => TState,
): [TState, (action: TAction) => void] {
  return useOptimistic(state, reducer);
}
