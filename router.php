<?php
declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$uri = '/' . ltrim($uri, '/');

$blocked = ['/data', '/includes'];
foreach ($blocked as $b) {
    if ($uri === $b || str_starts_with($uri, $b . '/')) {
        http_response_code(404);
        echo 'Not found';
        return true;
    }
}

$pay = [
    '/bill' => 'bill',
    '/withdraw' => 'withdraw',
    '/transfer' => 'transfer',
];
if (isset($pay[$uri])) {
    define('OH_FORCE_ACTION', $pay[$uri]);
    require __DIR__ . '/api.php';
    return true;
}

$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    return false;
}

if ($uri === '/' || $uri === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}

http_response_code(404);
echo 'Not found';
return true;
