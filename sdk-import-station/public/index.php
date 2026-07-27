<?php
/**
 * sdk-import-station — IOX shell (green station look, not pocket internet).
 * Rooms: welcome | files | mail | desk | active | search | exports
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/logimport/logImport_lib.php';

// Active station (io first · ab second) — cookie/session + ?station=
$stationId = sdk_boot_station();
$st = sdk_station_meta($stationId);

// Default land = welcome. But face / desk flash query params imply desk
// (GET load form only posts `face` and used to wipe ?room=desk).
// fileKeeper stem/new/edit → files room.
$room = isset($_GET['room']) ? strtolower(trim((string) $_GET['room'])) : '';
if ($room === '' || in_array($room, ['land', 'home'], true)) {
    $fileHints = ['stem', 'fk_ok', 'fk_mkdir_ok', 'new', 'edit'];
    $wantsFiles = false;
    foreach ($fileHints as $k) {
        if (isset($_GET[$k]) && (string) $_GET[$k] !== '') {
            // `new` alone can be ambiguous; only if filekeeper-ish
            if ($k === 'new' && !isset($_GET['folder']) && !isset($_GET['stem'])) {
                // still allow ?new=1 without room after POST redirect to files
            }
            $wantsFiles = true;
            break;
        }
    }
    // c= is revision open for files
    if (isset($_GET['c']) && (string) $_GET['c'] !== '') {
        $wantsFiles = true;
    }
    $deskHints = ['face', 'wip_ok', 'split_ok', 'unsplit_ok', 'clear_ok', 'enc_ok',
        'enc_also_ok', 'enc_ven_ok', 'enc_rm', 'red_ok', 'red_rm', 'apply_enc',
        'raw_enc', 'apply_red', 'raw_red', 'complete_ok', 'progress_ok', 'reopen'];
    $wantsDesk = false;
    foreach ($deskHints as $k) {
        if (isset($_GET[$k]) && (string) $_GET[$k] !== '') {
            $wantsDesk = true;
            break;
        }
    }
    if ($wantsFiles && !$wantsDesk) {
        $room = 'files';
    } elseif ($wantsDesk) {
        $room = 'desk';
    } else {
        $room = ($room === '' || $room === 'land' || $room === 'home') ? 'welcome' : $room;
    }
}
if (in_array($room, ['file', 'filekeeper', 'fk'], true)) {
    $room = 'files';
}
if (in_array($room, ['email', 'inbox'], true)) {
    $room = 'mail';
}
if (!in_array($room, ['welcome', 'files', 'mail', 'desk', 'active', 'exports', 'import', 'search', 'find', 'hunt'], true)) {
    $room = 'welcome';
}
if ($room === 'import') {
    $room = 'desk';
}
if (in_array($room, ['find', 'hunt'], true)) {
    $room = 'search';
}

// AB (show_imports=0): no glass import/export toolkit — Sam's desk only on IO
$showImports = (($st['show_imports'] ?? '1') === '1');
if (!$showImports && in_array($room, ['desk', 'active', 'exports', 'search'], true)) {
    $room = 'files';
}

$li = dirname(__DIR__) . '/app/logimport';
$fk = dirname(__DIR__) . '/app/filekeeper';
$app = dirname(__DIR__) . '/app';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $room !== 'welcome') {
    $isFileKeeper = isset($_POST['filekeeper_action'])
        || $room === 'files';
    $isMail = isset($_POST['mail_action']) || $room === 'mail';
    $isExportStatus = isset($_POST['mark_complete'])
        || isset($_POST['mark_in_progress'])
        || (isset($_POST['logimport_export_action']) && (string) $_POST['logimport_export_action'] !== '');
    if ($isMail) {
        require $app . '/mail/actorMail.php';
        // actor redirects
        $room = 'mail';
    } elseif ($isFileKeeper) {
        require $fk . '/actorDesk.php';
        // if actor set error without redirect, stay on files
        $room = 'files';
    } elseif ($showImports && ($isExportStatus || $room === 'exports')) {
        require $li . '/actorExports.php';
    } elseif ($showImports) {
        require $li . '/actorDesk.php';
    } else {
        // AB: ignore import POSTs
        $room = 'files';
    }
}

ob_start();
if ($room === 'welcome') {
    require $app . '/pageWelcome.php';
} elseif ($room === 'files') {
    require $fk . '/pageDesk.php';
} elseif ($room === 'mail') {
    require $app . '/mail/pageMail.php';
} elseif ($room === 'active') {
    require $li . '/pageActive.php';
} elseif ($room === 'exports') {
    require $li . '/pageExports.php';
} elseif ($room === 'search') {
    require $li . '/pageSearch.php';
} else {
    require $li . '/pageDesk.php';
}
$body = ob_get_clean();

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$roomDisplay = match ($room) {
    'welcome' => 'Welcome',
    'files' => 'Files',
    'mail' => 'Mail',
    'active' => 'Active imports',
    'exports' => 'Exports',
    'search' => 'Search glass',
    default => 'Start an import',
};
$roomSlug = match ($room) {
    'welcome' => 'welcome',
    'files' => 'files',
    'mail' => 'mail',
    'active' => 'imports-active',
    'exports' => 'exports',
    'search' => 'search',
    default => 'import',
};
$pageTitle = 'TERMINAL · ' . strtoupper($stationId) . ' · ' . strtoupper($roomSlug);

$chip = $st['chip'];
$statusBrand = $room === 'files' ? $st['brand_files'] : $st['brand_import'];
$brokenWhisper = $st['whisper'];
$ghost = $st['ghost'];
$foot = $st['foot'];
$hint = match ($room) {
    'files' => $st['hint_files'],
    'mail' => 'station wire · mail/{messages}',
    default => $st['hint_else'],
};
$face = $st['face'];
$userLabel = $st['user'];
$skin = $st['skin'];
$stationLabel = $st['label'];
$deckChip = $st['deck_chip'];
// Chip is the only station switcher (long-term: chip bar expands to all colors)
$nextStation = sdk_station_next();
$nextMeta = sdk_station_meta($nextStation);
$nextShowImports = (($nextMeta['show_imports'] ?? '1') === '1');
$chipRoom = $room;
// Don't land next face on import rooms it doesn't have
if (!$nextShowImports && in_array($chipRoom, ['desk', 'active', 'exports', 'search'], true)) {
    $chipRoom = 'files';
}
$chipHref = sdk_href($chipRoom, ['station' => $nextStation]);
$chipTitle = 'switch station → ' . ($nextMeta['chip'] ?? strtoupper($nextStation));

$importsOpen = $showImports && ($room === 'desk' || $room === 'active' || $room === 'search');
$filesOpen = ($room === 'files');
$mailOpen = ($room === 'mail');
$mailUnread = 0;
try {
    require_once $app . '/mail/mail_lib.php';
    $mailUnread = sdk_mail_unread_count($stationId);
} catch (Throwable $e) {
    $mailUnread = 0;
}
?>
<!DOCTYPE html>
<?php /* expose station for fileKeeper open-folder memory */ ?>

