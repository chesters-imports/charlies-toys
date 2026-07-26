<?php
/**
 * sdk-import-station — IOX shell (green station look, not pocket internet).
 * Rooms: welcome (land) | desk | active | exports
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/logimport/logImport_lib.php';

// Default land = welcome. But face / desk flash query params imply desk
// (GET load form only posts `face` and used to wipe ?room=desk).
$room = isset($_GET['room']) ? strtolower(trim((string) $_GET['room'])) : '';
if ($room === '' || in_array($room, ['land', 'home'], true)) {
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
    $room = $wantsDesk ? 'desk' : ($room === '' || $room === 'land' || $room === 'home' ? 'welcome' : $room);
}
if (!in_array($room, ['welcome', 'desk', 'active', 'exports', 'import', 'search', 'find', 'hunt'], true)) {
    $room = 'welcome';
}
if ($room === 'import') {
    $room = 'desk';
}
if (in_array($room, ['find', 'hunt'], true)) {
    $room = 'search';
}

$li = dirname(__DIR__) . '/app/logimport';
$app = dirname(__DIR__) . '/app';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $room !== 'welcome') {
    $isExportStatus = isset($_POST['mark_complete'])
        || isset($_POST['mark_in_progress'])
        || (isset($_POST['logimport_export_action']) && (string) $_POST['logimport_export_action'] !== '');
    if ($isExportStatus || $room === 'exports') {
        require $li . '/actorExports.php';
    } else {
        require $li . '/actorDesk.php';
    }
}

ob_start();
if ($room === 'welcome') {
    require $app . '/pageWelcome.php';
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
    'active' => 'Active imports',
    'exports' => 'Exports',
    'search' => 'Search glass',
    default => 'Start an import',
};
$roomSlug = match ($room) {
    'welcome' => 'welcome',
    'active' => 'imports-active',
    'exports' => 'exports',
    'search' => 'search',
    default => 'import',
};
$pageTitle = 'TERMINAL · IO · ' . strtoupper($roomSlug);

// IOX copy (from original shell — no multi-station)
$chip = 'IOX';
$statusBrand = "CHESTER'S NOW IMPORTING";
$brokenWhisper = 'for the collection and protection of your thoughts';
$ghost = 'put them here';
$foot = 'O·X';
$hint = 'the forest remembers';
$face = 'SDK';

$importsOpen = ($room === 'desk' || $room === 'active');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $h($pageTitle) ?></title>
  <!-- Shell first at full vault scale (18px base · Press Start / VT323). Do not override .tm-*. -->
  <link rel="stylesheet" href="/shell/fonts.css">
  <link rel="stylesheet" href="/shell/style.css">
  <link rel="stylesheet" href="/shell/skin-io.css">
  <!-- Tool layout + flatten nested boxes only -->
  <link rel="stylesheet" href="/logImport.css">
  <link rel="stylesheet" href="/shell/sdk-cognition.css">
</head>
<body class="terminal-station terminal-skin-io">

<?php
/*
 * Deck Host: data-deck-chrome must be a *horizontal* caption strip (like loreBOX),
 * NOT .tm-app (row flex). Host appends [data-deck-window-controls] into chrome —
 * on .tm-app that became a tall thin column on the right.
 */
?>
<header class="tm-deck-bar" data-deck-chrome>
  <span class="tm-deck-chip" data-deck-menu title="Menu">CHESTER'S IMPORTS</span>
  <span class="tm-deck-drag" data-deck-drag title="drag to move" aria-hidden="true"></span>
  <div class="tm-deck-win" data-deck-window-controls aria-label="Window"></div>
</header>

<div class="tm-app">
  <aside class="tm-rail" aria-label="Session rail">
    <div class="tm-rail-brand pywebview-drag-region" data-pocket-drag data-deck-drag
         title="drag · sdk-import-station">
      <span class="tm-rail-gt">&gt;|</span>
      <span class="tm-rail-iox"><?= $h($chip) ?></span>
    </div>
    <div class="tm-rail-face" title="sdk face">
      <span class="tm-rail-face-l">U</span>
      <span class="tm-rail-face-n"><?= $h($face) ?></span>
    </div>
    <div class="tm-rail-ghost" aria-hidden="true"><?= $h($ghost) ?></div>
    <div class="tm-rail-fill"></div>
    <div class="tm-rail-foot" aria-hidden="true"><?= $h($foot) ?></div>
  </aside>

  <div class="tm-workspace">
    <aside class="tm-sidebar" aria-label="IO tree">
      <div class="tm-page-title flicker">STATION<br>NETWORK</div>
      <div class="tm-logged">
        LOGGED IN AS: <strong>SDK-808</strong>
      </div>
      <nav class="tm-nav-arch" aria-label="Station">
        <ul>
          <li class="<?= $room === 'welcome' ? 'is-active' : '' ?>">
            <a href="<?= $h(sdk_href('welcome')) ?>">WELCOME MESSAGE</a>
          </li>
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
        <span class="tm-tab-path">Terminal IO / <?= $h($roomSlug) ?></span>
        <span class="tm-tab-agent"><?= $h($face) ?></span>
      </div>

      <main class="tm-main" id="terminalMain">
        <article class="tm-reading">
          <?= $body ?>
        </article>
      </main>
    </div>
  </div>

  <footer class="tm-statusbar">
    <span class="tm-status-brand"><?= $h($statusBrand) ?></span>
    <span class="tm-status-sep">|</span>
    <span>IO</span>
    <span class="tm-status-sep">|</span>
    <span><?= $h($roomSlug) ?></span>
    <span class="tm-status-sep">|</span>
    <span class="tm-status-agent">SDK-808</span>
    <span class="tm-status-flex"></span>
    <span class="tm-status-hint" title="<?= $h(SDK_GLASS_ROOT) ?>"><?= $h($hint) ?></span>
  </footer>
</div>

</body>
</html>
