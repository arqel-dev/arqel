import { useCallback, useEffect, useRef } from 'react';

export type AnnouncePriority = 'polite' | 'assertive';

const REGION_ID_POLITE = 'arqel-a11y-live-polite';
const REGION_ID_ASSERTIVE = 'arqel-a11y-live-assertive';

const REGION_STYLE: Partial<CSSStyleDeclaration> = {
  position: 'absolute',
  width: '1px',
  height: '1px',
  padding: '0',
  margin: '-1px',
  overflow: 'hidden',
  clip: 'rect(0,0,0,0)',
  whiteSpace: 'nowrap',
  border: '0',
};

function ensureRegion(priority: AnnouncePriority): HTMLElement | null {
  if (typeof document === 'undefined') return null;
  const id = priority === 'assertive' ? REGION_ID_ASSERTIVE : REGION_ID_POLITE;
  let region = document.getElementById(id);
  if (region) return region;

  region = document.createElement('div');
  region.id = id;
  region.setAttribute('aria-live', priority);
  region.setAttribute('aria-atomic', 'true');
  region.setAttribute('role', priority === 'assertive' ? 'alert' : 'status');
  Object.assign(region.style, REGION_STYLE);
  document.body.appendChild(region);
  return region;
}

export interface UseAnnounceReturn {
  announce: (message: string, priority?: AnnouncePriority) => void;
}

/**
 * Hook que injeta mensagens em uma live region oculta para screen readers.
 *
 * SSR-safe: nada é tocado se `document` não existir. Em browsers, cria duas regions
 * compartilhadas (polite/assertive) e reutiliza-as entre todos os consumidores.
 */
export function useAnnounce(): UseAnnounceReturn {
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    return () => {
      if (timeoutRef.current) clearTimeout(timeoutRef.current);
    };
  }, []);

  const announce = useCallback((message: string, priority: AnnouncePriority = 'polite') => {
    const region = ensureRegion(priority);
    if (!region) return;
    // Reset texto antes para garantir que screen readers releiam mensagens repetidas.
    region.textContent = '';
    if (timeoutRef.current) clearTimeout(timeoutRef.current);
    timeoutRef.current = setTimeout(() => {
      region.textContent = message;
    }, 50);
  }, []);

  return { announce };
}
