<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useLang, __ } from '@/composables/useLang';
import { useDarkMode } from '@/composables/useDarkMode';
import {
    Database,
    Key,
    Zap,
    Globe,
    Server,
    Shield,
    Workflow,
    GitBranch,
    Languages,
    Sun,
    Moon,
    Copy,
    Check,
    ArrowRight,
} from 'lucide-vue-next';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const { availableLocales, currentLocale, setLocale } = useLang();
const showLangMenu = ref(false);
const { isDark, toggleDark } = useDarkMode();

const installCommand = 'boilerplate new meu-app';
const copied = ref(false);
const copyCommand = async () => {
    try {
        await navigator.clipboard.writeText(installCommand);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        copied.value = false;
    }
};

const stack = [
    { name: 'Laravel 13', role: __('Application framework (PHP 8.5+)') },
    { name: 'Inertia.js + Vue 3', role: __('Front-end, no separate API') },
    { name: 'PostgreSQL', role: __('Primary datastore') },
    { name: 'Redis', role: __('Cache & session store') },
    { name: 'Laravel Horizon', role: __('Job queue (Redis-backed)') },
    { name: 'Laravel Reverb', role: __('Realtime broadcasting') },
    { name: 'RustFS', role: __('S3-compatible object storage') },
    { name: 'Laravel Pennant', role: __('Feature flags') },
    { name: 'Spatie Permission', role: __('Roles & permissions (RBAC)') },
    { name: 'KSUID', role: __('Sortable, URL-safe primary keys') },
];

const features = [
    {
        icon: Shield,
        title: __('RBAC & impersonation'),
        description: __('Roles and permissions seeded out of the box, plus admin "sign in as" for support and debugging.'),
    },
    {
        icon: GitBranch,
        title: __('Feature flags with rollout dates'),
        description: __('Pennant flags auto-activate in production from a configured first-deploy date, and stay on in local/dev.'),
    },
    {
        icon: Zap,
        title: __('Realtime out of the box'),
        description: __('Reverb and Echo channels wired up for live updates, no third-party realtime service needed.'),
    },
    {
        icon: Workflow,
        title: __('Async jobs'),
        description: __('Redis-backed queue supervised by Horizon, with a dashboard for throughput and failed jobs.'),
    },
    {
        icon: Server,
        title: __('Object storage'),
        description: __('RustFS, S3-compatible, for uploads and avatars.'),
    },
    {
        icon: Key,
        title: __('Sortable string IDs'),
        description: __('Every model uses 27-character KSUIDs instead of auto-increment integers, so IDs sort by creation time and are safe to expose.'),
    },
    {
        icon: Languages,
        title: __('Built-in i18n'),
        description: __('Portuguese, English and Spanish, switchable per user.'),
    },
    {
        icon: Database,
        title: __('Fully containerized'),
        description: __('Postgres, Redis, Horizon, RustFS and the app itself run from one docker-compose file.'),
    },
];
</script>

