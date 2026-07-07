/**
 * Guard for server-controlled URLs (e.g. notification `data.action_url`)
 * that must never be rendered as a navigable `Link` unless they are
 * relative to the current origin.
 *
 * Accepts only strings starting with a single `/` — `//evil.com` (a
 * protocol-relative URL) and any absolute URL (`https://…`,
 * `javascript:…`) are rejected. This mirrors the allowlist approach
 * already used for `data.icon` in `NotificationList`: untrusted payload
 * data only unlocks a narrow, safe subset of behavior, never arbitrary
 * navigation — closing an open-redirect/phishing vector where a
 * malicious `data.action_url` could send the user off-site.
 */
export function isSameOriginRelativeUrl(url: string): boolean {
  return url.startsWith('/') && !url.startsWith('//');
}
