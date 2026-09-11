# AGENTS.md

Laravel 13 + Inertia/Vue 3 boilerplate. Postgres, Redis (cache, session, queue),
Horizon, Reverb, RustFS, Pennant feature flags, Spatie RBAC. Domain code is
organized into modules under `Modules/` (see "Modules" below) rather than a
flat `app/`. Everything runs in Docker Compose; nothing is installed on the
host.

## Commands

```bash
make setup            # build, up, install, migrate
make up / make down
make artisan args="…" # any artisan command
make test             # phpunit
make pint             # formatter — run it ONLY on files you changed
make npm-build
make octane-reload    # PHP changes need this; see below
```

**The app runs on Octane (Swoole), not PHP-FPM.** Nginx proxies to it on port
8000. Two consequences you must work with:

- **Edited PHP is not picked up until workers reload.** Run `make octane-reload`
  after changing anything under `app/`, `Modules/`, `config/`, `routes/` or
  `bootstrap/`. Blade and frontend assets are unaffected. A newly
  `composer require`-d package needs more than a reload — see Gotchas.
- **The application instance outlives the request.** Anything you leave on it
  leaks into the next user's request in that worker: static properties,
  singletons holding request data, and globals like `App::setLocale()`. Set such
  state per request or not at all. `Modules/FeatureFlags/routes/api.php`
  applies `SetLocaleMiddleware` for exactly this reason — a group that never
  sets a locale inherits whatever the worker last used.

Pint has never been run over the whole tree. `./vendor/bin/pint app/` reformats
~24 untouched files and buries your diff. Always pass explicit paths.

## Every feature needs both gates

They answer different questions and do not substitute for each other.

- **Feature flag** — does this capability exist for this user at all?
  Register it in `Modules/FeatureFlags/config/config.php` (merged under both
  the `featureflags` and `features` config keys — see the Modules section),
  then put `feature:<name>` on the route group. Controllers must not check
  flags themselves.
- **RBAC** — inside a capability that exists, what may this user do?
  A Policy per model, permissions named `<resource>.{view,create,edit,delete}`,
  seeded in `Modules/Permissions/database/seeders/RolePermissionSeeder.php`.
  Note `edit`, not `update` — this matches `usePermissions().canEdit()` on
  the frontend.

Two rules that are easy to get wrong:

- Policies must call `User::checkPermission()`, never Spatie's
  `hasPermissionTo()`. Only the former honours `denied_permissions`, which lets
  an admin revoke one permission from one person without rebuilding their role.
- `is_admin` bypasses RBAC, but **not** feature flags — except in
  `Modules\FeatureFlags\Providers\FeatureFlagsServiceProvider::resolveFeature`,
  which exempts admins from flags deliberately. Expect admins to keep access
  to a feature you switched off.

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
- **All user-facing strings** go through `__()`, on both sides. In PHP that is
  Laravel's global helper; in Vue it is a helper you must import
  (`import { __ } from '@/composables/useLang'`), reading the `translations`
  Inertia prop. It is also registered as a global template property, so
  `{{ __('Save') }}` works without the import — but `<script setup>` needs it,
  and that is where hardcoded strings hide: a label map or a toast message in
  the script block is just as user-facing as the template.
  Keys are the English sentence, living in the owning module's
  `lang/{en,pt,es}.json` — or root `lang/{en,pt,es}.json` if 2+ modules share
  the literal string. See the Modules section for how these get merged.
  `TranslationCoverageTest` fails on a key with no entry, on a locale missing a
  key its siblings have, and on user-facing text left unwrapped in a template.
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

## Enums

Any closed, fixed set of values — `Modules/<Name>/app/Enums/*` — follows the
same shape:

- **Backed by `string`**, never `int`. A string value is self-documenting in
  the database and in API responses; an int forces a lookup to mean anything.
- **Business logic that varies per case lives on the enum**, as a method with
  a `match ($this)`, not as a conditional scattered across services or
  components — `label()`, `color()`, `isActive()`. One enum accumulates every
  case-dependent rule instead of each caller re-deciding it.
- **Never re-type a case's value as a string literal elsewhere.** Reference
  the case itself (`UserStatusEnum::ONLINE`) or its `->value`; use
  `Enum::cases()` for exhaustive lists. A literal that happens to match today
  silently stops matching the day a case is renamed, and nothing catches it.
- **Validate with `Rule::enum(EnumClass::class)`**, not a hand-written
  `in:a,b,c` list or `Rule::in(array_column(...))`. The enum is then the only
  place that knows its own values — adding a case changes one file, not two
  that have to be remembered to stay in sync.
- **Cast every column that holds a case**, including nullable ones
  (`'from_status' => UserStatusEnum::class` casts a null column to `null`,
  not an error) — a column left as a plain string defeats the enum the moment
  someone reads it back.
- **Migrations declare `string`, not a native DB `enum` column.** A native
  enum turns "add a case" into a schema migration; a string column with an
  app-level enum turns it into a one-line PHP change.
