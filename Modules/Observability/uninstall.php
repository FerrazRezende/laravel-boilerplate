<?php

/**
 * Undoes everything this module wires into files outside its own directory.
 * Run by scripts/remove-feature.php, which supplies replaceOrFail()/step()
 * and deletes the module directory afterwards.
 *
 * Keep this in step with the module: every shared file it touches belongs here.
 */

declare(strict_types=1);

step('tirando a flag observability');

replaceOrFail('Modules/FeatureFlags/config/config.php', <<<'PHP'
        'observability' => [
            'name' => 'Observability',
            'description' => 'Live job progress and the /system/jobs screen.',
            'implemented_at' => '2026-09-11',
        ],

PHP);

step('tirando o item Jobs da navegação');

replaceOrFail('resources/js/lib/navigation.ts', <<<'TS'
    {
        route: 'system.jobs.index',
        active: 'system.jobs.*',
        label: 'Jobs',
        icon: Activity,
        feature: 'observability',
        adminOnly: true,
    },

TS);

replaceOrFail(
    'resources/js/lib/navigation.ts',
    'import { Activity, Flag,',
    'import { Flag,',
);

step('tirando os canais de progresso');

replaceOrFail('routes/channels.php', <<<'PHP'

// Live job progress (Observability module). Operators watch every job; a user
// only ever gets the jobs they dispatched themselves.
Broadcast::channel('jobs-admin', function ($user) {
    return (bool) $user->is_admin;
});

Broadcast::channel('jobs.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});
PHP);
