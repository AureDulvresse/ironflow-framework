<?php

declare(strict_types=1);

namespace Ironflow\Facades;

use Ironflow\Support\Facade;

/**
 * @method static void send(object|iterable $notifiables, \Ironflow\Notifications\Notification $notification)
 * @method static void sendNow(object|iterable $notifiables, \Ironflow\Notifications\Notification $notification)
 * @method static void extend(string $channel, callable $handler)
 */
class Notification extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ironflow\Notifications\NotificationManager::class;
    }
}
