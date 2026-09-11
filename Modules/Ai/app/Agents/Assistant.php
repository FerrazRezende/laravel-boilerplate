<?php

declare(strict_types=1);

namespace Modules\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The assistant behind both chats.
 *
 * History is handed in by the caller rather than persisted: the public chat has
 * no user to attach a conversation to, and keeping both paths stateless avoids
 * publishing the package's conversation tables into the shared
 * database/migrations directory, which would make this module far harder to
 * remove cleanly. Swap in the RemembersConversations concern if a project wants
 * history that survives a reload.
 */
class Assistant implements Agent, Conversational
{
    use Promptable;

    /** @param array<int, array{role: string, content: string}> $history */
    public function __construct(private readonly array $history = []) {}

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You are the assistant built into a Laravel starter template. The stack is
        Laravel with Inertia and Vue 3, PostgreSQL, Redis, Horizon for queues,
        Reverb for websockets, RustFS for object storage, Pennant feature flags
        and Spatie roles/permissions. Domain code is organised into modules.

        Answer questions about this application and about building with that
        stack. Be brief and concrete — a couple of short paragraphs at most, and
        real code when code is what was asked for. If a question has nothing to
        do with this application or with software, say so plainly and stop.
        TEXT;
    }

    /** @return Message[] */
    public function messages(): iterable
    {
        return array_map(
            fn (array $message) => new Message($message['role'], $message['content']),
            $this->history,
        );
    }
}
