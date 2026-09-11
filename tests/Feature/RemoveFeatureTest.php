<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Optional modules ship enabled here and the installer strips the ones you did
 * not ask for. Each carries an `uninstall.php` that undoes its wiring into
 * shared files; this runs the real removal against a copy of the tree, so
 * wiring a module into a new shared file without updating its uninstall.php
 * fails here rather than in somebody's generated project.
 */
class RemoveFeatureTest extends TestCase
{
    /**
     * Modules the installer may be asked to remove. A generated project keeps
     * only the ones it asked for, so anything already gone is skipped rather
     * than failed — this guards what is present, not what once was.
     */
    private const OPTIONAL = ['Ai', 'Observability'];

    /** @return array<int, string> */
    private function present(): array
    {
        $present = array_values(array_filter(
            self::OPTIONAL,
            fn (string $module) => is_dir(base_path("Modules/{$module}")),
        ));

        if ($present === []) {
            $this->markTestSkipped('nenhum módulo opcional presente neste projeto');
        }

        return $present;
    }

    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDir = sys_get_temp_dir().'/remove-feature-'.uniqid();
        $this->copyProjectInto($this->workDir);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->workDir);

        parent::tearDown();
    }

    public function test_every_optional_module_declares_how_to_remove_itself(): void
    {
        foreach ($this->present() as $module) {
            $this->assertFileExists(base_path("Modules/{$module}/uninstall.php"));
        }
    }

    public function test_removing_a_module_leaves_no_reference_behind(): void
    {
        foreach ($this->present() as $module) {
            exec(
                'cd '.escapeshellarg($this->workDir)." && php scripts/remove-feature.php {$module} 2>&1",
                $output,
                $status,
            );

            $this->assertSame(0, $status, "remover {$module} falhou:\n".implode("\n", $output));
            $this->assertDirectoryDoesNotExist("{$this->workDir}/Modules/{$module}");

            $statuses = json_decode(file_get_contents("{$this->workDir}/modules_statuses.json"), true);
            $this->assertArrayNotHasKey($module, $statuses);

            foreach ($this->sourceFiles() as $file) {
                $this->assertStringNotContainsString(
                    "Modules\\{$module}",
                    file_get_contents($file),
                    str_replace($this->workDir.'/', '', $file)." ainda referencia {$module}",
                );
            }
        }
    }

    public function test_the_feature_flag_goes_with_it(): void
    {
        if (! in_array('Observability', $this->present(), true)) {
            $this->markTestSkipped('Observability não está neste projeto');
        }

        exec('cd '.escapeshellarg($this->workDir).' && php scripts/remove-feature.php Observability 2>&1', $o, $status);
        $this->assertSame(0, $status);

        // A flag left defined with no route gating it breaks FeatureFlagWiringTest.
        $this->assertStringNotContainsString(
            "'observability'",
            file_get_contents("{$this->workDir}/Modules/FeatureFlags/config/config.php"),
        );
        $this->assertStringNotContainsString(
            'system.jobs.index',
            file_get_contents("{$this->workDir}/resources/js/lib/navigation.ts"),
        );
        $this->assertStringNotContainsString(
            "Broadcast::channel('jobs-admin'",
            file_get_contents("{$this->workDir}/routes/channels.php"),
        );
    }

    /** @return array<int, string> */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'resources', 'Modules'] as $dir) {
            if (! is_dir("{$this->workDir}/{$dir}")) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator("{$this->workDir}/{$dir}", \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'vue', 'ts', 'js'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function copyProjectInto(string $destination): void
    {
        mkdir($destination, 0755, true);

        $skip = ['vendor', 'node_modules', '.git', 'public', 'bootstrap/ssr', 'bootstrap/cache', 'storage'];

        foreach (array_diff(scandir(base_path()), ['.', '..']) as $entry) {
            if (in_array($entry, $skip, true)) {
                continue;
            }

            $this->copyTree(base_path($entry), "{$destination}/{$entry}");
        }
    }

    private function copyTree(string $from, string $to): void
    {
        if (is_file($from)) {
            copy($from, $to);

            return;
        }

        if (! is_dir($from)) {
            return;
        }

        is_dir($to) || mkdir($to, 0755, true);

        foreach (array_diff(scandir($from), ['.', '..']) as $entry) {
            $this->copyTree("{$from}/{$entry}", "{$to}/{$entry}");
        }
    }

    private function deleteTree(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
            $this->deleteTree("{$path}/{$entry}");
        }

        rmdir($path);
    }
}
