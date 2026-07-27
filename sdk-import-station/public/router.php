<?php
// PHP built-in server router: static files as-is; else index.php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Deck Host health probe
if ($path === '/api/health' || $path === '/health') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    require_once dirname(__DIR__) . '/config.php';
    echo json_encode([
        'ok' => true,
        'rom' => 'sdk-import-station',
        'service' => 'iox',
        'rooms' => ['welcome', 'files', 'desk', 'active', 'search', 'exports'],
        'paper' => defined('SDK_PAPER_ROOT') ? SDK_PAPER_ROOT : null,
        'media' => defined('SDK_MEDIA_ROOT') ? SDK_MEDIA_ROOT : null,
    ], JSON_UNESCAPED_SLASHES);
    return true;
}

// Media door — print media:m.HEX (fallback when not inlined as data:)
if ($path === '/media' || str_starts_with($path, '/media/')) {
    require_once dirname(__DIR__) . '/config.php';
    require_once dirname(__DIR__) . '/app/filekeeper/media_lib.php';
    $id = isset($_GET['id']) ? (string) $_GET['id'] : '';
    if ($id === '' && preg_match('#/media/([^/?]+)#', $path, $m)) {
        $id = rawurldecode($m[1]);
    }
    fk_media_seed_from_legacy($id);
    $filePath = fk_media_resolve($id);
    if ($filePath === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "media not found: $id";
        return true;
    }
    $mime = fk_media_mime($filePath);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($filePath));
    header('Cache-Control: private, max-age=3600');
    readfile($filePath);
    return true;
}

$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/index.php';
