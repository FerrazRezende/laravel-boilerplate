<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { Send } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { __ } from '@/composables/useLang';
import { useChat } from '../composables/useChat';

const props = withDefaults(
    defineProps<{
        endpoint: string;
        placeholder?: string;
        emptyState?: string;
        heightClass?: string;
    }>(),
    { heightClass: 'h-72' },
);

const { messages, pending, error, send } = useChat(props.endpoint);
const draft = ref('');
const scroller = ref<HTMLElement | null>(null);

watch(
    () => messages.value.map((m) => m.content).join(''),
    async () => {
        await nextTick();
        if (scroller.value) {
            scroller.value.scrollTop = scroller.value.scrollHeight;
        }
    },
);

async function submit() {
    const text = draft.value;
    draft.value = '';
    await send(text);
}
</script>

<template>
    <div class="flex flex-col">
        <div
            ref="scroller"
            class="flex flex-col gap-3 overflow-y-auto rounded-lg border border-border bg-muted/30 p-4"
            :class="heightClass"
        >
            <p v-if="messages.length === 0" class="m-auto max-w-xs text-center text-sm text-muted-foreground">
                {{ emptyState ?? __('Ask anything about this application.') }}
            </p>

            <div
                v-for="(message, i) in messages"
                :key="i"
                class="max-w-[85%] rounded-lg px-3 py-2 text-sm leading-relaxed"
                :class="
                    message.role === 'user'
                        ? 'self-end bg-primary text-primary-foreground'
                        : 'self-start border border-border bg-background text-foreground'
                "
            >
                <span class="whitespace-pre-wrap">{{ message.content }}</span>
                <span
                    v-if="message.role === 'assistant' && message.content === '' && pending"
                    class="text-muted-foreground"
                >…</span>
            </div>
        </div>

        <p v-if="error" class="mt-2 text-sm text-destructive">{{ error }}</p>

        <form class="mt-3 flex items-center gap-2" @submit.prevent="submit">
            <input
                v-model="draft"
                type="text"
                :placeholder="placeholder ?? __('Type a message')"
                :disabled="pending"
                class="h-10 flex-1 rounded-md border border-border bg-background px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-60"
            />
            <Button type="submit" size="icon" :disabled="pending || draft.trim() === ''">
                <Send class="h-4 w-4" />
            </Button>
        </form>
    </div>
</template>
