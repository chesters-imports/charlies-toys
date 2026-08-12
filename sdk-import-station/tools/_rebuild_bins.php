<?php
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/app/filekeeper/paper_lib.php';

foreach (['icu', 'jx', 'dr', 'osx', 'io', 'ab', 'cu', 'dc'] as $s) {
    $GLOBALS['FK_STATION_OVERRIDE'] = $s;
    $GLOBALS['FK_PAPER_ROOT_OVERRIDE'] = SDK_PAPER_BASE . '/' . $s;
    echo $s . ' auth=' . fk_station_auth() . ' uid=' . fk_terminal_uid() . "\n";
    fk_rebuild_terminal_bin();
    echo '  bin=' . basename(fk_bin_path()) . ' heads=' . count(fk_list_heads(0)) . "\n";
}
