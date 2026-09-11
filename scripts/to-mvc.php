<?php

/**
 * Converts this modular project into a flat Laravel MVC layout.
 *
 *     php scripts/to-mvc.php
 *
 * Everything under Modules/ lands in app/, routes/, database/, lang/,
 * resources/js/ and tests/, namespaces become App\, and nwidart disappears.
 *
 * Bulk moves are convention-driven, so a new module needs no change here.
 * The handful of edits that can't be derived (the Pennant wiring, the event
 * binding, the translations middleware, the Vite resolver) assert their target
 * before touching it: if someone reshapes one of those files, this script dies
 * loudly instead of quietly emitting a broken project.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

// ---------------------------------------------------------------- helpers

function fail(string $message): never
{
    fwrite(STDERR, "\n  ERRO: {$message}\n\n");
    exit(1);
}

function step(string $message): void
{
    echo "  → {$message}\n";
}

function rmrf(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }

    if (! is_dir($path)) {
        return;
    }

    foreach (scandir($path) as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            rmrf($path.'/'.$entry);
        }
    }

    rmdir($path);
}

/** Moves every file under $from into $to, keeping the relative structure. */
function moveInto(string $from, string $to): int
{
    if (! is_dir($from)) {
        return 0;
    }

    $moved = 0;

    foreach (scandir($from) as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') {
            continue;
        }

        $source = $from.'/'.$entry;
        $target = $to.'/'.$entry;

        if (is_dir($source)) {
            is_dir($target) || mkdir($target, 0755, true);
            $moved += moveInto($source, $target);

            continue;
        }

        if (file_exists($target)) {
            fail("colisão ao mover: {$target} já existe (origem: {$source})");
        }

        is_dir(dirname($target)) || mkdir(dirname($target), 0755, true);
        rename($source, $target);
        $moved++;
    }

    return $moved;
}

/** @return array<int, string> */
function filesIn(array $dirs, array $extensions): array
{
    $found = [];

    foreach ($dirs as $dir) {
        if (! is_dir($dir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), $extensions, true)) {
                $found[] = $file->getPathname();
            }
        }
    }

    return $found;
}

function replaceOrFail(string $file, string $search, string $replace): void
{
    if (! is_file($file)) {
        fail("esperava editar {$file}, mas o arquivo não existe");
    }

    $contents = file_get_contents($file);

    if (! str_contains($contents, $search)) {
        fail("não achei em {$file} o trecho esperado:\n---\n{$search}\n---");
    }

    file_put_contents($file, str_replace($search, $replace, $contents));
}

/** Swaps a markdown section: its heading through the next same-level heading. */
function replaceSection(string $file, string $heading, string $replacement): void
{
    if (! is_file($file)) {
        fail("esperava editar {$file}, mas o arquivo não existe");
    }

    $contents = file_get_contents($file);
    $start = strpos($contents, $heading."\n");

    if ($start === false) {
        fail("não achei a seção \"{$heading}\" em {$file}");
    }

    $after = $start + strlen($heading) + 1;
    $next = preg_match('/^## /m', $contents, $matches, PREG_OFFSET_CAPTURE, $after)
        ? $matches[0][1]
        : strlen($contents);

    file_put_contents($file, substr($contents, 0, $start).$replacement."\n\n".substr($contents, $next));
}

function readJson(string $file): array
{
    return json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
}

function writeJson(string $file, array $data): void
{
    file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
    );
}

/** Splits a routes file into its `use` imports and its body. */
function splitRoutesFile(string $file): array
{
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    $uses = [];
    $body = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // declare() is only legal as a file's first statement, so it cannot
        // survive being concatenated into the middle of the merged file.
        if (str_starts_with($trimmed, 'declare(')) {
            continue;
        }

        if ($trimmed === '<?php' || $trimmed === '') {
            if ($trimmed === '' && $body !== []) {
                $body[] = $line;
            }

            continue;
        }

        if (str_starts_with($trimmed, 'use ') && str_ends_with($trimmed, ';')) {
            $uses[] = $trimmed;

            continue;
        }

        $body[] = $line;
    }

    return [$uses, rtrim(implode("\n", $body))];
}