<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $h($pageTitle) ?></title>
  <!-- Shell first at full vault scale (18px base · Press Start / VT323). Do not override .tm-*. -->
  <link rel="stylesheet" href="/shell/fonts.css">
  <link rel="stylesheet" href="/shell/style.css">
  <link rel="stylesheet" href="/shell/skin-io.css">
  <link rel="stylesheet" href="/shell/skin-ab.css">
  <link rel="stylesheet" href="/shell/skin-rx.css">
  <!-- Tool layout + flatten nested boxes only -->
  <link rel="stylesheet" href="/logImport.css">
  <link rel="stylesheet" href="/shell/sdk-cognition.css">
  <?php if ($room === 'files'): ?>
  <link rel="stylesheet" href="/fileKeeper.css">
  <?php endif; ?>
</head>
<body class="terminal-station terminal-skin-<?= $h($skin) ?><?= $room === 'files' ? ' tm-room-files' : '' ?>">
<script>window.sdkStation = <?= json_encode($stationId, JSON_UNESCAPED_UNICODE) ?>;</script>

<?php
/*
 * Deck Host: data-deck-chrome must be a *horizontal* caption strip (like loreBOX),
 * NOT .tm-app (row flex). Host appends [data-deck-window-controls] into chrome —
 * on .tm-app that became a tall thin column on the right.
 */
