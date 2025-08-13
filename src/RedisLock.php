<?php
declare(strict_types=1);

namespace App;

use Redis;

final class RedisLock
{
    private Redis $redis;

    public function __construct(Config $config)
    {
        $this->redis = new Redis();
        $this->redis->connect($config->redisHost(), $config->redisPort(), 1.0);
    }

    // Возвращает true, если удалось взять лок
    public function acquire(string $key, int $ttlSeconds): bool
    {
        return (bool)$this->redis->set($key, '1', ['nx', 'ex' => $ttlSeconds]);
    }

    public function release(string $key): void
    {
        $this->redis->del($key);
    }
}