<?php
/**
 * logImport · Desk — load tree-core, view, notes, hand-split, encode, redact.
 * Glass never modified. WIP → z/logs/tree_cores/wip/
 * Phase 2 Woods: privatize before Log Yard.
 */
require_once __DIR__ . '/logImport_lib.php';

$faceQ = isset($_GET['face']) ? trim((string) $_GET['face']) : '';
$wipOk = isset($_GET['wip_ok']);
$splitOk = isset($_GET['split_ok']);
$unsplitOk = isset($_GET['unsplit_ok']);
$clearOk = isset($_GET['clear_ok']);
$encOk = isset($_GET['enc_ok']);
$encAlsoOk = isset($_GET['enc_also_ok']);
$encVenOk = isset($_GET['enc_ven_ok']);
$encRm = isset($_GET['enc_rm']);
$redOk = isset($_GET['red_ok']);
$redRm = isset($_GET['red_rm']);
$applyEnc = isset($_GET['apply_enc']);
$rawEnc = isset($_GET['raw_enc']);
$applyRed = isset($_GET['apply_red']);
$rawRed = isset($_GET['raw_red']);
$completeOk = isset($_GET['complete_ok']);
$progressOk = isset($_GET['progress_ok']);
$forceReopen = isset($_GET['reopen']);
$err = $GLOBALS['LOGIMPORT_ERROR'] ?? null;

$catalog = logimport_load_catalog();
$core = $faceQ !== '' ? logimport_core_by_face($faceQ) : null;
$messages = [];
$wip = null;
$workingTitle = '';
$segments = [];
$lastSeq = -1;
$cuts = [];
$seqToSeg = [];
$encodes = [];
$redactions = [];
$doEncode = false;
$doRedact = false;

// Complete export → gate desk unless ?reopen=1
$exportGate = false;
$exportGroup = null;
if ($core) {
    $faceKeyEarly = logimport_face_key((string) $core['face_id']);
    $exportSt = logimport_export_status_load($faceKeyEarly);
    if (($exportSt['status'] ?? '') === 'complete' && !$forceReopen) {
        $exportGate = true;
        $exportGroup = logimport_export_group_by_face($faceKeyEarly);
    }
}

if ($core && !$exportGate) {
    $wip = logimport_wip_load((string) $core['face_id']);
    $workingTitle = is_array($wip) && ($wip['yard_title'] ?? '') !== ''
        ? (string) $wip['yard_title']
        : (string) ($core['title'] ?? '');
    $conv = logimport_load_conversation($core);
    if ($conv) {
        $messages = logimport_extract_messages($conv);
    } else {
        $err = $err ?: 'could not load glass (run run-catalog.bat for work/glass extracts)';
    }
    if ($messages) {
        $lastSeq = (int) $messages[count($messages) - 1]['seq'];
        $segments = logimport_segments_normalize(
            is_array($wip) ? ($wip['segments'] ?? []) : [],
            $lastSeq
        );
        $cuts = logimport_segment_cuts(
            $segments !== []
                ? $segments
                : [['from_seq' => 0, 'to_seq' => $lastSeq, 'title' => '']]
        );
        $seqToSeg = logimport_seq_to_segment($segments, $lastSeq);
    }
    $encodes = logimport_encodes_list($wip);
    $redactions = logimport_redactions_list($wip);
    $flags = logimport_view_flags($wip);
    $doEncode = $flags['apply_encode'];
    $doRedact = $flags['apply_redact'];
}

$self = htmlspecialchars(sdk_href('desk'), ENT_QUOTES, 'UTF-8');
$nCores = is_array($catalog) ? (int) ($catalog['n_cores'] ?? 0) : 0;
$nSeg = count($segments);
$faceVal = $core ? (string) $core['face_id'] : '';
$wipList = logimport_list_wips();
$importHref = function_exists('mypi_room_href')
    ? mypi_room_href('io', 'import')
    : '/terminal/io/import';
$exportsHref = function_exists('mypi_room_href')
    ? mypi_room_href('io', 'exports')
    : '/terminal/io/exports';
$h = static function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};