?>
<header class="tm-deck-bar" data-deck-chrome>
  <span class="tm-deck-chip" data-deck-menu title="Menu"><?= $h($deckChip) ?></span>
  <span class="tm-deck-drag" data-deck-drag title="drag to move" aria-hidden="true"></span>
  <div class="tm-deck-win" data-deck-window-controls aria-label="Window"></div>
</header>

<div class="tm-app">
  <aside class="tm-rail" aria-label="Session rail">
    <?php /* Chip = whole top rail tile clickable (station cycle). Window drag lives on deck header only. */ ?>
    <div class="tm-rail-brand">
      <a class="tm-rail-iox" href="<?= $h($chipHref) ?>" aria-label="<?= $h($chipTitle) ?>">
        <span class="tm-rail-gt" aria-hidden="true">&gt;|</span>
        <span class="tm-rail-iox-label"><?= $h($chip) ?></span>
      </a>
    </div>
    <div class="tm-rail-face">
      <span class="tm-rail-face-l">U</span>
      <span class="tm-rail-face-n"><?= $h($face) ?></span>
    </div>
    <div class="tm-rail-ghost" aria-hidden="true"><?= $h($ghost) ?></div>
    <div class="tm-rail-fill"></div>
    <div class="tm-rail-foot" aria-hidden="true"><?= $h($foot) ?></div>
  </aside>

  <div class="tm-workspace">
    <aside class="tm-sidebar" aria-label="Station tree">
      <div class="tm-page-title flicker">STATION<br>NETWORK</div>
      <div class="tm-logged">
        LOGGED IN AS: <strong><?= $h($userLabel) ?></strong>
      </div>
      <nav class="tm-nav-arch" aria-label="Station">
        <ul>
          <li class="<?= $room === 'welcome' ? 'is-active' : '' ?>">
            <a href="<?= $h(sdk_href('welcome')) ?>">WELCOME MESSAGE</a>
          </li>
          <li class="<?= $filesOpen ? 'is-active' : '' ?>">
            <a href="<?= $h(sdk_href('files')) ?>">FILES</a>
          </li>
          <li class="<?= $mailOpen ? 'is-active' : '' ?>">
            <a href="<?= $h(sdk_href('mail')) ?>">MAIL<?= $mailUnread > 0 ? ' · ' . (int) $mailUnread : '' ?></a>
          </li>
          <?php if ($showImports): ?>
          <li class="tm-nav-parent <?= $importsOpen ? 'is-open is-active' : '' ?>">
            <a href="<?= $h(sdk_href('desk')) ?>">IMPORTS</a>
            <ul class="tm-nav-sub">
              <li class="<?= $room === 'desk' ? 'is-active' : '' ?>">
                <a href="<?= $h(sdk_href('desk')) ?>">START AN IMPORT</a>
              </li>
              <li class="<?= $room === 'active' ? 'is-active' : '' ?>">
                <a href="<?= $h(sdk_href('active')) ?>">ACTIVE IMPORTS</a>
              </li>
              <li class="<?= $room === 'search' ? 'is-active' : '' ?>">
                <a href="<?= $h(sdk_href('search')) ?>">SEARCH GLASS</a>
              </li>
            </ul>
          </li>
          <li class="<?= $room === 'exports' ? 'is-active' : '' ?>">
            <a href="<?= $h(sdk_href('exports')) ?>">EXPORTS</a>
          </li>
          <?php endif; ?>
        </ul>
      </nav>
    </aside>

    <div class="tm-center">
      <div class="tm-broken-top" aria-hidden="true">
        <span class="tm-broken-label">&gt;|TERMINAL</span>
        <span class="tm-broken-whisper"><?= $h($brokenWhisper) ?></span>
      </div>
      <div class="tm-tabline">
        <span class="tm-tab is-on"><?= $h($roomDisplay) ?></span>
        <span class="tm-tab-path"><?= $h($stationLabel) ?> / <?= $h($roomSlug) ?></span>
        <span class="tm-tab-agent"><?= $h($face) ?></span>
      </div>

      <main class="tm-main" id="terminalMain">
        <article class="tm-reading<?= $room === 'files' ? ' tm-reading-files' : '' ?>">
          <?= $body ?>
        </article>
      </main>
    </div>
  </div>

  <footer class="tm-statusbar">
    <span class="tm-status-brand"><?= $h($statusBrand) ?></span>
    <span class="tm-status-sep">|</span>
    <span><?= $h(strtoupper($stationId)) ?></span>
    <span class="tm-status-sep">|</span>
    <span><?= $h($roomSlug) ?></span>
    <span class="tm-status-sep">|</span>
    <span class="tm-status-agent"><?= $h($userLabel) ?></span>
    <span class="tm-status-flex"></span>
    <span class="tm-status-hint"><?= $h($hint) ?></span>
  </footer>
