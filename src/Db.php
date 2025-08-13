<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Db
{
    private PDO $pdo;

    public function __construct(Config $config)
    {
        $this->pdo = new PDO(
            $config->dbDsn(),
            $config->dbUser(),
            $config->dbPass(),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}