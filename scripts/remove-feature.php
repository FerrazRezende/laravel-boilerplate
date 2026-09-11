<?php

/**
 * Removes an optional module from this project.
 *
 *     php scripts/remove-feature.php Observability
 *
 * Optional features ship enabled in this repo so they are developed, reviewed
 * and tested like everything else; the installer strips the ones you did not
 * ask for. Each optional module owns an `uninstall.php` next to its
 * `module.json` that undoes its own wiring into shared files — so adding a new
 * optional feature later means writing that file, with nothing central to edit.
 *
 * The helpers below assert before they cut: if a needle has moved, this dies
 * loudly instead of leaving a half-removed project behind.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

function fail(string $message): never
{
    fwrite(STDERR, "\n  ERRO: {$message}\n\n");
    exit(1);
}

function step(string $message): void
{
    echo "  → {$message}\n";
}

function replaceOrFail(string $file, string $search, string $replace = ''): void
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

function rmrf(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }

    if (! is_dir($path)) {
        return;
    }

    foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
        rmrf($path.'/'.$entry);
    }

    rmdir($path);
}

$name = $argv[1] ?? fail('uso: php scripts/remove-feature.php <Modulo>');

if (! is_dir("Modules/{$name}")) {
    fail("não existe Modules/{$name} — já foi removido?");
}

$uninstall = "Modules/{$name}/uninstall.php";

if (! is_file($uninstall)) {
    fail("Modules/{$name} não tem uninstall.php, então não é um módulo opcional");
}

echo "\nRemovendo {$name}\n\n";

require $uninstall;

step('apagando o módulo');
rmrf("Modules/{$name}");

if (is_file('modules_statuses.json')) {
    $statuses = json_decode(file_get_contents('modules_statuses.json'), true, flags: JSON_THROW_ON_ERROR);
    unset($statuses[$name]);
    file_put_contents(
        'modules_statuses.json',
        json_encode($statuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
    );
}

echo "\nPronto. Rode composer dump-autoload.\n\n";
