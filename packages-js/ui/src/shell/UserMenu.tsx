/**
 * `<UserMenu>` — authenticated-user dropdown for the Topbar `userMenu` slot.
 *
 * Presentational: reads `user`/`logoutUrl`/`profileUrl` via props (no
 * data-fetching). Built on the vendored shadcn DropdownMenu. Logout posts
 * via Inertia (CSRF + redirect handled server-side). Theme control lives
 * here (moved out of the Topbar). Usable standalone — the Profile item and
 * the theme group are each conditional.
 */

import { useTheme } from '@arqel-dev/react/providers';
import { useArqelTranslations } from '@arqel-dev/react/utils';
import { Link, router } from '@inertiajs/react';
import type { ReactElement } from 'react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
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

function useThemeSafe(): { theme?: string; setTheme?: (t: string) => void } {
  try {
    return useTheme() as { theme?: string; setTheme?: (t: string) => void };
  } catch {
    return {};
  }
}

export function UserMenu({ user, logoutUrl, profileUrl, className }: UserMenuProps): ReactElement {
  const t = useArqelTranslations();
  const { theme, setTheme } = useThemeSafe();

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
        <span aria-hidden="true" className="text-muted-foreground">
          ▾
        </span>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-56">
        <DropdownMenuLabel className="flex flex-col">
          {name && <span className="font-medium">{name}</span>}
          {email && <span className="truncate text-xs text-muted-foreground">{email}</span>}
          {!name && !email && <span className="font-medium">{label}</span>}
        </DropdownMenuLabel>

        {profileUrl && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
              <Link href={profileUrl}>{t('arqel.auth.menu.profile', 'Profile')}</Link>
            </DropdownMenuItem>
          </>
        )}

        {setTheme && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuLabel className="text-xs text-muted-foreground">
              {t('arqel.auth.menu.theme', 'Theme')}
            </DropdownMenuLabel>
            <DropdownMenuRadioGroup value={theme ?? 'system'} onValueChange={(v) => setTheme(v)}>
              <DropdownMenuRadioItem value="light">
                {t('arqel.auth.menu.theme_light', 'Light')}
              </DropdownMenuRadioItem>
              <DropdownMenuRadioItem value="dark">
                {t('arqel.auth.menu.theme_dark', 'Dark')}
              </DropdownMenuRadioItem>
              <DropdownMenuRadioItem value="system">
                {t('arqel.auth.menu.theme_system', 'System')}
              </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
          </>
        )}

        <DropdownMenuSeparator />
        <DropdownMenuItem
          className="text-destructive focus:text-destructive"
          onSelect={() => router.post(logoutUrl)}
        >
          {t('arqel.auth.menu.logout', 'Log out')}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
