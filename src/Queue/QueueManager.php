<?php

declare(strict_types=1);

namespace Ironflow\Queue;

use Ironflow\Database\Connection;

/**
 * Database-backed job queue.
 *
 * Jobs are stored in the `jobs` table (see the queue migration) and processed
 * by a worker started with `php forge queue:work`. Failed jobs land in
 * `failed_jobs` for inspection and replay.
 *
 * For higher throughput you can swap the storage backend (e.g. Redis) without
 * changing the Job API or call sites; this class is the single integration
 * point.
 */
class QueueManager
{
    public function __construct(
        private readonly Connection $db,
        private readonly string $table = 'jobs',
        private readonly string $failedTable = 'failed_jobs'
    ) {
    }

    /** Push a job for immediate processing. */
    public function push(Job $job): int
    {
        return $this->enqueue($job, 0);
    }

    /** Push a job to run after $delaySeconds. */
    public function later(int $delaySeconds, Job $job): int
    {
        return $this->enqueue($job, $delaySeconds);
    }

    private function enqueue(Job $job, int $delaySeconds): int
    {
        return $this->db->insert($this->table, [
            'queue'        => $job->queue,
            'payload'      => serialize($job),
            'attempts'     => 0,
            'available_at' => time() + $delaySeconds,
            'created_at'   => time(),
        ]);
    }

    /**
     * Reserve and return the next available job on the given queue, or null.
     * Uses a transaction + locking update to avoid two workers grabbing the
     * same row.
     */
    public function pop(string $queue = 'default'): ?ReservedJob
    {
        return $this->db->transaction(function () use ($queue) {
            $row = $this->db->selectOne(
                "SELECT * FROM {$this->table}
                 WHERE queue = ? AND available_at <= ? AND reserved_at IS NULL
                 ORDER BY id ASC LIMIT 1",
                [$queue, time()]
            );

            if ($row === null) {
                return null;
            }

            $this->db->update(
                $this->table,
                ['reserved_at' => time(), 'attempts' => $row['attempts'] + 1],
                ['id' => $row['id']]
            );

            $job = unserialize($row['payload']);
            if (!$job instanceof \Ironflow\Queue\Job) {
                throw new \RuntimeException('Invalid job payload for id ' . $row['id']);
            }

            return new ReservedJob(
                (int) $row['id'],
                $job,
                (int) $row['attempts'] + 1
            );
        });
    }

    /** Remove a completed job. */
    public function delete(int $id): void
    {
        $this->db->delete($this->table, ['id' => $id]);
    }

    /** Release a job back to the queue for a later retry. */
    public function release(ReservedJob $job, int $delaySeconds): void
    {
        $this->db->update(
            $this->table,
            ['reserved_at' => null, 'available_at' => time() + $delaySeconds],
            ['id' => $job->id]
        );
    }

    /** Move a job to the failed_jobs table. */
    public function markFailed(ReservedJob $job, \Throwable $e): void
    {
        $this->db->insert($this->failedTable, [
            'queue'      => $job->job->queue,
            'payload'    => serialize($job->job),
            'exception'  => (string) $e,
            'failed_at'  => time(),
        ]);
        $this->delete($job->id);
    }

    public function size(string $queue = 'default'): int
    {
        $row = $this->db->selectOne(
            "SELECT COUNT(*) AS cnt FROM {$this->table} WHERE queue = ?",
            [$queue]
        );
        return (int) ($row['cnt'] ?? 0);
    }
}
