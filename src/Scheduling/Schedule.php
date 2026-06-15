<?php

declare(strict_types=1);

namespace Ironflow\Scheduling;

use Ironflow\Queue\Job;
use Ironflow\Queue\QueueManager;

/**
 * Task scheduler — define recurring work in one place and trigger it from a
 * single cron entry:
 *
 *   * * * * * cd /path/to/app && php forge schedule:run >> /dev/null 2>&1
 *
 * Define tasks in a module's boot() or a dedicated scheduler file:
 *
 *   $schedule->call(fn() => Cache::flush())->daily();
 *   $schedule->command('db:backup')->dailyAt('02:00');
 *   $schedule->job(new PruneStaleSessions())->hourly();
 *   $schedule->call($fn)->everyMinutes(15)->weekdays();
 */
class Schedule
{
    /** @var ScheduledEvent[] */
    private array $events = [];

    public function __construct(private readonly ?QueueManager $queue = null)
    {
    }

    /** Schedule a closure to run in-process. */
    public function call(callable $callback, array $args = []): ScheduledEvent
    {
        return $this->events[] = new ScheduledEvent(
            fn() => $callback(...$args),
            'closure'
        );
    }

    /** Schedule a forge console command (run as a subprocess). */
    public function command(string $command): ScheduledEvent
    {
        return $this->events[] = new ScheduledEvent(
            function () use ($command) {
                $php = PHP_BINARY;
                $forge = \Ironflow\Application::getInstance()->getBasePath('forge');
                $cmd = escapeshellarg($php) . ' ' . escapeshellarg($forge) . ' ' . $command;
                exec($cmd, $out, $code);
                if ($code !== 0) {
                    throw new \RuntimeException("Scheduled command '{$command}' exited with code {$code}: " . implode("\n", $out));
                }
            },
            'command:' . $command
        );
    }

    /** Schedule a queued job to be dispatched. */
    public function job(Job $job): ScheduledEvent
    {
        return $this->events[] = new ScheduledEvent(
            function () use ($job) {
                $this->queue?->push($job);
            },
            'job:' . $job::class
        );
    }

    /**
     * Run all events that are due at the given time (defaults to now).
     *
     * @return array<array{event:string, status:string}> Run summary.
     */
    public function run(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $results = [];

        foreach ($this->events as $event) {
            if (!$event->isDue($now)) {
                continue;
            }

            try {
                $event->run();
                $results[] = ['event' => $event->description(), 'status' => 'ok'];
            } catch (\Throwable $e) {
                $results[] = ['event' => $event->description(), 'status' => 'error: ' . $e->getMessage()];
            }
        }

        return $results;
    }

    /** @return ScheduledEvent[] */
    public function events(): array
    {
        return $this->events;
    }
}
