<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards `scripts/to-mvc.php`, which produces the `--mvc` flavour of this
 * boilerplate. It runs the real script against a throwaway copy of the tree,
 * so adding a module that the flattener cannot handle fails here — in the same
 * change that adds it — rather than weeks later in somebody's new project.
 */
class ToMvcTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDir = sys_get_temp_dir().'/to-mvc-'.uniqid();
        $this->copyProjectInto($this->workDir);

        exec('cd '.escapeshellarg($this->workDir).' && php scripts/to-mvc.php 2>&1', $output, $status);

        $this->assertSame(0, $status, "o achatador falhou:\n".implode("\n", $output));
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->workDir);

        parent::tearDown();
    }

    #[Test]
    public function it_leaves_no_trace_of_the_module_layer(): void
    {
        foreach (['Modules', 'modules_statuses.json', 'config/modules.php', 'MODULES.md'] as $path) {
            $this->assertFileDoesNotExist("{$this->workDir}/{$path}");
        }

        foreach ($this->sourceFiles() as $file) {
            $contents = file_get_contents($file);
            $relative = str_replace($this->workDir.'/', '', $file);

            foreach (['Modules\\', '@modules/', 'module_path(', 'Nwidart'] as $needle) {
                $this->assertStringNotContainsString($needle, $contents, "{$relative} ainda referencia {$needle}");
            }
        }
    }

    #[Test]
    public function it_removes_its_own_tooling_from_the_generated_project(): void
    {
        $this->assertDirectoryDoesNotExist("{$this->workDir}/scripts");
        $this->assertFileDoesNotExist("{$this->workDir}/tests/Feature/ToMvcTest.php");
    }

    #[Test]
    public function every_module_contributes_its_code_to_the_flat_tree(): void
    {
        // One representative per module, so a module that silently fails to
        // move is caught rather than averaged away by a file count.
        $expected = [
            'app/Providers/FeatureServiceProvider.php',   // FeatureFlags
            'app/Models/User.php',                        // Identity
            'app/Http/Controllers/RoleController.php',    // Permissions
            'app/Services/UserStatusService.php',         // Presence
            'app/Services/ProfilePictureService.php',     // Profile
            'routes/auth.php',
            'routes/api.php',
            'config/features.php',
            'resources/js/Pages/Auth/Login.vue',
            'resources/js/components/NotificationCenter.vue',
        ];

        foreach ($expected as $path) {
            $this->assertFileExists("{$this->workDir}/{$path}");
        }
    }

    #[Test]
    public function inertia_page_subfolders_survive_the_move(): void
    {
        // Controllers render by path string ('Users/Index'), and three modules
        // ship an Index.vue — dropping the subfolder would both collide and
        // break every render call.
        foreach (['Features/Index', 'Features/Show', 'Permissions/Index', 'Roles/Form', 'Users/Index', 'Users/Show'] as $page) {
            $this->assertFileExists("{$this->workDir}/resources/js/Pages/{$page}.vue");
        }
    }

    #[Test]
    public function the_public_api_keeps_its_own_prefix_and_middleware(): void
    {
        // Routing this file through withRouting(api:) would make it /api/api/v1
        // and add the framework's api middleware group on top of its own.
        $bootstrap = file_get_contents("{$this->workDir}/bootstrap/app.php");

        $this->assertStringNotContainsString('api: ', $bootstrap);
        $this->assertStringContainsString("require base_path('routes/api.php')", $bootstrap);
        $this->assertStringContainsString("prefix('api/v1')", file_get_contents("{$this->workDir}/routes/api.php"));
    }

    #[Test]
    public function translations_from_every_module_land_in_the_root_files(): void
    {
        $modular = count(json_decode(file_get_contents(base_path('lang/en.json')), true));

        foreach (['en', 'pt', 'es'] as $locale) {
            $flat = json_decode(file_get_contents("{$this->workDir}/lang/{$locale}.json"), true);

            $this->assertGreaterThan($modular, count($flat), "lang/{$locale}.json não ganhou as chaves dos módulos");
            $this->assertArrayHasKey('Online', $flat);
        }
    }

    /** @return array<int, string> */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'tests', 'resources'] as $dir) {
            if (! is_dir("{$this->workDir}/{$dir}")) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator("{$this->workDir}/{$dir}", \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'vue', 'js', 'ts'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function copyProjectInto(string $destination): void
    {
        mkdir($destination, 0755, true);

        // vendor/ and node_modules are the bulk of the tree and the flattener
        // never reads them, so skipping them keeps this test fast. Without
        // vendor/ the script also skips its Pint pass, which is fine here.
        $skip = ['vendor', 'node_modules', '.git', 'public/build', 'bootstrap/ssr', 'bootstrap/cache'];

        foreach ($this->projectEntries() as $entry) {
            if (in_array($entry, $skip, true)) {
                continue;
            }

            $this->copyTree(base_path($entry), "{$destination}/{$entry}");
        }
    }

    /** @return array<int, string> */
    private function projectEntries(): array
    {
        return array_values(array_diff(scandir(base_path()), ['.', '..', 'vendor', 'node_modules', '.git']));
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
