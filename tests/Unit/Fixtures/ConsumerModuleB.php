<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Module\Attributes\Module;
use Ironflow\Module\BaseModule;

#[Module(
    name: 'consumer-b',
    imports: [ProviderModuleA::class],
    providers: [
        ModuleBServiceNeedingPrivate::class,
        ModuleBServiceNeedingExported::class,
        ModuleBController::class,
        ModuleBServiceNeedingNamedBinding::class,
    ],
    exports: [],
)]
class ConsumerModuleB extends BaseModule
{
}
