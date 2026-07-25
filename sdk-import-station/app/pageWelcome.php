<?php
/**
 * Land / welcome — HTML body from land/welcome.html (edit that file freely).
 */
$landFile = (defined('SDK_ROOT') ? SDK_ROOT : dirname(__DIR__)) . '/land/welcome.html';
$raw = is_file($landFile)
    ? (string) file_get_contents($landFile)
    : '<p class="logimport-warn">Missing <code>land/welcome.html</code> — create it and refresh.</p>';

// Strip HTML comments so the edit-instructions block doesn't paint on screen
$raw = preg_replace('/<!--.*?-->/s', '', $raw) ?? $raw;
?>
<div class="logimport land-page">
  <?= $raw ?>
</div>
