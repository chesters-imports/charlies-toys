<?php
/**
 * fileKeeper · Desk — list · VIEW markdown · EDIT (station paper)
 */
declare(strict_types=1);

require_once __DIR__ . '/paper_lib.php';
require_once __DIR__ . '/media_lib.php';

$stemQ = isset($_GET['stem']) ? trim((string) $_GET['stem']) : '';
$openQ = isset($_GET['c']) ? trim((string) $_GET['c']) : '';
$wantEdit = isset($_GET['edit']) || isset($_GET['new']);
$ok = isset($_GET['fk_ok']);

$new = isset($_GET['new']) || ($stemQ === '' && $openQ === '' && !$ok);

$err = $GLOBALS['FILEKEEPER_ERROR'] ?? null;

$heads = [];
$folderNames = [];
try {
    $heads = fk_list_heads(200);
    $folderNames = fk_list_folders();
} catch (Throwable $e) {
    $err = $e->getMessage();
}

$byFolder = ['' => []];
foreach ($folderNames as $fn) {
    $byFolder[$fn] = [];
}
foreach ($heads as $h) {
    $hm = json_decode((string) ($h['meta_json'] ?? '{}'), true) ?: [];
    $f = fk_folder_norm((string) ($hm['folder'] ?? $h['_folder'] ?? ''));
    if (!isset($byFolder[$f])) {
        $byFolder[$f] = [];
    }
    $byFolder[$f][] = $h;
}

$title = '';
$body = '';
$parent = '';
$stem = '';
$rev = 1;
$revs = [];
$currentUid = '';
$eventDisplay = '';
$eventUnix = 0;
$tagsRaw = '';
$folder = isset($_GET['folder']) ? fk_folder_norm((string) $_GET['folder']) : '';

if ($openQ !== '') {
    $row = fk_get_revision($openQ);
    if ($row) {
        $meta = json_decode((string) ($row['meta_json'] ?? '{}'), true) ?: [];
        $title = (string) ($row['topic'] ?? '');
        $body = (string) ($row['body'] ?? '');
        $stem = (string) ($meta['stem_c_uid'] ?? '');
        $parent = (string) ($row['c_uid']);
        $rev = (int) ($meta['rev'] ?? 1);
        $currentUid = (string) $row['c_uid'];
        $tagsRaw = (string) ($row['tags_raw'] ?? '');
        $folder = fk_folder_norm((string) ($meta['folder'] ?? ''));
        $eventUnix = (int) (!empty($row['event_unix']) ? $row['event_unix'] : ($row['ingest_unix'] ?? 0));
        if ($eventUnix > 0) {
            $eventDisplay = date('Y-m-d H:i:s', $eventUnix);
        }
        $new = false;
        if ($stem !== '') {
            $revs = fk_revisions($stem);
        }
    }
} elseif ($stemQ !== '' || $ok) {
    $stemKey = $stemQ !== '' ? $stemQ : trim((string) ($_GET['stem'] ?? ''));
    if ($stemKey !== '') {
        $revs = fk_revisions($stemKey);
        if ($revs) {
            $row = $revs[count($revs) - 1];
            $meta = json_decode((string) ($row['meta_json'] ?? '{}'), true) ?: [];
            $title = (string) ($row['topic'] ?? '');
            $body = (string) ($row['body'] ?? '');
            $stem = (string) ($meta['stem_c_uid'] ?? $stemKey);
            $parent = (string) ($row['c_uid']);
            $rev = (int) ($meta['rev'] ?? 1);
            $currentUid = (string) $row['c_uid'];
            $tagsRaw = (string) ($row['tags_raw'] ?? '');
            $folder = fk_folder_norm((string) ($meta['folder'] ?? ''));
            $eventUnix = (int) (!empty($row['event_unix']) ? $row['event_unix'] : ($row['ingest_unix'] ?? 0));
            if ($eventUnix > 0) {
                $eventDisplay = date('Y-m-d H:i:s', $eventUnix);
            }
            $new = false;
        }
    }
}

