<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;

class MakeTestCommand extends Command
{
    protected string $signature   = 'make:test {name?} {--unit : Create a unit test instead of a feature test} {--module=}';
    protected string $description  = 'Create a new Pest test file';

    protected function handle(): int
    {
        $name   = $this->argumentOrAsk('name', 'Test name (e.g. PostController):');
        $name   = str_ends_with($name, 'Test') ? $name : $name . 'Test';
        $module = $this->option('module');
        $unit   = (bool) $this->option('unit');

        $sub = $unit ? 'Unit' : 'Feature';

        if ($module) {
            $path = base_path("modules/{$module}/Tests/{$sub}/{$name}.php");
        } else {
            $path = base_path("tests/{$sub}/{$name}.php");
        }

        @mkdir(dirname($path), 0755, true);

        $subject = str_replace('Test', '', $name);

        file_put_contents($path, <<<PHP
<?php

declare(strict_types=1);

it('{$subject} works', function () {
    expect(true)->toBeTrue();
});
PHP);
        $this->success("Test [{$name}] created at {$path}.");
        return self::SUCCESS;
    }
}
