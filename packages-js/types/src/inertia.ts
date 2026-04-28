/**
 * Inertia shared props injected by
 * `Arqel\Core\Http\Middleware\HandleArqelInertiaRequests`.
 *
 * Apps extend the Inertia `PageProps` interface so every page
 * receives `auth`, `panel`, `tenant`, `flash`, `translations`,
 * `arqel.version` for free.
 */

export interface AuthUserPayload {
  id: number | string;
  name?: string | null;
  email?: string | null;
}

export interface AuthPayload {
  user: AuthUserPayload | null;
  /** Shape: `{ ability: boolean, ... }` from `AbilityRegistry`. */
  can: Record<string, boolean>;
}

export interface PanelPayload {
  id: string;
  path: string;
  /** `Panel::brand()` → `{ name, logo? }`. */
  brand: {
    name: string;
    logo: string | null;
  };
}

export interface FlashPayload {
  success: string | null;
  error: string | null;
  info: string | null;
  warning: string | null;
}

export interface ArqelMeta {
  version: string;
}

/**
 * The full set of shared props every Arqel page receives.
 */
export interface SharedProps {
  auth: AuthPayload;
  panel: PanelPayload | null;
  /** Tenant payload — null in Phase 1 (scaffold only). */
  tenant: unknown;
  flash: FlashPayload;
  /** Translation map under the `arqel::*` namespace. */
  translations: Record<string, unknown>;
  arqel: ArqelMeta;
}

/**
 * Convenience alias for Inertia v3 `usePage<T>()`. Apps with extra
 * shared props extend via intersection: `SharedProps & MyShared`.
 */
export type ArqelPageProps = SharedProps;
