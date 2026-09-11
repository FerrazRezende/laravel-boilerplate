<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * MODULES.md, AI.md and QUEUES.md exist for this repository's GitHub page, not
 * for the projects generated from it, so the installer strips them on every
 * install. The script asserts the README wording before cutting the links —
 * this runs it for real, so a reworded README fails here rather than leaving a
 * dead link in every new project.
 */
class StripRepoDocsTest extends TestCase
{
    private const REPO_ONLY = ['MODULES.md', 'AI.md', 'QUEUES.md'];

    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();

        // A generated project has already been stripped, so there is nothing
        // here to guard — this only has meaning in the boilerplate repo.
        if (! is_file(base_path('MODULES.md'))) {
            $this->markTestSkipped('este projeto já foi gerado a partir do boilerplate');
        }

        $this->workDir = sys_get_temp_dir().'/strip-docs-'.uniqid();
        mkdir($this->workDir.'/scripts', 0755, true);

        foreach ([...self::REPO_ONLY, 'README.md', 'AGENTS.md'] as $doc) {
            if (is_file(base_path($doc))) {
                copy(base_path($doc), "{$this->workDir}/{$doc}");
            }
        }

        copy(base_path('scripts/strip-repo-docs.php'), "{$this->workDir}/scripts/strip-repo-docs.php");

        exec('cd '.escapeshellarg($this->workDir).' && php scripts/strip-repo-docs.php 2>&1', $output, $status);

        $this->assertSame(0, $status, "o strip falhou:\n".implode("\n", $output));
    }

    protected function tearDown(): void
    {
        if (! isset($this->workDir) || ! is_dir($this->workDir)) {
            parent::tearDown();

            return;
        }

        foreach (array_diff(scandir($this->workDir.'/scripts'), ['.', '..']) as $file) {
            unlink("{$this->workDir}/scripts/{$file}");
        }
        rmdir($this->workDir.'/scripts');

        foreach (array_diff(scandir($this->workDir), ['.', '..']) as $file) {
            unlink("{$this->workDir}/{$file}");
        }
        rmdir($this->workDir);

        parent::tearDown();
    }

    public function test_the_showcase_documentation_does_not_ship(): void
    {
        foreach (self::REPO_ONLY as $doc) {
            $this->assertFileDoesNotExist("{$this->workDir}/{$doc}");
        }
    }

    public function test_the_conventions_do_ship(): void
    {
        // AGENTS.md is what anyone — or anything — writing code in the
        // generated project reads first. It stays.
        $this->assertFileExists("{$this->workDir}/AGENTS.md");
        $this->assertFileExists("{$this->workDir}/README.md");
    }

    public function test_the_readme_is_left_without_dead_links(): void
    {
        $readme = file_get_contents("{$this->workDir}/README.md");

        foreach (self::REPO_ONLY as $doc) {
            $this->assertStringNotContainsString($doc, $readme);
        }

        $this->assertStringContainsString('AGENTS.md', $readme);
    }
}
