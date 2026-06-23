import { useArqelTranslations } from '@arqel-dev/react/utils';
import type { ReactNode } from 'react';
import type { Theme } from './types';
import { useTheme } from './useTheme';

const CYCLE: readonly Theme[] = ['system', 'light', 'dark'];

/**
 * English fallbacks for each theme's aria-label/title. The localized values
 * live in `arqel.theme.toggle.*` (resolved client-side); these literals are
 * rendered when the dictionary is absent so the accessible name stays stable.
 */
const LABEL_FALLBACKS: Record<Theme, string> = {
  system: 'Theme: system (click for light)',
  light: 'Theme: light (click for dark)',
  dark: 'Theme: dark (click for system)',
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
  const t = useArqelTranslations();

  const icon = theme === 'light' ? <SunIcon /> : theme === 'dark' ? <MoonIcon /> : <MonitorIcon />;
  const label = t(`arqel.theme.toggle.${theme}`, LABEL_FALLBACKS[theme]);

  return (
    <button
      type="button"
      onClick={() => setTheme(nextTheme(theme))}
      aria-label={label}
      title={label}
      data-theme-current={theme}
      className={className}
    >
      {icon}
    </button>
  );
}
