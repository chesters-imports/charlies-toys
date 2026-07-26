<?php
/**
 * logImport · Search — keyword hunt over work/glass cores.
 * Open a hit → desk face load → still process for TPS yourself.
 */
require_once __DIR__ . '/logImport_lib.php';

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$mode = isset($_GET['mode']) ? strtolower(trim((string) $_GET['mode'])) : 'partial';
if (!in_array($mode, ['partial', 'whole'], true)) {
    $mode = 'partial';
}
$wholeWord = $mode === 'whole';
$ran = $q !== '';
$results = [];
$err = null;
$ms = 0;

if ($ran) {
    $t0 = microtime(true);
    try {
        $results = logimport_search_cores($q, $wholeWord, 250);
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
    $ms = (int) round((microtime(true) - $t0) * 1000);
}

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$deskHref = static function (string $face) use ($h): string {
    if (function_exists('sdk_href')) {
        return $h(sdk_href('desk', ['face' => $face]));
    }
    return $h('/?room=desk&face=' . rawurlencode($face));
};
?>
<div class="logimport logimport-search">
  <p class="logimport-meta">
    Hunt glass extracts for a keyword. Results show <strong>hit count</strong> per core
    (raw glass scan — may take a few seconds over ~900 cores).
    Open → Start an import (you still cut / seal for TPS).
  </p>

  <form class="logimport-search-form" method="get" action="/">
    <input type="hidden" name="room" value="search">
    <label class="logimport-search-q">
      <span class="logimport-meta">Keyword</span>
      <input
        type="search"
        name="q"
        value="<?= $h($q) ?>"
        placeholder="e.g. sopr · AIDM · export · elon"
        autofocus
        autocomplete="off"
      >
    </label>
    <fieldset class="logimport-search-mode">
      <legend class="logimport-meta">Match</legend>
      <label>
        <input type="radio" name="mode" value="partial" <?= $mode === 'partial' ? 'checked' : '' ?>>
        Partial <span class="logimport-meta">— match inside longer words too</span>
      </label>
      <label>
        <input type="radio" name="mode" value="whole" <?= $mode === 'whole' ? 'checked' : '' ?>>
        Whole word <span class="logimport-meta">— only the word by itself</span>
      </label>
    </fieldset>
    <button type="submit" class="logimport-btn">Search glass</button>
  </form>

  <?php if ($err): ?>
    <p class="logimport-err"><?= $h($err) ?></p>
  <?php endif; ?>

  <?php if ($ran && !$err): ?>
    <p class="logimport-meta">
      <strong><?= count($results) ?></strong> core(s) with
      <code><?= $h($q) ?></code>
      · <?= $wholeWord ? 'whole word' : 'partial' ?>
      · <?= (int) $ms ?> ms
      <?php if (count($results) >= 250): ?>
        · capped at 250
      <?php endif; ?>
    </p>

    <?php if (!$results): ?>
      <p class="logimport-meta">No hits. Try partial match, or rebuild catalog glass if empty.</p>
    <?php else: ?>
      <table class="logimport-search-table">
        <thead>
          <tr>
            <th>Hits</th>
            <th>Face</th>
            <th>Tag</th>
            <th>Title</th>
            <th>Snip</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results as $row):
              $face = (string) $row['face_id'];
              $href = $deskHref($face);
              ?>
            <tr>
              <td class="logimport-search-hits"><?= (int) $row['hits'] ?></td>
              <td><code><?= $h($face) ?></code></td>
              <td class="logimport-meta"><?= $h((string) $row['testament_tag']) ?></td>
              <td><?= $h((string) $row['title']) ?></td>
              <td class="logimport-search-snip"><?= $h((string) $row['snip']) ?></td>
              <td><a class="logimport-btn" href="<?= $href ?>">Open import</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php elseif (!$ran): ?>
    <p class="logimport-meta">
      Tip: <strong>whole word</strong> when a short name hides inside other words
      (you want Elon, not every “belong”). <strong>Partial</strong> when you want
      stems and pieces (import, sopr).
    </p>
  <?php endif; ?>
</div>