- The frontend mirrors each enum as a hand-kept literal union (e.g.
  `Modules/Presence/resources/assets/js/types/user-status.ts`) — there is no
  codegen in this template. Adding or renaming a case means updating that
  union too; nothing will warn you if you forget.

## Modules

Domain code lives under `Modules/<Name>/`, one module per business domain
(`Identity`, `Profile`, `Presence`, `FeatureFlags`, `Permissions`), managed by
`nwidart/laravel-modules`. Each module mirrors the shape of root `app/` —
`app/Http/Controllers`, `app/Models`, `app/Services`, and so on — plus its own
`routes/`, `database/migrations`, `lang/`, `tests/`, and
`resources/assets/js/Pages` for its Vue pages/components.

**What stays in root `app/` instead of a module:** framework glue with no
single domain owner — `HandleInertiaRequests`, `SetLocaleMiddleware`,
`ShareTranslationsMiddleware`, the base `Controller` class, `HasKsuid`,
`AppServiceProvider`, `HorizonServiceProvider`. If you're adding something
used by exactly one domain, it's a module. If it's read or extended by every
module, it's core — and core additions should be rare, since core is the one
place every module-owner has to coordinate on.

**The one sanctioned cross-module dependency**: every module may depend on
`Identity` for the `User` model (`Modules\Identity\Models\User`) and on root
`App\Traits\HasKsuid`. No other module-to-module dependency is allowed on the
PHP side — if your feature needs another module's model or service, that's a
sign either the boundary is wrong or the shared thing belongs in
`Identity`/core instead. On the Vue side this is looser: core components
(`AuthenticatedLayout.vue`) import module components via the `@modules`
Vite alias, and one module's admin UI may read another module's exported
composables/state for genuinely cross-cutting display concerns (Permissions'
admin user list reading Presence's live online/offline state is the existing
example) — that's a read-only frontend import, not a backend coupling, and is
fine.

