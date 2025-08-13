<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Db;
use PDO;

final class OrderRepository
{
    public function __construct(private Db $db) {}

    public function insertRandomOrder(): void
    {
        // Случайный продукт
        $productId = (int)$this->db->pdo()->query("SELECT id FROM products ORDER BY random() LIMIT 1")->fetchColumn();
        $quantity = random_int(1, 5);

        $stmt = $this->db->pdo()->prepare("INSERT INTO orders (product_id, quantity) VALUES (:pid, :q)");
        $stmt->execute([':pid' => $productId, ':q' => $quantity]);
    }

    // Последние N заказов с присоединёнными категориями
    public function getLastNOrdersWithCategories(int $limit = 100): array
    {
        $sql = "
          SELECT o.id, o.quantity, o.created_at, c.id AS category_id, c.name AS category_name
          FROM orders o
          JOIN products p ON p.id = o.product_id
          JOIN categories c ON c.id = p.category_id
          ORDER BY o.created_at DESC
          LIMIT :limit
        ";
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}