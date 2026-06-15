<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;

class MakeJobCommand extends Command
{
    protected string $signature   = 'make:job {name?} {--module=}';
    protected string $description  = 'Create a new queued job class';

    protected function handle(): int
    {
        $name   = $this->argumentOrAsk('name', 'Job name (e.g. SendWelcomeEmail):');
        $module = $this->option('module');

        if ($module) {
            $path = base_path("modules/{$module}/Jobs/{$name}.php");
            $ns   = "Modules\\{$module}\\Jobs";
        } else {
            $path = base_path("app/Jobs/{$name}.php");
            $ns   = 'App\\Jobs';
        }

        @mkdir(dirname($path), 0755, true);

        file_put_contents($path, <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use Ironflow\\Queue\\Job;

class {$name} extends Job
{
    public int \$tries = 3;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        // ...
    }
}
PHP);
        $this->success("Job [{$name}] created.");
        return self::SUCCESS;
    }
}
