import { reactive } from 'vue';
import { getEcho } from '@/composables/echo';

export interface JobProgress {
  id: string;
  label: string;
  percentage: number;
  started_at: string;
}

/**
 * Live job progress, keyed by job id.
 *
 * Module-level state with a boolean guard rather than per-component refs: the
 * layout remounts on every Inertia navigation, so a component-scoped
 * subscription would pile up duplicates.
 */
export const jobs = reactive(new Map<string, JobProgress>());

let watching = false;

/**
 * Name carries no `private-` prefix — Echo adds it, and the server broadcasts
 * on PrivateChannel('jobs-admin'), which is that same wire name. Passing the
 * prefix here would double it and silently deliver nothing.
 */
export function watchAdminJobs(): () => void {
  if (watching) {
    return () => {};
  }

  watching = true;

  const echo = getEcho();
  const channel = echo.private('jobs-admin');

  channel.listen('.job.progress', (event: JobProgress) => {
    jobs.set(event.id, event);

    // 100% means the job is done; drop it once the bar has had a moment to land.
    if (event.percentage >= 100) {
      setTimeout(() => jobs.delete(event.id), 2000);
    }
  });

  return () => {
    echo.leave('jobs-admin');
    watching = false;
  };
}

export function seedJobs(initial: JobProgress[]): void {
  initial.forEach((job) => jobs.set(job.id, job));
}
