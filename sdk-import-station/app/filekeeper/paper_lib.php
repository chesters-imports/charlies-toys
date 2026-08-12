<?php
/**
 * Station paper store — chips + terminal.bin (Sophia-shaped).
 * Primary matter: leaf[n]-….chip · membership: terminal[{auth}]-filekeeper.bin
 * No revision history. Auth = station chip code (IOX, DRX, OSX, …).
 */
declare(strict_types=1);

function fk_paper_root(): string {
    // migration / tools may pin an arbitrary station folder
    if (!empty($GLOBALS['FK_PAPER_ROOT_OVERRIDE'])) {
        $r = rtrim(str_replace('\\', '/', (string) $GLOBALS['FK_PAPER_ROOT_OVERRIDE']), '/');
    } elseif (function_exists('sdk_paper_root_path')) {
        $r = sdk_paper_root_path();
    } else {
        $r = defined('SDK_PAPER_ROOT') ? SDK_PAPER_ROOT : (SDK_ROOT . '/paper/io');
    }
    $r = rtrim(str_replace('\\', '/', $r), '/');
    if (!is_dir($r)) {
        @mkdir($r, 0775, true);
    }
    if (!is_dir($r . '/_root')) {
        @mkdir($r . '/_root', 0775, true);
    }
    return $r;
}

/** Station folder id (io, dr, osx, …). */
function fk_station_id(): string {
    if (!empty($GLOBALS['FK_STATION_OVERRIDE'])) {
        return strtolower((string) $GLOBALS['FK_STATION_OVERRIDE']);
    }
    if (function_exists('sdk_station')) {
        return strtolower((string) sdk_station());
    }
    return 'io';
}

/** Auth / chip code on papers: IOX, DRX, OSX, DCO, ABX, CUX, … */
function fk_station_auth(?string $stationId = null): string {
    $id = $stationId !== null ? strtolower($stationId) : fk_station_id();
    $fallback = [
        'io' => 'IOX',
        'ab' => 'ABX',
        'cu' => 'CUX',
        'icu' => 'CUX',
        'dr' => 'DRX',
        'osx' => 'OSX',
        'rx' => 'OSX',
        'dc' => 'DCO',
        'jx' => 'JX',
    ];
    // only trust sdk meta when this id is a live station (invalid ids fall back to IOX in meta)
    if (
        function_exists('sdk_station_valid')
        && function_exists('sdk_station_meta')
        && sdk_station_valid($id)
    ) {
        $m = sdk_station_meta($id);
        $chip = strtoupper(trim((string) ($m['chip'] ?? '')));
        if ($chip !== '') {
            return $chip;
        }
    }
    return $fallback[$id] ?? strtoupper($id);
}

function fk_terminal_uid(?string $stationId = null): string {
    $auth = strtolower(fk_station_auth($stationId));
    return 'terminal[' . $auth . ']';
}

function fk_bin_filename(?string $stationId = null): string {
    return fk_terminal_uid($stationId) . '-filekeeper.bin';
}

function fk_bin_path(?string $stationId = null): string {
    // bin sits in station root, beside folder dirs / chips
    if ($stationId !== null && function_exists('sdk_paper_root_path')) {
        $root = rtrim(str_replace('\\', '/', sdk_paper_root_path($stationId)), '/');
    } else {
        $root = fk_paper_root();
    }
    return $root . '/' . fk_bin_filename($stationId);
}

function fk_folder_norm(string $folder): string {
    $folder = str_replace(['\\', '..'], ['/', ''], trim($folder));
    $folder = trim($folder, '/');
    if ($folder === '' || $folder === '.' || $folder === '_root') {
        return '';
    }
    $folder = explode('/', $folder)[0];
    $folder = preg_replace('/[^\w\- .]+/u', '', $folder) ?? '';
    $folder = trim($folder);
    if (strlen($folder) > 80) {
        $folder = substr($folder, 0, 80);
    }
    // reserved
    if (preg_match('/^_fk_archive|_mausoleum|^\./', $folder)) {
        return '';
    }
    return $folder;
}

function fk_dir_for_folder(string $folder): string {
    $root = fk_paper_root();
    $folder = fk_folder_norm($folder);
    if ($folder === '') {
        return $root . '/_root';
    }
    return $root . '/' . $folder;
}

