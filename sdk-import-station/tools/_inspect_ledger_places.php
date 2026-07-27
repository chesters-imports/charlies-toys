<?php
$db = 'C:/Builds/my-pocket-internet/d/_LEDGER/chesters_imports.sqlite';
$pdo = new PDO('sqlite:' . $db);
$sql = "SELECT topic, sys, dom, room, mod, place_label, place_path, meta_json FROM crates WHERE kind='file' AND tool='fileKeeper' AND (deleted_at IS NULL OR deleted_at=0)";
foreach ($pdo->query($sql) as $r) {
    $m = json_decode((string) $r['meta_json'], true) ?: [];
    $stem = $m['stem_c_uid'] ?? '';
    $folder = $m['folder'] ?? '';
    echo sprintf(
        "%s | sys=%s dom=%s room=%s | folder=%s | %s\n",
        mb_substr((string) $r['topic'], 0, 36),
        $r['sys'],
        $r['dom'],
        $r['room'],
        $folder,
        $r['place_label'] ?: $r['place_path']
    );
}
