<?php
require dirname(__DIR__) . '/config.php';
$_GET['station'] = 'ab';
echo 'boot=' . sdk_boot_station() . "\n";
echo 'paper=' . sdk_paper_root_path() . "\n";
require dirname(__DIR__) . '/app/filekeeper/paper_lib.php';
echo 'heads_ab=' . count(fk_list_heads(200)) . "\n";
echo 'next=' . sdk_station_next() . "\n";
$_GET['station'] = 'io';
$GLOBALS['SDK_STATION'] = 'io';
$_SESSION['sdk_station'] = 'io';
echo 'io paper=' . sdk_paper_root_path('io') . "\n";
echo 'heads_io=' . count(fk_list_heads(200)) . " (note: still active station ab until reboot)\n";
// force io listing
$GLOBALS['SDK_STATION'] = 'io';
echo 'heads_io_forced=' . count(fk_list_heads(200)) . "\n";
