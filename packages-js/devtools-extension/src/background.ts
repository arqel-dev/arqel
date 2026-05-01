/**
 * Arqel DevTools — background service worker.
 *
 * Stub: logs install events and acks messages from the content script.
 * Real bridge logic ships with DEVTOOLS-002.
 */

export interface ArqelMessage {
  type: string;
  payload?: unknown;
}

export interface ArqelMessageResponse {
  ok: boolean;
  type: string;
}

export function handleMessage(message: ArqelMessage): ArqelMessageResponse {
  return { ok: true, type: message.type };
}

// Guard the registration so the file can be imported in test environments
// where the `chrome.*` globals don't exist.
const runtime: typeof chrome.runtime | undefined =
  typeof chrome !== 'undefined' ? chrome.runtime : undefined;

if (runtime?.onInstalled) {
  runtime.onInstalled.addListener(() => {
    console.warn('[arqel-devtools] installed');
  });
}

if (runtime?.onMessage) {
  runtime.onMessage.addListener((message: ArqelMessage, _sender, sendResponse) => {
    sendResponse(handleMessage(message));
    return true;
  });
}