if ($new) {
    $title = '';
    $body = '';
    $parent = '';
    $stem = '';
    $rev = 1;
    $currentUid = '';
    $eventDisplay = '';
    $eventUnix = 0;
    $tagsRaw = '';
    if (!isset($_GET['folder'])) {
        $folder = '';
    }
    $wantEdit = true;
}

$mode = 'view';
if ($new || $wantEdit) {
    $mode = 'edit';
}
if ($ok && !$wantEdit && $stem !== '') {
    $mode = 'view';
}

$self = sdk_href('files');
$editHref = sdk_href('files', ['stem' => $stem, 'edit' => '1']);
$viewHref = sdk_href('files', ['stem' => $stem]);

$rendered = '';
if ($mode === 'view' && $currentUid !== '' && $body !== '') {
    // seed known legacy sine asset if body references it
    if (preg_match_all('/media:([a-zA-Z0-9.]+)/', $body, $mm)) {
        foreach (array_unique($mm[1]) as $mid) {
            fk_media_seed_from_legacy($mid);
        }
    }
    $forView = fk_media_rewrite_body_for_view($body);
    $rendered = fk_render_md($forView);
}

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<div class="filekeeper">
  <div class="filekeeper-layout">
    <aside class="filekeeper-list" aria-label="File tree">
      <div class="fk-tree">
        <?php
        $renderFileLink = static function (array $row, string $stemActive, bool $isNew) use ($h): void {
            $hm = json_decode((string) ($row['meta_json'] ?? '{}'), true) ?: [];
            $hs = (string) ($hm['stem_c_uid'] ?? $row['_stem'] ?? $row['c_uid']);
            $on = (!$isNew && $stemActive !== '' && $hs === $stemActive);
            $when = (int) (!empty($row['event_unix']) ? $row['event_unix'] : ($row['ingest_unix'] ?? 0));
            $href = sdk_href('files', ['stem' => $hs]);
            echo '<li>';
            echo '<a href="' . $h($href) . '" class="' . ($on ? 'is-on' : '') . '">';
            echo $h($row['topic'] !== '' ? (string) $row['topic'] : 'untitled');
            echo '<span class="fk-meta">r' . (int) ($hm['rev'] ?? 1);
            echo $when ? ' · ' . date('m/d H:i', $when) : '';
            echo '</span></a></li>';
        };
        ?>

        <?php foreach ($byFolder as $fname => $files):
            if ($fname === '') {
                continue;
            }
            $openFolder = ($folder === $fname);
            if (!$openFolder && $stem !== '') {
                foreach ($files as $fh) {
                    $fhm = json_decode((string) ($fh['meta_json'] ?? '{}'), true) ?: [];
                    if (($fhm['stem_c_uid'] ?? $fh['_stem'] ?? '') === $stem) {
                        $openFolder = true;
                        break;
                    }
                }
            }
            ?>
          <details class="fk-folder" data-folder="<?= $h($fname) ?>"<?= $openFolder ? ' open' : '' ?>>
            <summary class="fk-folder-sum">
              <span class="fk-folder-chev" aria-hidden="true"></span>
              <form method="post" action="<?= $h(sdk_href('files')) ?>" class="fk-rename-folder"
                    onclick="event.stopPropagation()"
                    onmousedown="event.stopPropagation()">
                <input type="hidden" name="filekeeper_action" value="rename_folder">
                <input type="hidden" name="fk_folder_from" value="<?= $h($fname) ?>">
                <input class="fk-folder-name-input" type="text" name="fk_folder_to"
                       value="<?= $h($fname) ?>" maxlength="80" autocomplete="off"
                       aria-label="Folder name">
                <span class="fk-folder-n"><?= count($files) ?></span>
                <button type="submit" class="fk-btn fk-btn-ren" title="Rename folder">ren</button>
              </form>
            </summary>
            <ul class="fk-file-ul fk-folder-files">
              <?php if ($files): ?>
                <?php foreach ($files as $row) {
                    $renderFileLink($row, $stem, $new);
                } ?>
              <?php else: ?>
                <li class="fk-empty-folder">empty</li>
              <?php endif; ?>
            </ul>
          </details>
        <?php endforeach; ?>

        <?php if (!empty($byFolder[''])): ?>
          <ul class="fk-file-ul fk-root-files">
            <?php foreach ($byFolder[''] as $row) {
                $renderFileLink($row, $stem, $new);
            } ?>
          </ul>
        <?php endif; ?>

        <?php if (!$heads && !$folderNames): ?>
          <p class="fk-empty-hint">no files yet</p>
        <?php endif; ?>
      </div>

      <div class="fk-list-foot">
        <div class="fk-root-actions">
          <a class="fk-btn fk-btn-new<?= $new && $folder === '' ? ' is-on' : '' ?>"
             href="<?= $h(sdk_href('files', ['new' => '1'])) ?>">+ New file</a>
        </div>
        <form method="post" class="fk-mkdir" action="<?= $h(sdk_href('files')) ?>">
          <input type="hidden" name="filekeeper_action" value="mkdir">
          <input type="hidden" name="fk_tz" value="">
          <label class="fk-mkdir-label" for="fk_mkdir">+ folder</label>
          <input id="fk_mkdir" name="fk_mkdir" type="text" required maxlength="80" autocomplete="off">
          <button type="submit">make</button>
        </form>
      </div>
    </aside>

    <section class="filekeeper-panel">
      <?php if ($err): ?>
        <p class="filekeeper-status" style="color:#f66"><strong>error:</strong> <?= $h((string) $err) ?></p>
      <?php elseif ($ok): ?>
        <p class="filekeeper-status">
          <strong>saved</strong>
          · rev <?= $h((string) ($_GET['rev'] ?? '')) ?>
          <?php if (!empty($_GET['ev'])): ?>
            · event <?= $h(date('Y-m-d H:i:s', (int) $_GET['ev'])) ?>
          <?php endif; ?>
        </p>
      <?php endif; ?>

      <?php if ($mode === 'view' && !$new && $currentUid !== ''): ?>
        <article class="filekeeper-view">
          <header class="fk-view-head">
            <h2 class="fk-view-title"><?= $h($title !== '' ? $title : 'untitled') ?></h2>
            <div class="filekeeper-actions">
              <a class="fk-btn" href="<?= $h($editHref) ?>">Modify</a>
              <a class="fk-btn fk-btn-new" href="<?= $h(sdk_href('files', ['new' => '1'])) ?>">+ New file</a>
            </div>
          </header>
          <p class="fk-view-meta"><?php
            $metaBits = ['rev ' . (int) $rev, $folder !== '' ? $folder : 'root'];
            if ($eventDisplay !== '') {
                $metaBits[] = $eventDisplay;
            }
            echo $h(implode(' · ', $metaBits));
          ?></p>
          <div class="fk-view-body">
            <?php if ($rendered !== ''): ?>
              <?= $rendered ?>
            <?php else: ?>
              <p class="muted"><em>(empty)</em></p>
            <?php endif; ?>
          </div>
          <?php if (count($revs) > 1): ?>
            <div class="filekeeper-revs" aria-label="revisions">
              <span class="fk-revs-lab">REV</span>
              <?php foreach ($revs as $r):
                  $rm = json_decode((string) ($r['meta_json'] ?? '{}'), true) ?: [];
                  $rr = (int) ($rm['rev'] ?? 0);
                  $isHead = ($r['c_uid'] === $currentUid);
                  $rh = sdk_href('files', ['c' => $r['c_uid']]);
                  ?>
                <a href="<?= $h($rh) ?>"<?= $isHead ? ' class="is-on"' : '' ?>>r<?= $rr ?: '?' ?></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if ($stem !== ''): ?>
            <div class="fk-org">
              <form method="post" action="<?= $h(sdk_href('files')) ?>" class="fk-move-form">
                <input type="hidden" name="filekeeper_action" value="move">
                <input type="hidden" name="fk_stem" value="<?= $h($stem) ?>">
                <input type="hidden" name="fk_tz" class="fk-tz" value="">
                <label class="fk-attach-label" for="fk_move_folder">Move to folder</label>
                <div class="fk-attach-row">
                  <select id="fk_move_folder" name="fk_folder" class="fk-folder-select" style="flex:1 1 10rem">
                    <option value=""<?= $folder === '' ? ' selected' : '' ?>>(root)</option>
                    <?php foreach ($folderNames as $fn): ?>
                      <option value="<?= $h($fn) ?>"<?= $folder === $fn ? ' selected' : '' ?>><?= $h($fn) ?></option>
                    <?php endforeach; ?>
                    <option value="__new__">+ new folder name…</option>
                  </select>
                  <input id="fk_move_folder_new" name="fk_folder_new" type="text"
                         style="display:none;flex:1 1 8rem" maxlength="80" autocomplete="off">
                  <button type="submit" class="fk-btn">Move</button>
                </div>
              </form>
              <form method="post" action="<?= $h(sdk_href('files')) ?>" class="fk-delete-form"
                    onsubmit="return confirm('Delete this file and all revisions? This cannot be undone.');">
                <input type="hidden" name="filekeeper_action" value="delete">
                <input type="hidden" name="fk_stem" value="<?= $h($stem) ?>">
                <button type="submit" class="fk-btn fk-btn-danger">Delete file</button>
              </form>
            </div>
          <?php endif; ?>
        </article>

      <?php else: ?>
        <div class="filekeeper-editor">
          <?php if (!$new && $stem !== ''): ?>
            <p class="filekeeper-status">
              editing rev <?= (int) $rev ?> → save creates <strong>r<?= (int) $rev + 1 ?></strong>
              · <a href="<?= $h($viewHref) ?>">cancel · view</a>
            </p>
          <?php endif; ?>

          <form method="post" action="<?= $h(sdk_href('files')) ?>" class="filekeeper-form">
            <input type="hidden" name="filekeeper_action" value="save">
            <input type="hidden" name="fk_parent" value="<?= $h($parent) ?>">
            <input type="hidden" name="fk_stem" value="<?= $h($stem) ?>">
            <input type="hidden" name="fk_tz" id="fk-tz" value="">

            <label for="fk_title">Title</label>
            <input id="fk_title" name="fk_title" type="text" required
                   value="<?= $h($title) ?>"
                   autocomplete="off">

            <label for="fk_folder">Folder</label>
            <select id="fk_folder" name="fk_folder" class="fk-folder-select">
              <option value=""<?= $folder === '' ? ' selected' : '' ?>>(root)</option>
              <?php foreach ($folderNames as $fn): ?>
                <option value="<?= $h($fn) ?>"<?= $folder === $fn ? ' selected' : '' ?>>
                  <?= $h($fn) ?>
                </option>
              <?php endforeach; ?>
              <option value="__new__">+ new folder</option>
            </select>
            <input id="fk_folder_new" name="fk_folder_new" type="text" class="fk-folder-new"
                   style="display:none;margin-top:0.35rem" maxlength="80" autocomplete="off">

            <label for="fk_body">Body</label>
            <textarea id="fk_body" name="fk_body"><?= $h($body) ?></textarea>

            <label for="fk_event">Event time</label>
            <input id="fk_event" name="fk_event" type="text"
                   value="<?= $h($eventDisplay) ?>"
                   autocomplete="off">
            <input type="hidden" name="fk_event_unix" id="fk-event-unix" value="">
            <p class="filekeeper-status fk-event-preview" id="fk-event-preview" hidden></p>

            <label for="fk_tags">Tags</label>
            <input id="fk_tags" name="fk_tags" type="text"
                   value="<?= $h($tagsRaw) ?>"
                   autocomplete="off">

            <div class="filekeeper-actions">
              <button type="submit"><?= $new ? 'Create file' : 'Save revision' ?></button>
              <?php if (!$new && $stem !== ''): ?>
                <a class="fk-btn fk-btn-quiet" href="<?= $h($viewHref) ?>">Cancel</a>
              <?php endif; ?>
              <a class="fk-btn fk-btn-new" href="<?= $h(sdk_href('files', ['new' => '1'])) ?>">+ New file</a>
            </div>
          </form>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>
