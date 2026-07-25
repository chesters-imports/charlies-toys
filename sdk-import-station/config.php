<?php
/**
 * sdk-import-station — desk config (no alphabet).
 * Glass is read-only under ALICE_BOX/.glass-logs.
 * Work (catalog / glass extracts / wip / exports) lives under this island.
 */
declare(strict_types=1);

if (defined('SDK_IMPORT_STATION')) {
    return;
}
define('SDK_IMPORT_STATION', true);

$SDK_ROOT = str_replace('\\', '/', realpath(__DIR__) ?: __DIR__);
define('SDK_ROOT', $SDK_ROOT);

// Glass mountain (immutable inputs)
$glass = getenv('GLASS_LOGS_ROOT') ?: 'C:/ALICE_BOX/.glass-logs';
$glass = rtrim(str_replace('\\', '/', $glass), '/');
define('SDK_GLASS_ROOT', $glass);
define('SDK_OT_PATH', $glass . '/ot/conversations.json');
define('SDK_NT_DIR', $glass . '/nt');

// ROM work product (restart — not Builds tree_cores)
$work = getenv('SDK_WORK_ROOT') ?: ($SDK_ROOT . '/work');
$work = rtrim(str_replace('\\', '/', $work), '/');
define('SDK_WORK_ROOT', $work);
define('SDK_CATALOG', $work . '/catalog.json');
define('SDK_GLASS_DIR', $work . '/glass');
define('SDK_WIP_DIR', $work . '/wip');
define('SDK_EXPORTS_DIR', $work . '/exports');

foreach ([SDK_WORK_ROOT, SDK_GLASS_DIR, SDK_WIP_DIR, SDK_EXPORTS_DIR] as $d) {
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
}

/** Room hrefs inside this ROM (no myPI doors). */
function sdk_href(string $room, array $q = []): string {
    $base = match ($room) {
        'welcome', 'land', 'home' => '/?room=welcome',
        'import', 'desk' => '/?room=desk',
        'active', 'imports-active' => '/?room=active',
        'exports' => '/?room=exports',
        default => '/?room=welcome',
    };
    if ($q) {
        $base .= '&' . http_build_query($q);
    }
    return $base;
}

/** Append query to an sdk room URL (base already has ?room=). */
function sdk_url_with(string $href, array $q): string {
    if (!$q) {
        return $href;
    }
    $sep = str_contains($href, '?') ? '&' : '?';
    return $href . $sep . http_build_query($q);
}

/** Polyfill so ported pages keep working. */
if (!function_exists('mypi_room_href')) {
    function mypi_room_href(string $dom, string $room): string {
        return sdk_href($room);
    }
}
