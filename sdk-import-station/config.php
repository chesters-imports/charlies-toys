<?php
/**
 * sdk-import-station — desk config (no alphabet).
 * Glass is read-only under ALICE_BOX/.glass-logs.
 * Work (catalog / glass extracts / wip / exports) lives under this island.
 * Stations: io (1st) · ab (2nd) — chip switches color + paper root.
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

// Paper base — per-station roots under paper/{io|ab|…}
define('SDK_PAPER_BASE', $SDK_ROOT . '/paper');

// Media assets for media:m.HEX embeds
$media = getenv('SDK_MEDIA_ROOT') ?: ($SDK_ROOT . '/media');
$media = rtrim(str_replace('\\', '/', $media), '/');
define('SDK_MEDIA_ROOT', $media);

foreach ([SDK_WORK_ROOT, SDK_GLASS_DIR, SDK_WIP_DIR, SDK_EXPORTS_DIR, SDK_MEDIA_ROOT, SDK_PAPER_BASE . '/io', SDK_PAPER_BASE . '/io/_root', SDK_PAPER_BASE . '/ab', SDK_PAPER_BASE . '/ab/_root'] as $d) {
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
}

/**
 * Live stations (order = cycle order). AB is always second.
 * @return array<string, array<string, string>>
 */
function sdk_station_defs(): array {
    return [
        'io' => [
            'id' => 'io',
            'chip' => 'IOX',
            'skin' => 'io',
            'label' => 'Terminal IO',
            'face' => 'SDK',
            'user' => 'SDK-808',
            'foot' => 'O·X',
            'ghost' => 'put them here',
            'whisper' => 'for the collection and protection of your thoughts',
            'brand_import' => "CHESTER'S NOW IMPORTING",
            'brand_files' => "CHESTER'S PAPER",
            'hint_files' => 'paper/io',
            'hint_else' => 'the forest remembers',
            'deck_chip' => "CHESTER'S IMPORTS",
            'show_imports' => '1',
            'welcome' => 'io',
        ],
        'ab' => [
            'id' => 'ab',
            'chip' => 'ABX',
            'skin' => 'ab',
            'label' => 'Terminal AB',
            'face' => 'KDE',
            'user' => 'KDE-555',
            'foot' => 'A·B',
            'ghost' => 'the line is listening',
            'whisper' => 'for suppression · dossier · wire-watch',
            'brand_import' => 'ABX · ON WATCH',
            'brand_files' => "K'S PAPER",
            'hint_files' => 'paper/ab',
            'hint_else' => 'leeks · agents · the line',
            'deck_chip' => 'ABX · SUPPRESSION',
            // toolkit: files now; dossier later — not Sam's glass import/export
            'show_imports' => '0',
            'welcome' => 'ab',
        ],
    ];
}

/** @return list<string> */
function sdk_station_order(): array {
    return array_keys(sdk_station_defs());
}

function sdk_station_valid(string $id): bool {
    return isset(sdk_station_defs()[$id]);
}

/**
 * Resolve active station from query → cookie → default io.
 * Call once at request start (index.php).
 */
function sdk_boot_station(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $fromQ = isset($_GET['station']) ? strtolower(trim((string) $_GET['station'])) : '';
    if ($fromQ === 'abx') {
        $fromQ = 'ab';
    }
    if ($fromQ === 'iox') {
        $fromQ = 'io';
    }
    if ($fromQ !== '' && sdk_station_valid($fromQ)) {
        $_SESSION['sdk_station'] = $fromQ;
        setcookie('sdk_station', $fromQ, [
            'expires' => time() + 86400 * 400,
            'path' => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $GLOBALS['SDK_STATION'] = $fromQ;
        return $fromQ;
    }
    if (!empty($_SESSION['sdk_station']) && sdk_station_valid((string) $_SESSION['sdk_station'])) {
        $GLOBALS['SDK_STATION'] = (string) $_SESSION['sdk_station'];
        return $GLOBALS['SDK_STATION'];
    }
    $fromC = isset($_COOKIE['sdk_station']) ? strtolower(trim((string) $_COOKIE['sdk_station'])) : '';
    if ($fromC !== '' && sdk_station_valid($fromC)) {
        $_SESSION['sdk_station'] = $fromC;
        $GLOBALS['SDK_STATION'] = $fromC;
        return $fromC;
    }
    $GLOBALS['SDK_STATION'] = 'io';
    return 'io';
}

function sdk_station(): string {
    if (!empty($GLOBALS['SDK_STATION']) && sdk_station_valid((string) $GLOBALS['SDK_STATION'])) {
        return (string) $GLOBALS['SDK_STATION'];
    }
    return sdk_boot_station();
}

/** @return array<string, string> */
function sdk_station_meta(?string $id = null): array {
    $id = $id ?? sdk_station();
    $defs = sdk_station_defs();
    return $defs[$id] ?? $defs['io'];
}

/** Paper root for active (or given) station. */
function sdk_paper_root_path(?string $station = null): string {
    $s = $station ?? sdk_station();
    if (!sdk_station_valid($s)) {
        $s = 'io';
    }
    $p = SDK_PAPER_BASE . '/' . $s;
    if (!is_dir($p)) {
        @mkdir($p, 0775, true);
    }
    if (!is_dir($p . '/_root')) {
        @mkdir($p . '/_root', 0775, true);
    }
    return $p;
}

/** Next station in cycle (IOX/ABX click). */
function sdk_station_next(?string $id = null): string {
    $order = sdk_station_order();
    $id = $id ?? sdk_station();
    $i = array_search($id, $order, true);
    if ($i === false) {
        return $order[0];
    }
    return $order[($i + 1) % count($order)];
}

/**
 * Room hrefs — always carry station= so color sticks.
 * @param array<string, scalar> $q
 */
function sdk_href(string $room, array $q = []): string {
    if (!isset($q['station'])) {
        $q['station'] = sdk_station();
    }
    $base = match ($room) {
        'welcome', 'land', 'home' => '/?room=welcome',
        'files', 'file', 'filekeeper', 'fk' => '/?room=files',
        'mail', 'email', 'inbox' => '/?room=mail',
        'import', 'desk' => '/?room=desk',
        'active', 'imports-active' => '/?room=active',
        'exports' => '/?room=exports',
        'search', 'find', 'hunt' => '/?room=search',
        default => '/?room=welcome',
    };
    // merge station + extra into query
    $parts = [];
    parse_str(ltrim(strstr($base, '?') ?: '', '?'), $parts);
    foreach ($q as $k => $v) {
        $parts[$k] = $v;
    }
    return '/?' . http_build_query($parts);
}

/** Append query to an sdk room URL. */
function sdk_url_with(string $href, array $q): string {
    if (!$q) {
        return $href;
    }
    $sep = str_contains($href, '?') ? '&' : '?';
    return $href . $sep . http_build_query($q);
}

/** Chip click: same room, next station. */
function sdk_href_cycle_station(string $room = 'files'): string {
    return sdk_href($room, ['station' => sdk_station_next()]);
}

/** Polyfill so ported pages keep working. */
if (!function_exists('mypi_room_href')) {
    function mypi_room_href(string $dom, string $room): string {
        return sdk_href($room, ['station' => $dom === 'ab' ? 'ab' : sdk_station()]);
    }
}

// Legacy alias — many call sites; resolves to *active* station paper
if (!defined('SDK_PAPER_ROOT')) {
    // Placeholder; real path via sdk_paper_root_path() after boot
    define('SDK_PAPER_ROOT', SDK_PAPER_BASE . '/io');
}
