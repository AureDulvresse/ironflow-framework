<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Notifications\Notification;

class CustomChannelNotification extends Notification
{
    public function __construct(private readonly string $channel)
    {
    }

    public function via(object $notifiable): array
    {
        return [$this->channel];
    }
}
