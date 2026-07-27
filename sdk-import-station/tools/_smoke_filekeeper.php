<?php
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/app/filekeeper/paper_lib.php';

$r = fk_save([
    'title' => 'Smoke file',
    'body' => "# hello\n\nSam paper test.",
    'folder' => 'sam',
    'event_unix' => 1726500000,
    'event_raw' => '09/16/2025',
]);
echo "save: ";
var_export($r);
echo "\nheads=" . count(fk_list_heads()) . "\n";
echo "folders=" . implode(',', fk_list_folders()) . "\n";
echo "paper=" . SDK_PAPER_ROOT . "\n";
if (!empty($r['ok'])) {
    $revs = fk_revisions($r['stem_c_uid']);
    echo "revs=" . count($revs) . "\n";
    $r2 = fk_save([
        'title' => 'Smoke file',
        'body' => "# hello\n\nrev 2",
        'stem' => $r['stem_c_uid'],
        'folder' => 'sam',
        'event_unix' => 1726503600,
    ]);
    echo "rev2: ";
    var_export($r2);
    echo "\n";
}
