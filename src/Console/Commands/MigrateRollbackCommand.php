<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;
use Ironflow\Database\Connection;
use Ironflow\Database\Migrations\Migrator;

/**
 * Rolls back the most recently run batch of migrations.
 */
class MigrateRollbackCommand extends Command
{
    protected string $signature = 'migrate:rollback {--path=}';
    protected string $description = 'Rollback the last batch of migrations';

    public function __construct(private readonly Connection $db)
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        $migrator   = new Migrator($this->db);
        $explicitPath = $this->option('path');
        $paths      = $explicitPath !== null && is_dir((string) $explicitPath)
            ? [(string) $explicitPath]
            : Migrator::discoverPaths(base_path());

        $rolledBack = [];
        foreach ($paths as $p) {
            $rolledBack = array_merge($rolledBack, $migrator->rollback($p));
        }

        if (empty($rolledBack)) {
            $this->info('Nothing to rollback.');
        } else {
            $this->newLine();
            foreach ($rolledBack as $item) {
                $this->migrationLine($item['file'], $item['ms'], rollback: true);
            }
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
