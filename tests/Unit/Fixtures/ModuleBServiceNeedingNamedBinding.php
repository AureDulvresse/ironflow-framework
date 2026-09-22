<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Attributes\Inject;

class ModuleBServiceNeedingNamedBinding
{
    public function __construct(#[Inject('provider-a.secret')] public mixed $secret)
    {
    }
}
