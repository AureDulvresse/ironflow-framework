<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;
use Ironflow\Database\Connection;
use Ironflow\Database\Migrations\Migrator;

class MigrateStatusCommand extends Command
{
    protected string $signature = 'migrate:status';
    protected string $description = 'Show the status of each migration';

    public function __construct(private readonly Connection $db)
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        $migrator = new Migrator($this->db);
        $paths    = Migrator::discoverPaths(base_path());

        $all = [];
        foreach ($paths as $p) {
            $all = array_merge($all, $migrator->status($p));
        }

        if (empty($all)) {
            $this->info('No migrations found.');
            return self::SUCCESS;
        }

        $this->newLine();

        foreach ($all as $s) {
            $name = mb_strimwidth($s['migration'], 0, 54, '…');
            $dots = str_repeat('.', max(2, 56 - mb_strlen($name)));

            if ($s['ran']) {
                $badge  = '<options=bold;fg=green> RAN </>';
                $detail = "<fg=gray>batch {$s['batch']}</>";
            } else {
                $badge  = '<options=bold;fg=yellow>WAIT </>';
                $detail = '<fg=gray>pending</>';
            }

            $this->output->writeln("   {$badge}  {$name} <fg=gray>{$dots}</> {$detail}");
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
