<?php

declare(strict_types=1);

namespace Modules\Observability\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Live progress for running jobs, in Redis.
 *
 * Entries expire on their own: the TTL is refreshed on every write, so a worker
 * that is killed mid-job leaves nothing behind and no cleanup job is needed.
 * The index is a sorted set scored by start time rather than a KEYS scan, which
 * must never run against a production Redis.
 */
final class JobProgressStore
{
    private const TTL = 300;

    private const INDEX = 'job-progress:running';

    public function put(
        string $jobId,
        string $label,
        int $percentage,
        ?string $startedAt = null,
        ?string $queue = null,
    ): void {
        $startedAt ??= now()->toIso8601String();

        Redis::setex($this->key($jobId), self::TTL, json_encode([
            'id' => $jobId,
            'label' => $label,
            'percentage' => max(0, min(100, $percentage)),
            'started_at' => $startedAt,
            'queue' => $queue,
            'updated_at' => now()->toIso8601String(),
        ]));

        Redis::zadd(self::INDEX, now()->timestamp, $jobId);
    }

    public function forget(string $jobId): void
    {
        Redis::del($this->key($jobId));
        Redis::zrem(self::INDEX, $jobId);
    }

    public function find(string $jobId): ?array
    {
        $data = Redis::get($this->key($jobId));

        if ($data === false || $data === null) {
            return null;
        }

        return json_decode($data, true) ?: null;
    }

    /**
     * Running jobs, newest first. Ids whose entry has already expired are
     * dropped from the index on the way past — that is the only cleanup the
     * index ever gets, and it is enough.
     *
     * @return array<int, array<string, mixed>>
     */
    public function running(): array
    {
        $jobs = [];

        foreach (Redis::zrevrange(self::INDEX, 0, 99) as $jobId) {
            $entry = $this->find($jobId);

            if ($entry === null) {
                Redis::zrem(self::INDEX, $jobId);

                continue;
            }

            $jobs[] = $entry;
        }

        return $jobs;
    }

    /**
     * How many tracked jobs are reporting on each queue, keyed by queue name.
     *
     * @return array<string, int>
     */
    public function countByQueue(): array
    {
        $counts = [];

        foreach ($this->running() as $entry) {
            $queue = $entry['queue'] ?? null;

            if (is_string($queue) && $queue !== '') {
                $counts[$queue] = ($counts[$queue] ?? 0) + 1;
            }
        }

        return $counts;
    }

    private function key(string $jobId): string
    {
        return "job-progress:{$jobId}";
    }
}
