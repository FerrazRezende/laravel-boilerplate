# Spec: job progress reporting

Status: draft, not implemented. Written for review.

## Problem

Horizon answers "what is queued, what failed". It does not answer "how far along
is this job, and is it stuck". Its Metrics tab carries historical runtime per job
class and wait time per queue, but nothing live and nothing per individual job.

## Why this cannot live inside Horizon

Checked against the installed `laravel/horizon` v5.49:

- The package publishes only `HorizonServiceProvider` and `config/horizon.php`
  (`src/HorizonServiceProvider.php`). There is no view or asset publish tag.
- The UI is a compiled bundle at `vendor/laravel/horizon/dist/app.js`, loaded by a
  single `resources/views/layout.blade.php`. There is no slot, card or column
  extension point.

Adding a progress column to Horizon therefore means editing compiled JavaScript
inside `vendor/`, which `composer update` overwrites. That is a fork with extra
steps, so this spec keeps Horizon untouched and puts progress elsewhere.

## Scope

**In:** a trait jobs opt into, a place to store progress, broadcasting, and a
minimal screen to watch running jobs.

**Out, deliberately:** Laravel Pulse, CPU/RAM metrics, Prometheus/Grafana, and a
queue-capacity panel. Queue depth and time-to-clear already exist in Horizon's
`api/workload` and are not worth reimplementing until there is a reason.

## Design

### 1. The trait

```php
class ImportSpreadsheet implements ShouldQueue
{
    use Queueable, TracksProgress;

    public function handle(): void
    {
        $rows = $this->rows();

        foreach ($rows as $i => $row) {
            $this->import($row);
            $this->progress($i + 1, count($rows));   // or $this->progress(42)
        }
    }
}
```

`progress(int $done, ?int $total = null)` accepts either absolute counts or a
percentage. It should be cheap and safe to call in a tight loop — see throttling
below.

The trait also needs a label. Default to the job's class basename; allow
`progressLabel(): string` to override, so the screen can say "Importing 3.200
rows" rather than `ImportSpreadsheet`.

### 2. Storage

Redis, one key per job: `job-progress:{jobId}`, holding percentage, label,
started-at and updated-at. TTL of a few minutes, refreshed on each write, so a
crashed worker leaves no permanent entry and no cleanup job is needed.

A running-jobs index (a Redis set or sorted set by start time) is needed so the
screen can list without `KEYS`, which must never be used against a production
Redis.

**Open question:** which job id. Laravel's `$this->job->getJobId()` is the queue
job id and changes on retry. If progress should survive a retry, the job needs a
stable id of its own — a KSUID assigned at construction, consistent with the rest
of the project.

### 3. Throttling

A job looping over 100k rows would otherwise issue 100k Redis writes and drown
the queue it is reporting on. The trait should write at most once per N percent
or once per M milliseconds, whichever the implementation picks, and always flush
the terminal value.

### 4. Broadcasting

The project already runs Reverb, and `UserStatusUpdatedEvent` is the pattern to
follow: `ShouldBroadcast`, a `PrivateChannel`, an explicit `broadcastAs()` and a
trimmed `broadcastWith()`.

A `JobProgressUpdated` event on a private channel scoped to whoever should see
it. **Open question:** scope. Two candidates, and they are not equivalent:

- `private-jobs.{userId}` — the user who dispatched it watches their own job.
  This is the case that actually matters day to day: the person waiting on an
  import wants a bar on the page that started it.
- `private-jobs-admin` — every job, for an operator screen.

Doing both means the trait must know who dispatched the job, which means
capturing the authenticated user at construction. That is a real coupling and
should be decided before implementing, not discovered halfway.

Broadcast must respect the throttle above, or every progress tick becomes a
websocket frame.

### 5. The screen

`/system/jobs` — Inertia/Vue in the project's own design, behind a feature flag
and `is_admin`, per the conventions in AGENTS.md. Strings in `lang/{en,pt,es}.json`.

Per running job: label, elapsed time, and a progress bar when the job reports
one. For jobs that do not use the trait, show elapsed time against the historical
average for that job class, which Horizon already records — that answers "is this
stuck?" without any instrumentation.

Links out to `/horizon` for failed jobs and deep inspection. Horizon stays the
tool for triage; this screen is only the live view.

## Edge cases to handle

- **Job fails mid-flight.** Progress key must be cleared, not left frozen at 60%.
- **Job is retried.** Depends on the id decision above.
- **Job never calls `progress()`.** Must degrade to elapsed-time-only, never an
  empty or fake bar.
- **Worker killed.** TTL handles it; the screen should not show entries whose
  updated-at is stale.
- **Redis unavailable.** `progress()` must never fail the job it is reporting on.
  Swallow and continue.

## Prior art in this repository, to reconcile

`resources/js/composables/useEcho.ts` already declares `DatabaseStepUpdated` with
`step` and `progress` fields — the exact shape this spec needs. It is dead: no
file imports it, and there is no `Database` model or controller. Related vestiges
from the same removed feature: `DATABASE_CREATED` and `CREDENTIAL_CREATED` in
`UserActivityTypeEnum`, and the "Databases" / "Create database" strings in the
three lang files.

Decide before implementing whether to revive that shape as the contract or delete
it. Leaving a dead duplicate of the new event's payload next to the new event is
the worst of the three options.

## Decisions needed before implementation

1. Job identity: queue job id, or a KSUID owned by the job that survives retries?
2. Broadcast scope: dispatcher-only, admin-only, or both?
3. Ship the `/system/jobs` screen in the same change, or only the trait plus a
   demo consumer on the page that dispatches?
4. Delete the dead `useEcho.ts` and the database vestiges as part of this, or in a
   separate cleanup?
