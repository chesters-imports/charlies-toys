<?php
/**
 * Land / welcome — per-station HTML under land/ (edit freely).
 * IO: welcome.html · AB: welcome-ab.html · …
 */
$root = defined('SDK_ROOT') ? SDK_ROOT : dirname(__DIR__);
$which = 'io';
if (function_exists('sdk_station_meta')) {
    $which = (string) (sdk_station_meta()['welcome'] ?? sdk_station());
} elseif (function_exists('sdk_station')) {
    $which = sdk_station();
}
$candidates = match ($which) {
    'ab' => [$root . '/land/welcome-ab.html', $root . '/land/welcome.html'],
    'cu' => [$root . '/land/welcome-cu.html', $root . '/land/welcome.html'],
    'dr' => [$root . '/land/welcome-dr.html', $root . '/land/welcome.html'],
    'rx' => [$root . '/land/welcome-rx.html', $root . '/land/welcome.html'],
    'dc' => [$root . '/land/welcome-dc.html', $root . '/land/welcome.html'],
    default => [$root . '/land/welcome.html'],
};
$landFile = null;
foreach ($candidates as $c) {
    if (is_file($c)) {
        $landFile = $c;
        break;
    }
}
$raw = $landFile
    ? (string) file_get_contents($landFile)
    : '<p class="logimport-warn">Missing welcome land file for this station.</p>';

// Strip HTML comments so the edit-instructions block doesn't paint on screen
$raw = preg_replace('/<!--.*?-->/s', '', $raw) ?? $raw;
?>
<div class="logimport land-page">
  <?= $raw ?>
</div>
