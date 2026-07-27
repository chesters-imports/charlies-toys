<?php
/**
 * Station paper store — real folders under SDK_PAPER_ROOT (IO life files).
 * Not ledger / not glass. One stem = one .fk.json (head + revision history).
 */
declare(strict_types=1);

function fk_paper_root(): string {
    if (function_exists('sdk_paper_root_path')) {
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

function fk_folder_norm(string $folder): string {
    $folder = str_replace(['\\', '..'], ['/', ''], trim($folder));
    $folder = trim($folder, '/');
    if ($folder === '' || $folder === '.' || $folder === '_root') {
        return '';
    }
    // one layer only
    $folder = explode('/', $folder)[0];
    $folder = preg_replace('/[^\w\- .]+/u', '', $folder) ?? '';
    $folder = trim($folder);
    if (strlen($folder) > 80) {
        $folder = substr($folder, 0, 80);
    }
    return $folder;
}

/**
 * Narrative stem for new files — readable when chipped onto cards later.
 * e.g. FK-IO-enemies-list-EL-a3f2b1 · FK-AB-wolf-in-the-wires-WITW-c91e02
 */
function fk_stem_slug(string $title): string {
    $station = 'io';
    if (function_exists('sdk_station')) {
        $station = strtolower(sdk_station());
    }
    $station = preg_replace('/[^\w]+/', '', $station) ?: 'io';

    $title = trim($title);
    // Treat underscores / dots like spaces (sine_wave_waving.txt → real words)
    $forWords = preg_replace('/\.(txt|md|json|log)$/i', '', $title) ?? $title;
    $forWords = str_replace(['_', '.', '/'], ' ', $forWords);
    $words = preg_split('/\s+/', $forWords) ?: [];
    $initials = '';
    foreach ($words as $w) {
        if ($w === '') {
            continue;
        }
        if (preg_match('/[A-Za-z]/', $w, $m)) {
            $initials .= strtoupper($m[0]);
        } elseif (preg_match('/\d/', $w, $m)) {
            $initials .= $m[0];
        }
        if (strlen($initials) >= 6) {
            break;
        }
    }
    if ($initials === '') {
        $initials = 'X';
    }

    $s = strtolower($forWords);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? 'file';
    $s = trim($s, '-');
    if ($s === '') {
        $s = 'file';
    }
    if (strlen($s) > 36) {
        $s = rtrim(substr($s, 0, 36), '-');
    }
    $salt = substr(bin2hex(random_bytes(3)), 0, 6);
    return 'FK-' . strtoupper($station) . '-' . $s . '-' . $initials . '-' . $salt;
}

function fk_dir_for_folder(string $folder): string {
    $root = fk_paper_root();
    $folder = fk_folder_norm($folder);
    if ($folder === '') {
        return $root . '/_root';
    }
    return $root . '/' . $folder;
}

function fk_path_for_stem(string $stem, string $folder = ''): string {
    $stem = preg_replace('/[^\w\-]+/', '', $stem) ?? '';
    if ($stem === '') {
        throw new InvalidArgumentException('bad stem');
    }
    // Prefer known folder if provided; else search
    if ($folder !== '' || $folder === '') {
        $try = fk_dir_for_folder($folder) . '/' . $stem . '.fk.json';
        if (is_file($try)) {
            return $try;
        }
    }
    $found = fk_find_stem_path($stem);
    if ($found !== null) {
        return $found;
    }
    return fk_dir_for_folder($folder) . '/' . $stem . '.fk.json';
}

function fk_find_stem_path(string $stem): ?string {
    $stem = preg_replace('/[^\w\-]+/', '', $stem) ?? '';
    if ($stem === '') {
        return null;
    }
    $root = fk_paper_root();
    $name = $stem . '.fk.json';
    $direct = $root . '/_root/' . $name;
    if (is_file($direct)) {
        return $direct;
    }
    foreach (scandir($root) ?: [] as $d) {
        if ($d === '.' || $d === '..' || $d === '_root') {
            continue;
        }
        $p = $root . '/' . $d . '/' . $name;
        if (is_file($p)) {
            return $p;
        }
    }
    return null;
}

/** @return list<string> */
function fk_list_folders(): array {
    $root = fk_paper_root();
    $out = [];
    foreach (scandir($root) ?: [] as $d) {
        if ($d === '.' || $d === '..' || $d === '_root') {
            continue;
        }
        $full = $root . '/' . $d;
        if (is_dir($full) && $d[0] !== '.') {
            $out[] = $d;
        }
    }
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

/**
 * Heads for tree: each stem's latest revision as a row shaped like old ledger heads.
 * @return list<array<string,mixed>>
 */
function fk_list_heads(int $limit = 200): array {
    $root = fk_paper_root();
    $heads = [];
    $scan = static function (string $dir, string $folder) use (&$heads): void {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if (!str_ends_with($f, '.fk.json')) {
                continue;
            }
            $path = $dir . '/' . $f;
            $data = fk_read_file($path);
            if (!$data) {
                continue;
            }
            $head = $data['head'] ?? [];
            $stem = (string) ($data['stem'] ?? pathinfo($f, PATHINFO_FILENAME));
            $heads[] = [
                'c_uid' => (string) ($head['c_uid'] ?? $stem . '-r' . ($head['rev'] ?? 1)),
                'topic' => (string) ($head['title'] ?? ''),
                'body' => (string) ($head['body'] ?? ''),
                'tags_raw' => (string) ($head['tags_raw'] ?? ''),
                'event_unix' => (int) ($head['event_unix'] ?? 0),
                'ingest_unix' => (int) ($head['saved_at'] ?? 0),
                'meta_json' => json_encode([
                    'stem_c_uid' => $stem,
                    'rev' => (int) ($head['rev'] ?? 1),
                    'folder' => $folder,
                ], JSON_UNESCAPED_UNICODE),
                '_path' => $path,
                '_stem' => $stem,
                '_folder' => $folder,
            ];
        }
    };
    $scan($root . '/_root', '');
    foreach (fk_list_folders() as $folder) {
        $scan($root . '/' . $folder, $folder);
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

/** @return array<string,mixed>|null full doc */
function fk_load_stem(string $stem): ?array {
    $path = fk_find_stem_path($stem);
    if ($path === null) {
        return null;
    }
    $data = fk_read_file($path);
    if ($data) {
        $data['_path'] = $path;
    }
    return $data;
}

/**
 * @return list<array<string,mixed>> revision rows oldest→newest (ledger-shaped)
 */
function fk_revisions(string $stem): array {
    $doc = fk_load_stem($stem);
    if (!$doc) {
        return [];
    }
    $revs = $doc['revisions'] ?? [];
    if (!is_array($revs)) {
        $revs = [];
    }
    $out = [];
    foreach ($revs as $r) {
        if (!is_array($r)) {
            continue;
        }
        $revN = (int) ($r['rev'] ?? 0);
        $cUid = (string) ($r['c_uid'] ?? ($stem . '-r' . $revN));
        $out[] = [
            'c_uid' => $cUid,
            'topic' => (string) ($r['title'] ?? ''),
            'body' => (string) ($r['body'] ?? ''),
            'tags_raw' => (string) ($r['tags_raw'] ?? ''),
            'event_unix' => (int) ($r['event_unix'] ?? 0),
            'ingest_unix' => (int) ($r['saved_at'] ?? 0),
            'meta_json' => json_encode([
                'stem_c_uid' => $stem,
                'rev' => $revN,
                'folder' => (string) ($doc['folder'] ?? ''),
            ], JSON_UNESCAPED_UNICODE),
        ];
    }
    return $out;
}

/** @return array<string,mixed>|null single revision by c_uid */
function fk_get_revision(string $cUid): ?array {
    // c_uid form: {stem}-r{n} or search all
    if (preg_match('/^(.+)-r(\d+)$/', $cUid, $m)) {
        $stem = $m[1];
        $want = (int) $m[2];
        foreach (fk_revisions($stem) as $r) {
            $rm = json_decode((string) ($r['meta_json'] ?? '{}'), true) ?: [];
            if ((int) ($rm['rev'] ?? 0) === $want || $r['c_uid'] === $cUid) {
                return $r;
            }
        }
    }
    foreach (fk_list_heads(500) as $h) {
        $stem = (string) ($h['_stem'] ?? '');
        foreach (fk_revisions($stem) as $r) {
            if ($r['c_uid'] === $cUid) {
                return $r;
            }
        }
    }
    return null;
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
    $tags = trim((string) ($in['tags_raw'] ?? ''));
    $folder = fk_folder_norm((string) ($in['folder'] ?? ''));
    $stem = trim((string) ($in['stem'] ?? ''));
    $stem = preg_replace('/[^\w\-]+/', '', $stem) ?? '';
    $eventUnix = (int) ($in['event_unix'] ?? 0);
    if ($eventUnix <= 0) {
        $eventUnix = time();
    }
    $now = time();

    $existing = $stem !== '' ? fk_load_stem($stem) : null;
    if ($existing && $stem !== '') {
        $oldFolder = fk_folder_norm((string) ($existing['folder'] ?? ''));
        $revs = $existing['revisions'] ?? [];
        if (!is_array($revs)) {
            $revs = [];
        }
        $lastRev = 0;
        foreach ($revs as $r) {
            if (is_array($r)) {
                $lastRev = max($lastRev, (int) ($r['rev'] ?? 0));
            }
        }
        $newRev = $lastRev + 1;
        $cUid = $stem . '-r' . $newRev;
        $head = [
            'c_uid' => $cUid,
            'title' => $title,
            'body' => $body,
            'tags_raw' => $tags,
            'event_unix' => $eventUnix,
            'event_raw' => (string) ($in['event_raw'] ?? ''),
            'rev' => $newRev,
            'saved_at' => $now,
            'timezone' => (string) ($in['timezone'] ?? ''),
        ];
        $revs[] = $head;
        $station = function_exists('sdk_station') ? sdk_station() : (string) ($existing['station'] ?? 'io');
        $doc = [
            'stem' => $stem,
            'folder' => $folder,
            'station' => $station,
            'head' => $head,
            'revisions' => $revs,
        ];
        foreach (['legacy_stem', 'legacy_source', 'legacy_dom', 'legacy_place'] as $lk) {
            if (!empty($existing[$lk])) {
                $doc[$lk] = $existing[$lk];
            }
        }
        // move file if folder changed
        $oldPath = (string) ($existing['_path'] ?? fk_path_for_stem($stem, $oldFolder));
        $newDir = fk_dir_for_folder($folder);
        if (!is_dir($newDir)) {
            @mkdir($newDir, 0775, true);
        }
        $newPath = $newDir . '/' . $stem . '.fk.json';
        $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($newPath, $json . "\n") === false) {
            return ['ok' => false, 'error' => 'write failed'];
        }
        if ($oldPath !== $newPath && is_file($oldPath)) {
            @unlink($oldPath);
        }
        return ['ok' => true, 'stem_c_uid' => $stem, 'rev' => $newRev];
    }

    // new stem
    $stem = $stem !== '' ? $stem : fk_stem_slug($title);
    // collision
    while (fk_find_stem_path($stem) !== null) {
        $stem = fk_stem_slug($title);
    }
    $cUid = $stem . '-r1';
    $head = [
        'c_uid' => $cUid,
        'title' => $title,
        'body' => $body,
        'tags_raw' => $tags,
        'event_unix' => $eventUnix,
        'event_raw' => (string) ($in['event_raw'] ?? ''),
        'rev' => 1,
        'saved_at' => $now,
        'timezone' => (string) ($in['timezone'] ?? ''),
    ];
    $station = function_exists('sdk_station') ? sdk_station() : 'io';
    $doc = [
        'stem' => $stem,
        'folder' => $folder,
        'station' => $station,
        'head' => $head,
        'revisions' => [$head],
    ];
    $dir = fk_dir_for_folder($folder);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $path = $dir . '/' . $stem . '.fk.json';
    $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json . "\n") === false) {
        return ['ok' => false, 'error' => 'write failed'];
    }
    return ['ok' => true, 'stem_c_uid' => $stem, 'rev' => 1];
}

/**
 * Delete a stem (file) from paper.
 * @return array{ok:bool,error?:string}
 */
function fk_delete_stem(string $stem): array {
    $stem = preg_replace('/[^\w\-]+/', '', $stem) ?? '';
    if ($stem === '') {
        return ['ok' => false, 'error' => 'bad stem'];
    }
    $path = fk_find_stem_path($stem);
    if ($path === null || !is_file($path)) {
        return ['ok' => false, 'error' => 'file not found'];
    }
    if (!@unlink($path)) {
        return ['ok' => false, 'error' => 'delete failed'];
    }
    return ['ok' => true];
}

/**
 * Move stem to another folder (reorganize). Same stem id, new path.
 * @return array{ok:bool,error?:string,folder?:string}
 */
function fk_move_stem(string $stem, string $newFolder): array {
    $stem = preg_replace('/[^\w\-]+/', '', $stem) ?? '';
    if ($stem === '') {
        return ['ok' => false, 'error' => 'bad stem'];
    }
    $doc = fk_load_stem($stem);
    if (!$doc) {
        return ['ok' => false, 'error' => 'file not found'];
    }
    $newFolder = fk_folder_norm($newFolder);
    $oldPath = (string) ($doc['_path'] ?? '');
    $oldFolder = fk_folder_norm((string) ($doc['folder'] ?? ''));
    if ($oldFolder === $newFolder) {
        return ['ok' => true, 'folder' => $newFolder];
    }
    $doc['folder'] = $newFolder;
    unset($doc['_path']);
    $newDir = fk_dir_for_folder($newFolder);
    if (!is_dir($newDir)) {
        @mkdir($newDir, 0775, true);
    }
    $newPath = $newDir . '/' . $stem . '.fk.json';
    $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($newPath, $json . "\n") === false) {
        return ['ok' => false, 'error' => 'move write failed'];
    }
    if ($oldPath !== '' && $oldPath !== $newPath && is_file($oldPath)) {
        @unlink($oldPath);
    }
    return ['ok' => true, 'folder' => $newFolder];
}

