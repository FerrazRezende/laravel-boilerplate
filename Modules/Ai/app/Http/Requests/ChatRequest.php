<?php

declare(strict_types=1);

namespace Modules\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * History is capped rather than trusted: it arrives from the browser, and
     * on the public chat there is no account behind it, so an unbounded array
     * would be a way to run up a bill one request at a time.
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['sometimes', 'array', 'max:20'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:8000'],
        ];
    }

    /** @return array<int, array{role: string, content: string}> */
    public function history(): array
    {
        return array_map(
            fn (array $message) => ['role' => $message['role'], 'content' => $message['content']],
            $this->validated('history', []),
        );
    }
}
