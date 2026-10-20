<?php

declare(strict_types=1);

namespace Modules\Ai\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Modules\Ai\Agents\Assistant;
use Modules\Ai\Http\Requests\ChatRequest;

class ChatController extends Controller
{
    /**
     * Signed-in chat: the full history the browser is holding goes through.
     */
    public function chat(ChatRequest $request): StreamableAgentResponse|JsonResponse
    {
        if (! $this->configured()) {
            return $this->notConfigured();
        }

        return (new Assistant($request->history()))->stream($request->validated('message'));
    }

    /**
     * Landing-page chat. Open to anyone, so it is throttled by IP at the route,
     * capped site-wide per day here, and given a short history and a smaller
     * answer budget — the SDK ships no protection of its own for a public
     * agent, and every request costs real money.
     */
    public function publicChat(ChatRequest $request): StreamableAgentResponse|JsonResponse
    {
        if (! $this->configured()) {
            return $this->notConfigured();
        }

        $budget = 'ai:public-chat:daily';

        if (RateLimiter::tooManyAttempts($budget, (int) config('ai.assistant.public_daily_limit', 500))) {
            return response()->json([
                'message' => __('The assistant has answered all it can for today. Please try again tomorrow.'),
            ], 429);
        }

        RateLimiter::hit($budget, 86400);

        $history = $this->recentHistory(
            array_slice($request->history(), -6),
            (int) config('ai.assistant.public_history_characters', 6000),
        );

        return (new Assistant($history, (int) config('ai.assistant.public_max_tokens', 512)))
            ->stream($request->validated('message'));
    }

    /**
     * The newest turns that fit in the character budget. The history arrives
     * from the browser, so its size is the caller's choice unless cut here.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<int, array{role: string, content: string}>
     */
    private function recentHistory(array $history, int $characters): array
    {
        $kept = [];

        foreach (array_reverse($history) as $turn) {
            $characters -= mb_strlen($turn['content']);

            if ($characters < 0) {
                break;
            }

            array_unshift($kept, $turn);
        }

        return $kept;
    }

    /**
     * A project created without an API key should say so, not return a 500 from
     * somewhere inside the provider client.
     */
    private function configured(): bool
    {
        return filled(config('ai.providers.'.config('ai.default').'.key'));
    }

    private function notConfigured(): JsonResponse
    {
        return response()->json([
            'message' => __('No AI provider key is configured. Add one to your .env to enable the assistant.'),
        ], 503);
    }
}