<script>
(function () {
  var tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
  document.querySelectorAll('#fk-tz, .fk-tz').forEach(function (el) { el.value = tz; });
})();
</script>
<?php if ($mode === 'edit'): ?>
<script>
(function () {
  var tzEl = document.getElementById('fk-tz');
  if (tzEl) tzEl.value = Intl.DateTimeFormat().resolvedOptions().timeZone;

  var input = document.getElementById('fk_event');
  var hidden = document.getElementById('fk-event-unix');
  var preview = document.getElementById('fk-event-preview');
  var form = document.querySelector('form.filekeeper-form');
  if (!input || !hidden || !preview) return;

  function scrub(s) {
    s = String(s || '').replace(/[\u200B-\u200F\u202A-\u202E\u2060-\u206F\uFEFF\u00AD]/g, '');
    s = s.replace(/[\u00A0\u202F\u2000-\u200A\u3000]/g, ' ');
    s = s.replace(/\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday|mon|tue|wed|thu|fri|sat|sun)\b[, ]*/gi, '');
    s = s.replace(/(\d)\s*([ap])\.?\s*m\.?/gi, '$1 $2m');
    s = s.replace(/\bat\b/gi, ' ');
    s = s.replace(/[,·—–]/g, ' ');
    s = s.replace(/\s+/g, ' ').trim();
    return s;
  }

  function parseLoose(s) {
    s = scrub(s);
    if (!s || /^(now|today|n)$/i.test(s)) return Math.floor(Date.now() / 1000);
    if (/^\d{9,13}$/.test(s)) {
      var n = parseInt(s, 10);
      if (n > 9999999999) n = Math.floor(n / 1000);
      return n;
    }
    var tryS = s.replace(/\b([ap])m\b/gi, function (_, a) {
      return a.toUpperCase() + 'M';
    });
    if (/^\d{4}-\d{2}-\d{2} \d{1,2}:\d{2}/.test(tryS)) {
      var msIso = Date.parse(tryS.replace(' ', 'T'));
      if (!isNaN(msIso)) return Math.floor(msIso / 1000);
    }
    var us = tryS.match(
      /^(\d{1,2})\/(\d{1,2})\/(\d{2,4})\s+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?$/i
    );
    if (us) {
      var y = parseInt(us[3], 10);
      if (y < 100) y += 2000;
      var hh = parseInt(us[4], 10);
      var ap = (us[7] || '').toUpperCase();
      if (ap === 'PM' && hh < 12) hh += 12;
      if (ap === 'AM' && hh === 12) hh = 0;
      var dUs = new Date(y, parseInt(us[1], 10) - 1, parseInt(us[2], 10), hh, parseInt(us[5], 10), us[6] ? parseInt(us[6], 10) : 0);
      if (!isNaN(dUs.getTime())) return Math.floor(dUs.getTime() / 1000);
    }
    var mon = tryS.match(
      /^([A-Za-z]+)\s+(\d{1,2})\s+(\d{4})\s+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?$/i
    );
    if (mon) {
      var dMon = new Date(
        mon[1] + ' ' + mon[2] + ', ' + mon[3] + ' ' + mon[4] + ':' + mon[5] +
        (mon[6] ? ':' + mon[6] : ':00') + (mon[7] ? ' ' + mon[7].toUpperCase() : '')
      );
      if (!isNaN(dMon.getTime())) return Math.floor(dMon.getTime() / 1000);
    }
    var ms = Date.parse(tryS);
    if (!isNaN(ms)) return Math.floor(ms / 1000);
    var d = new Date(tryS);
    if (!isNaN(d.getTime())) return Math.floor(d.getTime() / 1000);
    return null;
  }

  function paint() {
    var raw = input.value;
    if (!raw.trim()) {
      hidden.value = '';
      preview.hidden = true;
      preview.textContent = '';
      return;
    }
    var u = parseLoose(raw);
    if (u == null) {
      hidden.value = '';
      preview.hidden = true;
      preview.textContent = '';
      return;
    }
    hidden.value = String(u);
    var d = new Date(u * 1000);
    preview.hidden = false;
    preview.textContent = d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'medium' });
  }

  input.addEventListener('input', paint);
  input.addEventListener('change', paint);
  input.addEventListener('blur', paint);
  if (form) {
    form.addEventListener('submit', function () {
      paint();
      if (!input.value.trim()) {
        hidden.value = String(Math.floor(Date.now() / 1000));
      }
    });
  }
  paint();

  var foldSel = document.getElementById('fk_folder');
  var foldNew = document.getElementById('fk_folder_new');
  if (foldSel && foldNew) {
    function syncFolder() {
      var isNew = foldSel.value === '__new__';
      foldNew.style.display = isNew ? 'block' : 'none';
      if (isNew) foldNew.focus();
    }
    foldSel.addEventListener('change', syncFolder);
    syncFolder();
  }
})();
</script>
<?php endif; ?>
<?php if ($mode === 'view' && !$new && $stem !== ''): ?>
<script>
(function () {
  var moveSel = document.getElementById('fk_move_folder');
  var moveNew = document.getElementById('fk_move_folder_new');
  if (moveSel && moveNew) {
    function syncMove() {
      var isNew = moveSel.value === '__new__';
      moveNew.style.display = isNew ? 'block' : 'none';
    }
    moveSel.addEventListener('change', syncMove);
    syncMove();
  }
})();
</script>
<?php endif; ?>
<script>
/* Remember which folders are open (per station) across refresh / new file */
(function () {
  var station = "io";
  try {
    var q = new URLSearchParams(window.location.search);
    station = (q.get("station") || "io").toLowerCase();
  } catch (e) {}
  if (typeof window.sdkStation === "string" && window.sdkStation) {
    station = window.sdkStation;
  }
  var key = "fk-open-folders-" + station;

  function readOpen() {
    try {
      var raw = localStorage.getItem(key);
      var arr = raw ? JSON.parse(raw) : [];
      return Array.isArray(arr) ? arr : [];
    } catch (e) {
      return [];
    }
  }

  function writeOpen(names) {
    try {
      localStorage.setItem(key, JSON.stringify(names));
    } catch (e) {}
  }

  function collectOpen() {
    var names = [];
    document.querySelectorAll("details.fk-folder[data-folder]").forEach(function (d) {
      if (d.open) {
        var n = d.getAttribute("data-folder");
        if (n) names.push(n);
      }
    });
    return names;
  }

  function restore() {
    var saved = readOpen();
    if (!saved.length) return;
    var set = {};
    for (var i = 0; i < saved.length; i++) set[saved[i]] = true;
    document.querySelectorAll("details.fk-folder[data-folder]").forEach(function (d) {
      var n = d.getAttribute("data-folder");
      if (n && set[n]) d.open = true;
    });
  }

  function persist() {
    writeOpen(collectOpen());
  }

  restore();
  document.querySelectorAll("details.fk-folder").forEach(function (d) {
    d.addEventListener("toggle", persist);
  });
})();
</script>
