/**
 * Arqel DevTools — background service worker (DEVTOOLS-002).
 *
 * Maintains a per-tab map of detection state and updates the
 * extension toolbar action accordingly:
 *
 *  - inactive (Arqel not detected) → grayscale icon set
 *  - active (Arqel detected, with version) → coloured icon set
 *
 * The two icon sets share the same artwork for now (TODO: ship a
 * dedicated grayscale variant). Paths are configurable via
 * `createBackground({ activeIconPaths, inactiveIconPaths })` so tests
 * can assert the calls without bundling icon assets.
 */

export interface ArqelMessage {
  type: string;
  payload?: unknown;
}

export interface ArqelMessageResponse {
  ok: boolean;
  type: string;
}

export interface ArqelDetectedMessage extends ArqelMessage {
  type: 'arqel.detected';
  detected: boolean;
  version: string | null;
}

export interface TabDetectionState {
  detected: boolean;
  version: string | null;
}

export type IconPaths = Readonly<Record<'16' | '32' | '48' | '128', string>>;

const DEFAULT_ACTIVE_ICONS: IconPaths = Object.freeze({
  '16': 'icons/icon-16.png',
  '32': 'icons/icon-32.png',
  '48': 'icons/icon-48.png',
  '128': 'icons/icon-128.png',
});

const DEFAULT_INACTIVE_ICONS: IconPaths = Object.freeze({
  // TODO(DEVTOOLS-002): ship dedicated grayscale assets in `icons/inactive/`.
  '16': 'icons/icon-16.png',
  '32': 'icons/icon-32.png',
  '48': 'icons/icon-48.png',
  '128': 'icons/icon-128.png',
});

export interface BackgroundDeps {
  setIcon?: (details: { tabId: number; path: IconPaths }) => void;
  activeIconPaths?: IconPaths;
  inactiveIconPaths?: IconPaths;
}

export interface BackgroundController {
  readonly state: ReadonlyMap<number, TabDetectionState>;
  handleMessage(message: ArqelMessage, sender?: { tab?: { id?: number } }): ArqelMessageResponse;
  removeTab(tabId: number): void;
  /** Read-only snapshot for tests. */
  getTabState(tabId: number): TabDetectionState | undefined;
}

export function createBackground(deps: BackgroundDeps = {}): BackgroundController {
  const state = new Map<number, TabDetectionState>();
  const activePaths = deps.activeIconPaths ?? DEFAULT_ACTIVE_ICONS;
  const inactivePaths = deps.inactiveIconPaths ?? DEFAULT_INACTIVE_ICONS;
  const setIcon =
    deps.setIcon ??
    ((details: { tabId: number; path: IconPaths }): void => {
      if (typeof chrome !== 'undefined' && chrome.action?.setIcon) {
        try {
          // chrome.action.setIcon's typed signature is awkward; cast to
          // satisfy `@types/chrome` without losing the runtime contract.
          chrome.action.setIcon(details as unknown as chrome.action.TabIconDetails);
        } catch (error) {
          console.warn('[arqel-devtools] setIcon failed', error);
        }
      }
    });

  function applyIcon(tabId: number, detected: boolean): void {
    const path = detected ? activePaths : inactivePaths;
    setIcon({ tabId, path });
  }

  function handleMessage(
    message: ArqelMessage,
    sender?: { tab?: { id?: number } },
  ): ArqelMessageResponse {
    if (message.type === 'arqel.detected') {
      const tabId = sender?.tab?.id;
      if (typeof tabId === 'number') {
        const detected = Boolean((message as ArqelDetectedMessage).detected);
        const version =
          detected && typeof (message as ArqelDetectedMessage).version === 'string'
            ? ((message as ArqelDetectedMessage).version as string)
            : null;
        state.set(tabId, { detected, version });
        applyIcon(tabId, detected);
      }
    }
    return { ok: true, type: message.type };
  }

  function removeTab(tabId: number): void {
    state.delete(tabId);
  }

  return {
    state,
    handleMessage,
    removeTab,
    getTabState(tabId): TabDetectionState | undefined {
      return state.get(tabId);
    },
  };
}

/** Legacy export retained for back-compat with DEVTOOLS-001 tests. */
export function handleMessage(message: ArqelMessage): ArqelMessageResponse {
  return { ok: true, type: message.type };
}

// Wire up real Chrome runtime listeners when available. Skipped under
// Vitest (test mode) to avoid touching the global mock.
const env = (import.meta as ImportMeta & { env?: { MODE?: string; VITEST?: boolean } }).env;
const isTest = env?.MODE === 'test' || env?.VITEST === true;

const runtime: typeof chrome.runtime | undefined =
  typeof chrome !== 'undefined' ? chrome.runtime : undefined;

if (!isTest && runtime) {
  const controller = createBackground();

  if (runtime.onInstalled) {
    runtime.onInstalled.addListener(() => {
      console.warn('[arqel-devtools] installed');
    });
  }

  if (runtime.onMessage) {
    runtime.onMessage.addListener((message: ArqelMessage, sender, sendResponse) => {
      const tabId = sender?.tab?.id;
      const senderArg = typeof tabId === 'number' ? { tab: { id: tabId } } : undefined;
      sendResponse(controller.handleMessage(message, senderArg));
      return true;
    });
  }

  if (typeof chrome !== 'undefined' && chrome.tabs?.onRemoved) {
    chrome.tabs.onRemoved.addListener((tabId: number) => {
      controller.removeTab(tabId);
    });
  }
}
