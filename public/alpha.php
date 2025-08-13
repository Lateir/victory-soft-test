<?php
declare(strict_types=1);

use App\Config;
use App\Db;
use App\Http;
use App\RedisLock;
use App\Repositories\OrderRepository;
use App\Services\AlphaService;

// Простейший автолоадер для примера без Composer
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
$repo = new OrderRepository($db);
$lock = new RedisLock($config);

$alpha = new AlphaService($repo, $lock);
$result = $alpha->runWithLock('alpha_lock', 5);

Http::jsonResponse($result);