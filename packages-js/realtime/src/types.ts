/**
 * Tipos públicos do Echo expostos para consumidores do `@arqel-dev/realtime`.
 *
 * Esses tipos são intencionalmente shape-based (não estruturais aos pacotes
 * `laravel-echo` / `pusher-js`) para evitar acoplar a API pública do Arqel
 * a versões internas. Hooks em `@arqel-dev/hooks` podem importar esses tipos
 * sem precisar redeclarar `Window`.
 */

/**
 * Listener handler usado em todas as subscrições de canal.
 */
export type EchoEventListener = (payload: unknown) => void;

/**
 * Canal público / privado básico — o suficiente para `useResourceUpdates`,
 * `useActionProgress`, `useWidgetRealtime`.
 */
export interface EchoChannelLike {
  listen: (event: string, callback: EchoEventListener) => EchoChannelLike;
  stopListening: (event: string) => EchoChannelLike;
  // Algumas implementações expõem `notification` para canais privados;
  // tornamos opcional para não exigir que mocks o implementem.
  notification?: (callback: EchoEventListener) => EchoChannelLike;
}

/**
 * Membro genérico de canal de presença.
 */
export interface PresenceMember {
  id: string | number;
  info?: Record<string, unknown>;
}

/**
 * Canal de presença — superset do canal privado.
 */
export interface PresenceChannelLike extends EchoChannelLike {
  here: (callback: (members: PresenceMember[]) => void) => PresenceChannelLike;
  joining: (callback: (member: PresenceMember) => void) => PresenceChannelLike;
  leaving: (callback: (member: PresenceMember) => void) => PresenceChannelLike;
}

/**
 * Conector low-level — usado para hooks de auto-reconnect.
 */
export interface EchoConnectorLike {
  pusher?: {
    connection?: {
      bind: (event: string, callback: EchoEventListener) => void;
      unbind?: (event: string, callback?: EchoEventListener) => void;
      state?: string;
    };
  };
}

/**
 * Subset minimo da instância do Laravel Echo que o Arqel consome.
 *
 * Hooks devem aceitar `EchoLike` e nunca depender de tipos internos do
 * pacote `laravel-echo` — assim consumidores podem fornecer mocks ou
 * implementações alternativas (por exemplo Soketi).
 */
export interface EchoLike {
  channel: (name: string) => EchoChannelLike;
  private: (name: string) => EchoChannelLike;
  join: (name: string) => PresenceChannelLike;
  leave: (name: string) => void;
  leaveChannel: (name: string) => void;
  disconnect: () => void;
  connector?: EchoConnectorLike;
}

/**
 * Configuração aceita por `setupEcho`. Espelha a forma do construtor do
 * Laravel Echo focada no broadcaster Reverb (que usa o protocolo Pusher).
 */
export interface EchoConfig {
  /** App key da Reverb / Pusher. */
  key: string;
  /** Host WebSocket (default `window.location.hostname`). */
  wsHost?: string;
  /** Porta WebSocket plain. */
  wsPort?: number | string;
  /** Porta WebSocket TLS. */
  wssPort?: number | string;
  /** Força TLS na conexão. */
  forceTLS?: boolean;
  /** Alias legacy de `forceTLS`. */
  encrypted?: boolean;
  /** Cluster Pusher (não usado pelo Reverb, mas aceito). */
  cluster?: string;
  /** Endpoint Laravel para auth de canais privados/presence. */
  authEndpoint?: string;
}
