<script setup lang="ts">
import { ref } from 'vue';
import { MessageCircle, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { __ } from '@/composables/useLang';
import ChatPanel from './ChatPanel.vue';

const open = ref(false);
</script>

<template>
    <div class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
        <div
            v-if="open"
            class="w-[min(22rem,calc(100vw-3rem))] rounded-xl border border-border bg-popover p-4 shadow-xl"
        >
            <div class="mb-3 flex items-center justify-between">
                <span class="text-sm font-semibold text-popover-foreground">{{ __('Assistant') }}</span>
                <button
                    type="button"
                    class="text-muted-foreground transition-colors hover:text-foreground"
                    :aria-label="__('Close')"
                    @click="open = false"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <ChatPanel :endpoint="route('ai.chat')" />
        </div>

        <Button size="icon" class="h-12 w-12 rounded-full shadow-lg" :aria-label="__('Assistant')" @click="open = !open">
            <MessageCircle class="h-5 w-5" />
        </Button>
    </div>
</template>
