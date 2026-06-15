<?php

declare(strict_types=1);

namespace Ironflow\Facades;

use Ironflow\Support\Facade;

/**
 * @method static int push(\Ironflow\Queue\Job $job)
 * @method static int later(int $delaySeconds, \Ironflow\Queue\Job $job)
 * @method static int size(string $queue = 'default')
 */
class Queue extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ironflow\Queue\QueueManager::class;
    }
}
