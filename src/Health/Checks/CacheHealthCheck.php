<?php

declare(strict_types=1);

namespace Ironflow\Health\Checks;

use Ironflow\Cache\CacheManager;
use Ironflow\Health\HealthCheck;
use Ironflow\Health\HealthResult;

/**
 * Verifies the cache can round-trip a value.
 */
class CacheHealthCheck implements HealthCheck
{
    public function __construct(private readonly CacheManager $cache)
    {
    }

    public function name(): string
    {
        return 'cache';
    }

    public function run(): HealthResult
    {
        $key = '__health_probe__';
        $value = 'ping';

        try {
            $this->cache->put($key, $value, 10);
            $read = $this->cache->get($key);
            $this->cache->forget($key);

            return $read === $value
                ? HealthResult::ok('Read/write OK')
                : HealthResult::fail('Value mismatch on read-back');
        } catch (\Throwable $e) {
            return HealthResult::fail('Error: ' . $e->getMessage());
        }
    }
}
