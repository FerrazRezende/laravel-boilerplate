<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Activity, ExternalLink, Play } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { __ } from '@/composables/useLang';
import { jobs, seedJobs, watchAdminJobs, type JobProgress } from '../../composables/useJobProgress';

interface Queue {
    name: string;
    connection: string;
    supervisor: string;
    capacity: number;
    min_workers: number;
    workers: number;
    running: number | null;
    pending: number | null;
    tracked: number;
    wait: number | null;
}

const props = defineProps<{
    jobs: JobProgress[];
    queues: Queue[];
    defaultQueue: string | null;
    horizonUrl: string;
}>();

const running = computed(() => Array.from(jobs.values()).sort((a, b) => b.started_at.localeCompare(a.started_at)));

const demoQueue = ref(props.defaultQueue ?? props.queues[0]?.name ?? '');

let stopWatching: (() => void) | null = null;
let poll: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    seedJobs(props.jobs);
    stopWatching = watchAdminJobs();

    // Worker and queue-depth counts have no event behind them — they are read
    // from Redis and Horizon on request — so this is the one thing on the
    // screen that polls. `only` keeps it to the queue props, and preserveState
    // keeps the websocket subscription from being torn down every five seconds.
    poll = setInterval(() => {
        router.reload({ only: ['queues'], preserveState: true, preserveScroll: true });
    }, 5000);
});

onUnmounted(() => {
    stopWatching?.();

    if (poll) {
        clearInterval(poll);
    }
});

const startedAt = (job: JobProgress) => new Date(job.started_at).toLocaleTimeString();

const runDemo = () => {
    router.post(
        route('system.jobs.demo'),
        { queue: demoQueue.value || null },
        // preserveState so the page is not remounted: a remount drops the
        // 'jobs-admin' subscription and re-authorizes it, and the first
        // progress report of the job being started lands in that gap.
        { preserveScroll: true, preserveState: true },
    );
};

const capacityPercent = (queue: Queue) =>
    queue.capacity > 0 ? Math.min(100, Math.round((queue.workers / queue.capacity) * 100)) : 0;

const waitLabel = (seconds: number | null) => {
    if (seconds === null || seconds <= 0) {
        return '—';
    }

    return seconds < 60 ? `${seconds}s` : `${Math.round(seconds / 60)}min`;
};

const queueVariant = (name: string) => {
    if (name === 'high') {
        return 'default';
    }

    return name === 'low' ? 'outline' : 'secondary';
};
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
                    <Select v-model="demoQueue">
                        <SelectTrigger class="w-32" size="sm">
                            <SelectValue :placeholder="__('Queue')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="queue in props.queues" :key="queue.name" :value="queue.name">
                                {{ queue.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Button variant="outline" size="sm" @click="runDemo">
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

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ __('Queues') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{{ __('Queue') }}</TableHead>
                                <TableHead>{{ __('Workers') }}</TableHead>
                                <TableHead class="text-right">{{ __('Running') }}</TableHead>
                                <TableHead class="text-right">{{ __('Waiting') }}</TableHead>
                                <TableHead class="text-right">{{ __('Time to clear') }}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="queue in props.queues" :key="queue.name">
                                <TableCell>
                                    <Badge :variant="queueVariant(queue.name)">{{ queue.name }}</Badge>
                                    <span class="ml-2 font-mono text-xs text-muted-foreground">{{ queue.connection }}</span>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-sm tabular-nums">
                                            {{ queue.workers }}/{{ queue.capacity }}
                                        </span>
                                        <div class="h-1.5 w-20 overflow-hidden rounded-full bg-muted">
                                            <div
                                                class="h-full rounded-full bg-primary transition-all duration-300"
                                                :style="{ width: `${capacityPercent(queue)}%` }"
                                            />
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right font-mono tabular-nums">{{ queue.running ?? '—' }}</TableCell>
                                <TableCell class="text-right font-mono tabular-nums">{{ queue.pending ?? '—' }}</TableCell>
                                <TableCell class="text-right font-mono tabular-nums text-muted-foreground">
                                    {{ waitLabel(queue.wait) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <p class="mt-3 text-xs text-muted-foreground">
                        {{ __('Workers shows what Horizon has running against what the queue is allowed. Zero workers with items waiting means Horizon is down.') }}
                    </p>
                </CardContent>
            </Card>

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
                            <span class="flex items-center gap-2">
                                <span class="font-medium">{{ job.label }}</span>
                                <Badge v-if="job.queue" :variant="queueVariant(job.queue)">{{ job.queue }}</Badge>
                            </span>
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