// ---------------------------------------------------------------- preflight

if (! is_dir('Modules')) {
    fail('não existe Modules/ aqui — este projeto já é plano.');
}

$modules = [];
foreach (scandir('Modules') as $entry) {
    if ($entry !== '.' && $entry !== '..' && is_dir("Modules/{$entry}")) {
        $modules[] = $entry;
    }
}
sort($modules);

echo "\nAchatando ".count($modules).' módulos: '.implode(', ', $modules)."\n\n";

// ------------------------------------------------- providers (before moves)

// The 3 providers per module are nwidart scaffolding with exactly three pieces
// of real behaviour between them. Extract those, then drop every provider file
// so the bulk move below can't collide on the 5 identically-named ones.

step('extraindo a fiação do Pennant para app/Providers/FeatureServiceProvider.php');

$pennant = 'Modules/FeatureFlags/app/Providers/FeatureFlagsServiceProvider.php';

replaceOrFail($pennant, 'use Nwidart\Modules\Support\ModuleServiceProvider;', 'use Illuminate\Support\ServiceProvider;');
replaceOrFail($pennant, 'class FeatureFlagsServiceProvider extends ModuleServiceProvider', 'class FeatureServiceProvider extends ServiceProvider');
replaceOrFail($pennant, <<<'PHP'
    /**
     * The name of the module.
     */
    protected string $name = 'FeatureFlags';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'featureflags';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // The base ModuleServiceProvider merges config/config.php under the
        // 'featureflags' key; also merge it under 'features' so every
        // pre-existing config('features.xxx') call site keeps working.
        $this->mergeConfigFrom(module_path($this->name, 'config/config.php'), 'features');

        $definitions
PHP, <<<'PHP'
    public function boot(): void
    {
        $definitions
PHP);

is_dir('app/Providers') || mkdir('app/Providers', 0755, true);
rename($pennant, 'app/Providers/FeatureServiceProvider.php');

step('registrando o FeatureServiceProvider em bootstrap/providers.php');

replaceOrFail(
    'bootstrap/providers.php',
    'use App\Providers\EventServiceProvider;',
    "use App\Providers\EventServiceProvider;\nuse App\Providers\FeatureServiceProvider;",
);

replaceOrFail(
    'bootstrap/providers.php',
    '    EventServiceProvider::class,',
    "    EventServiceProvider::class,\n    FeatureServiceProvider::class,",
);

step('mesclando o listener do Presence no EventServiceProvider da raiz');

replaceOrFail('app/Providers/EventServiceProvider.php', <<<'PHP'
use Illuminate\Auth\Events\Registered;
PHP, <<<'PHP'
use App\Events\UserStatusUpdatedEvent;
use App\Listeners\CacheUserStatusListener;
use Illuminate\Auth\Events\Registered;
PHP);

replaceOrFail('app/Providers/EventServiceProvider.php', <<<'PHP'
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];
PHP, <<<'PHP'
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        UserStatusUpdatedEvent::class => [
            CacheUserStatusListener::class,
        ],
    ];
PHP);

foreach ($modules as $module) {
    rmrf("Modules/{$module}/app/Providers");
}

// ---------------------------------------------------------------- routes

// Each module's web.php is already a self-contained Route::group, so the bodies
// concatenate cleanly. Identity's provider wraps its file in the `web` group;
// inside root routes/web.php Laravel already does that, so the wrapper is
// dropped rather than replicated.

step('mesclando as rotas em routes/web.php');

$routeUses = [];
$routeBodies = [];

foreach ($modules as $module) {
    $file = "Modules/{$module}/routes/web.php";

    if (! is_file($file) || trim(file_get_contents($file)) === '<?php') {
        continue;
    }

    [$uses, $body] = splitRoutesFile($file);
    $routeUses = array_merge($routeUses, $uses);
    $routeBodies[] = "// ---- {$module}\n\n{$body}";
}

if ($routeBodies === []) {
    fail('nenhuma rota de módulo encontrada — a estrutura mudou?');
}

[$rootUses, $rootBody] = splitRoutesFile('routes/web.php');
$allUses = array_values(array_unique(array_merge($rootUses, $routeUses)));
sort($allUses);