/**
 * Rename a folder directory (all files inside keep stems).
 * @return array{ok:bool,error?:string,folder?:string}
 */
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
    // update folder field inside each fk.json
    foreach (scandir($toDir) ?: [] as $f) {
        if (!str_ends_with($f, '.fk.json')) {
            continue;
        }
        $path = $toDir . '/' . $f;
        $data = fk_read_file($path);
        if (!$data) {
            continue;
        }
        $data['folder'] = $to;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            file_put_contents($path, $json . "\n");
        }
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
 * Server-side loose datetime (fallback when JS unix empty).
 */
function fk_parse_event_time(string $raw, string $tz = ''): ?int {
    $raw = trim($raw);
    if ($raw === '' || preg_match('/^(now|today|n)$/i', $raw)) {
        return time();
    }
    // strip weekday noise
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
    // m/d/Y h:i am/pm
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
    // pull out pre-built HTML blocks (media figures) so we don't escape them
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

    // fenced code
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
    // Keep blank lines: markdown often smooshes; Chester paper should breathe.
    $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
    $out = [];
    $para = [];
    $flush = static function () use (&$para, &$out): void {
        if ($para) {
            // Each source line stays a visual line (hard breaks — not smooshed prose)
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
            // Each blank source line = real vertical gap (stackable)
            $out[] = '<div class="fk-gap" aria-hidden="true"></div>';
            continue;
        }
        $para[] = $line;
    }
    $flush();
    // wrap consecutive li
    $html = implode("\n", $out);
    $html = preg_replace('/(?:<li>.*?<\/li>\n?)+/s', '<ul class="fk-ul">$0</ul>', $html) ?? $html;
    return $html;
}
