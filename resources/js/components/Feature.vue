<script setup lang="ts">
/**
 * Renders its slot only while the named feature is active for the current user.
 *
 * Use it for buttons and sections inside a page. Navigation is gated by the
 * registry in navigation.ts instead, which cannot be forgotten; this can, so
 * prefer the registry wherever the choice exists.
 */
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ name: string }>();

const page = usePage();

const active = computed(() =>
    ((page.props.activeFeatures as string[] | undefined) ?? []).includes(props.name),
);
</script>

<template>
    <slot v-if="active" />
    <slot v-else name="fallback" />
</template>
