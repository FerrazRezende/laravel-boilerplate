<?php

declare(strict_types=1);

namespace Modules\Ai\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
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
     * Landing-page chat. Open to anyone, so it is throttled by IP at the route
     * and kept to the last few turns here — the SDK ships no protection of its
     * own for a public agent, and every request costs real money.
     */
    public function publicChat(ChatRequest $request): StreamableAgentResponse|JsonResponse
    {
        if (! $this->configured()) {
            return $this->notConfigured();
        }

        $history = array_slice($request->history(), -6);

        return (new Assistant($history))->stream($request->validated('message'));
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
