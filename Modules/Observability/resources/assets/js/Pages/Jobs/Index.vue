<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Activity, ExternalLink, Play } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { __ } from '@/composables/useLang';
import { jobs, seedJobs, watchAdminJobs, type JobProgress } from '../../composables/useJobProgress';

const props = defineProps<{
    jobs: JobProgress[];
    horizonUrl: string;
}>();

const running = computed(() => Array.from(jobs.values()).sort((a, b) => b.started_at.localeCompare(a.started_at)));

let stopWatching: (() => void) | null = null;

onMounted(() => {
    seedJobs(props.jobs);
    stopWatching = watchAdminJobs();
});

onUnmounted(() => {
    stopWatching?.();
});

const startedAt = (job: JobProgress) => new Date(job.started_at).toLocaleTimeString();
</script>

<template>
    <Head :title="__('Jobs')" />

    <AuthenticatedLayout :auth="$page.props.auth">
        <template #header>
            <h1 class="text-2xl font-semibold text-foreground">{{ __('Running jobs') }}</h1>
        </template>

        <div class="space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="max-w-2xl text-sm text-muted-foreground">
                    {{ __('Live progress for jobs that report it. Horizon stays the place to triage failures.') }}
                </p>

                <div class="flex shrink-0 items-center gap-2">
                    <Button variant="outline" size="sm" @click="router.post(route('system.jobs.demo'), {}, { preserveScroll: true })">
                        <Play class="mr-2 h-4 w-4" />
                        {{ __('Run a demo job') }}
                    </Button>
                    <a :href="horizonUrl" target="_blank" rel="noopener">
                        <Button variant="ghost" size="sm">
                            {{ __('Open Horizon') }}
                            <ExternalLink class="ml-2 h-4 w-4" />
                        </Button>
                    </a>
                </div>
            </div>

            <Card v-if="running.length === 0">
                <CardContent class="flex flex-col items-center gap-3 py-12 text-center">
                    <Activity class="h-8 w-8 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">{{ __('No jobs running right now.') }}</p>
                </CardContent>
            </Card>

            <Card v-else class="overflow-hidden py-0">
                <CardContent class="p-0">
                    <div
                        v-for="(job, i) in running"
                        :key="job.id"
                        class="px-5 py-4"
                        :class="{ 'border-t border-border': i > 0 }"
                    >
                        <div class="flex items-baseline justify-between gap-4">
                            <span class="font-medium">{{ job.label }}</span>
                            <span class="font-mono text-sm tabular-nums text-muted-foreground">{{ job.percentage }}%</span>
                        </div>

                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-primary transition-all duration-300"
                                :style="{ width: `${job.percentage}%` }"
                            />
                        </div>

                        <p class="mt-2 font-mono text-xs text-muted-foreground">
                            {{ __('Started') }} {{ startedAt(job) }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
