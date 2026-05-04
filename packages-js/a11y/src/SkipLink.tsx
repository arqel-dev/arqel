import type { CSSProperties, MouseEvent, ReactElement } from 'react';

export interface SkipLinkProps {
  /** ID do elemento de destino (sem `#`). */
  targetId: string;
  /** Label visível e acessível. Default: "Pular para o conteúdo principal". */
  label?: string;
  /** Classe extra opcional para integração com Tailwind/ShadCN. */
  className?: string;
}

const baseStyle: CSSProperties = {
  position: 'absolute',
  top: '0.5rem',
  left: '0.5rem',
  padding: '0.5rem 1rem',
  background: '#111827',
  color: '#ffffff',
  borderRadius: '0.375rem',
  fontWeight: 600,
  textDecoration: 'none',
  zIndex: 9999,
  transform: 'translateY(-200%)',
  transition: 'transform 150ms ease',
};

const focusStyle: CSSProperties = {
  transform: 'translateY(0)',
  outline: '2px solid #6366f1',
  outlineOffset: '2px',
};

/**
 * Link que aparece somente em foco e move o foco para um landmark de conteúdo principal.
 *
 * Deve ser o primeiro elemento focável do `<body>` (recomendado dentro do layout root).
 */
export function SkipLink({
  targetId,
  label = 'Pular para o conteúdo principal',
  className,
}: SkipLinkProps): ReactElement {
  const handleClick = (event: MouseEvent<HTMLAnchorElement>): void => {
    const target = document.getElementById(targetId);
    if (!target) return;
    event.preventDefault();
    if (!target.hasAttribute('tabindex')) {
      target.setAttribute('tabindex', '-1');
    }
    target.focus();
    if (typeof target.scrollIntoView === 'function') {
      target.scrollIntoView({ block: 'start' });
    }
  };

  const handleFocus = (event: React.FocusEvent<HTMLAnchorElement>): void => {
    Object.assign(event.currentTarget.style, focusStyle);
  };
  const handleBlur = (event: React.FocusEvent<HTMLAnchorElement>): void => {
    event.currentTarget.style.transform = 'translateY(-200%)';
    event.currentTarget.style.outline = '';
  };

  return (
    <a
      href={`#${targetId}`}
      onClick={handleClick}
      onFocus={handleFocus}
      onBlur={handleBlur}
      style={baseStyle}
      className={className}
      data-arqel-skip-link=""
    >
      {label}
    </a>
  );
}
