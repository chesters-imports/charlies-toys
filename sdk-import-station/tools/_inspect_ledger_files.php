<?php
$dbs = [
    'C:/Builds/my-pocket-internet/d/_LEDGER/chesters_imports.sqlite',
    'C:/Builds/my-pocket-internet/d/_LEDGER/mypi.sqlite',
];
foreach ($dbs as $db) {
    echo "=== $db ===\n";
    if (!is_file($db)) {
        echo "missing\n";
        continue;
    }
    $pdo = new PDO('sqlite:' . $db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    echo "tables: " . implode(', ', $tables) . "\n";
    foreach ($tables as $t) {
        $c = $pdo->query('SELECT COUNT(*) FROM "' . str_replace('"', '', $t) . '"')->fetchColumn();
        echo "  $t: $c\n";
    }
    // try crates
    if (in_array('crates', $tables, true)) {
        $cols = $pdo->query('PRAGMA table_info(crates)')->fetchAll(PDO::FETCH_ASSOC);
        echo "crates cols: " . implode(', ', array_column($cols, 'name')) . "\n";
        $kinds = $pdo->query('SELECT kind, COUNT(*) c FROM crates GROUP BY kind')->fetchAll(PDO::FETCH_ASSOC);
        echo "kinds: " . json_encode($kinds) . "\n";
        $tools = $pdo->query("SELECT tool, COUNT(*) c FROM crates WHERE kind='file' GROUP BY tool")->fetchAll(PDO::FETCH_ASSOC);
        echo "file tools: " . json_encode($tools) . "\n";
        $n = (int) $pdo->query("SELECT COUNT(*) FROM crates WHERE kind='file'")->fetchColumn();
        echo "file rows: $n\n";
        if ($n > 0) {
            $rows = $pdo->query("SELECT c_uid, topic, tool, substr(body,1,80) b, meta_json, tags_raw, event_unix, ingest_unix FROM crates WHERE kind='file' ORDER BY ingest_unix DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $meta = json_decode((string) ($r['meta_json'] ?? ''), true) ?: [];
                echo sprintf(
                    "  - %s | %s | stem=%s folder=%s rev=%s ev=%s\n",
                    $r['c_uid'],
                    $r['topic'],
                    $meta['stem_c_uid'] ?? '?',
                    $meta['folder'] ?? '',
                    $meta['rev'] ?? '?',
                    $r['event_unix'] ?: $r['ingest_unix']
                );
            }
        }
    }
    echo "\n";
}
