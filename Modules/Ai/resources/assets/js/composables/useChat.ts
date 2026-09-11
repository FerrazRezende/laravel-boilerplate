import { ref } from 'vue';
import { __ } from '@/composables/useLang';

export interface ChatMessage {
  role: 'user' | 'assistant';
  content: string;
}

/**
 * Talks to the assistant over SSE.
 *
 * The agent's `stream()` response is a plain event stream, so this reads it with
 * fetch rather than pulling in a client library. History lives here in the
 * browser and is sent with each turn — nothing is persisted server-side.
 */
export function useChat(endpoint: string) {
  const messages = ref<ChatMessage[]>([]);
  const pending = ref(false);
  const error = ref<string | null>(null);

  async function send(text: string): Promise<void> {
    const content = text.trim();

    if (content === '' || pending.value) {
      return;
    }

    error.value = null;
    messages.value.push({ role: 'user', content });
    pending.value = true;

    // The reply is appended to as chunks arrive, so the bubble fills in live.
    const reply: ChatMessage = { role: 'assistant', content: '' };
    messages.value.push(reply);

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'text/event-stream',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        },
        body: JSON.stringify({
          message: content,
          history: messages.value.slice(0, -2),
        }),
      });

      if (!response.ok || !response.body) {
        throw new Error((await response.json().catch(() => ({})))?.message ?? `HTTP ${response.status}`);
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';

      for (;;) {
        const { done, value } = await reader.read();

        if (done) {
          break;
        }

        buffer += decoder.decode(value, { stream: true });

        // SSE frames are separated by a blank line. Each carries one stream
        // event as JSON; the run ends with a literal [DONE]. Only text deltas
        // are rendered — the rest describe tool calls and usage.
        const frames = buffer.split('\n\n');
        buffer = frames.pop() ?? '';

        frames.forEach((frame) => {
          frame
            .split('\n')
            .filter((line) => line.startsWith('data:'))
            .forEach((line) => {
              const payload = line.slice(5).trim();

              if (payload === '' || payload === '[DONE]') {
                return;
              }

              try {
                const event = JSON.parse(payload);

                if (event.type === 'text_delta') {
                  reply.content += event.delta ?? '';
                }
              } catch {
                // A frame we cannot parse is not worth breaking the reply over.
              }
            });
        });
      }
      // The provider is called after the response headers have gone out, so a
      // failure there (a bad key, most often) arrives as a stream that ends
      // without a single delta. Silence is the only symptom, so treat it as one.
      if (reply.content === '') {
        throw new Error(__('The assistant did not reply. Check the provider key and the logs.'));
      }
    } catch (e) {
      error.value = e instanceof Error ? e.message : String(e);
      messages.value.pop();
    } finally {
      pending.value = false;
    }
  }

  function reset(): void {
    messages.value = [];
    error.value = null;
  }

  return { messages, pending, error, send, reset };
}
