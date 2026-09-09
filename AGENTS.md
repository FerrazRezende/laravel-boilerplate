# AGENTS.md

Laravel 12 + Inertia/Vue 3 boilerplate. Postgres, Redis (cache, session, queue),
Horizon, Reverb, MinIO, Pennant feature flags, Spatie RBAC. Everything runs in
Docker Compose; nothing is installed on the host.

## Commands

```bash
make setup            # build, up, install, migrate
make up / make down
make artisan args="…" # any artisan command
make test             # phpunit
make pint             # formatter — run it ONLY on files you changed
make npm-build
```

Pint has never been run over the whole tree. `./vendor/bin/pint app/` reformats
~24 untouched files and buries your diff. Always pass explicit paths.

## Every feature needs both gates

They answer different questions and do not substitute for each other.

- **Feature flag** — does this capability exist for this user at all?
  Register it in `config/features.php`, then put `feature:<name>` on the route
  group. Controllers must not check flags themselves.
- **RBAC** — inside a capability that exists, what may this user do?
  A Policy per model, permissions named `<resource>.{view,create,update,delete}`,
  seeded in `RolePermissionSeeder`.

Two rules that are easy to get wrong:

- Policies must call `User::checkPermission()`, never Spatie's
  `hasPermissionTo()`. Only the former honours `denied_permissions`, which lets
  an admin revoke one permission from one person without rebuilding their role.
- `is_admin` bypasses RBAC, but **not** feature flags — except in
  `FeatureServiceProvider::resolveFeature`, which exempts admins from flags
  deliberately. Expect admins to keep access to a feature you switched off.

The frontend receives `activeFeatures` and `userPermissions` from
`HandleInertiaRequests`. Gate nav links on both; gate buttons on a `can` array
the controller sends, so the UI never disagrees with the Policy.

## Conventions

- **Queries** live in Eloquent scopes (`scopePublished`), not in controllers.
  Compose them with `when()` / `unless()` instead of branching around the query.
- **Route model binding** — type-hint the model, never look it up from an id.
- **Dependency injection** — type-hint services in the constructor or method.
  Reach for `app()` only where injection is impossible, such as middleware.
- **Ids are KSUIDs.** Models `use HasKsuid`; migrations declare `char(*, 27)`,
  never `id()` or `foreignId()`.
- **All user-facing strings** go through `__()` and exist in `lang/en.json`,
  `lang/pt.json` and `lang/es.json`. Keys are the English sentence.
- **Auth is stock Breeze.** Do not add bespoke steps. Admin-created users are
  invited by email to set their own password; `MustVerifyEmail` is off on
  purpose, since arriving via an emailed link already proves the address.
- Return **404, not 403**, for records the user may not know exist. A 403
  confirms existence.

## Comments

Adapted from John Ousterhout's *A Philosophy of Software Design* (ch. 12–16) and
Google's engineering practices. The rule in one line:

> **Comments must add precision or intuition that the code cannot carry. If a
> comment restates the code, delete it.**

Write a comment when, and only when, one of these is true:

- **Why, not what.** A non-obvious reason: a constraint, a past bug, an
  invariant, a deliberate trade-off, or an option rejected for a reason.
- **Surprise.** Behaviour a competent reader would not predict from the code.
- **Interface contract.** What a caller must know to use a public method
  correctly and cannot see from the signature.

Do not write:

- Restatements — `// increment the counter` over `$counter++`.
- Section banners, `// --- Helpers ---`, changelogs, commented-out code.
- Narration of the task that produced the change: "added for the invite flow",
  "fixes issue #123", "as requested". That belongs in the commit message and
  rots as the code moves.
- Docblocks that only repeat the signature. A `@param` that adds no information
  beyond the type hint is noise; type hints already say it.

Prefer renaming to commenting. A comment explaining a bad name is a defect
report against the name. When a comment is warranted, keep it to one or two
lines, place it above the code it explains, and write full sentences.

## Gotchas

- npm must run as the host user; running it as root leaves `node_modules`
  owned by root and the `vite` container then cannot write its cache, which
  surfaces as bogus MIME/CORS errors in the browser. `make` handles this.
- Pennant caches resolved flag values in the `features` table. Changing a
  `FeatureSetting` row directly does nothing until `Feature::purge()` runs, so
  always go through `FeatureFlagService`.
- Host ports are `APP_PORT` / `DB_HOST_PORT` / `REDIS_HOST_PORT`, separate from
  the in-network `DB_PORT` / `REDIS_PORT`. Change the former on a port clash;
  changing the latter breaks the app's own connections.