function fk_title_slug(string $title): string {
    $s = strtolower(trim($title));
    $s = preg_replace('/[^a-z0-9]+/', '', $s) ?? '';
    if ($s === '') {
        $s = 'untitled';
    }
    if (strlen($s) > 40) {
        $s = substr($s, 0, 40);
    }
    return $s;
}

/** leaf[n] only */
function fk_norm_leaf_uid(string $uid): string {
    $uid = trim($uid);
    if (preg_match('/^leaf\[(\d+)\]$/i', $uid, $m)) {
        return 'leaf[' . (int) $m[1] . ']';
    }
    // legacy stem or c_uid leaf[n]-r1
    if (preg_match('/^leaf\[(\d+)\]/i', $uid, $m)) {
        return 'leaf[' . (int) $m[1] . ']';
    }
    return $uid;
}

function fk_is_leaf_uid(string $uid): bool {
    return (bool) preg_match('/^leaf\[\d+\]$/i', trim($uid));
}

/** Next free leaf[n] under current station paper root (scan all folders). */
function fk_next_leaf_uid(): string {
    $max = -1;
    foreach (fk_scan_chip_paths() as $p) {
        $base = basename($p);
        if (preg_match('/^leaf\[(\d+)\]/i', $base, $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    return 'leaf[' . ($max + 1) . ']';
}

/**
 * @return list<string> absolute paths to .chip files in station
 */
function fk_scan_chip_paths(): array {
    $root = fk_paper_root();
    $out = [];
    $scan = static function (string $dir) use (&$out, &$scan): void {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $full = $dir . '/' . $f;
            if (is_dir($full)) {
                if ($f === '_fk_archive' || $f[0] === '.') {
                    continue;
                }
                // only one layer of folders for paper (plus _root)
                if (basename($dir) === basename(fk_paper_root()) || basename($dir) === '_root' || true) {
                    // scan immediate children only for station root folders
                }
                continue;
            }
            if (str_ends_with(strtolower($f), '.chip')) {
                $out[] = str_replace('\\', '/', $full);
            }
        }
    };
    // _root chips
    if (is_dir($root . '/_root')) {
        foreach (scandir($root . '/_root') ?: [] as $f) {
            if (str_ends_with(strtolower($f), '.chip')) {
                $out[] = $root . '/_root/' . $f;
            }
        }
    }
    // chips loose in station root (rare)
    foreach (scandir($root) ?: [] as $f) {
        if (str_ends_with(strtolower($f), '.chip')) {
            $out[] = $root . '/' . $f;
        }
    }
    // folder chips
    foreach (fk_list_folders() as $folder) {
        $dir = $root . '/' . $folder;
        if (!is_dir($dir)) {
            continue;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if (str_ends_with(strtolower($f), '.chip')) {
                $out[] = $dir . '/' . $f;
            }
        }
    }
    return $out;
}

function fk_find_chip_path(string $leafUid): ?string {
    $leafUid = fk_norm_leaf_uid($leafUid);
    if (!fk_is_leaf_uid($leafUid)) {
        // try legacy: search by file stem containing old FK- name — not used for primary
        return null;
    }
    $prefix = $leafUid . '-';
    $exact = $leafUid . '.chip';
    foreach (fk_scan_chip_paths() as $p) {
        $b = basename($p);
        if ($b === $exact || str_starts_with($b, $prefix)) {
            return $p;
        }
    }
    return null;
}

function fk_chip_filename(string $leafUid, string $title): string {
    $leafUid = fk_norm_leaf_uid($leafUid);
    return $leafUid . '-' . fk_title_slug($title) . '.chip';
}

/** @return array{meta:array<string,mixed>,body:string}|null */
function fk_parse_chip_file(string $path): ?array {
    if (!is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $raw = str_replace("\r\n", "\n", $raw);
    if (!str_starts_with($raw, "---\n")) {
        return null;
    }
    $end = strpos($raw, "\n---\n", 4);
    if ($end === false) {
        // allow trailing --- only
        if (preg_match('/^---\n(.*)\n---\s*$/s', $raw, $m)) {
            $fm = $m[1];
            $body = '';
        } else {
            return null;
        }
    } else {
        $fm = substr($raw, 4, $end - 4);
        $body = substr($raw, $end + 5);
        if (str_starts_with($body, "\n")) {
            // keep as-is
        }
    }
    $meta = fk_parse_simple_fm($fm);
    return ['meta' => $meta, 'body' => (string) $body];
}

/**
 * Minimal nested FM parser (store/chip/pin blocks).
 * @return array<string,mixed>
 */
function fk_parse_simple_fm(string $fm): array {
    $lines = explode("\n", $fm);
    $root = [];
    $stack = [&$root];
    $indents = [-1];
    $pendingKey = null;
    $pendingIndent = 0;

    foreach ($lines as $line) {
        if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
            continue;
        }
        if (!preg_match('/^(\s*)([^:]+):\s*(.*)$/', $line, $m)) {
            continue;
        }
        $indent = strlen(str_replace("\t", '  ', $m[1]));
        $key = trim($m[2]);
        $val = trim($m[3]);

        while (count($indents) > 1 && $indent <= $indents[count($indents) - 1]) {
            array_pop($indents);
            array_pop($stack);
        }
        $cur = &$stack[count($stack) - 1];

        if ($val === '' || $val === '|' || $val === '>') {
            $cur[$key] = [];
            $stack[] = &$cur[$key];
            $indents[] = $indent;
            continue;
        }
        // list on same line: ["a", "b"]
        if (str_starts_with($val, '[')) {
            $decoded = json_decode($val, true);
            $cur[$key] = is_array($decoded) ? $decoded : [];
            continue;
        }
        // quoted / json string
        if (
            (str_starts_with($val, '"') && str_ends_with($val, '"'))
            || (str_starts_with($val, "'") && str_ends_with($val, "'"))
        ) {
            $decoded = json_decode($val);
            $cur[$key] = is_string($decoded) ? $decoded : trim($val, "\"'");
            continue;
        }
        if (preg_match('/^-?\d+$/', $val)) {
            $cur[$key] = (int) $val;
            continue;
        }
        $cur[$key] = $val;
    }
    return $root;
}

function fk_yaml_scalar($v): string {
    if (is_int($v) || is_float($v)) {
        return (string) $v;
    }
    if (is_bool($v)) {
        return $v ? 'true' : 'false';
    }
    $s = (string) $v;
    if ($s === '' || preg_match('/[:#\[\]{}\n\r"\']/', $s) || preg_match('/^\s|\s$/', $s)) {
        return json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return $s;
}

/**
 * @param array<string,mixed> $chip meta: uid, name, auth, tags?, folder?, tps_created?, tps_updated?
 */
function fk_format_chip_text(array $chip, string $body): string {
    $uid = fk_norm_leaf_uid((string) ($chip['uid'] ?? 'leaf[0]'));
    $name = (string) ($chip['name'] ?? 'untitled');
    $auth = (string) ($chip['auth'] ?? fk_station_auth());
    $created = (int) ($chip['tps_created'] ?? time());
    $updated = (int) ($chip['tps_updated'] ?? $created);
    $tags = $chip['tags'] ?? [];
    if (!is_array($tags)) {
        $tags = [];
    }
    $tags = array_values(array_filter(array_map('strval', $tags), static fn ($t) => $t !== ''));
    $fname = fk_chip_filename($uid, $name);
    $tagsJson = json_encode($tags, JSON_UNESCAPED_UNICODE);
    $lines = [
        '---',
        'store:',
        '  file: ' . $fname,
        'chip:',
        '  uid: ' . $uid,
        '  name: ' . fk_yaml_scalar($name),
        '  auth: ' . fk_yaml_scalar($auth),
        'pin:',
        '  tps_created: ' . $created,
        '  tps_updated: ' . $updated,
        '  tags: ' . $tagsJson,
    ];
    $folder = fk_folder_norm((string) ($chip['folder'] ?? ''));
    if ($folder !== '') {
        $lines[] = '  folder: ' . fk_yaml_scalar($folder);
    }
    if (!empty($chip['legacy_stem'])) {
        $lines[] = '  legacy_stem: ' . fk_yaml_scalar((string) $chip['legacy_stem']);
    }
    $lines[] = '---';
    $text = implode("\n", $lines) . "\n";
    if ($body !== '') {
        $text .= $body;
        if (!str_ends_with($body, "\n")) {
            $text .= "\n";
        }
    }
    return $text;
}

/**
 * @return array{ok:bool,path?:string,uid?:string,error?:string}
 */
function fk_write_chip(array $chip, string $body, string $folder = ''): array {
    $uid = fk_norm_leaf_uid((string) ($chip['uid'] ?? ''));
    if (!fk_is_leaf_uid($uid)) {
        $uid = fk_next_leaf_uid();
    }
    $chip['uid'] = $uid;
    $name = trim((string) ($chip['name'] ?? 'untitled')) ?: 'untitled';
    $chip['name'] = $name;
    $chip['auth'] = (string) ($chip['auth'] ?? fk_station_auth());
    $folder = fk_folder_norm($folder !== '' ? $folder : (string) ($chip['folder'] ?? ''));
    $chip['folder'] = $folder;
    $dir = fk_dir_for_folder($folder);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $fname = fk_chip_filename($uid, $name);
    $path = $dir . '/' . $fname;
    // remove old path for this uid if rename
    $old = fk_find_chip_path($uid);
    $text = fk_format_chip_text($chip, $body);
    if (file_put_contents($path, $text) === false) {
        return ['ok' => false, 'error' => 'write failed'];
    }
    if ($old !== null && str_replace('\\', '/', $old) !== str_replace('\\', '/', $path) && is_file($old)) {
        @unlink($old);
    }
    fk_rebuild_terminal_bin();
    return ['ok' => true, 'path' => $path, 'uid' => $uid];
}

/**
 * Chip → API head row (legacy list shape).
 * @return array<string,mixed>|null
 */
function fk_chip_to_head(string $path): ?array {
    $parsed = fk_parse_chip_file($path);
    if (!$parsed) {
        return null;
    }
    $meta = $parsed['meta'];
    $chip = is_array($meta['chip'] ?? null) ? $meta['chip'] : [];
    $pin = is_array($meta['pin'] ?? null) ? $meta['pin'] : [];
    $uid = fk_norm_leaf_uid((string) ($chip['uid'] ?? ''));
    if (!fk_is_leaf_uid($uid)) {
        return null;
    }
    $folder = fk_folder_norm((string) ($pin['folder'] ?? ''));
    // infer folder from path if missing
    if ($folder === '') {
        $parent = basename(dirname($path));
        if ($parent !== '_root' && $parent !== basename(fk_paper_root())) {
            $folder = fk_folder_norm($parent);
        }
    }
    $tags = $pin['tags'] ?? [];
    if (!is_array($tags)) {
        $tags = [];
    }
    $tagsRaw = implode(', ', array_map('strval', $tags));
    $created = (int) ($pin['tps_created'] ?? 0);
    $updated = (int) ($pin['tps_updated'] ?? $created);
    return [
        'c_uid' => $uid,
        'topic' => (string) ($chip['name'] ?? ''),
        'body' => (string) $parsed['body'],
        'tags_raw' => $tagsRaw,
        'event_unix' => $created,
        'ingest_unix' => $updated,
        'meta_json' => json_encode([
            'stem_c_uid' => $uid,
            'rev' => 1,
            'folder' => $folder,
        ], JSON_UNESCAPED_UNICODE),
        '_path' => $path,
        '_stem' => $uid,
        '_folder' => $folder,
        '_auth' => (string) ($chip['auth'] ?? ''),
    ];
}

/** @return list<string> */
function fk_list_folders(): array {
    $root = fk_paper_root();
    $out = [];
    foreach (scandir($root) ?: [] as $d) {
        if ($d === '.' || $d === '..' || $d === '_root') {
            continue;
        }
        if ($d === '_fk_archive' || str_starts_with($d, '.')) {
            continue;
        }
        $full = $root . '/' . $d;
        if (is_dir($full)) {
            $out[] = $d;
        }
    }
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

/**
 * Heads for tree (each chip once).
 * @return list<array<string,mixed>>
 */
function fk_list_heads(int $limit = 200): array {
    $heads = [];
    foreach (fk_scan_chip_paths() as $path) {
        $h = fk_chip_to_head($path);
        if ($h) {
            $heads[] = $h;
        }
    }
    usort($heads, static function ($a, $b) {
        $ea = (int) ($a['event_unix'] ?: $a['ingest_unix']);
        $eb = (int) ($b['event_unix'] ?: $b['ingest_unix']);
        return $eb <=> $ea;
    });
    if ($limit > 0 && count($heads) > $limit) {
        $heads = array_slice($heads, 0, $limit);
    }
    return $heads;
}

/** @return array<string,mixed>|null full doc shaped for old callers */
function fk_load_stem(string $stem): ?array {
    $uid = fk_norm_leaf_uid($stem);
    $path = fk_find_chip_path($uid);
    if ($path === null) {
        return null;
    }
    $h = fk_chip_to_head($path);
    if (!$h) {
        return null;
    }
    $meta = json_decode((string) ($h['meta_json'] ?? '{}'), true) ?: [];
    return [
        'stem' => $uid,
        'folder' => (string) ($meta['folder'] ?? $h['_folder'] ?? ''),
        'station' => fk_station_id(),
        'head' => [
            'c_uid' => $uid,
            'title' => $h['topic'],
            'body' => $h['body'],
            'tags_raw' => $h['tags_raw'],
            'event_unix' => $h['event_unix'],
            'saved_at' => $h['ingest_unix'],
            'rev' => 1,
        ],
        'revisions' => [],
        '_path' => $path,
        '_chip' => true,
    ];
}

/**
 * No revision history — single living chip as one "rev" for desk UI.
 * @return list<array<string,mixed>>
 */
function fk_revisions(string $stem): array {
    $doc = fk_load_stem($stem);
    if (!$doc) {
        return [];
    }
    $head = $doc['head'] ?? [];
    $folder = fk_folder_norm((string) ($doc['folder'] ?? ''));
    $uid = (string) ($doc['stem'] ?? $stem);
    return [[
        'c_uid' => $uid,
        'topic' => (string) ($head['title'] ?? ''),
        'body' => (string) ($head['body'] ?? ''),
        'tags_raw' => (string) ($head['tags_raw'] ?? ''),
        'event_unix' => (int) ($head['event_unix'] ?? 0),
        'ingest_unix' => (int) ($head['saved_at'] ?? 0),
        'meta_json' => json_encode([
            'stem_c_uid' => $uid,
            'rev' => 1,
            'folder' => $folder,
        ], JSON_UNESCAPED_UNICODE),
    ]];
}

function fk_get_revision(string $cUid): ?array {
    $uid = fk_norm_leaf_uid($cUid);
    if (!fk_is_leaf_uid($uid)) {
        // legacy {stem}-r{n}
        if (preg_match('/^(.+)-r(\d+)$/', $cUid, $m)) {
            $uid = fk_norm_leaf_uid($m[1]);
        }
    }
    $revs = fk_revisions($uid);
    return $revs[0] ?? null;
}

/**
 * @param array{title:string,body?:string,tags_raw?:string,stem?:string,folder?:string,event_unix?:int,event_raw?:string,timezone?:string} $in
 * @return array{ok:bool,stem_c_uid?:string,rev?:int,error?:string}
 */
function fk_save(array $in): array {
    $title = trim((string) ($in['title'] ?? ''));
    if ($title === '') {
        return ['ok' => false, 'error' => 'title required'];
    }
    $body = (string) ($in['body'] ?? '');
    $tagsRaw = trim((string) ($in['tags_raw'] ?? ''));
    $tags = [];
    if ($tagsRaw !== '') {
        foreach (preg_split('/[,;]+/', $tagsRaw) ?: [] as $t) {
            $t = trim($t);
            if ($t !== '') {
                $tags[] = $t;
            }
        }
    }
    $folder = fk_folder_norm((string) ($in['folder'] ?? ''));
    $stem = trim((string) ($in['stem'] ?? ''));
    $uid = $stem !== '' ? fk_norm_leaf_uid($stem) : '';
    $eventUnix = (int) ($in['event_unix'] ?? 0);
    if ($eventUnix <= 0) {
        $eventUnix = time();
    }
    $now = time();
    $created = $eventUnix;
    $existing = ($uid !== '' && fk_is_leaf_uid($uid)) ? fk_load_stem($uid) : null;
    if ($existing) {
        $created = (int) ($existing['head']['event_unix'] ?? $eventUnix);
        if ($created <= 0) {
            $created = $eventUnix;
        }
    } else {
        $uid = fk_next_leaf_uid();
    }

    $w = fk_write_chip([
        'uid' => $uid,
        'name' => $title,
        'auth' => fk_station_auth(),
        'tps_created' => $created,
        'tps_updated' => $now,
        'tags' => $tags,
        'folder' => $folder,
    ], $body, $folder);

    if (empty($w['ok'])) {
        return ['ok' => false, 'error' => $w['error'] ?? 'write failed'];
    }
    return ['ok' => true, 'stem_c_uid' => $w['uid'] ?? $uid, 'rev' => 1];
}

function fk_delete_stem(string $stem): array {
    $uid = fk_norm_leaf_uid($stem);
    $path = fk_find_chip_path($uid);
    if ($path === null || !is_file($path)) {
        return ['ok' => false, 'error' => 'file not found'];
    }
    if (!@unlink($path)) {
        return ['ok' => false, 'error' => 'delete failed'];
    }
    fk_rebuild_terminal_bin();
    return ['ok' => true];
}

function fk_move_stem(string $stem, string $newFolder): array {
    $uid = fk_norm_leaf_uid($stem);
    $doc = fk_load_stem($uid);
    if (!$doc) {
        return ['ok' => false, 'error' => 'file not found'];
    }
    $newFolder = fk_folder_norm($newFolder);
    $head = $doc['head'] ?? [];
    $tagsRaw = (string) ($head['tags_raw'] ?? '');
    $tags = [];
    if ($tagsRaw !== '') {
        foreach (preg_split('/[,;]+/', $tagsRaw) ?: [] as $t) {
            $t = trim($t);
            if ($t !== '') {
                $tags[] = $t;
            }
        }
    }
    $w = fk_write_chip([
        'uid' => $uid,
        'name' => (string) ($head['title'] ?? 'untitled'),
        'auth' => fk_station_auth(),
        'tps_created' => (int) ($head['event_unix'] ?? time()),
        'tps_updated' => time(),
        'tags' => $tags,
        'folder' => $newFolder,
    ], (string) ($head['body'] ?? ''), $newFolder);
    if (empty($w['ok'])) {
        return ['ok' => false, 'error' => $w['error'] ?? 'move failed'];
    }
    return ['ok' => true, 'folder' => $newFolder];
}

function fk_rename_folder(string $from, string $to): array {
    $from = fk_folder_norm($from);
    $to = fk_folder_norm($to);
    if ($from === '' || $to === '') {
        return ['ok' => false, 'error' => 'folder names required'];
    }
    if ($from === $to) {
        return ['ok' => true, 'folder' => $to];
    }
    $root = fk_paper_root();
    $fromDir = $root . '/' . $from;
    $toDir = $root . '/' . $to;
    if (!is_dir($fromDir)) {
        return ['ok' => false, 'error' => 'folder not found'];
    }
    if (is_dir($toDir)) {
        return ['ok' => false, 'error' => 'target folder already exists'];
    }
    if (!@rename($fromDir, $toDir)) {
        return ['ok' => false, 'error' => 'rename failed'];
    }
    foreach (scandir($toDir) ?: [] as $f) {
        if (!str_ends_with(strtolower($f), '.chip')) {
            continue;
        }
        $path = $toDir . '/' . $f;
        $parsed = fk_parse_chip_file($path);
        if (!$parsed) {
            continue;
        }
        $chip = is_array($parsed['meta']['chip'] ?? null) ? $parsed['meta']['chip'] : [];
        $pin = is_array($parsed['meta']['pin'] ?? null) ? $parsed['meta']['pin'] : [];
        fk_write_chip([
            'uid' => (string) ($chip['uid'] ?? ''),
            'name' => (string) ($chip['name'] ?? 'untitled'),
            'auth' => (string) ($chip['auth'] ?? fk_station_auth()),
            'tps_created' => (int) ($pin['tps_created'] ?? time()),
            'tps_updated' => (int) ($pin['tps_updated'] ?? time()),
            'tags' => is_array($pin['tags'] ?? null) ? $pin['tags'] : [],
            'folder' => $to,
            'legacy_stem' => (string) ($pin['legacy_stem'] ?? ''),
        ], (string) $parsed['body'], $to);
    }
    return ['ok' => true, 'folder' => $to];
}

/** @return array{ok:bool,folder?:string,error?:string} */
function fk_mkdir(string $folder): array {
    $folder = fk_folder_norm($folder);
    if ($folder === '') {
        return ['ok' => false, 'error' => 'folder name required'];
    }
    $dir = fk_paper_root() . '/' . $folder;
    if (is_dir($dir)) {
        return ['ok' => true, 'folder' => $folder];
    }
    if (!@mkdir($dir, 0775, true)) {
        return ['ok' => false, 'error' => 'mkdir failed'];
    }
    @file_put_contents($dir . '/.folderkeep', "station paper folder\n");
    return ['ok' => true, 'folder' => $folder];
}

/**
 * Rebuild terminal[auth]-filekeeper.bin from chips on this station.
 */
function fk_rebuild_terminal_bin(): void {
    $root = fk_paper_root();
    $auth = fk_station_auth();
    $uid = fk_terminal_uid();
    $members = [];
    $minCreated = 0;
    $maxUpdated = 0;
    foreach (fk_list_heads(0) as $h) {
        $id = (string) ($h['_stem'] ?? $h['c_uid'] ?? '');
        if (fk_is_leaf_uid($id)) {
            $members[] = $id;
        }
        $c = (int) ($h['event_unix'] ?? 0);
        $u = (int) ($h['ingest_unix'] ?? 0);
        if ($c > 0 && ($minCreated === 0 || $c < $minCreated)) {
            $minCreated = $c;
        }
        if ($u > $maxUpdated) {
            $maxUpdated = $u;
        }
    }
    // de-dupe preserve order
    $seen = [];
    $uniq = [];
    foreach ($members as $m) {
        if (!isset($seen[$m])) {
            $seen[$m] = true;
            $uniq[] = $m;
        }
    }
    $members = $uniq;
    $now = time();
    if ($minCreated <= 0) {
        $minCreated = $now;
    }
    if ($maxUpdated <= 0) {
        $maxUpdated = $now;
    }
    $fname = fk_bin_filename();
    $chipsJson = json_encode($members, JSON_UNESCAPED_UNICODE);
    $name = $auth . ' Files';
    $text = implode("\n", [
        '---',
        'store:',
        '  file: ' . $fname,
        '  type: bin',
        '  bit_count: ' . count($members),
        'bin:',
        '  uid: ' . $uid,
        '  type: filekeeper',
        '  name: ' . fk_yaml_scalar($name),
        '  auth: ' . fk_yaml_scalar($auth),
        '  class: terminal',
        '  chips: ' . $chipsJson,
        'pin:',
        '  tps_created: ' . $minCreated,
        '  tps_updated: ' . $maxUpdated,
        '  tags: []',
        '---',
        '',
    ]);
    // drop other terminal*.bin in root (rebrand / rename)
    foreach (scandir($root) ?: [] as $f) {
        if (preg_match('/^terminal\[.+\]-filekeeper\.bin$/i', $f) && $f !== $fname) {
            @unlink($root . '/' . $f);
        }
    }
    file_put_contents($root . '/' . $fname, $text);
}

/* ─── legacy JSON helpers (migration / archive only) ─── */

/** @return array<string,mixed>|null */
function fk_read_file(string $path): ?array {
    if (!is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * Server-side loose datetime (fallback when JS unix empty).
 */
function fk_parse_event_time(string $raw, string $tz = ''): ?int {
    $raw = trim($raw);
    if ($raw === '' || preg_match('/^(now|today|n)$/i', $raw)) {
        return time();
    }
    $s = preg_replace('/[\x{200B}-\x{200F}\x{FEFF}]/u', '', $raw) ?? $raw;
    $s = preg_replace('/\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday|mon|tue|wed|thu|fri|sat|sun)\b[, ]*/i', '', $s) ?? $s;
    $s = preg_replace('/\s+/', ' ', trim($s)) ?? $s;
    if (preg_match('/^\d{9,13}$/', $s)) {
        $n = (int) $s;
        if ($n > 9999999999) {
            $n = (int) floor($n / 1000);
        }
        return $n;
    }
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})\s+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(am|pm)?$/i', $s, $m)) {
        $y = (int) $m[3];
        if ($y < 100) {
            $y += 2000;
        }
        $hh = (int) $m[4];
        $ap = strtolower($m[7] ?? '');
        if ($ap === 'pm' && $hh < 12) {
            $hh += 12;
        }
        if ($ap === 'am' && $hh === 12) {
            $hh = 0;
        }
        $ts = mktime($hh, (int) $m[5], isset($m[6]) && $m[6] !== '' ? (int) $m[6] : 0, (int) $m[1], (int) $m[2], $y);
        return $ts !== false ? $ts : null;
    }
    $ts = strtotime($s);
    return $ts !== false ? $ts : null;
}

/** Lightweight markdown for view (no Parsedown dependency). Passes through HTML figure/img from media rewrite. */
function fk_render_md(string $body): string {
    if ($body === '') {
        return '';
    }
    $slots = [];
    $body = preg_replace_callback(
        '/<figure\b[^>]*>.*?<\/figure>/is',
        static function ($m) use (&$slots) {
            $key = '@@FKHTML' . count($slots) . '@@';
            $slots[$key] = $m[0];
            return "\n\n" . $key . "\n\n";
        },
        $body
    ) ?? $body;

    $parts = preg_split('/^```[a-zA-Z0-9]*\s*$/m', $body) ?: [$body];
    if (count($parts) > 1) {
        $html = '';
        $code = false;
        foreach ($parts as $i => $chunk) {
            if ($i === 0 && $chunk === '') {
                $code = true;
                continue;
            }
            if ($code) {
                $html .= '<pre class="fk-pre">' . htmlspecialchars($chunk, ENT_QUOTES, 'UTF-8') . '</pre>';
                $code = false;
            } else {
                $html .= fk_render_md_inline_block($chunk);
                $code = true;
            }
        }
    } else {
        $html = fk_render_md_inline_block($body);
    }
    foreach ($slots as $key => $frag) {
        $html = str_replace(htmlspecialchars($key, ENT_QUOTES, 'UTF-8'), $frag, $html);
        $html = str_replace($key, $frag, $html);
    }
    return $html;
}

function fk_render_md_inline_block(string $body): string {
    $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
    $out = [];
    $para = [];
    $flush = static function () use (&$para, &$out): void {
        if ($para) {
            $bits = [];
            foreach ($para as $pl) {
                $t = htmlspecialchars($pl, ENT_QUOTES, 'UTF-8');
                $t = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $t) ?? $t;
                $t = preg_replace('/`([^`]+)`/', '<code>$1</code>', $t) ?? $t;
                $t = preg_replace('/@@FKHTML(\d+)@@/', '@@FKHTML$1@@', $t) ?? $t;
                $bits[] = $t;
            }
            $out[] = '<p class="fk-p">' . implode("<br>\n", $bits) . '</p>';
            $para = [];
        }
    };
    foreach ($lines as $line) {
        if (preg_match('/^@@FKHTML\d+@@$/', trim($line))) {
            $flush();
            $out[] = trim($line);
            continue;
        }
        if (preg_match('/^(#{1,4})\s+(.+)$/', $line, $m)) {
            $flush();
            $n = strlen($m[1]);
            $out[] = '<h' . $n . '>' . htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8') . '</h' . $n . '>';
            continue;
        }
        if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
            $flush();
            $out[] = '<li>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</li>';
            continue;
        }
        if (preg_match('/^<!--\s*IMG SUPPORT/i', $line)) {
            $flush();
            $out[] = '<p class="fk-img-support-ok">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
            continue;
        }
        if (trim($line) === '') {
            $flush();
            $out[] = '<div class="fk-gap" aria-hidden="true"></div>';
            continue;
        }
        $para[] = $line;
    }
    $flush();
    $html = implode("\n", $out);
    $html = preg_replace('/(?:<li>.*?<\/li>\n?)+/s', '<ul class="fk-ul">$0</ul>', $html) ?? $html;
    return $html;
}