file_put_contents('routes/web.php', "<?php\n\n".implode("\n", $allUses)."\n\n".$rootBody."\n\n".implode("\n\n", $routeBodies)."\n");

// Identity's auth.php keeps its own file, which is where Breeze puts it anyway
// and what the `require __DIR__.'/auth.php'` line inside the merged body expects.
if (is_file('Modules/Identity/routes/auth.php')) {
    rename('Modules/Identity/routes/auth.php', 'routes/auth.php');
}

// The public API file hand-picks its middleware and writes its own api/v1
// prefix. Routing it through withRouting(api:) would double the prefix to
// /api/api/v1 and bolt the `api` middleware group on top, so it stays loaded
// explicitly — the flat equivalent of the module's loadRoutesFrom().
step('movendo a API pública para routes/api.php, carregada explicitamente');

if (is_file('Modules/FeatureFlags/routes/api.php')) {
    rename('Modules/FeatureFlags/routes/api.php', 'routes/api.php');

    replaceOrFail('bootstrap/app.php', <<<'PHP'
        health: '/up',
    )
PHP, <<<'PHP'
        health: '/up',
        then: function (): void {
            // Not wired through withRouting(api:) on purpose: this file writes
            // its own api/v1 prefix and picks its own middleware, so the
            // framework's api group would double the prefix and the stack.
            require base_path('routes/api.php');
        },
    )
PHP);
}

// ---------------------------------------------------------------- bulk moves

step('movendo o código dos módulos para app/');

$phpMoved = 0;
foreach ($modules as $module) {
    $phpMoved += moveInto("Modules/{$module}/app", 'app');
}

step("  {$phpMoved} arquivos PHP");

step('movendo migrations, factories e seeders');

foreach ($modules as $module) {
    moveInto("Modules/{$module}/database/migrations", 'database/migrations');
    moveInto("Modules/{$module}/database/factories", 'database/factories');

    // The per-module *DatabaseSeeder stubs are nwidart scaffolding nothing calls.
    foreach (glob("Modules/{$module}/database/seeders/*.php") ?: [] as $seeder) {
        if (str_ends_with($seeder, "{$module}DatabaseSeeder.php")) {
            unlink($seeder);
        }
    }

    moveInto("Modules/{$module}/database/seeders", 'database/seeders');
}

step('movendo o frontend para resources/');

foreach ($modules as $module) {
    moveInto("Modules/{$module}/resources/assets/js", 'resources/js');
    moveInto("Modules/{$module}/resources/views", 'resources/views');
}

step('movendo os testes para tests/');

foreach ($modules as $module) {
    moveInto("Modules/{$module}/tests", 'tests');
}

step('movendo a config do FeatureFlags para config/features.php');

if (is_file('Modules/FeatureFlags/config/config.php')) {
    if (file_exists('config/features.php')) {
        fail('config/features.php já existe');
    }
    rename('Modules/FeatureFlags/config/config.php', 'config/features.php');
}

step('mesclando as traduções');

foreach (['en', 'pt', 'es'] as $locale) {
    $target = "lang/{$locale}.json";
    $merged = is_file($target) ? readJson($target) : [];

    foreach ($modules as $module) {
        $file = "Modules/{$module}/lang/{$locale}.json";

        if (! is_file($file)) {
            continue;
        }

        foreach (readJson($file) as $key => $value) {
            if (array_key_exists($key, $merged) && $merged[$key] !== $value) {
                fail("chave de tradução conflitante em {$locale}: \"{$key}\"");
            }

            $merged[$key] = $value;
        }
    }

    writeJson($target, $merged);
}

// ------------------------------------------------------- namespace rewrite

step('reescrevendo namespaces Modules\\X\\ → App\\');

// The lookbehind keeps this off Nwidart\Modules\..., where "Modules\" sits in
// the middle of somebody else's namespace rather than at the start of ours.
$head = '/(?<![A-Za-z0-9_\\\\])Modules\\\\([A-Za-z0-9_]+)\\\\';

