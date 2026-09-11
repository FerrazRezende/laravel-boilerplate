## Structure

Domain code lives in a single `app/`, in stock Laravel shape:
`app/Http/Controllers`, `app/Http/Requests`, `app/Http/Resources`,
`app/Http/Middleware`, `app/Models`, `app/Services`, `app/Enums`,
`app/Events`, `app/Listeners`, `app/Policies`, `app/Notifications`,
`app/Providers`. Everything is under the `App\` namespace; there is no module
layer and no `nwidart/laravel-modules`.

Supporting trees follow the framework defaults too: `routes/`,
`database/migrations`, `database/seeders`, `database/factories`,
`lang/{en,pt,es}.json`, `tests/{Feature,Unit}`, and Vue under
`resources/js/Pages` with shared pieces in `resources/js/components`,
`resources/js/composables` and `resources/js/types`.

**Routes**: `routes/web.php` holds the web routes and `require`s
`routes/auth.php` (Breeze's auth routes) the way a stock Laravel app does.

`routes/api.php` is the exception worth knowing about: it is **not** wired
through `withRouting(api: ...)`. It writes its own `api/v1` prefix and picks
its own middleware (`auth:sanctum` plus `SetLocaleMiddleware`), so it is
loaded by an explicit `require` in `bootstrap/app.php`'s `then:` callback.
Moving it to the `api:` shortcut would double the URL prefix to
`/api/api/v1/...` and add the framework's `api` middleware group (rate
limiting included) on top of the stack it already declares. Leave it alone
unless you intend both of those changes.

**Translations**: keys are the English sentence and live in
`lang/{en,pt,es}.json`. `__('Enable feature')` resolves through Laravel's
translator as usual. Note that `ShareTranslationsMiddleware` also hands the
same locale file to the frontend as an Inertia prop by reading the JSON
directly rather than going through the translator — if you change how
translations reach the frontend, that's the second place to look.

**Feature flags**: `App\Providers\FeatureServiceProvider` registers every
`Feature::define()` from `config/features.php` and implements the rollout
strategies (percentage, user list, all, inactive) plus the admin bypass. A
new flag is a new entry in `config/features.php`; the provider picks it up
without further wiring.

**Adding new domain code**: put the class in the directory for its type and
give it the matching `App\` namespace — no registration step. New global
middleware is the one exception: the class goes in `app/Http/Middleware`, but
adding it to the stack or the alias map is an edit to `bootstrap/app.php`,
which is where request-lifecycle ordering is decided.
