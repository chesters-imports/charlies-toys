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
        'rooms' => ['welcome', 'files', 'mail', 'desk', 'active', 'search', 'exports'],
        'paper' => defined('SDK_PAPER_ROOT') ? SDK_PAPER_ROOT : null,
        'media' => defined('SDK_MEDIA_ROOT') ? SDK_MEDIA_ROOT : null,
        'mail' => true,
    ], JSON_UNESCAPED_SLASHES);
    return true;
}

// Outside / narrative mail inject (local desk only — token gate)
if ($path === '/api/mail/inject') {
    require_once dirname(__DIR__) . '/config.php';
    require_once dirname(__DIR__) . '/app/mail/mail_lib.php';
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'POST only']);
        return true;
    }
    $raw = file_get_contents('php://input') ?: '';
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    $token = (string) ($body['token'] ?? $_SERVER['HTTP_X_MAIL_TOKEN'] ?? '');
    if (!hash_equals(sdk_mail_inject_token(), $token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'bad token']);
        return true;
    }
    $r = sdk_mail_inject(
        (string) ($body['to'] ?? ''),
        (string) ($body['from'] ?? $body['from_label'] ?? 'unknown@outside.wire'),
        (string) ($body['subject'] ?? '(no subject)'),
        (string) ($body['body'] ?? ''),
        isset($body['from_key']) ? (string) $body['from_key'] : null
    );
    if (!$r['ok']) {
        http_response_code(400);
        echo json_encode($r);
        return true;
    }
    http_response_code(201);
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
