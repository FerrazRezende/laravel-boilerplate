<?php

declare(strict_types=1);

namespace Modules\Observability\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\MasterSupervisor;
use Laravel\Horizon\ProvisioningPlan;
use Laravel\Horizon\SupervisorOptions;

/**
 * What each queue is doing right now: how many workers it is allowed, how many
 * it has, and how much work is sitting in it.
 *
 * The list of queues comes from Horizon's provisioning plan rather than a
 * constant here, so adding a supervisor to config/horizon.php is the only step
 * needed to make a new queue show up on the screen.
 *
 * Live numbers come from Redis directly instead of from Horizon's workload
 * repository alone: with Horizon stopped, the workload is empty, and a screen
 * that then reported an empty queue would be hiding exactly the backlog an
 * operator opened it to see.
 */
final class QueueStats
{
    public function __construct(
        private readonly WorkloadRepository $workload,
        private readonly JobProgressStore $progress,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $workload = $this->workload();
        $tracked = $this->progress->countByQueue();

        $queues = [];

        foreach ($this->planned() as $queue) {
            $name = $queue['name'];
            $live = $workload[$name] ?? null;
            $depth = $this->depth($queue['connection'], $name);

            $queues[] = [
                'name' => $name,
                'connection' => $queue['connection'],
                'supervisor' => $queue['supervisor'],
                'capacity' => $queue['capacity'],
                'min_workers' => $queue['min_workers'],
                'workers' => $live['processes'] ?? 0,
                'running' => $depth['running'],
                'pending' => $depth['pending'],
                'tracked' => $tracked[$name] ?? 0,
                'wait' => $live['wait'] ?? null,
            ];
        }

        return $queues;
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_column($this->planned(), 'name');
    }

    /**
     * The queue a dispatch with no opinion lands on.
     */
    public function default(): ?string
    {
        $connection = config('queue.default');
        $queue = config("queue.connections.{$connection}.queue");

        return is_string($queue) ? $queue : null;
    }

    /**
     * The queues Horizon would run here, in the order they are declared —
     * which is the order they are meant to be read in.
     *
     * @return array<int, array<string, mixed>>
     */
    private function planned(): array
    {
        $queues = [];

        foreach ($this->supervisors() as $supervisor => $options) {
            foreach (explode(',', $options['queue']) as $name) {
                $name = trim($name);

                if ($name === '') {
                    continue;
                }

                // Two supervisors on one queue is unusual but legal, and then
                // the queue's ceiling is the sum of both pools.
                $queues[$name] = [
                    'name' => $name,
                    'connection' => $options['connection'],
                    'supervisor' => isset($queues[$name])
                        ? $queues[$name]['supervisor'].', '.$supervisor
                        : $supervisor,
                    'capacity' => ($queues[$name]['capacity'] ?? 0) + $options['capacity'],
                    'min_workers' => ($queues[$name]['min_workers'] ?? 0) + $options['min_workers'],
                ];
            }
        }

        return array_values($queues);
    }

    /**
     * Supervisor options for this environment, flattened to the four fields
     * this screen cares about.
     *
     * @return array<string, array{connection: string, queue: string, capacity: int, min_workers: int}>
     */
    private function supervisors(): array
    {
        $environment = config('horizon.env') ?? config('app.env');

        $plan = collect(ProvisioningPlan::get(MasterSupervisor::name())->parsed)
            ->first(fn ($_, $name) => Str::is($name, $environment));

        if (! empty($plan)) {
            return collect($plan)->map(fn (SupervisorOptions $options) => [
                'connection' => $options->connection,
                'queue' => (string) $options->queue,
                'capacity' => (int) $options->maxProcesses,
                'min_workers' => (int) $options->minProcesses,
            ])->all();
        }

        // An environment with no plan of its own — `testing`, or a `staging`
        // nobody added to horizon.php. Horizon starts no workers there, but the
        // queues still exist and still take work, so they are listed as
        // declared instead of the screen claiming there are none.
        return collect(config('horizon.defaults', []))->map(fn (array $options) => [
            'connection' => $options['connection'] ?? config('queue.default'),
            'queue' => implode(',', (array) ($options['queue'] ?? [])),
            'capacity' => (int) ($options['maxProcesses'] ?? 1),
            'min_workers' => (int) ($options['minProcesses'] ?? 1),
        ])->all();
    }

    /**
     * Process counts per queue, which only Horizon knows. Stopped or
     * unreachable, the rest of the screen still stands.
     *
     * @return array<string, array<string, mixed>>
     */
    private function workload(): array
    {
        try {
            return collect($this->workload->get())->keyBy('name')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Reserved means a worker has it in hand, so that is what "running" counts
     * — including jobs that report no progress at all.
     *
     * @return array{running: int|null, pending: int|null}
     */
    private function depth(string $connection, string $queue): array
    {
        if (config("queue.connections.{$connection}.driver") !== 'redis') {
            return ['running' => null, 'pending' => null];
        }

        try {
            $redis = Redis::connection(config("queue.connections.{$connection}.connection", 'default'));

            return [
                'running' => (int) $redis->zcard("queues:{$queue}:reserved"),
                'pending' => (int) $redis->llen("queues:{$queue}")
                    + (int) $redis->zcard("queues:{$queue}:delayed"),
            ];
        } catch (\Throwable) {
            return ['running' => null, 'pending' => null];
        }
    }
}
