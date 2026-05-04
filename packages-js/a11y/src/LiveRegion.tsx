import type { CSSProperties, ReactElement } from 'react';
import type { AnnouncePriority } from './useAnnounce';

export interface LiveRegionProps {
  /** Mensagem atual a anunciar. Atualizar troca o aria-live content. */
  message?: string;
  /** Prioridade ARIA. Default: 'polite'. */
  priority?: AnnouncePriority;
  /** ID opcional. */
  id?: string;
}

const style: CSSProperties = {
  position: 'absolute',
  width: '1px',
  height: '1px',
  padding: 0,
  margin: '-1px',
  overflow: 'hidden',
  clip: 'rect(0,0,0,0)',
  whiteSpace: 'nowrap',
  border: 0,
};

/**
 * Live region standalone — útil quando você precisa controlar a mensagem via prop em vez do hook.
 */
export function LiveRegion({
  message = '',
  priority = 'polite',
  id,
}: LiveRegionProps): ReactElement {
  return (
    <div
      id={id}
      role={priority === 'assertive' ? 'alert' : 'status'}
      aria-live={priority}
      aria-atomic="true"
      style={style}
      data-arqel-live-region=""
    >
      {message}
    </div>
  );
}
