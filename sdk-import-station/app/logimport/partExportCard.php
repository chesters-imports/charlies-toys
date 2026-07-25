<?php
/**
 * Shared export group card (exports bay + desk "already exported" gate).
 *
 * Expects:
 *   $g            — group from logimport_list_export_groups / export_group_by_face
 *   $h            — htmlspecialchars helper
 *   $importHref   — desk base URL
 *   $exportsHref  — optional; form posts here for mark complete / in progress
 *   $isFocus      — optional bool
 *   $reopenMode   — 'unlock' (default: ?reopen=1) | 'plain' (just ?face=)
 *   $returnTo     — optional POST return: 'import' after mark_in_progress
 */
if (!isset($g) || !is_array($g) || !isset($h) || !is_callable($h)) {
    return;
}
$pf = (string) ($g['parent_face'] ?? '');
$isComplete = ($g['status'] ?? '') === 'complete';
$isFocus = !empty($isFocus);
$n = (int) ($g['part_count'] ?? count($g['parts'] ?? []));
$glassName = (string) (($g['glass_title'] ?? '') !== '' ? $g['glass_title'] : ($g['display_title'] ?? 'untitled'));
$when = (int) ($g['exported_at'] ?? 0) > 0
    ? date('Y-m-d H:i', (int) $g['exported_at'])
    : '—';
$importHref = $importHref ?? (function_exists('mypi_room_href')
    ? mypi_room_href('io', 'import')
    : '/terminal/io/import');
$exportsHref = $exportsHref ?? (function_exists('mypi_room_href')
    ? mypi_room_href('io', 'exports')
    : '/terminal/io/exports');
$reopenMode = $reopenMode ?? 'unlock';
if (function_exists('sdk_href')) {
    $rq = ['face' => $pf];
    if ($reopenMode === 'unlock') {
        $rq['reopen'] = '1';
    }
    $reopenHref = sdk_href('desk', $rq);
} else {
    $reopenHref = $importHref . (str_contains($importHref, '?') ? '&' : '?') . 'face=' . rawurlencode($pf);
    if ($reopenMode === 'unlock') {
        $reopenHref .= '&reopen=1';
    }
}
$returnTo = $returnTo ?? '';
?>
        <li class="logimport-export-group<?= $isFocus ? ' is-on' : '' ?><?= $isComplete ? ' is-complete' : ' is-progress' ?>">
          <div class="logimport-export-head">
            <div class="logimport-export-head-main">
              <strong><?= $h($pf) ?></strong>
              · <?= $h($glassName) ?>
              <?php if ($n > 1): ?>
                <span class="logimport-meta"> · <?= $n ?> parts</span>
              <?php elseif ($n === 1): ?>
                <span class="logimport-meta"> · 1 part</span>
              <?php endif; ?>
              <span class="logimport-meta"> · <?= $h($when) ?></span>
              <?php
              // show first part export_id if present on group parts
              $exIdShow = '';
              if (!empty($g['parts'][0]['export_id'])) {
                  $exIdShow = (string) $g['parts'][0]['export_id'];
              } elseif (!empty($g['parts'][0]['basename'])) {
                  $exIdShow = (string) pathinfo((string) $g['parts'][0]['basename'], PATHINFO_FILENAME);
              }
              if ($exIdShow !== ''):
              ?>
                <span class="logimport-meta"> · <code><?= $h($exIdShow) ?><?= $n > 1 ? '…' : '' ?></code></span>
              <?php endif; ?>
              <span class="logimport-export-badge<?= $isComplete ? ' is-complete' : '' ?>">
                <?= $isComplete ? 'complete' : 'in progress' ?>
              </span>
            </div>
            <div class="logimport-export-actions">
              <a class="logimport-export-reopen" href="<?= $h($reopenHref) ?>">reopen log</a>
              <form method="post" class="logimport-export-status-form" action="<?= $h($exportsHref) ?>">
                <input type="hidden" name="face" value="<?= $h($pf) ?>">
                <?php if ($returnTo !== ''): ?>
                  <input type="hidden" name="return" value="<?= $h($returnTo) ?>">
                <?php endif; ?>
                <?php if ($isComplete): ?>
                  <button type="submit" name="mark_in_progress" value="1" class="logimport-cut">
                    mark in progress
                  </button>
                <?php else: ?>
                  <button type="submit" name="mark_complete" value="1" class="logimport-export">
                    mark complete
                  </button>
                <?php endif; ?>
              </form>
            </div>
          </div>

          <?php if (($g['yard_title'] ?? '') !== '' && ($g['yard_title'] ?? '') !== $glassName): ?>
            <div class="logimport-meta logimport-export-yard">
              yard: <?= $h((string) $g['yard_title']) ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($g['parts'])): ?>
          <ul class="logimport-export-parts">
            <?php foreach ($g['parts'] as $ex):
                $part = $ex['part'] ?? null;
                $pCount = (int) ($ex['part_count'] ?? $n);
                $partLabel = ($part !== null && $pCount > 1)
                    ? 'part ' . (int) $part . '/' . $pCount
                    : '';
                $pWhen = (int) ($ex['saved_at'] ?? 0) > 0
                    ? date('Y-m-d H:i', (int) $ex['saved_at'])
                    : $when;
                ?>
              <li>
                <strong><?= $h((string) $ex['face_id']) ?></strong>
                · <?= $h((string) $ex['title']) ?>
                <?php if ($partLabel !== ''): ?>
                  <span class="logimport-meta"> · <?= $h($partLabel) ?></span>
                <?php endif; ?>
                <span class="logimport-meta"> · <?= $h($pWhen) ?></span>
                <span class="logimport-meta"> · <?= $h((string) ($ex['basename'] ?? basename((string) ($ex['path'] ?? '')))) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?>
            <p class="logimport-meta">no sealed part files on disk (status only)</p>
          <?php endif; ?>
        </li>
