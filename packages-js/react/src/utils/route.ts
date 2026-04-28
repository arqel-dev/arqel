/**
 * Typed wrapper over Ziggy's global `route()`. Ziggy is an
 * optional Laravel companion — if the user hasn't installed and
 * published Ziggy assets, `route()` will not be defined globally,
 * and we throw a clear error instead of returning `undefined`.
 */

type ZiggyParams = Record<string, string | number | boolean> | (string | number)[];

type ZiggyRouteFunction = (name: string, params?: ZiggyParams, absolute?: boolean) => string;

export type { ZiggyParams, ZiggyRouteFunction };

function getZiggy(): ZiggyRouteFunction | undefined {
  return (globalThis as { route?: ZiggyRouteFunction }).route;
}

export function route(name: string, params?: ZiggyParams, absolute?: boolean): string {
  const ziggy = getZiggy();

  if (typeof ziggy !== 'function') {
    throw new Error(
      "route(): Ziggy's `route()` helper is not defined on `globalThis`. Install `tightenco/ziggy` and add the `@routes` directive to your Blade layout.",
    );
  }

  return ziggy(name, params, absolute);
}
