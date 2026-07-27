<?php
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/app/filekeeper/paper_lib.php';

$root = fk_paper_root();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$rows = [];
foreach ($it as $f) {
    if (!$f->isFile() || !str_ends_with($f->getFilename(), '.fk.json')) {
        continue;
    }
    $d = json_decode((string) file_get_contents($f->getPathname()), true);
    if (!is_array($d)) {
        continue;
    }
    $rows[] = [
        'title' => (string) ($d['head']['title'] ?? '?'),
        'folder' => (string) ($d['folder'] ?? ''),
        'place' => (string) ($d['legacy_place'] ?? ''),
        'stem' => (string) ($d['stem'] ?? ''),
        'path' => $f->getPathname(),
    ];
}
usort($rows, static fn($a, $b) => strcmp($a['folder'] . $a['title'], $b['folder'] . $b['title']));
foreach ($rows as $r) {
    echo sprintf("%-42s | %-22s | %s\n", mb_substr($r['title'], 0, 42), $r['folder'] ?: '(root)', $r['place'] ?: '—');
}
echo "\ncount=" . count($rows) . "\n";
