<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Untranslated text reaches production quietly: `__()` falls back to the key,
 * so a missing entry looks like English copy rather than a bug, and a string
 * never wrapped in `__()` looks like nothing at all. Both shipped here — half
 * the profile screen was hardcoded English sitting next to translated markup
 * in the very same file.
 *
 * These walk the real source, so the next one fails here instead of on screen.
 */
class TranslationCoverageTest extends TestCase
{
    /** Proper nouns and product names that are the same in every language. */
    private const NOT_TRANSLATABLE = [
        'Admin', 'Dashboard', 'Boilerplate', 'GitHub', 'Horizon', 'Laravel',
        'KSUID', 'RBAC', 'API', 'Redis', 'Postgres', 'Reverb', 'RustFS', 'Pennant',
    ];

    public function test_every_translation_key_in_use_exists_in_all_three_locales(): void
    {
        $missing = [];

        foreach ($this->sourceFiles(['php', 'vue', 'ts', 'js']) as $file) {
            foreach ($this->keysUsedIn($file) as $key) {
                foreach (['en', 'pt', 'es'] as $locale) {
                    if (! array_key_exists($key, $this->translations($locale))) {
                        $missing[] = sprintf('%s [%s]: %s', $this->relative($file), $locale, $key);
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), "chaves sem tradução:\n".implode("\n", array_unique($missing)));
    }

    public function test_the_three_locales_carry_the_same_keys(): void
    {
        foreach ($this->langDirectories() as $dir) {
            $en = array_keys($this->readJson("{$dir}/en.json"));

            foreach (['pt', 'es'] as $locale) {
                $this->assertSame(
                    [],
                    array_values(array_diff($en, array_keys($this->readJson("{$dir}/{$locale}.json")))),
                    $this->relative($dir)."/{$locale}.json não tem todas as chaves do en.json",
                );
            }
        }
    }

    public function test_no_user_facing_text_is_hardcoded_in_a_template(): void
    {
        $hardcoded = [];

        foreach ($this->sourceFiles(['vue']) as $file) {
            $contents = file_get_contents($file);
            $template = explode('<template>', $contents, 2)[1] ?? '';

            preg_match_all('/>([^<>]+)</', $template, $matches);

            foreach ($matches[1] as $text) {
                $text = trim(preg_replace('/\s+/', ' ', $text));

                // Interpolation and translated calls are fine; so is anything
                // that is not a sentence a reader would expect in their language.
                if ($text === '' || str_contains($text, '{{') || str_contains($text, '__(')) {
                    continue;
                }

                if (! preg_match('/^[A-Z]/', $text) || substr_count($text, ' ') < 1) {
                    continue;
                }

                if (in_array(explode(' ', $text)[0], self::NOT_TRANSLATABLE, true)) {
                    continue;
                }

                $hardcoded[] = $this->relative($file).': '.$text;
            }
        }

        $this->assertSame([], $hardcoded, "texto cru em template:\n".implode("\n", $hardcoded));
    }

    /** @return array<int, string> */
    private function keysUsedIn(string $file): array
    {
        preg_match_all(
            '/__\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/',
            file_get_contents($file),
            $matches,
        );

        $keys = array_filter(array_merge($matches[1], $matches[2]));

        // A key built by concatenation (`__('user_status.' + status)`) cannot be
        // checked statically; the pieces it resolves to are asserted elsewhere.
        return array_values(array_filter(
            array_map(fn (string $key) => str_replace("\\'", "'", $key), $keys),
            fn (string $key) => ! str_ends_with($key, '.'),
        ));
    }

    /** @return array<string, string> */
    private function translations(string $locale): array
    {
        static $cache = [];

        if (! isset($cache[$locale])) {
            $merged = [];

            foreach ($this->langDirectories() as $dir) {
                $merged += $this->readJson("{$dir}/{$locale}.json");
            }

            $cache[$locale] = $merged;
        }

        return $cache[$locale];
    }

    /** @return array<int, string> */
    private function langDirectories(): array
    {
        return array_merge([base_path('lang')], glob(base_path('Modules/*/lang')) ?: []);
    }

    /** @return array<int, string> */
    private function sourceFiles(array $extensions): array
    {
        $roots = array_merge(
            [base_path('app'), base_path('resources'), base_path('routes'), base_path('database')],
            glob(base_path('Modules/*/app')) ?: [],
            glob(base_path('Modules/*/resources')) ?: [],
        );

        $files = [];

        foreach (array_filter($roots, 'is_dir') as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), $extensions, true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function readJson(string $file): array
    {
        return is_file($file) ? json_decode(file_get_contents($file), true) : [];
    }

    private function relative(string $path): string
    {
        return str_replace(base_path().'/', '', $path);
    }
}
