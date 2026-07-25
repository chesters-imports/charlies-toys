<?php
// PHP built-in server router: static files as-is; else index.php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Deck Host health probe
if ($path === '/api/health' || $path === '/health') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => true,
        'rom' => 'sdk-import-station',
        'service' => 'iox',
    ], JSON_UNESCAPED_SLASHES);
    return true;
}

$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/index.php';
