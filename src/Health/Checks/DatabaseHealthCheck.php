<?php

declare(strict_types=1);

namespace Ironflow\Health\Checks;

use Ironflow\Database\Connection;
use Ironflow\Health\HealthCheck;
use Ironflow\Health\HealthResult;

/**
 * Verifies the database is reachable by issuing a trivial query.
 */
class DatabaseHealthCheck implements HealthCheck
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function name(): string
    {
        return 'database';
    }

    public function run(): HealthResult
    {
        $start = microtime(true);
        try {
            $this->db->selectOne('SELECT 1 AS ok');
            $ms = round((microtime(true) - $start) * 1000, 2);
            return HealthResult::ok('Connected', ['response_ms' => $ms]);
        } catch (\Throwable $e) {
            return HealthResult::fail('Unreachable: ' . $e->getMessage());
        }
    }
}
