import { useInput } from 'ink';
import { useState } from 'react';

export type UseNavigableListOptions = {
  itemCount: number;
  initialIndex?: number | undefined;
  onSelect?: ((index: number) => void) | undefined;
  onCancel?: (() => void) | undefined;
};

export type UseNavigableListResult = {
  index: number;
  setIndex: (i: number) => void;
};

/**
 * Generic up/down/enter/esc keyboard navigation for Ink lists.
 */
export function useNavigableList(options: UseNavigableListOptions): UseNavigableListResult {
  const { itemCount, initialIndex = 0, onSelect, onCancel } = options;
  const [index, setIndex] = useState(initialIndex);

  useInput((input, key) => {
    if (itemCount <= 0) return;
    if (key.upArrow || input === 'k') {
      setIndex((i) => (i - 1 + itemCount) % itemCount);
    } else if (key.downArrow || input === 'j') {
      setIndex((i) => (i + 1) % itemCount);
    } else if (key.return) {
      onSelect?.(index);
    } else if (key.escape || input === 'q') {
      onCancel?.();
    }
  });

  return { index, setIndex };
}
