<?php
declare(strict_types=1);

use App\Http;

spl_autoload_register(function($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});

// Сколько запусков
$n = Http::getInt('n', 1000, 1, 20000);

// Путь к CLI-скрипту
$script = realpath(__DIR__ . '/alpha.php');
if ($script === false || !is_file($script)) {
    Http::jsonResponse([
        'requested' => $n,
        'dispatched' => 0,
        'error' => 'alpha.php not found',
    ], 500);
    exit;
}


@set_time_limit(10);

$dispatched = 0;
$errors = [];

// Запускаем N фоновых процессов
for ($i = 1; $i <= $n; $i++) {

    $cmd = 'php ' . $script. ' ' .
        ' > /dev/null 2>&1 &';

    $out = [];
    $code = 0;
    @exec($cmd, $out, $code);

    if ($code === 0) {
        $dispatched++;
    } else {
        $errors[] = ['i' => $i, 'code' => $code];
    }
}

Http::jsonResponse([
    'requested' => $n,
    'dispatched' => $dispatched,
    'errors' => $errors,
]);