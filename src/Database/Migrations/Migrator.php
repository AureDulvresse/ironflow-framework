<?php

declare(strict_types=1);

namespace Ironflow\Database\Migrations;

use Ironflow\Database\Connection;
use Ironflow\Database\Schema\Schema;

/**
 * Discovers, runs, and rolls back migrations.
 * Tracks ran migrations in a `migrations` table with batch numbers.
 */
class Migrator
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
        $this->ensureTable();
    }

    /** @throws \RuntimeException If a discovered migration file doesn't return a Migration instance, or its class can't be found. */
    public function run(string $path): array
    {
        $pending = $this->getPendingMigrations($path);
        if (empty($pending)) {
            return [];
        }

        $batch = $this->getLastBatch() + 1;
        $ran = [];

        foreach ($pending as $file) {
            $migration = $this->resolveMigration($file);
            $t0 = hrtime(true);

            // up() and the tracking insert succeed or fail together — a
            // migration that partially applies before throwing must not be
            // marked as ran, or it would silently be skipped on retry while
            // leaving the schema half-changed. (MySQL DDL still auto-commits
            // regardless — this only buys atomicity where the driver supports
            // transactional DDL, e.g. SQLite/PostgreSQL, but is harmless elsewhere.)
            $this->db->transaction(function () use ($migration, $file, $batch): void {
                $migration->up();
                $this->db->insert('migrations', [
                    'migration' => basename($file, '.php'),
                    'batch' => $batch,
                    'ran_at' => date('Y-m-d H:i:s'),
                ]);
            });

            $ms = (int) round((hrtime(true) - $t0) / 1_000_000);
            $ran[] = ['file' => basename($file), 'ms' => $ms];
        }

        return $ran;
    }

    /** @throws \RuntimeException If a migration file doesn't return a Migration instance, or its class can't be found. */
    public function rollback(string $path): array
    {
        $lastBatch = $this->getLastBatch();
        if ($lastBatch === 0) {
            return [];
        }

        $rows = $this->db->select(
            'SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC',
            [$lastBatch]
        );

        $rolledBack = [];

        foreach ($rows as $row) {
            $file = $path . '/' . $row['migration'] . '.php';
            if (!is_file($file)) {
                continue;
            }

            $migration = $this->resolveMigration($file);
            $t0 = hrtime(true);

            $this->db->transaction(function () use ($migration, $row): void {
                $migration->down();
                $this->db->statement('DELETE FROM migrations WHERE migration = ?', [$row['migration']]);
            });

            $ms = (int) round((hrtime(true) - $t0) / 1_000_000);
            $rolledBack[] = ['file' => basename($file), 'ms' => $ms];
        }

        return $rolledBack;
    }

    public function status(string $path): array
    {
        $ran = array_column(
            $this->db->select('SELECT migration, batch FROM migrations ORDER BY id'),
            null,
            'migration'
        );

        $files = $this->getMigrationFiles($path);
        $status = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $status[] = [
                'migration' => $name,
                'ran' => isset($ran[$name]),
                'batch' => $ran[$name]['batch'] ?? null,
            ];
        }

        return $status;
    }

    public function dropAll(): void
    {
        $sm     = $this->db->getSchemaManager();
        $tables = $sm->listTableNames();
        foreach (array_reverse($tables) as $table) {
            if ($table === 'migrations') {
                continue;
            }
            Schema::drop($table);
        }
        $this->db->statement('DELETE FROM migrations');
    }

    public function fresh(string $path): array
    {
        $this->dropAll();
        return $this->run($path);
    }

    /**
     * Discover all migration directories under a base path.
     * Checks: {base}/database/migrations, {base}/modules/*\/Database/Migrations, {base}/modules/*\/Migrations
     *
     * @return string[]
     */
    public static function discoverPaths(string $basePath): array
    {
        $paths = [];

        $global = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        if (is_dir($global)) {
            $paths[] = $global;
        }

        $modulesPath = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . 'modules';
        if (is_dir($modulesPath)) {
            foreach (['/*/Database/Migrations', '/*/Migrations'] as $pattern) {
                foreach (glob($modulesPath . $pattern) ?: [] as $dir) {
                    if (!in_array($dir, $paths, true)) {
                        $paths[] = $dir;
                    }
                }
            }
        }

        return $paths;
    }

    private function getPendingMigrations(string $path): array
    {
        $ran = array_column($this->db->select('SELECT migration FROM migrations'), 'migration');
        $files = $this->getMigrationFiles($path);

        return array_filter($files, function ($file) use ($ran) {
            return !in_array(basename($file, '.php'), $ran, true);
        });
    }

    private function getMigrationFiles(string $path): array
    {
        $files = glob($path . '/*.php') ?: [];
        sort($files);
        return $files;
    }

    /**
     * Resolve a Migration instance from a file.
     *
     * Supports two patterns:
     *   1. Anonymous class  — file ends with `return new class extends Migration { ... };`
     *   2. Named class      — file defines class CreateXxxTable extends Migration
     */
    private function resolveMigration(string $file): Migration
    {
        $contents = (string) file_get_contents($file);

        if (preg_match('/return\s+new\s+class\b/i', $contents)) {
            $instance = require $file;
            if ($instance instanceof Migration) {
                return $instance;
            }
            throw new \RuntimeException(
                "Migration file [{$file}] uses anonymous-class syntax but did not return a Migration instance."
            );
        }

        require_once $file;
        $class = $this->classFromFile($file);

        if (!class_exists($class)) {
            throw new \RuntimeException(
                "Migration class [{$class}] not found after requiring [{$file}]. "
                . "The class name must match the file name, or use the `return new class` pattern."
            );
        }

        return new $class();
    }

    private function classFromFile(string $file): string
    {
        $name = basename($file, '.php');
        // Convert 2024_01_01_000000_create_posts_table → CreatePostsTable
        $parts      = explode('_', $name);
        $classParts = array_slice($parts, 4);
        $className  = implode('', array_map('ucfirst', $classParts));

        // Prepend the namespace declared in the file (handles namespaced migrations)
        $contents = file_get_contents($file);
        if ($contents !== false && preg_match('/^\s*namespace\s+([^\s;]+)/m', $contents, $m)) {
            return $m[1] . '\\' . $className;
        }

        return $className;
    }

    private function getLastBatch(): int
    {
        $row = $this->db->selectOne('SELECT MAX(batch) as b FROM migrations');
        return (int) ($row['b'] ?? 0);
    }

    private function ensureTable(): void
    {
        $this->db->statement(
            "CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL,
                batch INTEGER NOT NULL DEFAULT 0,
                ran_at DATETIME
            )"
        );
    }
}
