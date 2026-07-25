<?php
/**
 * logImport · Exports — sealed materials.
 */
require_once __DIR__ . '/logImport_lib.php';

$groups = logimport_list_export_groups();
$importHref = function_exists('sdk_href')
    ? sdk_href('desk')
    : (function_exists('mypi_room_href') ? mypi_room_href('io', 'import') : '/?room=desk');
$exportsHref = function_exists('sdk_href')
    ? sdk_href('exports')
    : (function_exists('mypi_room_href') ? mypi_room_href('io', 'exports') : '/?room=exports');
$exportOk = isset($_GET['export_ok']);
$completeOk = isset($_GET['complete_ok']);
$progressOk = isset($_GET['progress_ok']);
$partsN = isset($_GET['parts']) ? (int) $_GET['parts'] : 0;
$focusFace = isset($_GET['face']) ? logimport_face_key((string) $_GET['face']) : '';
$err = isset($GLOBALS['LOGIMPORT_ERROR']) ? (string) $GLOBALS['LOGIMPORT_ERROR'] : '';
$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$reopenMode = 'unlock';
?>
<div class="logimport">
  <?php if ($err !== ''): ?>
    <p class="logimport-warn"><?= $h($err) ?></p>
  <?php elseif ($exportOk): ?>
    <p class="logimport-status">
      Sealed<?= $partsN > 1 ? ' · ' . (int) $partsN . ' parts' : '' ?>
    </p>
  <?php elseif ($completeOk): ?>
    <p class="logimport-status">Marked complete</p>
  <?php elseif ($progressOk): ?>
    <p class="logimport-status">Back in progress</p>
  <?php endif; ?>

  <?php if (!$groups): ?>
    <p class="logimport-meta">
      No exports yet. <a href="<?= $h($importHref) ?>">Open desk</a>
    </p>
  <?php else: ?>
    <ul class="logimport-export-groups">
      <?php foreach ($groups as $g):
          $isFocus = $focusFace !== '' && $focusFace === (string) $g['parent_face'];
          require __DIR__ . '/partExportCard.php';
      endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
