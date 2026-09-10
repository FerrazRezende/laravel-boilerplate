<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Nwidart\Modules\Facades\Module;
use Symfony\Component\HttpFoundation\Response;

class ShareTranslationsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = App::getLocale();

        Inertia::share('translations', $this->mergedTranslations($locale));
        Inertia::share('locale', $locale);

        return $next($request);
    }

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
}
