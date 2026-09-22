<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Queue\Job;

class TestQueueJob extends Job
{
    public function __construct(public string $note = 'hello')
    {
    }

    public function handle(): void
    {
    }
}