// message seqs that are wholly redacted (for button state)
$redMsgSeqs = [];
foreach ($redactions as $r) {
    if (($r['kind'] ?? '') === 'message') {
        $redMsgSeqs[(int) $r['seq']] = (string) $r['id'];
    }
}
?>
<div class="logimport logimport-tight">
  <div class="logimport-topbar">
    <form class="logimport-load" method="get" action="/">
      <input type="hidden" name="room" value="desk">
      <label for="li_face">#</label>
      <input id="li_face" name="face" type="text" inputmode="numeric" pattern="[0-9]*"
             value="<?= $h($faceQ !== '' ? logimport_face_key($faceQ) : '') ?>"
             placeholder="100" autocomplete="off">
      <button type="submit">load</button>
    </form>
    <?php if ($catalog): ?>
      <span class="logimport-top-meta"><?= (int) $nCores ?> cores</span>
    <?php endif; ?>
    <?php if ($core && !$exportGate): ?>
      <span class="logimport-top-meta logimport-top-core">
        <strong><?= $h($faceVal) ?></strong>
        · <?= $h((string) ($core['create_date_utc'] ?? '')) ?>
        · <?= count($messages) ?> msgs<?php if ($nSeg > 0): ?> · <?= (int) $nSeg ?> parts<?php endif; ?>
        · <?= $h((string) ($core['title'] ?? '')) ?>
      </span>
    <?php endif; ?>
  </div>

  <?php if (!$catalog): ?>
    <p class="logimport-warn">
      No catalog — run <code>run-catalog.bat</code>
    </p>
  <?php endif; ?>

  <?php
  $flash = null;
  if ($err) {
      $flash = [(string) $err, true];
  } elseif ($completeOk) {
      $flash = ['Complete'];
  } elseif ($progressOk) {
      $flash = ['In progress'];
  } elseif ($splitOk) {
      $flash = ['Cut placed'];
  } elseif ($unsplitOk) {
      $flash = ['Cut removed'];
  } elseif ($clearOk) {
      $flash = ['Cuts cleared'];
  } elseif ($encOk || $encAlsoOk) {
      $flash = ['Encode saved'];
  } elseif ($encVenOk) {
      $flash = ['Pushed to VEN'];
  } elseif ($encRm) {
      $flash = ['Encode removed'];
  } elseif ($redOk) {
      $flash = ['Redaction saved'];
  } elseif ($redRm) {
      $flash = ['Redaction removed'];
  } elseif ($applyEnc) {
      $flash = ['Aliases on'];
  } elseif ($rawEnc) {
      $flash = ['Aliases off'];
  } elseif ($applyRed) {
      $flash = ['Redactions on'];
  } elseif ($rawRed) {
      $flash = ['Redactions off'];
  } elseif ($wipOk) {
      $flash = ['WIP saved'];
  }
  if ($flash):
  ?>
    <p class="logimport-status<?= !empty($flash[1]) ? ' logimport-err' : '' ?>"><?= $h($flash[0]) ?></p>
  <?php endif; ?>

  <?php if ($core && $exportGate): ?>
    <div class="logimport-already">
      <h2 class="logimport-already-h">Already exported</h2>
      <p class="logimport-meta">
        <strong><?= $h($faceVal) ?></strong> is complete — reopen or mark in progress to edit.
      </p>
      <ul class="logimport-export-groups">
        <?php
        $g = is_array($exportGroup) ? $exportGroup : [
            'parent_face' => $faceVal,
            'glass_title' => (string) ($core['title'] ?? ''),
            'yard_title' => '',
            'display_title' => (string) ($core['title'] ?? 'untitled'),
            'part_count' => 0,
            'exported_at' => 0,
            'status' => 'complete',
            'completed_at' => null,
            'parts' => [],
        ];
        $isFocus = true;
        $reopenMode = 'unlock';
        $returnTo = 'import';
        require __DIR__ . '/partExportCard.php';
        ?>
      </ul>
    </div>

  <?php elseif ($core): ?>
    <?php if ($forceReopen): ?>
      <p class="logimport-status logimport-reopen-banner">Reopened</p>
    <?php endif; ?>

    <form method="post" action="" id="li_main" class="logimport-desk">
      <input type="hidden" name="face" value="<?= $h($faceVal) ?>">

      <?php /* Left: document. Right: sticky alteration rail (always in view). */ ?>
      <div class="logimport-desk-grid">
        <div class="logimport-doc">
          <div class="logimport-title-row">
            <label class="logimport-title-lab" for="li_title">title</label>
            <input class="logimport-title" id="li_title" name="yard_title" type="text"
                   value="<?= $h($workingTitle) ?>">
          </div>

          <div class="logimport-thread" id="li_thread">
            <?php if (!$messages): ?>
              <p class="logimport-meta">No messages in this core.</p>
            <?php else: ?>
              <div class="logimport-asst-toolbar" id="li_asst_toolbar">
                <span class="logimport-meta">assistants collapsed · your words first</span>
                <button type="button" class="logimport-cut" id="li_asst_expand_all">expand all asst</button>
                <button type="button" class="logimport-cut" id="li_asst_collapse_all">collapse all asst</button>
              </div>
              <?php
              $prevSeg = -1;
              foreach ($messages as $m):
                  $seq = (int) $m['seq'];
                  $si = $seqToSeg[$seq] ?? 0;
                  if ($nSeg > 0 && $si !== $prevSeg):
                      $prevSeg = $si;
                      $segTitle = trim((string) ($segments[$si]['title'] ?? ''));
                      ?>
                <div class="logimport-seg-head" id="li-seghead-<?= (int) $si ?>">
                  <span class="logimport-seg-scissors" aria-hidden="true">✂</span>
                  <input type="text"
                         name="seg_title[<?= (int) $si ?>]"
                         class="logimport-seg-title"
                         id="li-seg-<?= (int) $si ?>"
                         value="<?= $h($segTitle) ?>"
                         placeholder="name this part"
                         autocomplete="off">
                  <span class="logimport-seg-range">#<?= (int) $segments[$si]['from_seq'] ?>–#<?= (int) $segments[$si]['to_seq'] ?></span>
                </div>
                      <?php
                  endif;
                  $tx = logimport_transform_text((string) $m['text'], $seq, $wip, $doRedact, $doEncode, true);
                  $cls = 'logimport-msg role-' . $h($m['role']);
                  if (!empty($tx['encode_marked'])) {
                      $cls .= ' has-enc';
                  }
                  if (!empty($tx['wholly_redacted'])) {
                      $cls .= ' is-redacted';
                  }
                  ?>
                <?php
                $ctF = $m['create_time'] ?? null;
                $ctF = is_numeric($ctF) ? (float) $ctF : null;
                $ctShow = '';
                if ($ctF !== null && $ctF > 0) {
                    $ctShow = date('Y-m-d H:i', (int) floor($ctF));
                    // local wall clock is fine for split sense; full unix in title
                    $ctTitle = gmdate('c', (int) floor($ctF)) . ' UTC';
                } else {
                    $ctTitle = '';
                }
                ?>
                <?php
                $isAsst = ($m['role'] ?? '') === 'assistant';
                $rawBody = (string) ($tx['text'] ?? '');
                $peek = '';
                if ($isAsst) {
                    $one = preg_replace('/\s+/', ' ', trim(strip_tags($rawBody))) ?? '';
                    if (strlen($one) > 96) {
                        $peek = substr($one, 0, 96) . '…';
                    } else {
                        $peek = $one;
                    }
                }
                ?>
                <div class="<?= $cls ?><?= $isAsst ? ' is-asst-collapsed' : '' ?>" id="li-msg-<?= $seq ?>"
                     data-role="<?= $h((string) $m['role']) ?>">
                  <?php if ($isAsst): ?>
                  <button type="button" class="li-role li-asst-toggle" aria-expanded="false"
                          data-asst-toggle="1" id="li-asst-h-<?= $seq ?>">
                    <span class="li-role-name">assistant</span>
                    <span class="li-seq">#<?= $seq ?></span>
                    <?php if ($ctShow !== ''): ?>
                      <span class="li-time"><?= $h($ctShow) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($tx['hit_count'])): ?>
                      <span class="li-enc-count"><?= (int) $tx['hit_count'] ?> enc</span>
                    <?php endif; ?>
                    <span class="li-asst-chev" aria-hidden="true">▸</span>
                    <?php if ($peek !== ''): ?>
                      <span class="li-asst-peek"><?= $h($peek) ?></span>
                    <?php endif; ?>
                  </button>
                  <div class="li-asst-panel" id="li-asst-p-<?= $seq ?>" hidden>
                  <?php else: ?>
                  <div class="li-role">
                    <span class="li-role-name"><?= $h($m['role']) ?></span>
                    <span class="li-seq">#<?= $seq ?></span>
                    <?php if ($ctShow !== ''): ?>
                      <span class="li-time"><?= $h($ctShow) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($tx['hit_count'])): ?>
                      <span class="li-enc-count"><?= (int) $tx['hit_count'] ?> enc</span>
                    <?php endif; ?>
                  </div>
                  <?php endif; ?>
                  <?php if (!empty($tx['encode_marked'])): ?>
                    <pre class="li-body li-body-enc"><?= logimport_format_marked_html((string) $tx['text']) ?></pre>
                  <?php else: ?>
                    <pre class="li-body"><?= $h($tx['text']) ?></pre>
                  <?php endif; ?>
                  <div class="logimport-cut-row">
                    <?php if (isset($redMsgSeqs[$seq])): ?>
                      <button type="submit" name="remove_redact" value="<?= $h($redMsgSeqs[$seq]) ?>" class="logimport-cut is-cut">
                        unredact msg #<?= $seq ?>
                      </button>
                    <?php else: ?>
                      <button type="submit" name="add_redact_msg" value="<?= $seq ?>" class="logimport-cut">
                        redact msg #<?= $seq ?>
                      </button>
                    <?php endif; ?>
                    <?php if ($seq < $lastSeq): ?>
                      <?php if (in_array($seq, $cuts, true)): ?>
                        <button type="submit" name="unsplit_after" value="<?= $seq ?>" class="logimport-cut is-cut">
                          unsplit after #<?= $seq ?>
                        </button>
                      <?php else: ?>
                        <button type="submit" name="split_after" value="<?= $seq ?>" class="logimport-cut">
                          split after #<?= $seq ?>
                        </button>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                  <?php if ($isAsst): ?>
                  </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <label class="logimport-meta" for="li_notes">notes</label>
          <textarea class="logimport-notes" id="li_notes" name="notes" placeholder="notes…"><?= $h((string) ($wip['notes'] ?? '')) ?></textarea>

          <div class="logimport-actions">
            <button type="submit" name="logimport_action" value="save_wip">save wip</button>
            <?php if ($nSeg > 0): ?>
              <button type="submit" name="clear_splits" value="1" class="logimport-cut">clear all cuts</button>
            <?php endif; ?>
            <button type="submit" name="submit_export" value="1" class="logimport-export">
              seal export<?= $nSeg > 1 ? ' · ' . (int) $nSeg . ' parts' : '' ?>
            </button>
          </div>
        </div>

        <aside class="logimport-rail" aria-label="Encode and redact">
          <div class="logimport-rail-inner">
            <div class="logimport-rail-apply" id="li-apply">
              <span class="logimport-rail-label">view</span>
              <div class="logimport-apply-row">
                <?php if ($doEncode): ?>
                  <button type="submit" name="clear_apply_encode" value="1" class="logimport-cut is-cut">show originals</button>
                <?php else: ?>
                  <button type="submit" name="apply_encode" value="1"<?= $encodes ? '' : ' disabled' ?>>Apply aliases</button>
                <?php endif; ?>
                <?php if ($doRedact): ?>
                  <button type="submit" name="clear_apply_redact" value="1" class="logimport-cut is-cut">show unbarred</button>
                <?php else: ?>
                  <button type="submit" name="apply_redact" value="1"<?= $redactions ? '' : ' disabled' ?>>Apply redactions</button>
                <?php endif; ?>
              </div>
            </div>

            <section class="logimport-panel" id="li-encode">
              <h3 class="logimport-subh">Encode</h3>
              <div class="logimport-enc-form logimport-enc-form-stack">
                <input type="text" name="enc_original" placeholder="original" autocomplete="off">
                <input type="text" name="enc_alias" placeholder="alias" autocomplete="off">
                <input type="text" name="enc_code" placeholder="code (optional)" autocomplete="off" class="logimport-code">
                <input type="text" name="enc_also" placeholder="also spelled…" autocomplete="off" class="logimport-also">
                <button type="submit" name="add_encode" value="1">+ encode</button>
              </div>
              <?php if ($encodes): ?>
                <ul class="logimport-book">
                  <?php foreach ($encodes as $e):
                      $also = is_array($e['also'] ?? null) ? $e['also'] : [];
                      $eid = (string) $e['id'];
                      $eidH = $h($eid);
                      $alsoKey = logimport_encode_form_key($eid);
                      ?>
                    <li class="logimport-enc-row">
                      <div class="logimport-enc-main">
                        <code class="li-code"><?= $h($e['code']) ?></code>
                        <span class="li-alias"><?= $h($e['alias']) ?></span>
                        <span class="li-arrow">←</span>
                        <span class="li-orig muted"><?= $h($e['original']) ?></span>
                        <?php if ($also): ?>
                          <span class="logimport-meta"> · also <?= $h(implode(', ', $also)) ?></span>
                        <?php endif; ?>
                      </div>
                      <div class="logimport-enc-actions">
                        <input type="text" name="enc_also_<?= $h($alsoKey) ?>"
                               placeholder="+ also" class="logimport-also-inline" autocomplete="off">
                        <button type="submit" name="add_encode_also" value="<?= $eidH ?>">add also</button>
                        <button type="submit" name="push_encode_ven" value="<?= $eidH ?>" title="push to VEN (if available)">→ VEN</button>
                        <button type="submit" name="remove_encode" value="<?= $eidH ?>" class="logimport-cut">remove</button>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </section>

            <section class="logimport-panel" id="li-redact">
              <h3 class="logimport-subh">Redact</h3>
              <div class="logimport-enc-form logimport-enc-form-stack">
                <input type="text" name="red_original" placeholder="phrase to bar" autocomplete="off">
                <input type="text" name="red_label" placeholder="label (optional)" autocomplete="off" class="logimport-code">
                <button type="submit" name="add_redact_phrase" value="1">+ redact</button>
              </div>
              <?php if ($redactions): ?>
                <ul class="logimport-book">
                  <?php foreach ($redactions as $r): ?>
                    <li>
                      <?php if (($r['kind'] ?? '') === 'message'): ?>
                        <code class="li-code">MSG</code>
                        <span class="li-alias">#<?= (int) $r['seq'] ?></span>
                      <?php else: ?>
                        <code class="li-code">█</code>
                        <span class="li-orig"><?= $h((string) ($r['original'] ?? '')) ?></span>
                        <?php if (!empty($r['label'])): ?>
                          <span class="muted">(<?= $h((string) $r['label']) ?>)</span>
                        <?php endif; ?>
                      <?php endif; ?>
                      <button type="submit" name="remove_redact" value="<?= $h($r['id']) ?>" class="logimport-cut">remove</button>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </section>
          </div>
        </aside>
      </div>
    </form>

  <?php elseif ($faceQ !== ''): ?>
    <p class="logimport-warn">No core #<?= $h(logimport_face_key($faceQ)) ?></p>
  <?php endif; ?>
