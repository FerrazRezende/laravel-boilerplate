<?php

/**
 * Removes the documentation that exists for this repository's own sake.
 *
 *     php scripts/strip-repo-docs.php
 *
 * MODULES.md, AI.md and QUEUES.md explain the boilerplate to someone deciding
 * whether to use it — why it is modular, why job progress cannot live inside
 * Horizon, why the public chat cannot use a feature flag. That is reading for
 * the GitHub page, not baggage for every project generated from it.
 *
 * AGENTS.md deliberately stays: it is the convention rulebook for the code the
 * project just inherited, and it is what agents read before touching it.
 *
 * The installer runs this on every install.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

function fail(string $message): never
{
    fwrite(STDERR, "\n  ERRO: {$message}\n\n");
    exit(1);
}

const REPO_ONLY = ['MODULES.md', 'AI.md', 'QUEUES.md'];

foreach (REPO_ONLY as $doc) {
    if (is_file($doc)) {
        unlink($doc);
        echo "  → removido {$doc}\n";
    }
}

// The README links two of them, and a dead link is worse than a missing page.
// Asserted rather than pattern-matched: if this section is reworded, this
// script should stop and be updated, not quietly leave a broken link behind.
$readme = 'README.md';
$contents = is_file($readme) ? file_get_contents($readme) : fail('não achei o README.md');

$linkBlock = "\n\nDuas partes têm documentação própria: [QUEUES.md](QUEUES.md) para filas,\nHorizon e progresso de jobs, e [AI.md](AI.md) para o assistente.";

if (! str_contains($contents, $linkBlock)) {
    fail('o bloco de links das docs mudou no README.md — atualize este script');
}

$contents = str_replace($linkBlock, '', $contents);

$modulesLink = 'Para o mapa completo, os diagramas e como criar um
módulo novo, veja [MODULES.md](MODULES.md).';

if (! str_contains($contents, $modulesLink)) {
    fail('a seção Arquitetura mudou no README.md — atualize este script');
}

$contents = str_replace($modulesLink, 'As convenções do projeto estão no
[AGENTS.md](AGENTS.md).', $contents);

file_put_contents($readme, $contents);

echo "  → links removidos do README.md\n";
