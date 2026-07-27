<?php
/**
 * Re-stamp + rehome paper files using ledger sys/dom (terminal color).
 * AB/ICU/RX leave paper/io → paper/{station}/. IO stays.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/app/filekeeper/paper_lib.php';

$db = 'C:/Builds/my-pocket-internet/d/_LEDGER/chesters_imports.sqlite';
$pdo = new PDO('sqlite:' . $db);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// legacy_stem → station (dom)
$map = [];
$sql = "SELECT meta_json, sys, dom, room, place_path FROM crates
        WHERE kind='file' AND tool='fileKeeper'
          AND (deleted_at IS NULL OR deleted_at=0)";
foreach ($pdo->query($sql) as $r) {
    $m = json_decode((string) $r['meta_json'], true) ?: [];
    $stem = (string) ($m['stem_c_uid'] ?? '');
    if ($stem === '') {
        continue;
    }
    $dom = strtolower(trim((string) ($r['dom'] ?? 'io')));
    if (!in_array($dom, ['io', 'ab', 'rx', 'icu', 'cu', 'jx'], true)) {
        $dom = 'io';
    }
    // latest write wins
    $map[$stem] = $dom;
}

$paperBase = dirname(SDK_PAPER_ROOT); // .../paper
if (!is_dir($paperBase)) {
    $paperBase = SDK_ROOT . '/paper';
}

$rootIo = SDK_PAPER_ROOT;
$moved = 0;
$stamped = 0;
$unknown = 0;

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootIo, FilesystemIterator::SKIP_DOTS)
);

$files = [];
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.fk.json')) {
        $files[] = $f->getPathname();
    }
}

foreach ($files as $path) {
    $doc = json_decode((string) file_get_contents($path), true);
    if (!is_array($doc)) {
        continue;
    }
    $legacy = (string) ($doc['legacy_stem'] ?? '');
    $station = 'io';
    if ($legacy !== '' && isset($map[$legacy])) {
        $station = $map[$legacy];
    } else {
        // title heuristics for welcome banners if no legacy
        $title = (string) ($doc['head']['title'] ?? '');
        if (preg_match('/\bABX\b|WELCOME TO AB/i', $title)) {
            $station = 'ab';
        } elseif (preg_match('/\bICU\b|WELCOME TO ICU/i', $title)) {
            $station = 'icu';
        } elseif (preg_match('/\bRX\b|WELCOME TO RX/i', $title)) {
            $station = 'rx';
        } elseif ($legacy === '') {
            $unknown++;
        }
    }

    $doc['station'] = $station;
    $doc['legacy_dom'] = $station;
    $folder = fk_folder_norm((string) ($doc['folder'] ?? ''));
    $stem = (string) ($doc['stem'] ?? pathinfo($path, PATHINFO_FILENAME));

    $destRoot = $paperBase . '/' . $station;
    $destDir = $folder === '' ? ($destRoot . '/_root') : ($destRoot . '/' . $folder);
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0775, true);
    }
    $dest = $destDir . '/' . $stem . '.fk.json';

    $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        echo "encode fail $stem\n";
        continue;
    }
    file_put_contents($dest, $json . "\n");
    $stamped++;

    $normPath = str_replace('\\', '/', $path);
    $normDest = str_replace('\\', '/', $dest);
    if ($normPath !== $normDest) {
        @unlink($path);
        $moved++;
        echo "→ $station : " . ($doc['head']['title'] ?? $stem) . "\n";
    } else {
        echo "= $station : " . ($doc['head']['title'] ?? $stem) . "\n";
    }
}

// drop empty leftover dirs under io (optional soft)
echo "\nDONE stamped=$stamped moved_off_io_path=$moved unknown_map=$unknown\n";
echo "paper base: $paperBase\n";
foreach (['io', 'ab', 'rx', 'icu'] as $s) {
    $p = $paperBase . '/' . $s;
    $n = 0;
    if (is_dir($p)) {
        $ri = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS));
        foreach ($ri as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.fk.json')) {
                $n++;
            }
        }
    }
    echo "  paper/$s : $n files\n";
}
