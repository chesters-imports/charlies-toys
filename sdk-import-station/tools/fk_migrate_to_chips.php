<?php
/**
 * One-way: paper station .fk.json files become leaf chips + terminal bin.
 * Archives .fk.json under paper/{station}/_fk_archive/
 *
 * Usage: php tools/fk_migrate_to_chips.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/config.php';
require $root . '/app/filekeeper/paper_lib.php';

$paperBase = SDK_PAPER_BASE;
$stations = [];
foreach (scandir($paperBase) ?: [] as $d) {
    if ($d === '.' || $d === '..' || !is_dir($paperBase . '/' . $d)) {
        continue;
    }
    if ($d[0] === '.') {
        continue;
    }
    $stations[] = $d;
}

$authMap = [
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

$totalChips = 0;
$totalArchived = 0;

foreach ($stations as $station) {
    $stationRoot = $paperBase . '/' . $station;
    $auth = $authMap[$station] ?? strtoupper($station);
    if (function_exists('sdk_station_meta') && function_exists('sdk_station_valid') && sdk_station_valid($station)) {
        $m = sdk_station_meta($station);
        if (!empty($m['chip'])) {
            $auth = strtoupper((string) $m['chip']);
        }
    }

    // pin station paper root (works even for legacy folders not in sdk_station_defs)
    $GLOBALS['FK_STATION_OVERRIDE'] = $station;
    $GLOBALS['FK_PAPER_ROOT_OVERRIDE'] = $stationRoot;
    $GLOBALS['SDK_STATION'] = $station;
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['sdk_station'] = $station;
    }

    $fkFiles = [];
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($stationRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($rii as $f) {
        /** @var SplFileInfo $f */
        if (!$f->isFile()) {
            continue;
        }
        $path = str_replace('\\', '/', $f->getPathname());
        if (str_contains($path, '/_fk_archive/')) {
            continue;
        }
        if (str_ends_with(strtolower($f->getFilename()), '.fk.json')) {
            $fkFiles[] = $path;
        }
    }

    if (!$fkFiles) {
        // still write empty-or-existing chip bin
        echo "[$station / $auth] no .fk.json — rebuild bin only\n";
        fk_rebuild_terminal_bin();
        continue;
    }

    // stable order by path
    sort($fkFiles, SORT_STRING);
    $n = 0;
    $archiveRoot = $stationRoot . '/_fk_archive';
    if (!is_dir($archiveRoot)) {
        @mkdir($archiveRoot, 0775, true);
    }

    foreach ($fkFiles as $path) {
        $doc = fk_read_file($path);
        if (!$doc) {
            echo "  skip unreadable $path\n";
            continue;
        }
        $head = is_array($doc['head'] ?? null) ? $doc['head'] : [];
        $title = trim((string) ($head['title'] ?? ''));
        if ($title === '') {
            $title = (string) ($doc['stem'] ?? pathinfo($path, PATHINFO_FILENAME));
        }
        $body = (string) ($head['body'] ?? '');
        $body = str_replace("\r\n", "\n", $body);
        $tagsRaw = trim((string) ($head['tags_raw'] ?? ''));
        $tags = [];
        if ($tagsRaw !== '') {
            foreach (preg_split('/[,;]+/', $tagsRaw) ?: [] as $t) {
                $t = trim($t);
                if ($t !== '') {
                    $tags[] = $t;
                }
            }
        }
        $created = (int) ($head['event_unix'] ?? 0);
        if ($created <= 0) {
            $created = (int) ($head['saved_at'] ?? 0);
        }
        if ($created <= 0) {
            $created = time();
        }
        $updated = (int) ($head['saved_at'] ?? $created);
        if ($updated <= 0) {
            $updated = $created;
        }

        // folder from doc or path relative to station
        $folder = fk_folder_norm((string) ($doc['folder'] ?? ''));
        $relDir = str_replace('\\', '/', dirname($path));
        $relDir = substr($relDir, strlen($stationRoot));
        $relDir = trim($relDir, '/');
        if ($folder === '' && $relDir !== '' && $relDir !== '_root') {
            $parts = explode('/', $relDir);
            $folder = fk_folder_norm($parts[0] ?? '');
        }

        $uid = 'leaf[' . $n . ']';
        $legacy = (string) ($doc['stem'] ?? pathinfo($path, PATHINFO_FILENAME));

        $w = fk_write_chip([
            'uid' => $uid,
            'name' => $title,
            'auth' => $auth,
            'tps_created' => $created,
            'tps_updated' => $updated,
            'tags' => $tags,
            'folder' => $folder,
            'legacy_stem' => $legacy,
        ], $body, $folder);

        if (empty($w['ok'])) {
            echo "  FAIL $path — " . ($w['error'] ?? '?') . "\n";
            continue;
        }

        // archive fk.json preserving relative path
        $rel = substr($path, strlen($stationRoot) + 1);
        $dest = $archiveRoot . '/' . $rel;
        $destDir = dirname($dest);
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }
        if (@rename($path, $dest)) {
            $totalArchived++;
        } else {
            @copy($path, $dest);
            @unlink($path);
            $totalArchived++;
        }
        echo "  + $uid  $title\n";
        $n++;
        $totalChips++;
    }

    fk_rebuild_terminal_bin();
    $bin = fk_bin_path();
    echo "[$station / $auth] chips=$n bin=" . basename($bin) . "\n";
}

echo "\nDONE chips=$totalChips archived_fk=$totalArchived\n";
