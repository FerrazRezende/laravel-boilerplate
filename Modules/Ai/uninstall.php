<?php

/**
 * Undoes everything this module wires into files outside its own directory.
 * Run by scripts/remove-feature.php, which supplies replaceOrFail()/step()/rmrf()
 * and deletes the module directory afterwards.
 *
 * The laravel/ai package itself is dropped by the installer, not here — this
 * script only edits files.
 */

declare(strict_types=1);

step('tirando a flag ai');

replaceOrFail('Modules/FeatureFlags/config/config.php', <<<'PHP'
        'ai' => [
            'name' => 'AI assistant',
            'description' => 'Chat backed by the Laravel AI SDK, on the landing page and in the app.',
            'implemented_at' => '2026-09-11',
        ],

PHP);

step('tirando o balão de chat do layout');

replaceOrFail(
    'resources/js/Layouts/AuthenticatedLayout.vue',
    "import ChatBubble from '@modules/Ai/resources/assets/js/components/ChatBubble.vue';\n",
);

replaceOrFail(
    'resources/js/Layouts/AuthenticatedLayout.vue',
    "        <ChatBubble v-if=\"activeFeatures?.includes('ai')\" />\n\n",
);

step('tirando o chat da landing page');

replaceOrFail(
    'resources/js/Pages/Welcome.vue',
    "import ChatPanel from '@modules/Ai/resources/assets/js/components/ChatPanel.vue';\n",
);

replaceOrFail('resources/js/Pages/Welcome.vue', <<<'VUE'
            <!-- Assistant -->
            <section v-if="$page.props.aiEnabled" class="px-4 pb-4 sm:px-6">
                <div class="mx-auto max-w-4xl">
                    <p class="font-mono text-xs uppercase tracking-[0.2em] text-muted-foreground">{{ __('Try it') }}</p>
                    <h2 class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">{{ __('Ask the assistant') }}</h2>

                    <div class="mt-6">
                        <ChatPanel
                            :endpoint="route('ai.public-chat')"
                            :empty-state="__('Ask what is in the box, or how something here is wired up.')"
                        />
                    </div>
                </div>
            </section>


VUE);

step('tirando a prop aiEnabled das props compartilhadas');

replaceOrFail('app/Http/Middleware/HandleInertiaRequests.php', <<<'PHP'

            // Visitor-facing, so it cannot come from activeFeatures: those are
            // resolved per user and a guest has none. (Ai module)
            'aiEnabled' => filled(config('ai.providers.'.config('ai.default').'.key')),
PHP);

step('tirando a config e as chaves de provider do .env');

rmrf('config/ai.php');

foreach (['.env.example', '.env'] as $envFile) {
    if (! is_file($envFile)) {
        continue;
    }

    replaceOrFail($envFile, <<<'ENV'

# =============================================================================
# AI PROVIDER (Laravel AI SDK)
# =============================================================================
# Fill in the one you use; AI_DEFAULT picks which provider the assistant talks
# to. Leaving these blank is fine — the chat then reports that it is not
# configured instead of failing.
AI_DEFAULT=anthropic
ANTHROPIC_API_KEY=
OPENAI_API_KEY=
GEMINI_API_KEY=

ENV);
}
