<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import NotificationCenter from '@modules/Identity/resources/assets/js/components/NotificationCenter.vue';
import StatusPickerDropdown from '@modules/Presence/resources/assets/js/components/StatusPickerDropdown.vue';
import {
    Sun,
    Moon,
    LogOut,
    PanelLeftClose,
    PanelLeft,
    Layers,
} from 'lucide-vue-next';
import ImpersonateBanner from '@/components/ImpersonateBanner.vue';
import { useDarkMode } from '@/composables/useDarkMode';
import { useLang, __ } from '@/composables/useLang';
import { usePermissions } from '@/composables/usePermissions';
import { useUserStatus } from '@modules/Presence/resources/assets/js/composables/useUserStatus';
import { ensureOnlinePresenceJoined } from '@modules/Presence/resources/assets/js/composables/useOnlinePresence';
import { ensureIdleAwayWatching } from '@modules/Presence/resources/assets/js/composables/useIdleAway';
import { navigation, type NavItem } from '@/lib/navigation';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import type { UserStatus } from '@modules/Presence/resources/assets/js/types/user-status';

const { setStatus, refreshStatus, currentStatus } = useUserStatus();

// Only set online on first mount if user has no status yet in Redis.
// On subsequent Inertia navigations the layout remounts, so we check
// the current status first to avoid overwriting a manual status like "busy".
const hasInitialized = ref(false);

onMounted(async () => {
  // This layout is a component wrapped inside every page rather than an
  // Inertia persistent layout, so it remounts on every navigation. Joining
  // here is safe to repeat: it's a one-time-per-session no-op after the
  // first call — see useOnlinePresence.ts for why it has to be guarded there
  // rather than by anything Vue's lifecycle gives us.
  ensureOnlinePresenceJoined();
  ensureIdleAwayWatching();

  if (!hasInitialized.value) {
    hasInitialized.value = true;
    await refreshStatus();
    // Only set online if user has no active status in Redis
    if (!currentStatus.value || currentStatus.value === 'offline') {
      await setStatus('online');
    }
  }
});

// Set status to offline when tab is closed or navigated away
const handleBeforeUnload = () => {
  fetch('/api/user/status', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
    },
    body: JSON.stringify({ status: 'offline' }),
    keepalive: true,
  });
};

onMounted(() => {
  window.addEventListener('beforeunload', handleBeforeUnload);
});

onUnmounted(() => {
  window.removeEventListener('beforeunload', handleBeforeUnload);
});

defineProps<{
    auth: {
        user: {
            id: string;
            name: string;
            email: string;
            is_admin: boolean;
            avatar?: string;
        };
    };
    userStatus?: UserStatus;
    title?: string;
}>();

const page = usePage();
const activeFeatures = computed(() => page.props.activeFeatures as string[] | undefined);
const impersonating = computed(() => page.props.impersonating as {
    is_impersonating: boolean;
    original_user_id?: number | null;
    original_user?: { name: string; email: string } | null;
    target_user?: { name: string; email: string };
} | undefined);

const { isDark, toggleDark } = useDarkMode();
const { hasPermission } = usePermissions();
const collapsed = ref(false);

const visibleNav = computed(() => {
    const user = (page.props.auth as { user?: { is_admin?: boolean } } | undefined)?.user;
    const features = activeFeatures.value ?? [];

    return navigation.filter((item) => {
        if (item.adminOnly && user?.is_admin !== true) return false;
        if (item.feature && !features.includes(item.feature)) return false;

        // Admins bypass RBAC, matching how the policies decide.
        if (item.permission && user?.is_admin !== true && !hasPermission(item.permission)) {
            return false;
        }

        return true;
    });
});

const isCurrent = (item: NavItem): boolean => {
    const patterns = item.active ?? item.route;

    return (Array.isArray(patterns) ? patterns : [patterns]).some((pattern) =>
        route().current(pattern),
    );
};

const toggleSidebar = () => {
    collapsed.value = !collapsed.value;
};

const initials = (name: string): string => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-screen bg-background">
        <!-- Sidebar -->
        <aside
            :class="[
                'fixed left-0 top-0 z-40 h-screen border-r border-border bg-card transition-all duration-300 flex flex-col',
                collapsed ? 'w-16' : 'w-64',
            ]"
        >
            <!-- Header -->
            <div
                :class="[
                    'flex h-16 items-center border-b border-border',
                    collapsed ? 'px-2 justify-center' : 'px-4 gap-3',
                ]"
            >
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-9 w-9 shrink-0"
                    @click="toggleSidebar"
                >
                    <PanelLeftClose v-if="!collapsed" class="h-4 w-4" />
                    <PanelLeft v-else class="h-4 w-4" />
                </Button>

                <div
                    v-if="!collapsed"
                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary"
                >
                    <Layers class="h-5 w-5 text-primary-foreground" />
                </div>
                <span
                    v-if="!collapsed"
                    class="text-lg font-semibold text-foreground"
                >
                    Boilerplate
                </span>
            </div>

            <!-- Navigation: rendered from the registry in lib/navigation.ts.
                 Gates live beside each entry there, so a link cannot appear
                 without them. -->
            <nav class="flex-1 space-y-1 p-2">
                <template v-for="item in visibleNav" :key="item.route">
                    <p
                        v-if="item.section && !collapsed"
                        class="px-3 mb-2 mt-4 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                    >
                        {{ __(item.section) }}
                    </p>

                    <Link
                        :href="route(item.route)"
                        :class="[
                            'flex items-center rounded-lg text-sm font-medium transition-colors',
                            collapsed ? 'justify-center p-3' : 'gap-3 px-3 py-2',
                            isCurrent(item)
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                        ]"
                    >
                        <component :is="item.icon" class="h-5 w-5 shrink-0" />
                        <span v-if="!collapsed">{{ __(item.label) }}</span>
                    </Link>
                </template>
            </nav>

            <!-- Footer -->
            <div class="border-t border-border p-2">
                <StatusPickerDropdown
                    :avatar-url="auth.user.avatar"
                    :user-name="auth.user.name"
                    :initial-status="userStatus"
                    :compact="collapsed"
                />
            </div>
        </aside>

        <!-- Main Content -->
        <main :class="['flex-1 transition-all duration-300 flex flex-col', collapsed ? 'ml-16' : 'ml-64']">
            <!-- Impersonate Banner -->
            <ImpersonateBanner
                v-if="impersonating?.is_impersonating"
                :user-name="auth.user.name"
            />
            <!-- Header -->
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-border bg-background/80 backdrop-blur-sm px-6">
                <div>
                    <slot name="header" />
                </div>
                <div class="flex items-center gap-2">
                    <NotificationCenter />
                    <Button variant="ghost" size="icon" @click="toggleDark">
                        <Sun v-if="isDark" class="h-5 w-5" />
                        <Moon v-else class="h-5 w-5" />
                    </Button>
                    <Link :href="route('logout')" method="post" as="button">
                        <Button variant="ghost" size="sm" class="gap-2">
                            <LogOut class="h-4 w-4" />
                            {{ __('Sign out') }}
                        </Button>
                    </Link>
                </div>
            </header>

            <!-- Page Content -->
            <div class="p-6">
                <slot />
            </div>
        </main>
    </div>
</template>
