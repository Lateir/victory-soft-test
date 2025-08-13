<?php
declare(strict_types=1);

namespace App;

final class Http
{
    public static function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function getInt(string $key, int $default, int $min = 1, int $max = 100000): int
    {
        $val = isset($_GET[$key]) ? (int)$_GET[$key] : $default;
        if ($val < $min) $val = $min;
        if ($val > $max) $val = $max;
        return $val;
    }
}