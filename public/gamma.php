<?php
declare(strict_types=1);

use App\Config;
use App\Db;
use App\Http;
use App\Services\StatsService;

spl_autoload_register(function($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});

$config = new Config();
$db = new Db($config);
$svc = new StatsService($db);

Http::jsonResponse($svc->last100Stats());