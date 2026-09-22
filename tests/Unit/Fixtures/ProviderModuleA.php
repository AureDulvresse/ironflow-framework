<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Module\Attributes\Module;
use Ironflow\Module\BaseModule;

#[Module(
    name: 'provider-a',
    imports: [],
    providers: [ModuleAPrivateService::class, ModuleAExportedService::class],
    exports: [ModuleAExportedService::class],
)]
class ProviderModuleA extends BaseModule
{
}
