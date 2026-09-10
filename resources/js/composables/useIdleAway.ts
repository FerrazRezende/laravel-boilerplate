import { useUserStatus } from './useUserStatus';

// Module-level singleton, same reasoning as useOnlinePresence.ts: the layout
// remounts on every Inertia navigation, so the idle timer and listeners must
// live outside any component instance to survive that and be started once.
const IDLE_TIMEOUT_MS = 5 * 60 * 1000;
const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'scroll', 'click', 'touchstart'] as const;

let watching = false;
let idleTimer: ReturnType<typeof setTimeout> | null = null;
let wentAwayAutomatically = false;

export function ensureIdleAwayWatching(): void {
  if (watching) return;
  watching = true;

  const { currentStatus, setStatus } = useUserStatus();

  const goAway = () => {
    if (currentStatus.value !== 'online') return;
    wentAwayAutomatically = true;
    void setStatus('away');
  };

  const resetTimer = () => {
    if (idleTimer) clearTimeout(idleTimer);
    idleTimer = setTimeout(goAway, IDLE_TIMEOUT_MS);
  };

  const onActivity = () => {
    // Only auto-revert a transition this watcher made itself — a status the
    // user picked by hand (including 'away') is left alone.
    if (wentAwayAutomatically && currentStatus.value === 'away') {
      wentAwayAutomatically = false;
      void setStatus('online');
    } else if (currentStatus.value !== 'away') {
      wentAwayAutomatically = false;
    }

    resetTimer();
  };

  ACTIVITY_EVENTS.forEach((event) => window.addEventListener(event, onActivity, { passive: true }));

  resetTimer();
}
