<?php
declare(strict_types=1);

namespace App;

final class Config
{
    public function dbDsn(): string
    {
        $host = getenv('DB_HOST') ?: 'postgres';
        $port = getenv('DB_PORT') ?: '5432';
        $db   = getenv('DB_NAME') ?: 'app';
        return "pgsql:host={$host};port={$port};dbname={$db}";
    }

    public function dbUser(): string { return getenv('DB_USER') ?: 'app'; }
    public function dbPass(): string { return getenv('DB_PASS') ?: 'app'; }

    public function redisHost(): string { return getenv('REDIS_HOST') ?: 'redis'; }
    public function redisPort(): int { return (int)(getenv('REDIS_PORT') ?: 6379); }

}