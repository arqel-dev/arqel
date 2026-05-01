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
  /** DEVTOOLS-003: cache last Arqel state per tab and relay to panel ports. */
  setTabArqelState(tabId: number, arqelState: unknown): void;
  getTabArqelState(tabId: number): unknown;
  registerPanelPort(tabId: number, port: PanelPort): () => void;
  /** Read-only snapshot of registered panel ports per tab (for tests). */
  panelPortCount(tabId: number): number;
}

/**
 * Minimal subset of `chrome.runtime.Port` we depend on. Kept structural
 * so tests can supply lightweight fakes.
 */
export interface PanelPort {
  postMessage(message: unknown): void;
  onDisconnect: { addListener(cb: () => void): void };
}

export function createBackground(deps: BackgroundDeps = {}): BackgroundController {
  const state = new Map<number, TabDetectionState>();
  const arqelStates = new Map<number, unknown>();
  const panelPorts = new Map<number, Set<PanelPort>>();
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
    if (message.type === 'arqel.state') {
      const tabId = sender?.tab?.id;
      if (typeof tabId === 'number') {
        const arqelState = (message as ArqelMessage & { state?: unknown }).state;
        setTabArqelState(tabId, arqelState);
      }
    }
    return { ok: true, type: message.type };
  }

  function removeTab(tabId: number): void {
    state.delete(tabId);
    arqelStates.delete(tabId);
    panelPorts.delete(tabId);
  }

  function setTabArqelState(tabId: number, arqelState: unknown): void {
    arqelStates.set(tabId, arqelState);
    const ports = panelPorts.get(tabId);
    if (ports) {
      for (const port of ports) {
        try {
          port.postMessage({ type: 'arqel.state', state: arqelState });
        } catch (error) {
          console.warn('[arqel-devtools] panel port postMessage failed', error);
        }
      }
    }
  }

  function getTabArqelState(tabId: number): unknown {
    return arqelStates.get(tabId);
  }

  function registerPanelPort(tabId: number, port: PanelPort): () => void {
    let bucket = panelPorts.get(tabId);
    if (!bucket) {
      bucket = new Set();
      panelPorts.set(tabId, bucket);
    }
    bucket.add(port);
    port.onDisconnect.addListener(() => {
      bucket?.delete(port);
    });
    // Push current snapshot immediately if available.
    const snapshot = arqelStates.get(tabId);
    if (snapshot !== undefined) {
      try {
        port.postMessage({ type: 'arqel.state', state: snapshot });
      } catch (error) {
        console.warn('[arqel-devtools] initial port snapshot failed', error);
      }
    }
    return () => {
      bucket?.delete(port);
    };
  }

  function panelPortCount(tabId: number): number {
    return panelPorts.get(tabId)?.size ?? 0;
  }

  return {
    state,
    handleMessage,
    removeTab,
    getTabState(tabId): TabDetectionState | undefined {
      return state.get(tabId);
    },
    setTabArqelState,
    getTabArqelState,
    registerPanelPort,
    panelPortCount,
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

  // DEVTOOLS-003: long-lived port from each DevTools panel instance. The
  // panel must announce its target tabId on first message because port
  // senders inside DevTools don't carry a `tab.id`.
  if (runtime.onConnect) {
    runtime.onConnect.addListener((port: chrome.runtime.Port) => {
      if (port.name !== 'arqel-devtools-panel') return;
      let registered = false;
      const initListener = (msg: unknown) => {
        const m = msg as { type?: string; tabId?: number };
        if (!registered && m?.type === 'arqel.panel.hello' && typeof m.tabId === 'number') {
          controller.registerPanelPort(m.tabId, port);
          registered = true;
        }
      };
      port.onMessage.addListener(initListener);
    });
  }
}