</div>
<?php if ($core && $messages): ?>
<script>
(function () {
  function setAsstOpen(wrap, open) {
    if (!wrap) return;
    var btn = wrap.querySelector("[data-asst-toggle]");
    var panel = wrap.querySelector(".li-asst-panel");
    if (!btn || !panel) return;
    wrap.classList.toggle("is-asst-collapsed", !open);
    wrap.classList.toggle("is-asst-open", !!open);
    btn.setAttribute("aria-expanded", open ? "true" : "false");
    panel.hidden = !open;
  }

  function toggleAsst(wrap) {
    var open = wrap.classList.contains("is-asst-open");
    setAsstOpen(wrap, !open);
  }

  document.querySelectorAll(".logimport-msg.role-assistant").forEach(function (wrap) {
    var btn = wrap.querySelector("[data-asst-toggle]");
    if (!btn) return;
    // default collapsed
    setAsstOpen(wrap, false);
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      toggleAsst(wrap);
    });
  });

  var ex = document.getElementById("li_asst_expand_all");
  var col = document.getElementById("li_asst_collapse_all");
  if (ex) {
    ex.addEventListener("click", function () {
      document.querySelectorAll(".logimport-msg.role-assistant").forEach(function (w) {
        setAsstOpen(w, true);
      });
    });
  }
  if (col) {
    col.addEventListener("click", function () {
      document.querySelectorAll(".logimport-msg.role-assistant").forEach(function (w) {
        setAsstOpen(w, false);
      });
    });
  }

  if (location.hash && location.hash.indexOf("li-msg-") === 1) {
    var el = document.getElementById(location.hash.slice(1));
    if (el) {
      if (el.classList.contains("role-assistant")) {
        setAsstOpen(el, true);
      }
      var thread = document.getElementById("li_thread");
      if (thread && thread.contains(el)) {
        var top = el.offsetTop - thread.offsetTop - 24;
        thread.scrollTop = Math.max(0, top);
      } else {
        el.scrollIntoView({ block: "center" });
      }
    }
  }
  if (location.hash === "#li-encode" || location.hash === "#li-redact") {
    var p = document.getElementById(location.hash.slice(1));
    if (p) p.scrollIntoView({ block: "nearest" });
  }
})();
</script>
<?php endif; ?>
