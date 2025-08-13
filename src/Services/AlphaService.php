<?php
declare(strict_types=1);

namespace App\Services;

use App\RedisLock;
use App\Repositories\OrderRepository;

final class AlphaService
{
    public function __construct(
        private OrderRepository $orders,
        private RedisLock $lock
    ) {}

    public function runWithLock(string $lockKey = 'alpha_lock', int $ttl = 5): array
    {
        if (!$this->lock->acquire($lockKey, $ttl)) {
            return ['status' => 'skipped', 'reason' => 'already running'];
        }

        try {
            // Имитируем долгую работу
            usleep(1_000_000); // 1 сек
            $this->orders->insertRandomOrder();
            return ['status' => 'ok'];
        } finally {
            $this->lock->release($lockKey);
        }
    }
}