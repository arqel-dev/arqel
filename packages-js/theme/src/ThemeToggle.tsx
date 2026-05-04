import type { ReactNode } from 'react';
import type { Theme } from './types';
import { useTheme } from './useTheme';

const CYCLE: readonly Theme[] = ['system', 'light', 'dark'];

const LABELS: Record<Theme, string> = {
  system: 'Tema: sistema (clique para claro)',
  light: 'Tema: claro (clique para escuro)',
  dark: 'Tema: escuro (clique para sistema)',
};

function nextTheme(current: Theme): Theme {
  const idx = CYCLE.indexOf(current);
  return CYCLE[(idx + 1) % CYCLE.length] ?? 'system';
}

function SunIcon(): ReactNode {
  return (
    <svg
      aria-hidden="true"
      width="16"
      height="16"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <circle cx="12" cy="12" r="4" />
      <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
    </svg>
  );
}

function MoonIcon(): ReactNode {
  return (
    <svg
      aria-hidden="true"
      width="16"
      height="16"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
    </svg>
  );
}

function MonitorIcon(): ReactNode {
  return (
    <svg
      aria-hidden="true"
      width="16"
      height="16"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <rect x="2" y="3" width="20" height="14" rx="2" />
      <path d="M8 21h8M12 17v4" />
    </svg>
  );
}

export interface ThemeToggleProps {
  className?: string;
}

/**
 * Botão que cicla entre `system → light → dark → system`.
 * Mostra ícone correspondente ao **tema atualmente selecionado**
 * (não ao próximo) e usa `aria-label` descritivo para acessibilidade.
 */
export function ThemeToggle({ className }: ThemeToggleProps = {}): ReactNode {
  const { theme, setTheme } = useTheme();

  const icon = theme === 'light' ? <SunIcon /> : theme === 'dark' ? <MoonIcon /> : <MonitorIcon />;

  return (
    <button
      type="button"
      onClick={() => setTheme(nextTheme(theme))}
      aria-label={LABELS[theme]}
      title={LABELS[theme]}
      data-theme-current={theme}
      className={className}
    >
      {icon}
    </button>
  );
}
