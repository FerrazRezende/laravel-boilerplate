import { ref } from 'vue';
import { getEcho, destroyEcho } from './echo';
import type { UserStatusChangedEvent } from '../types/user-status';

export type StatusUpdateCallback = (event: UserStatusChangedEvent) => void;

export type ConnectionState = 'connecting' | 'connected' | 'disconnected' | 'error';

/**
 * Echo Channels Composable
 *
 * Manages Laravel Echo/Reverb WebSocket connections for user status updates.
 * Uses the shared Echo singleton from echo.ts.
 */
export function useEchoChannels() {
  const isConnected = ref<boolean>(false);
  const connectionState = ref<ConnectionState>('disconnected');
  const connectionError = ref<Error | null>(null);

  const activeChannels = new Map<string, ReturnType<ReturnType<typeof getEcho>['private']>>();
  const activeListeners = new Map<string, string[]>();

  /**
   * Connect to Echo with proper authentication
   */
  async function connect(): Promise<void> {
    if (isConnected.value) {
      return;
    }

    connectionState.value = 'connecting';
    connectionError.value = null;

    try {
      const echo = getEcho();

      // Set up connection state monitoring
      echo.connector.pusher.connection.bind('connected', () => {
        isConnected.value = true;
        connectionState.value = 'connected';
        connectionError.value = null;
      });

      echo.connector.pusher.connection.bind('disconnected', () => {
        isConnected.value = false;
        connectionState.value = 'disconnected';
      });

      echo.connector.pusher.connection.bind('connecting', () => {
        connectionState.value = 'connecting';
      });

      echo.connector.pusher.connection.bind('error', (error: any) => {
        connectionState.value = 'error';
        connectionError.value = error instanceof Error ? error : new Error('Connection error');
        isConnected.value = false;
      });

      // Wait for connection to be established
      return new Promise((resolve, reject) => {
        const timeout = setTimeout(() => {
          reject(new Error('Connection timeout'));
        }, 10000);

        echo.connector.pusher.connection.bind('connected', () => {
          clearTimeout(timeout);
          resolve();
        });

        echo.connector.pusher.connection.bind('error', (error: any) => {
          clearTimeout(timeout);
          reject(error);
        });
      });
    } catch (error) {
      connectionState.value = 'error';
      connectionError.value = error instanceof Error ? error : new Error('Connection failed');
      isConnected.value = false;
      throw error;
    }
  }

  /**
   * Disconnect from Echo and cleanup all channels
   */
  function disconnect(): void {
    activeChannels.clear();
    activeListeners.clear();
    destroyEcho();
    isConnected.value = false;
    connectionState.value = 'disconnected';
  }

  /**
   * Get list of actively subscribed channel names
   */
  function getActiveChannels(): string[] {
    return Array.from(activeChannels.keys());
  }

  /**
   * Reconnect to Echo with existing channels
   */
  async function reconnect(): Promise<void> {
    disconnect();
    await connect();
  }

  /**
   * Listen to the global presence channel for status updates from any user.
   */
  function listenToPresenceChannel(callback: StatusUpdateCallback): (() => void) | null {
    // Without the `private-` prefix: Echo adds it, and the server broadcasts on
    // PrivateChannel('presence'), which is the same wire name.
    const channelName = 'presence';

    if (activeChannels.has(channelName)) {
      return null;
    }

    try {
      const echo = getEcho();
      const channel = echo.private(channelName);

      channel.listen('.status.updated', callback);

      activeChannels.set(channelName, channel);
      activeListeners.set(channelName, ['.status.updated']);

      return () => {
        try {
          echo.leave(channelName);
          activeChannels.delete(channelName);
          activeListeners.delete(channelName);
        } catch (error) {
          console.error('[useEchoChannels] Error leaving presence channel:', error);
        }
      };
    } catch (error) {
      console.error('[useEchoChannels] Error subscribing to presence channel:', error);
      return null;
    }
  }

  // Joining 'online-users' lives in useOnlinePresence.ts, not here: it needs
  // to happen exactly once per browser session (see that file for why), which
  // a method on this factory — called fresh by every component instance —
  // cannot guarantee on its own.

  return {
    isConnected,
    connectionState,
    connectionError,
    connect,
    disconnect,
    reconnect,
    listenToPresenceChannel,
    getActiveChannels,
  };
}
