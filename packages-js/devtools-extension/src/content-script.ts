/**
 * Arqel DevTools — content script.
 *
 * Looks for the `window.__ARQEL_DEVTOOLS_HOOK__` marker that the runtime will
 * expose (DEVTOOLS-002) and reports the result to the background worker.
 */

export interface ArqelDevtoolsHook {
  version: string;
}

declare global {
  interface Window {
    __ARQEL_DEVTOOLS_HOOK__?: ArqelDevtoolsHook;
  }
}

export function detectArqel(target: Window | undefined = globalThis.window): boolean {
  if (!target) {
    return false;
  }
  const hook = target.__ARQEL_DEVTOOLS_HOOK__;
  return Boolean(hook && typeof hook.version === 'string' && hook.version.length > 0);
}

export interface DetectMessage {
  type: 'arqel.detect';
  detected: boolean;
  version: string | null;
}

export function buildDetectMessage(target: Window | undefined = globalThis.window): DetectMessage {
  const detected = detectArqel(target);
  const version = detected ? (target?.__ARQEL_DEVTOOLS_HOOK__?.version ?? null) : null;
  return { type: 'arqel.detect', detected, version };
}

if (typeof chrome !== 'undefined' && chrome.runtime?.sendMessage) {
  try {
    chrome.runtime.sendMessage(buildDetectMessage());
  } catch (error) {
    console.warn('[arqel-devtools] failed to dispatch detect message', error);
  }
}