// \b rather than a trailing \\ so these also catch the `namespace ...;` form,
// where the sub-namespace ends the statement instead of continuing.
$patterns = [
    $head.'Database\\\\Factories\\b/' => 'Database\\Factories',
    $head.'Database\\\\Seeders\\b/' => 'Database\\Seeders',
    $head.'Tests\\b/' => 'Tests',
    $head.'/' => 'App\\',
    '/namespace Modules\\\\([A-Za-z0-9_]+);/' => 'namespace App;',
];

$sources = filesIn(
    ['app', 'bootstrap', 'config', 'database', 'routes', 'tests', 'resources'],
    ['php', 'vue', 'js', 'ts'],
);

$rewritten = 0;
foreach ($sources as $file) {
    $original = file_get_contents($file);
    $updated = preg_replace(array_keys($patterns), array_values($patterns), $original);

    // Module Vue/TS imports resolved through the @modules Vite alias; now that
    // everything shares one tree they are plain @/ imports.
    $updated = preg_replace('#@modules/[A-Za-z0-9_]+/resources/assets/js/#', '@/', $updated);

    // Catch up call sites with the provider renamed above.
    $updated = str_replace('FeatureFlagsServiceProvider', 'FeatureServiceProvider', $updated);

    if ($updated !== $original) {
        file_put_contents($file, $updated);
        $rewritten++;
    }
}

step("  {$rewritten} arquivos tocados");

// ---------------------------------------------------------------- unwiring

step('desligando o nwidart do resto do projeto');

// The User factory override only existed because Laravel's resolver can't find
// a factory for a model outside App\Models.
replaceOrFail('app/Models/User.php', <<<'PHP'

    /**
     * Laravel's default factory resolver only strips an "App\Models\" prefix,
     * so it can't find a factory for a model living under Modules\Identity —
     * point it at the real one explicitly.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
PHP, '');

// Same story for the factory's explicit $model: the convention guesser works
// again once the factory and the model are both under the app's root namespace.
replaceOrFail('database/factories/UserFactory.php', <<<'PHP'
    /**
     * Factory::modelName()'s naming-convention guesser strips a literal
     * "Database\Factories\" substring and prepends the app's root
     * namespace — both assumptions break for a factory living under
     * Modules\Identity, so the model is named explicitly instead.
     */
    protected $model = User::class;


PHP, '');

// With one lang file there is no module list left to walk.
replaceOrFail('app/Http/Middleware/ShareTranslationsMiddleware.php', "use Nwidart\Modules\Facades\Module;\n", '');
replaceOrFail('app/Http/Middleware/ShareTranslationsMiddleware.php', <<<'PHP'
    /**
     * Every module owns its own lang/{locale}.json (see AGENTS.md's Modules
     * section), and Laravel's own translator merges those transparently for
     * __()/trans() — but this middleware hands the frontend a raw JSON blob
     * directly, bypassing the translator entirely, so it has to replicate
     * that merge itself: root lang/ first, each enabled module's lang/ after.
     *
     * @return array<string, string>
     */
    private function mergedTranslations(string $locale): array
    {
        $translations = [];

        $rootFile = lang_path("{$locale}.json");
        if (file_exists($rootFile)) {
            $translations = json_decode(file_get_contents($rootFile), true) ?? [];
        }

        foreach (Module::allEnabled() as $module) {
            $moduleFile = $module->getPath().'/lang/'.$locale.'.json';
            if (file_exists($moduleFile)) {
                $translations = array_merge(
                    $translations,
                    json_decode(file_get_contents($moduleFile), true) ?? [],
                );
            }
        }

        return $translations;
    }
PHP, <<<'PHP'
    /**
     * This middleware hands the frontend a raw JSON blob rather than going
     * through the translator, so it reads the locale file itself.
     *
     * @return array<string, string>
     */
    private function mergedTranslations(string $locale): array
    {
        $file = lang_path("{$locale}.json");

        if (! file_exists($file)) {
            return [];
        }

        return json_decode(file_get_contents($file), true) ?? [];
    }
PHP);

