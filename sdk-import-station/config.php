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

// Live FILES library (Go paper). Chip paper under paper/{station} is the old desk.
define('SDK_LIBRARY_ROOT', $SDK_ROOT . '/library');

foreach ([
    SDK_WORK_ROOT,
    SDK_GLASS_DIR,
    SDK_WIP_DIR,
    SDK_EXPORTS_DIR,
    SDK_MEDIA_ROOT,
    SDK_LIBRARY_ROOT,
    SDK_LIBRARY_ROOT . '/io',
    SDK_LIBRARY_ROOT . '/ab',
    SDK_LIBRARY_ROOT . '/cu',
    SDK_LIBRARY_ROOT . '/osx',
    SDK_LIBRARY_ROOT . '/dr',
    SDK_LIBRARY_ROOT . '/dc',
    SDK_PAPER_BASE . '/io',
    SDK_PAPER_BASE . '/io/_root',
    SDK_PAPER_BASE . '/ab',
    SDK_PAPER_BASE . '/ab/_root',
    SDK_PAPER_BASE . '/cu',
    SDK_PAPER_BASE . '/cu/_root',
    SDK_PAPER_BASE . '/osx',
    SDK_PAPER_BASE . '/osx/_root',
    SDK_PAPER_BASE . '/icu',
    SDK_PAPER_BASE . '/icu/_root',
    SDK_PAPER_BASE . '/dr',
    SDK_PAPER_BASE . '/dr/_root',
    SDK_PAPER_BASE . '/dc',
    SDK_PAPER_BASE . '/dc/_root',
] as $d) {
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
}

/**
 * Live stations (order = chip cycle). IO first · AB second · then more colors.
 * @return array<string, array<string, string>>
 */
function sdk_station_defs(): array {
    return [
        'io' => [
            'id' => 'io',
            'chip' => 'IOX',
            'skin' => 'io',
            'label' => 'Terminal IO',
            'face' => 'SDK-808',
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
            'face' => 'KDE-555',
            'user' => 'KDE-555',
            'foot' => 'A·B',
            'ghost' => 'the line is listening',
            'whisper' => 'for suppression · dossier · wire-watch',
            'brand_import' => 'ABX · ON WATCH',
            'brand_files' => "K'S PAPER",
            'hint_files' => 'paper/ab',
            'hint_else' => 'leeks · agents · the line',
            'deck_chip' => "CHESTER'S IMPORTS",
            // toolkit: files + dossier (REQ-006) — not Sam's glass import/export
            'show_imports' => '0',
            'welcome' => 'ab',
        ],
        // CUX — was ICU (*I See You*). Chip name CUX; paper inherits ICU files.
        'cu' => [
            'id' => 'cu',
            'chip' => 'CUX',
            'skin' => 'cu',
            'label' => 'Terminal CUX',
            'face' => 'THE-000',
            'user' => 'THE-000',
            'foot' => 'C·U',
            'ghost' => 'i see you',
            'whisper' => 'for the show · the set · amber phosphor',
            'brand_import' => 'CUX · WATCHERS',
            'brand_files' => 'CUX PAPER',
            'hint_files' => 'paper/cu',
            'hint_else' => 'shots · cuts · the glass as set',
            'deck_chip' => "CHESTER'S IMPORTS",
            'show_imports' => '0',
            'welcome' => 'cu',
        ],
        // DRX — Dr. X / Daniel Wake · project concept desk (not log plant, not watchers)
        'dr' => [
            'id' => 'dr',
            'chip' => 'DRX',
            'skin' => 'dr',
            'label' => 'Terminal DRX',
            'face' => 'DRX-159',
            'user' => 'DRX-159',
            'foot' => 'D·R',
            'ghost' => 'the whole map, not one log',
            'whisper' => 'project thoughts · released from the yard',
            'brand_import' => 'DRX · CONCEPT',
            'brand_files' => 'DRX PAPER',
            'hint_files' => 'paper/dr',
            'hint_else' => 'dev mind · purple teal · not the glass sawmill',
            'deck_chip' => "CHESTER'S IMPORTS",
            'show_imports' => '0',
            'welcome' => 'dr',
        ],
        // OSX — Oriel · BIOS blue (was RX; she outgrew that shirt)
        // flat, almost no scanlines, sharp white — not a phosphor clone
        'osx' => [
            'id' => 'osx',
            'chip' => 'OSX',
            'skin' => 'osx',
            'label' => 'Terminal OSX',
            'face' => 'OLB-369',
            'user' => 'OLB-369',
            'foot' => 'O·S',
            'ghost' => 'read the system',
            'whisper' => 'flat blue · sharp white · she is particular',
            'brand_import' => 'OSX · BIOS',
            'brand_files' => 'ORIEL PAPER',
            'hint_files' => 'paper/osx',
            'hint_else' => 'bios blue · codes · the system',
            'deck_chip' => "CHESTER'S IMPORTS",
            'show_imports' => '0',
            'welcome' => 'osx',
        ],
        // DCO — Danny and Company · all parts · specs written here (not agent telepathy)
        'dc' => [
            'id' => 'dc',
            'chip' => 'DCO',
            'skin' => 'dc',
            'label' => 'Terminal DCO',
            'face' => 'DCO',
            'user' => 'DCO',
            'foot' => 'D·C',
            'ghost' => 'many hands · one session',
            'whisper' => 'danny and company · write it down',
            'brand_import' => 'DCO · COMPANY',
            'brand_files' => 'DCO PAPER',
            'hint_files' => 'paper/dc',
            'hint_else' => 'specs · outlines · the reins',
            'deck_chip' => "CHESTER'S IMPORTS",
            'show_imports' => '0',
            'welcome' => 'dc',
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
    if ($fromQ === 'cux' || $fromQ === 'icu') {
        // live id is cu; chip label CUX (agent rebrand — house name was ICU)
        $fromQ = 'cu';
    }
    // DRX · Doctor X · Daniel Wake (number later)
    if ($fromQ === 'drx' || $fromQ === 'drw' || $fromQ === 'dx' || $fromQ === 'wx' || $fromQ === 'doctorx') {
        $fromQ = 'dr';
    }
    // OSX · Oriel (was RX / rxx / oriel)
    if (
        $fromQ === 'osx'
        || $fromQ === 'rx'
        || $fromQ === 'rxx'
        || $fromQ === 'oriel'
        || $fromQ === 'oriole'
        || $fromQ === 'olb'
        || $fromQ === 'olb-369'
    ) {
        $fromQ = 'osx';
    }
    // DCO · Danny and Company (parts · product specs)
    if ($fromQ === 'dco' || $fromQ === 'danny' || $fromQ === 'dannyco' || $fromQ === 'd&co') {
        $fromQ = 'dc';
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
        'dossier', 'dossierdesk', 'dd', 'factions' => '/?room=dossier',
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
