import type { CSSProperties, ReactElement, ReactNode } from 'react';

export interface VisuallyHiddenProps {
  children: ReactNode;
  /** Tag a renderizar (default: span). */
  as?: 'span' | 'div' | 'p' | 'label';
  /** Quando true, esconde também de screen readers. Default: false. */
  hidden?: boolean;
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
 * Esconde visualmente o conteúdo, mas o mantém disponível para screen readers.
 *
 * Use para labels de inputs icon-only, instruções contextuais, ou textos auxiliares
 * que só fazem sentido para tecnologias assistivas.
 */
export function VisuallyHidden({
  children,
  as: Tag = 'span',
  hidden = false,
}: VisuallyHiddenProps): ReactElement {
  return (
    <Tag style={style} aria-hidden={hidden ? 'true' : 'false'} data-arqel-visually-hidden="">
      {children}
    </Tag>
  );
}