// One page tree again, so Inertia's own resolver does the lookup directly.
foreach (['resources/js/app.js', 'resources/js/ssr.js'] as $entry) {
    $indent = $entry === 'resources/js/ssr.js' ? '        ' : '    ';

    replaceOrFail($entry, <<<JS
{$indent}resolve: (name) => {
{$indent}    const pages = {
{$indent}        ...import.meta.glob('./Pages/**/*.vue'),
{$indent}        ...import.meta.glob('/Modules/*/resources/assets/js/Pages/**/*.vue'),
{$indent}    };
JS, <<<JS
{$indent}resolve: (name) =>
{$indent}    resolvePageComponent(
{$indent}        `./Pages/\${name}.vue`,
{$indent}        import.meta.glob('./Pages/**/*.vue'),
{$indent}    ),
JS);

    // Drop whatever is left of the old body: the suffix-match workaround.
    $contents = file_get_contents($entry);
    $contents = preg_replace(
        '/\n[ ]*\/\/ Module pages glob.*?\n[ ]*\},\n/s',
        "\n",
        $contents,
        1,
    ) ?? $contents;
    $contents = preg_replace(
        '/\n[ ]*const path = Object\.keys\(pages\).*?\n[ ]*\},\n/s',
        "\n",
        $contents,
        1,
    ) ?? $contents;
    file_put_contents($entry, $contents);
}

replaceOrFail('vite.config.js', "\n            '@modules': path.resolve(__dirname, './Modules'),", '');

replaceOrFail('phpunit.xml', "\n            <directory>Modules/*/tests/Unit</directory>", '');
replaceOrFail('phpunit.xml', "\n            <directory>Modules/*/tests/Feature</directory>", '');
replaceOrFail('phpunit.xml', "\n            <directory>Modules/*/app</directory>", '');

$composer = readJson('composer.json');
unset($composer['extra']['merge-plugin'], $composer['config']['allow-plugins']['wikimedia/composer-merge-plugin']);
if ($composer['extra'] === []) {
    unset($composer['extra']);
}
writeJson('composer.json', $composer);

// ---------------------------------------------------------------- deletions

step('apagando o que era só encanamento de módulo');

foreach ([
    'Modules',
    'modules_statuses.json',
    'config/modules.php',
    'stubs/nwidart-stubs',
    'vite-module-loader.js',
    'lang/_unused.en.json',
    'lang/_unused.pt.json',
    'lang/_unused.es.json',
    'MODULES.md',
    'bootstrap/cache/modules.php',
    'bootstrap/cache/packages.php',
    'bootstrap/cache/services.php',
] as $path) {
    rmrf($path);
}

if (is_dir('stubs') && scandir('stubs') === ['.', '..']) {
    rmdir('stubs');
}

// ---------------------------------------------------------------- docs

step('trocando a documentação pela versão plana');

$docSwaps = [
    ['README.md', '## Arquitetura', 'readme-architecture.md'],
    ['AGENTS.md', '## Modules', 'agents-structure.md'],
];

foreach ($docSwaps as [$doc, $heading, $stub]) {
    $stubPath = __DIR__.'/mvc-docs/'.$stub;

    if (! is_file($stubPath)) {
        fail("faltou o stub de documentação {$stubPath}");
    }

    replaceSection($doc, $heading, rtrim(file_get_contents($stubPath)));
}

// Rewriting Modules\X\ to App\ leaves imports out of alphabetical order and
// strands a few now-unused ones, so the tree needs a formatting pass. Running
// it over everything is safe here precisely because the modular tree it came
// from was Pint-clean.
if (is_file('vendor/bin/pint')) {
    step('formatando com o Pint');
    exec('vendor/bin/pint --quiet 2>&1', $pintOutput, $pintStatus);

    if ($pintStatus !== 0) {
        fail("o Pint falhou:\n".implode("\n", $pintOutput));
    }
} else {
    step('vendor/bin/pint ausente — rode o Pint depois do composer install');
}

// This script and its test are tooling for producing the flat flavour, not
// part of what the flavour is. PHP has already read the file, so removing it
// mid-run is safe.
step('removendo o próprio ferramental do projeto gerado');
rmrf('tests/Feature/ToMvcTest.php');
rmrf(__DIR__);

echo "\nPronto. Agora rode:\n";
echo "  composer remove nwidart/laravel-modules\n";
echo "  composer dump-autoload\n\n";
