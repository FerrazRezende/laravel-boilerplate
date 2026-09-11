<?php

declare(strict_types=1);

namespace Modules\Observability\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;
use Modules\Observability\Jobs\DemoProgressJob;
use Modules\Observability\Services\JobProgressStore;
use Modules\Observability\Services\QueueStats;

class JobController extends Controller
{
    public function __construct(
        private readonly JobProgressStore $store,
        private readonly QueueStats $queues,
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $payload = [
            'jobs' => $this->store->running(),
            'queues' => $this->queues->all(),
            'defaultQueue' => $this->queues->default(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return inertia('Jobs/Index', [
            ...$payload,
            'horizonUrl' => url('/horizon'),
        ]);
    }

    /**
     * A freshly created project has no queued work of its own, so the screen
     * would open empty with nothing to demonstrate. This dispatches a job that
     * reports progress the way a real one would, onto the queue you pick —
     * firing several at the smallest pool is the quickest way to see a queue
     * fill past its capacity.
     */
    public function demo(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $queue = $request->validate([
            'queue' => ['nullable', 'string', Rule::in($this->queues->names())],
        ])['queue'] ?? null;

        // A null queue is the connection's default, which is what an
        // unspecified dispatch would have used anyway.
        DemoProgressJob::dispatch()->onQueue($queue);

        return back();
    }
}
