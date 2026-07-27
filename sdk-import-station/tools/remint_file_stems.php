<?php
/**
 * Remint narrative FK-{STATION}-slug-INITIALS-salt stems for existing paper files.
 * Updates stem, head/rev c_uids, renames .fk.json. Keeps legacy_stem.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/config.php';
require $root . '/app/filekeeper/paper_lib.php';

$paperBase = defined('SDK_PAPER_BASE') ? SDK_PAPER_BASE : ($root . '/paper');
$stations = ['io', 'ab', 'rx', 'icu'];

function fk_mint_stem_for(string $title, string $station, string $salt = ''): string {
    $station = preg_replace('/[^\w]+/', '', strtolower($station)) ?: 'io';
    $title = trim($title);
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
    if ($salt === '') {
        $salt = substr(bin2hex(random_bytes(3)), 0, 6);
    }
    return 'FK-' . strtoupper($station) . '-' . $s . '-' . $initials . '-' . $salt;
}

function fk_is_narrative_stem(string $stem): bool {
    return (bool) preg_match('/^FK-(IO|AB|RX|ICU)-.+/i', $stem);
}

$ok = 0;
$skip = 0;

foreach ($stations as $station) {
    $stationRoot = $paperBase . '/' . $station;
    if (!is_dir($stationRoot)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($stationRoot, FilesystemIterator::SKIP_DOTS)
    );
    $files = [];
    foreach ($it as $f) {
        if ($f->isFile() && str_ends_with($f->getFilename(), '.fk.json')) {
            $files[] = $f->getPathname();
        }
    }
    foreach ($files as $path) {
        $raw = file_get_contents($path);
        $doc = json_decode((string) $raw, true);
        if (!is_array($doc)) {
            echo "bad json $path\n";
            continue;
        }
        $oldStem = (string) ($doc['stem'] ?? pathinfo($path, PATHINFO_FILENAME));
        $title = (string) ($doc['head']['title'] ?? $oldStem);
        if ($title === '') {
            $title = $oldStem;
        }

        // Already narrative for this station? keep (unless force via env)
        $force = getenv('FK_REMINT_FORCE') === '1';
        if (!$force && fk_is_narrative_stem($oldStem)) {
            echo "keep $oldStem\n";
            $skip++;
            continue;
        }

        // stable salt from old stem hash so re-run is deterministic-ish
        $salt = substr(hash('sha256', $oldStem . '|' . $title), 0, 6);
        $newStem = fk_mint_stem_for($title, $station, $salt);
        $base = $newStem;
        $n = 0;
        $folder = fk_folder_norm((string) ($doc['folder'] ?? ''));
        $dir = ($folder === '')
            ? ($stationRoot . '/_root')
            : ($stationRoot . '/' . $folder);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $newPath = $dir . '/' . $newStem . '.fk.json';
        while (is_file($newPath) && realpath($newPath) !== realpath($path)) {
            $n++;
            $newStem = $base . '-' . $n;
            $newPath = $dir . '/' . $newStem . '.fk.json';
        }

        // rewrite c_uids in head + revisions
        $revs = $doc['revisions'] ?? [];
        if (!is_array($revs)) {
            $revs = [];
        }
        $newRevs = [];
        foreach ($revs as $r) {
            if (!is_array($r)) {
                continue;
            }
            $revN = (int) ($r['rev'] ?? 0);
            if ($revN <= 0) {
                $revN = count($newRevs) + 1;
            }
            $r['rev'] = $revN;
            $r['c_uid'] = $newStem . '-r' . $revN;
            $newRevs[] = $r;
        }
        if ($newRevs) {
            $doc['revisions'] = $newRevs;
            $doc['head'] = $newRevs[count($newRevs) - 1];
        } elseif (isset($doc['head']) && is_array($doc['head'])) {
            $revN = (int) ($doc['head']['rev'] ?? 1);
            $doc['head']['c_uid'] = $newStem . '-r' . $revN;
        }

        if (empty($doc['legacy_stem'])) {
            $doc['legacy_stem'] = $oldStem;
        }
        $doc['stem'] = $newStem;
        $doc['station'] = $station;
        $doc['legacy_dom'] = $doc['legacy_dom'] ?? $station;

        $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            echo "encode fail $oldStem\n";
            continue;
        }
        file_put_contents($newPath, $json . "\n");
        if (realpath($newPath) !== realpath($path) && is_file($path)) {
            @unlink($path);
        }
        echo "$oldStem  →  $newStem\n";
        $ok++;
    }
}

echo "\ndone reminted=$ok kept=$skip\n";
