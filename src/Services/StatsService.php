<?php
declare(strict_types=1);

namespace App\Services;

use App\Db;

final class StatsService
{
    public function __construct(private Db $db) {}

    public function last100Stats(): array
    {
        // 1) Метаданные окна
        $metaStmt = $this->db->pdo()->prepare("
            SELECT 
              window_size,
              COALESCE(EXTRACT(EPOCH FROM (last_ts - first_ts))::INT, 0) AS time_delta_sec
            FROM stats_meta
            WHERE id = 1
        ");
        $metaStmt->execute();
        $meta = $metaStmt->fetch() ?: ['window_size' => 0, 'time_delta_sec' => 0];

        // 2) Агрегаты по категориям
        $catsStmt = $this->db->pdo()->prepare("
            SELECT c.id AS category_id, c.name AS category_name, s.qty_sum AS quantity
            FROM stats_recent s
            JOIN categories c ON c.id = s.category_id
            ORDER BY s.qty_sum DESC, c.id ASC
        ");
        $catsStmt->execute();
        $categories = $catsStmt->fetchAll() ?: [];

        return [
            'orders_count'   => (int)($meta['window_size'] ?? 0),
            'time_delta_sec' => (int)($meta['time_delta_sec'] ?? 0),
            'categories'     => $categories,
            'generated_at'   => gmdate('c'),
        ];
    }
}