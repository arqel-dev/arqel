import { type RefObject, useEffect, useRef } from 'react';

const FOCUSABLE_SELECTOR = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
  '[contenteditable="true"]',
].join(',');

export interface UseFocusTrapOptions {
  /** Quando true, ao pressionar Escape, dispara `onEscape` (se fornecido). */
  onEscape?: () => void;
  /**
   * Quando false (default true), não restaura o foco para o elemento que tinha foco
   * antes da ativação ao desativar o trap.
   */
  restoreFocus?: boolean;
}

/**
 * Hook que confina o foco do teclado dentro de um container enquanto `active` for true.
 *
 * Útil para modais, drawers, dialogs e popovers. Implementa Tab cycling e Shift+Tab cycling.
 * Ao ativar, foca no primeiro elemento focável dentro do container.
 *
 * @param active liga/desliga o trap
 * @param options opções (onEscape, restoreFocus)
 * @returns ref a ser anexada ao container
 */
export function useFocusTrap<T extends HTMLElement = HTMLElement>(
  active: boolean,
  options: UseFocusTrapOptions = {},
): RefObject<T | null> {
  const containerRef = useRef<T | null>(null);
  const previouslyFocused = useRef<HTMLElement | null>(null);
  const { onEscape, restoreFocus = true } = options;

  useEffect(() => {
    if (!active) return;
    const container = containerRef.current;
    if (!container) return;

    previouslyFocused.current = (document.activeElement as HTMLElement) ?? null;

    const getFocusable = (): HTMLElement[] => {
      const nodes = container.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR);
      return Array.from(nodes).filter((el) => !el.hasAttribute('aria-hidden'));
    };

    const focusables = getFocusable();
    if (focusables.length > 0) {
      focusables[0]?.focus();
    } else {
      // garante que o container receba foco para permitir Escape
      container.setAttribute('tabindex', '-1');
      container.focus();
    }

    const handleKeydown = (event: KeyboardEvent): void => {
      if (event.key === 'Escape' && onEscape) {
        event.stopPropagation();
        onEscape();
        return;
      }
      if (event.key !== 'Tab') return;

      const items = getFocusable();
      if (items.length === 0) {
        event.preventDefault();
        return;
      }
      const first = items[0];
      const last = items[items.length - 1];
      if (!first || !last) return;

      const activeEl = document.activeElement as HTMLElement | null;
      if (event.shiftKey) {
        if (activeEl === first || !container.contains(activeEl)) {
          event.preventDefault();
          last.focus();
        }
      } else {
        if (activeEl === last) {
          event.preventDefault();
          first.focus();
        }
      }
    };

    document.addEventListener('keydown', handleKeydown, true);
    return () => {
      document.removeEventListener('keydown', handleKeydown, true);
      if (restoreFocus && previouslyFocused.current) {
        try {
          previouslyFocused.current.focus();
        } catch {
          // elemento pode ter sido removido — ignore
        }
      }
    };
  }, [active, onEscape, restoreFocus]);

  return containerRef;
}
