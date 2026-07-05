/**
 * `<UserMenu>` — authenticated-user dropdown for the Topbar `userMenu` slot.
 *
 * Presentational: reads `user`/`logoutUrl`/`profileUrl` via props (no
 * data-fetching). Built on the vendored shadcn DropdownMenu. Logout posts
 * via Inertia (CSRF + redirect handled server-side). Theme control lives
 * here (moved out of the Topbar). Usable standalone — the Profile item and
 * the theme group are each conditional.
 */

import { useThemeOptional } from '@arqel-dev/react/providers';
import { useArqelTranslations } from '@arqel-dev/react/utils';
import { Link, router } from '@inertiajs/react';
import { ChevronDown, LogOut, Moon, Sun, SunMoon, User } from 'lucide-react';
import type { ComponentType, ReactElement } from 'react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '../shadcn/ui/dropdown-menu.js';
import { cn } from '../utils/cn.js';

export interface UserMenuProps {
  user: { name?: string | null; email?: string | null };
  logoutUrl: string;
  profileUrl?: string;
  className?: string;
}

type ThemeValue = 'light' | 'dark' | 'system';

const THEME_OPTIONS: ReadonlyArray<{
  value: ThemeValue;
  icon: ComponentType<{ className?: string }>;
  labelKey: string;
  fallback: string;
}> = [
  { value: 'light', icon: Sun, labelKey: 'arqel.auth.menu.theme_light', fallback: 'Light' },
  { value: 'dark', icon: Moon, labelKey: 'arqel.auth.menu.theme_dark', fallback: 'Dark' },
  { value: 'system', icon: SunMoon, labelKey: 'arqel.auth.menu.theme_system', fallback: 'System' },
];

export function UserMenu({ user, logoutUrl, profileUrl, className }: UserMenuProps): ReactElement {
  const t = useArqelTranslations();
  const themeCtx = useThemeOptional();
  const theme = themeCtx?.theme;
  const setTheme = themeCtx?.setTheme;

  const name = user.name ?? undefined;
  const email = user.email ?? undefined;
  const label = name ?? email ?? t('arqel.auth.menu.account', 'Account');
  const initial = (name ?? email ?? '?').trim().charAt(0).toUpperCase();

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        aria-label={t('arqel.auth.menu.open', 'Open user menu')}
        className={cn(
          'inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
          className,
        )}
      >
        <span
          aria-hidden="true"
          className="flex h-7 w-7 items-center justify-center rounded-full bg-muted text-muted-foreground"
        >
          {initial}
        </span>
        <span className="hidden max-w-[10rem] truncate md:inline">{label}</span>
        <ChevronDown aria-hidden="true" className="h-4 w-4 text-muted-foreground" />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-64">
        <DropdownMenuLabel className="flex flex-col">
          {name && <span className="font-medium">{name}</span>}
          {email && <span className="truncate text-xs text-muted-foreground">{email}</span>}
          {!name && !email && <span className="font-medium">{label}</span>}
        </DropdownMenuLabel>

        {setTheme && (
          <>
            <DropdownMenuSeparator />
            {/*
              Theme control as a horizontal segmented control: the "Theme" label
              on the left, a grouped set of icon buttons on the right. Rendered
              outside a menu item (a plain row) so the icon buttons don't dismiss
              the dropdown on click — `onSelect={(e) => e.preventDefault()}` is
              not needed because these are bare <button>s, not DropdownMenuItems.
            */}
            <div className="flex items-center justify-between px-2 py-1.5">
              <span className="text-sm text-muted-foreground">
                {t('arqel.auth.menu.theme', 'Theme')}
              </span>
              <div className="inline-flex items-center gap-0.5 rounded-md bg-muted p-0.5">
                {THEME_OPTIONS.map(({ value, icon: Icon, labelKey, fallback }) => {
                  const active = (theme ?? 'system') === value;
                  return (
                    <button
                      key={value}
                      type="button"
                      aria-label={t(labelKey, fallback)}
                      aria-pressed={active}
                      onClick={() => setTheme(value)}
                      className={cn(
                        'flex h-7 w-8 items-center justify-center rounded-sm text-muted-foreground transition-colors',
                        'hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                        active && 'bg-background text-foreground shadow-sm',
                      )}
                    >
                      <Icon className="h-4 w-4" />
                    </button>
                  );
                })}
              </div>
            </div>
          </>
        )}

        {profileUrl && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
              <Link href={profileUrl} className="flex items-center gap-2">
                <User aria-hidden="true" className="h-4 w-4" />
                {t('arqel.auth.menu.profile', 'Profile')}
              </Link>
            </DropdownMenuItem>
          </>
        )}

        <DropdownMenuSeparator />
        <DropdownMenuItem
          className="flex items-center gap-2 text-destructive focus:text-destructive"
          onSelect={() => router.post(logoutUrl)}
        >
          <LogOut aria-hidden="true" className="h-4 w-4" />
          {t('arqel.auth.menu.logout', 'Log out')}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
