<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

class ModuleBServiceNeedingExported
{
    public function __construct(public ModuleAExportedService $dep)
    {
    }
}