**Routes**: each module's own `RouteServiceProvider` auto-loads its
`routes/web.php`/`routes/api.php` — nothing to register in `bootstrap/app.php`
for a new module's routes to work. If your routes file already declares its
own full `Route::middleware([...])->group(...)` wrapper (the common case,
since most of this app's route files do), override `map()` to
`$this->loadRoutesFrom(...)` directly rather than accepting the package's
default wrapper — the default re-wraps in `web`/`api` groups, which
double-applies middleware if your file already declared them.

**Translations**: each module owns `Modules/<Name>/lang/{en,pt,es}.json`.
Laravel's translator merges every module's JSON file into one lookup keyed by
the literal English sentence, so `__('Enable feature')` works exactly the
same whether the key lives in root `lang/en.json` or
`Modules/FeatureFlags/lang/en.json` — **no `module::key` syntax, ever**. Put a
key in root `lang/*.json` only if 2+ modules use the identical literal
string (`Save`, `Cancel`, generic validation messages); otherwise it belongs
in the one module that uses it. Never let the same literal string exist in
two different modules' JSON files with different translations — whichever
module's provider boots last silently wins, and nothing will warn you.
`ShareTranslationsMiddleware` hands this same merged set to the frontend as
an Inertia prop; it does **not** get this for free from Laravel's translator
(that middleware reads JSON files directly, for reasons unrelated to
modules), so it explicitly merges root + every `Module::allEnabled()`
module's `lang/{locale}.json` itself. If you ever change how translations
are shared with the frontend, remember this second, independent merge point
exists — it's not automatic just because `__()` works.
`lang/_unused.{en,pt,es}.json` holds keys confirmed to have zero call sites
anywhere in the app (leftover marketing copy from an earlier landing page) —
excluded from every locale on purpose; don't revive it as a real locale file.

**Scaffolding a new domain module:**

```bash
php artisan module:make <Name>
```

`config/modules.php`'s `generator.stubs.files` is trimmed down so this no
longer scaffolds Blade/Mix defaults (its own `vite.config.js`,
`resources/assets/js/app.js`, `resources/views/index.blade.php`) — this app
is Inertia-only on one shared Vite entry, so those were always dead weight,
generated fresh and deleted by hand on every single module until that config
was trimmed. Nothing to clean up now; you get `app/`, `routes/`, `database/`,
`config/`, `composer.json`, `module.json` and nothing else. Mirror
`FeatureFlags` as the reference layout for everything else. Vue pages go under
`resources/assets/js/Pages/` — they're picked up automatically by the glob in
`resources/js/app.js` (and `ssr.js`), no Vite config change needed. If your
module needs global middleware (a new `feature:`-style route guard, for
example), the **class** lives in your module but its **registration** in
`bootstrap/app.php`'s middleware stack/alias map is a core, append-only edit
— that file is the one place cross-cutting request-lifecycle ordering
decisions get made. Run `composer dump-autoload` after scaffolding (the
merge-plugin picks up the new module's generated `composer.json`).

**Optional modules.** Some modules ship here enabled but are stripped from a
generated project unless the installer was asked for them (`Observability` and
`Ai`; `boilerplate new --obs --ai` keeps both). They live in this repo so they are
written, reviewed and tested like everything else rather than as templates
inside the installer. Each one owns an `uninstall.php` beside its `module.json`
that undoes its own wiring into shared files, run by
`scripts/remove-feature.php`; `RemoveFeatureTest` performs the real removal
against a copy of the tree on every `php artisan test`, so wiring a module into
a new shared file without updating its `uninstall.php` fails here.

Keep an optional module a **leaf**: nothing else may import it, its strings live
in its own `lang/`, and it registers no global middleware. That is what keeps
removal to a handful of needles — `Presence` is expensive to remove precisely
because `Permissions` and `Profile` reach into it.

**Docs that stay here.** `MODULES.md`, `AI.md` and `QUEUES.md` explain the
boilerplate on its GitHub page and are stripped from generated projects by
`scripts/strip-repo-docs.php`, which the installer runs every time. `AGENTS.md`
and `README.md` ship. Add a doc of that kind and it goes in that script's list,
along with whatever link the README carries to it — `StripRepoDocsTest` runs
the real script and fails on a dead link.

**This layout has a flat counterpart.** `scripts/to-mvc.php` converts the whole
project into a stock `app/`-based Laravel app; it's what `boilerplate new
--mvc` runs. The bulk of it is convention-driven, so a new module is picked up
without touching the script — but the few edits that can't be derived (the
Pennant wiring in `FeatureFlagsServiceProvider`, the Presence event binding,
`ShareTranslationsMiddleware`, the Vite resolver in `app.js`/`ssr.js`) assert
their target text before rewriting it, and abort if it moved. `ToMvcTest`
runs the real script against a copy of the tree on every `php artisan test`,
so if your change breaks the flat flavour you'll see it here, not in someone's
generated project. `make mvc-verify` runs the heavier pass: flatten, drop
nwidart, then run the flattened project's own suite and Pint.

## Gotchas

- **Never write `private-` into a broadcast channel name.** `PrivateChannel`
  adds it on the server, Echo's `private()` adds it on the client, and Laravel
  strips exactly one before matching `routes/channels.php`. Put it in yourself
  and it arrives doubled: the subscription still authorizes, and not one event
  is ever delivered. Register `Broadcast::channel('jobs-admin')`, broadcast on
  `new PrivateChannel('jobs-admin')`, subscribe with `echo.private('jobs-admin')`.
  The status channel shipped broken this way for a while — `PresenceChannelTest`
  and `JobsScreenTest` now pin each client name to the event that feeds it.
  Note that a test cannot catch this over HTTP: `phpunit.xml` sets
  `BROADCAST_CONNECTION=null`, and the null broadcaster authorizes everything
  without reading `routes/channels.php`.
- npm must run as the host user; running it as root leaves `node_modules`
  owned by root and the `vite` container then cannot write its cache, which
  surfaces as bogus MIME/CORS errors in the browser. `make` handles this.
- Pennant caches resolved flag values in the `features` table. Changing a
  `FeatureSetting` row directly does nothing until `Feature::purge()` runs, so
  always go through `FeatureFlagService`.
- Host ports are `APP_PORT` / `DB_HOST_PORT` / `REDIS_HOST_PORT`, separate from
  the in-network `DB_PORT` / `REDIS_PORT`. Change the former on a port clash;
  changing the latter breaks the app's own connections.
- `RUSTFS_PORT` (the host-side mapping) and `RUSTFS_PUBLIC_URL` (baked into
  every presigned avatar URL) are two separate values that both encode the
  same port. Change one on a port clash and forget the other, and avatars
  redirect to a port nothing is listening on — wrong host port, not a broken
  signature. Octane made this worse to debug: a stale worker kept signing
  URLs with the old `RUSTFS_PUBLIC_URL` well after the `.env` file and even
  `php artisan octane:reload` had already picked up the new one, so the fix
  needed a full `docker compose restart app`, not just a reload.
- nginx resolves the `app` upstream once at container start and caches that IP
  for its lifetime. Recreating `app` alone (e.g. `docker compose up -d --build
  app`) leaves nginx pointed at a dead IP — 502 on every request even though
  `app` itself is healthy. Recreate `nginx` too (`docker compose up -d
  --force-recreate nginx`) whenever `app` gets a new container.
- `composer require`-ing a new package while Octane is already running is not
  picked up by `make octane-reload` — the worker's OPcache had the old
  `vendor/composer/autoload_*.php` compiled in memory with timestamp
  validation off, so it kept throwing "Class not found" for the new package
  even after `composer dump-autoload` regenerated those files correctly on
  disk. Needs a full `docker compose restart app`, same fix and same root
  cause as the `RUSTFS_PUBLIC_URL` gotcha above.
