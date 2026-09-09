import { Flag, Home, Shield, Users, type LucideIcon } from 'lucide-vue-next';

/**
 * The sidebar is rendered from this list, so a link cannot reach the screen
 * without passing the gates declared beside it. Adding a `v-if` in the layout
 * is what used to be forgotten; there is no longer a second way in.
 */
export interface NavItem {
    /** Ziggy route name the link points at. */
    route: string;
    /** Route pattern(s) that mark the item active, defaults to `route`. */
    active?: string | string[];
    label: string;
    icon: LucideIcon;
    /** Pennant flag that must be active. Omit for always-available items. */
    feature?: string;
    /** Permission required, e.g. `posts.view`. Omit to require none. */
    permission?: string;
    /** Restrict to god admins. */
    adminOnly?: boolean;
    /** Section heading rendered above this item. */
    section?: string;
}

export const navigation: NavItem[] = [
    {
        route: 'dashboard',
        label: 'Home',
        icon: Home,
    },
    {
        route: 'system.features.index',
        active: 'system.features.*',
        label: 'Features',
        icon: Flag,
        adminOnly: true,
        section: 'System',
    },
    {
        route: 'system.permissions.index',
        active: ['system.permissions.*', 'system.roles.*'],
        label: 'Permissions',
        icon: Shield,
        adminOnly: true,
    },
    {
        route: 'system.users.index',
        active: 'system.users.*',
        label: 'Users',
        icon: Users,
        adminOnly: true,
    },
];
