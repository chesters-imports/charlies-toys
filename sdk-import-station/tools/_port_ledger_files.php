<?php
/**
 * One-shot: port fileKeeper rows from Chester ledger SQLite → station paper/io.
 * Does not touch vaults. Re-run skips stems already present (legacy_stem match).
 */
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/app/filekeeper/paper_lib.php';

$dbs = [
    'C:/Builds/my-pocket-internet/d/_LEDGER/chesters_imports.sqlite',
    'C:/Builds/my-pocket-internet/d/_LEDGER/mypi.sqlite',
];

function fk_legacy_stem_to_local(string $stem): string {
    $s = trim($stem);
    $s = str_replace('.', '-', $s);
    $s = preg_replace('/[^\w\-]+/', '', $s) ?? '';
    if ($s === '') {
        $s = 'imported-' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
    return $s;
}

/** @return array<string, list<array<string,mixed>>> stem => rows oldest first */
function load_file_stems(PDO $pdo): array {
    $hasDeleted = false;
    $cols = $pdo->query('PRAGMA table_info(crates)')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if (($c['name'] ?? '') === 'deleted_at') {
            $hasDeleted = true;
        }
    }
    $sql = "SELECT * FROM crates WHERE kind = 'file' AND tool = 'fileKeeper'";
    if ($hasDeleted) {
        $sql .= " AND (deleted_at IS NULL OR deleted_at = 0)";
    }
    $sql .= " ORDER BY ingest_unix ASC, created_at ASC";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $by = [];
    foreach ($rows as $r) {
        $meta = json_decode((string) ($r['meta_json'] ?? ''), true) ?: [];
        $stem = (string) ($meta['stem_c_uid'] ?? $r['stem_c_uid'] ?? $r['c_uid'] ?? '');
        if ($stem === '') {
            $stem = (string) $r['c_uid'];
        }
        if (!isset($by[$stem])) {
            $by[$stem] = [];
        }
        $r['_meta'] = $meta;
        $by[$stem][] = $r;
    }
    // sort each stem by rev
    foreach ($by as $stem => &$list) {
        usort($list, static function ($a, $b) {
            $ra = (int) (($a['_meta']['rev'] ?? 0));
            $rb = (int) (($b['_meta']['rev'] ?? 0));
            if ($ra === $rb) {
                return ((int) ($a['ingest_unix'] ?? 0)) <=> ((int) ($b['ingest_unix'] ?? 0));
            }
            return $ra <=> $rb;
        });
    }
    unset($list);
    return $by;
}

function already_imported(string $legacyStem): bool {
    $root = fk_paper_root();
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), '.fk.json')) {
            continue;
        }
        $data = json_decode((string) file_get_contents($file->getPathname()), true);
        if (!is_array($data)) {
            continue;
        }
        if (($data['legacy_stem'] ?? '') === $legacyStem) {
            return true;
        }
        if (($data['stem'] ?? '') === fk_legacy_stem_to_local($legacyStem)) {
            return true;
        }
    }
    return false;
}

$imported = 0;
$skipped = 0;
$errors = 0;

foreach ($dbs as $dbPath) {
    if (!is_file($dbPath)) {
        echo "skip missing $dbPath\n";
        continue;
    }
    echo "=== source: $dbPath ===\n";
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stems = load_file_stems($pdo);
    echo "stems: " . count($stems) . "\n";

    foreach ($stems as $legacyStem => $revs) {
        if (already_imported($legacyStem)) {
            echo "  skip (exists): $legacyStem\n";
            $skipped++;
            continue;
        }
        $localStem = fk_legacy_stem_to_local($legacyStem);
        // ensure unique
        while (fk_find_stem_path($localStem) !== null) {
            $localStem = fk_legacy_stem_to_local($legacyStem) . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        }

        $folder = '';
        $revRows = [];
        $n = 0;
        foreach ($revs as $r) {
            $meta = $r['_meta'] ?? [];
            $folder = fk_folder_norm((string) ($meta['folder'] ?? $folder));
            $n = max($n, (int) ($meta['rev'] ?? ($n + 1)));
            if ((int) ($meta['rev'] ?? 0) === 0) {
                $n++;
            } else {
                $n = (int) $meta['rev'];
            }
            $eventUnix = (int) (!empty($r['event_unix']) ? $r['event_unix'] : ($r['ingest_unix'] ?? time()));
            // clamp absurd future events (clock skew in old data)
            if ($eventUnix > time() + 86400 * 365) {
                $eventUnix = (int) ($r['ingest_unix'] ?? time());
            }
            $cUid = $localStem . '-r' . $n;
            $revRows[] = [
                'c_uid' => $cUid,
                'title' => (string) ($r['topic'] ?? 'untitled'),
                'body' => (string) ($r['body'] ?? ''),
                'tags_raw' => (string) ($r['tags_raw'] ?? ''),
                'event_unix' => $eventUnix,
                'event_raw' => (string) ($meta['event_raw'] ?? ''),
                'rev' => $n,
                'saved_at' => (int) ($r['ingest_unix'] ?? $r['updated_at'] ?? time()),
                'timezone' => (string) ($r['timezone'] ?? ''),
                'legacy_c_uid' => (string) ($r['c_uid'] ?? ''),
            ];
        }
        if (!$revRows) {
            $errors++;
            echo "  empty: $legacyStem\n";
            continue;
        }
        // re-number revs 1..n cleanly in order
        $clean = [];
        $i = 0;
        foreach ($revRows as $rr) {
            $i++;
            $rr['rev'] = $i;
            $rr['c_uid'] = $localStem . '-r' . $i;
            $clean[] = $rr;
        }
        $head = $clean[count($clean) - 1];
        // place hint as tag note if present
        $place = trim((string) ($revs[count($revs) - 1]['place_label'] ?? $revs[count($revs) - 1]['place_path'] ?? ''));
        $doc = [
            'stem' => $localStem,
            'folder' => $folder,
            'legacy_stem' => $legacyStem,
            'legacy_source' => $dbPath,
            'legacy_place' => $place,
            'head' => $head,
            'revisions' => $clean,
        ];
        $dir = fk_dir_for_folder($folder);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/' . $localStem . '.fk.json';
        $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($path, $json . "\n") === false) {
            echo "  WRITE FAIL: $legacyStem\n";
            $errors++;
            continue;
        }
        echo "  ok: {$head['title']} → $folder/$localStem (" . count($clean) . " revs)\n";
        $imported++;
    }
}

echo "\nDONE imported=$imported skipped=$skipped errors=$errors paper=" . SDK_PAPER_ROOT . "\n";
