import { reactive } from 'vue';
import { getEcho } from './echo';

/**
 * Live roster of connected users, fed by the 'online-users' presence channel.
 * Module-level singleton, not per-component state: AuthenticatedLayout is a
 * component wrapped inside every page rather than an Inertia persistent
 * layout, so it remounts on every navigation. Joining once here — guarded by
 * `joined` below — means the channel is subscribed exactly once per browser
 * session regardless of how many times the layout itself mounts; joining
 * again per mount would keep adding new here/joining/leaving callbacks to the
 * same shared channel, since Echo's presence API offers no way to remove just
 * one of them without also removing every other listener bound to that event.
 */
export const onlineUserIds = reactive(new Set<string>());

let joined = false;

interface PresenceMember {
    id: string;
    name: string;
}

export function ensureOnlinePresenceJoined(): void {
    if (joined) return;
    joined = true;

    const channel = getEcho().join('online-users');

    channel.here((members: PresenceMember[]) => {
        members.forEach((member) => onlineUserIds.add(member.id));
    });
    channel.joining((member: PresenceMember) => {
        onlineUserIds.add(member.id);
    });
    channel.leaving((member: PresenceMember) => {
        onlineUserIds.delete(member.id);
    });
}
