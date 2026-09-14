<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every key in .env.example has to be read by something.
 *
 * A key nobody reads is worse than a missing one: it looks configured. This
 * project shipped REVERB_BACKEND_HOST for months with nothing reading it, and
 * the cost was every server-side broadcast silently going nowhere. Same shape
 * as MAIL_ENCRYPTION, which Laravel 11 replaced with MAIL_SCHEME.
 *
 * `boilerplate new` copies .env.example straight to .env, so this file is the
 * whole configuration surface a generated project starts with.
 */
class EnvExampleCoverageTest extends TestCase
{
    public function test_every_key_in_env_example_is_read_somewhere(): void
    {
        $haystack = $this->haystack();

        $dead = array_values(array_filter(
            $this->declaredKeys(),
            fn (string $key) => ! str_contains($haystack, $key),
        ));

        $this->assertSame([], $dead, sprintf(
            "Chaves no .env.example que ninguém lê: %s.\nOu ligue a chave no config, ou tire ela do arquivo.",
            implode(', ', $dead),
        ));
    }

    /**
     * @return array<int, string>
     */
    private function declaredKeys(): array
    {
        preg_match_all(
            '/^([A-Z][A-Z0-9_]*)=/m',
            (string) file_get_contents(base_path('.env.example')),
            $matches,
        );

        return array_values(array_unique($matches[1]));
    }

    /**
     * Everything that can legitimately read an env key: the app's own config
     * and code, the Compose stack, the frontend, the published package configs
     * that Laravel merges, and .env.example itself — a key can be there only
     * to be interpolated into another, the way APP_NAME feeds VITE_APP_NAME.
     */
    private function haystack(): string
    {
        $paths = array_merge(
            $this->phpFilesIn(base_path('config')),
            $this->phpFilesIn(base_path('app')),
            $this->phpFilesIn(base_path('bootstrap')),
            $this->phpFilesIn(base_path('routes')),
            $this->phpFilesIn(base_path('Modules')),
            glob(base_path('vendor/*/*/config/*.php')) ?: [],
            glob(base_path('resources/js/**/*.ts')) ?: [],
            glob(base_path('resources/js/*.js')) ?: [],
            [
                base_path('docker-compose.yml'),
                base_path('Dockerfile'),
                base_path('vite.config.js'),
            ],
        );

        $haystack = '';

        foreach ($paths as $path) {
            if (is_file($path)) {
                $haystack .= file_get_contents($path);
            }
        }

        // From .env.example itself, only the interpolations count: reading the
        // whole file back would make every key satisfy itself by being
        // declared, and the test would pass on anything.
        preg_match_all(
            '/\$\{?([A-Z][A-Z0-9_]*)\}?/',
            (string) file_get_contents(base_path('.env.example')),
            $interpolated,
        );

        return $haystack.' '.implode(' ', $interpolated[1]);
    }

    /**
     * @return array<int, string>
     */
    private function phpFilesIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