<template>
    <Head title="Boilerplate" />

    <div class="min-h-screen bg-background text-foreground">
        <!-- Header -->
        <header class="fixed inset-x-0 top-4 z-50 mx-auto w-[92%] max-w-3xl rounded-full border border-border bg-background/80 shadow-lg backdrop-blur-sm lg:w-[70%]">
            <div class="flex h-14 items-center justify-between px-4 sm:px-6">
                <Link href="/" class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary">
                        <Database class="h-5 w-5 text-primary-foreground" />
                    </div>
                    <span class="text-lg font-bold text-foreground">Boilerplate</span>
                </Link>

                <nav class="hidden items-center gap-6 md:flex">
                    <a href="#stack" class="text-sm text-muted-foreground transition-colors hover:text-foreground">{{ __('Stack') }}</a>
                    <a href="#features" class="text-sm text-muted-foreground transition-colors hover:text-foreground">{{ __('Features') }}</a>
                </nav>

                <div class="flex items-center gap-1.5">
                    <div class="relative">
                        <Button variant="ghost" size="icon" @click="showLangMenu = !showLangMenu" :title="__('Change language')">
                            <Globe class="h-5 w-5" />
                        </Button>

                        <div
                            v-if="showLangMenu"
                            class="absolute right-0 top-full mt-2 w-40 rounded-lg border border-border bg-popover p-1 shadow-lg"
                            @click.outside="showLangMenu = false"
                        >
                            <button
                                v-for="lang in availableLocales"
                                :key="lang.code"
                                @click="setLocale(lang.code); showLangMenu = false"
                                class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-popover-foreground transition-colors hover:bg-accent"
                                :class="{ 'bg-accent': currentLocale?.code === lang.code }"
                            >
                                <span>{{ lang.flag }}</span>
                                <span>{{ lang.label }}</span>
                            </button>
                        </div>
                    </div>

                    <Button variant="ghost" size="icon" @click="toggleDark" :title="__('Toggle theme')">
                        <Sun v-if="isDark" class="h-5 w-5" />
                        <Moon v-else class="h-5 w-5" />
                    </Button>

                    <template v-if="canLogin">
                        <Link v-if="$page.props.auth.user" :href="route('dashboard')">
                            <Button size="sm">{{ __('Dashboard') }}</Button>
                        </Link>
                        <template v-else>
                            <Link :href="route('login')" class="hidden sm:inline-flex">
                                <Button variant="ghost" size="sm">{{ __('Sign in') }}</Button>
                            </Link>
                            <Link v-if="canRegister" :href="route('register')">
                                <Button size="sm">{{ __('Create account') }}</Button>
                            </Link>
                        </template>
                    </template>
                </div>
            </div>
        </header>

        <main class="pt-28">
            <!-- Hero -->
            <section class="px-4 pb-20 pt-16 sm:px-6">
                <div class="relative mx-auto max-w-4xl rounded-2xl border border-border p-8 sm:p-12">
                    <span class="pointer-events-none absolute -left-px -top-px h-5 w-5 border-l-2 border-t-2 border-foreground/25" />
                    <span class="pointer-events-none absolute -right-px -top-px h-5 w-5 border-r-2 border-t-2 border-foreground/25" />
                    <span class="pointer-events-none absolute -bottom-px -left-px h-5 w-5 border-b-2 border-l-2 border-foreground/25" />
                    <span class="pointer-events-none absolute -bottom-px -right-px h-5 w-5 border-b-2 border-r-2 border-foreground/25" />

                    <div class="flex flex-col gap-1 border-b border-border pb-4 font-mono text-[11px] uppercase tracking-[0.2em] text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                        <span>{{ __('Spec') }} — Laravel Boilerplate</span>
                        <span>{{ __('Self-hosted · Docker Compose') }}</span>
                    </div>

                    <h1 class="mt-8 max-w-3xl text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-5xl md:text-6xl">
                        {{ __('One plate.') }}<br />
                        {{ __('Every new project cast from it.') }}
                    </h1>

                    <p class="mt-6 max-w-xl text-lg leading-relaxed text-muted-foreground">
                        {{ __('Auth, RBAC, feature flags, queues, realtime and object storage — wired once, ready every time you run the installer.') }}
                    </p>

                    <div class="mt-10 flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-3 rounded-lg border border-border bg-muted/50 px-4 py-3 font-mono text-sm">
                            <span class="select-none text-muted-foreground">$</span>
                            <code class="text-foreground">{{ installCommand }}</code>
                            <button
                                type="button"
                                @click="copyCommand"
                                class="ml-2 shrink-0 text-muted-foreground transition-colors hover:text-foreground"
                                :title="__('Copy command')"
                            >
                                <Check v-if="copied" class="h-4 w-4" />
                                <Copy v-else class="h-4 w-4" />
                            </button>
                        </div>

                        <Link v-if="canRegister && !$page.props.auth.user" :href="route('register')">
                            <Button size="lg" class="w-full sm:w-auto">
                                {{ __('Create account') }}
                                <ArrowRight class="ml-2 h-4 w-4" />
                            </Button>
                        </Link>
                    </div>
                </div>
            </section>

            <!-- Stack manifest -->
            <section id="stack" class="px-4 py-16 sm:px-6">
                <div class="mx-auto max-w-4xl">
                    <p class="font-mono text-xs uppercase tracking-[0.2em] text-muted-foreground">{{ __('Bill of materials') }}</p>
                    <h2 class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">{{ __('What ships inside') }}</h2>

                    <Card class="mt-8 overflow-hidden py-0">
                        <CardContent class="p-0">
                            <div
                                v-for="(item, i) in stack"
                                :key="item.name"
                                class="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-center sm:gap-4"
                                :class="{ 'border-t border-border': i > 0 }"
                            >
                                <span class="w-6 shrink-0 font-mono text-xs text-muted-foreground">{{ String(i + 1).padStart(2, '0') }}</span>
                                <span class="font-semibold sm:w-56 sm:shrink-0">{{ item.name }}</span>
                                <span class="text-sm text-muted-foreground">{{ item.role }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <!-- Features -->
            <section id="features" class="px-4 py-16 sm:px-6">
                <div class="mx-auto max-w-5xl">
                    <p class="font-mono text-xs uppercase tracking-[0.2em] text-muted-foreground">{{ __('Included') }}</p>
                    <h2 class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">{{ __('Batteries included, opinions kept') }}</h2>

                    <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Card v-for="feature in features" :key="feature.title" class="border-border">
                            <CardContent>
                                <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-muted">
                                    <component :is="feature.icon" class="h-5 w-5 text-foreground" />
                                </div>
                                <h3 class="font-semibold">{{ feature.title }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ feature.description }}</p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>

            <!-- Closing CTA -->
            <section class="px-4 py-16 sm:px-6">
                <div class="mx-auto max-w-4xl rounded-2xl border border-border bg-muted/30 p-8 text-center sm:p-12">
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ __('Already running?') }}</h2>
                    <p class="mx-auto mt-3 max-w-md text-muted-foreground">
                        {{ __('Sign in to the app this boilerplate ships with, or spin up a fresh one from the CLI.') }}
                    </p>
                    <div class="mt-6 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <template v-if="canLogin">
                            <Link v-if="$page.props.auth.user" :href="route('dashboard')">
                                <Button size="lg">{{ __('Go to dashboard') }}</Button>
                            </Link>
                            <template v-else>
                                <Link :href="route('login')">
                                    <Button variant="outline" size="lg">{{ __('Sign in') }}</Button>
                                </Link>
                                <Link v-if="canRegister" :href="route('register')">
                                    <Button size="lg">{{ __('Create account') }}</Button>
                                </Link>
                            </template>
                        </template>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t border-border px-4 py-10 sm:px-6">
            <div class="mx-auto flex max-w-5xl flex-col items-center justify-between gap-4 sm:flex-row">
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-md bg-primary">
                        <Database class="h-4 w-4 text-primary-foreground" />
                    </div>
                    <span class="text-sm font-semibold">Boilerplate</span>
                    <span class="text-sm text-muted-foreground">— {{ __('internal Laravel starter kit') }}</span>
                </div>
                <a
                    href="https://github.com/FerrazRezende/laravel-boilerplate"
                    target="_blank"
                    rel="noopener"
                    class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    GitHub
                </a>
            </div>
        </footer>
    </div>
</template>