</div>

<script>
/* Deck Host gem menu — lean doors + terminal switch (rail chip is primary) */
(function () {
  var stations = <?= json_encode(array_map(static function ($s) {
      return [
          'id' => $s['id'],
          'chip' => $s['chip'],
          'label' => $s['label'],
      ];
  }, array_values(sdk_station_defs())), JSON_UNESCAPED_UNICODE) ?>;
  var cur = <?= json_encode($stationId, JSON_UNESCAPED_UNICODE) ?>;
  var room = <?= json_encode($room, JSON_UNESCAPED_UNICODE) ?>;

  function goStation(id) {
    var q = new URLSearchParams(window.location.search);
    q.set("station", id);
    if (!q.get("room")) q.set("room", room || "files");
    window.location.search = q.toString();
  }

  var menu = [
    { label: "New window", action: "new_window" },
    { label: "Hard refresh", action: "hard_refresh" },
    { sep: true },
  ];
  stations.forEach(function (s) {
    var mark = s.id === cur ? " ·" : "";
    menu.push({
      label: s.chip + " · " + s.label + mark,
      run: function () {
        goStation(s.id);
      },
    });
  });
  window.DECK_ROM_MENU = menu;
  window.DECK_ROM_MENU_EXTRAS = [];
})();

/*
 * Quiet chrome: browser/webview status bar flashes full URLs over K'S PAPER etc.
 * Internal links keep working; href is moved off the attribute so hover shows nothing.
 * Native title= tooltips also stripped (unthemed OS popovers).
 */
(function () {
  function isInternal(href) {
    if (!href || href === "#" || href.indexOf("javascript:") === 0) return false;
    if (href.charAt(0) === "/" || href.charAt(0) === "?") return true;
    if (href.charAt(0) === "#") return false;
    try {
      var u = new URL(href, location.href);
      return u.origin === location.origin;
    } catch (e) {
      return false;
    }
  }

  function go(href) {
    if (!href) return;
    location.assign(href);
  }

  function quiet(a) {
    if (!a || a.nodeType !== 1 || a.tagName !== "A") return;
    if (a.getAttribute("data-quiet-nav") === "1") return;
    var href = a.getAttribute("href");
    if (!isInternal(href)) return;

    a.setAttribute("data-quiet-nav", "1");
    a.setAttribute("data-href", href);
    a.removeAttribute("href");
    a.removeAttribute("title");
    if (!a.getAttribute("role")) a.setAttribute("role", "link");
    if (!a.hasAttribute("tabindex")) a.setAttribute("tabindex", "0");
    if (!a.style.cursor) a.style.cursor = "pointer";

    a.addEventListener("click", function (e) {
      e.preventDefault();
      go(a.getAttribute("data-href"));
    });
    a.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        go(a.getAttribute("data-href"));
      }
    });
  }

  function scan(root) {
    var nodes = (root || document).querySelectorAll
      ? (root || document).querySelectorAll("a[href]")
      : [];
    for (var i = 0; i < nodes.length; i++) quiet(nodes[i]);
    // strip leftover title tooltips on chrome (not form fields)
    var titled = (root || document).querySelectorAll
      ? (root || document).querySelectorAll(
          ".tm-app [title], .tm-deck-bar [title], .tm-statusbar [title], a[title], .fk-tree [title]"
        )
      : [];
    for (var j = 0; j < titled.length; j++) {
      var el = titled[j];
      if (el.closest && el.closest("input, textarea, select, label")) continue;
      el.removeAttribute("title");
    }
  }

  function boot() {
    scan(document);
    try {
      window.status = "";
    } catch (e) {}
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }

  try {
    var mo = new MutationObserver(function (muts) {
      for (var i = 0; i < muts.length; i++) {
        var m = muts[i];
        if (m.addedNodes && m.addedNodes.length) scan(document);
      }
    });
    mo.observe(document.documentElement, { childList: true, subtree: true });
  } catch (e2) {}
})();
</script>
</body>
</html>
