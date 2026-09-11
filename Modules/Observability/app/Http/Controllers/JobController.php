<?php

declare(strict_types=1);

namespace Modules\Observability\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Modules\Observability\Jobs\DemoProgressJob;
use Modules\Observability\Services\JobProgressStore;

class JobController extends Controller
{
    public function __construct(private readonly JobProgressStore $store) {}

    public function index(Request $request): Response|JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $jobs = $this->store->running();

        if ($request->wantsJson()) {
            return response()->json(['jobs' => $jobs]);
        }

        return inertia('Jobs/Index', [
            'jobs' => $jobs,
            'horizonUrl' => url('/horizon'),
        ]);
    }

    /**
     * A freshly created project has no queued work of its own, so the screen
     * would open empty with nothing to demonstrate. This dispatches a job that
     * reports progress the way a real one would.
     */
    public function demo(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        DemoProgressJob::dispatch();

        return back();
    }
}
