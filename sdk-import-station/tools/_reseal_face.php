<?php
/**
 * Re-seal a face to .glassbox chip format (CLI).
 * Usage: php tools/_reseal_face.php 008
 */
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/app/logimport/logImport_lib.php';

$face = $argv[1] ?? '008';
$core = logimport_core_by_face($face);
if (!$core) {
    fwrite(STDERR, "no core $face\n");
    exit(1);
}
$r = logimport_export_submit($face, $core, null);
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
if (!empty($r['paths'])) {
    foreach ($r['paths'] as $p) {
        echo "WROTE $p\n";
    }
}
exit(!empty($r['ok']) ? 0 : 1);
