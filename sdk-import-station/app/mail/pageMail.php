<?php
/**
 * MAIL room — inbox / sent / compose / read between stations.
 */
declare(strict_types=1);

require_once __DIR__ . '/mail_lib.php';

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$station = sdk_station();
$st = sdk_station_meta($station);
$box = isset($_GET['box']) ? strtolower(trim((string) $_GET['box'])) : 'inbox';
if (!in_array($box, ['inbox', 'sent', 'compose'], true)) {
    $box = 'inbox';
}
$openId = isset($_GET['id']) ? (string) $_GET['id'] : '';
$flash = '';
if (!empty($_SESSION['mail_flash'])) {
    $flash = (string) $_SESSION['mail_flash'];
    unset($_SESSION['mail_flash']);
}

$unread = sdk_mail_unread_count($station);
$list = $box === 'compose' ? [] : sdk_mail_list_for($station, $box === 'sent' ? 'sent' : 'inbox');
$open = $openId !== '' ? sdk_mail_load($openId) : null;
if ($open !== null) {
    // auto-mark read when viewing inbox mail addressed to us
    if ($open['to'] === $station && empty($open['read'][$station])) {
        sdk_mail_mark_read($open['id'], $station);
        $open = sdk_mail_load($open['id']);
    }
}
$recipients = sdk_mail_recipients($station);
?>
<link rel="stylesheet" href="/mail.css">
<div class="mail-desk">
  <header class="mail-head">
    <div class="mail-title">MAIL · <?= $h(strtoupper($station)) ?></div>
    <div class="mail-sub muted">
      station wire · <?= $h($st['user'] ?? $station) ?> ·
      <?= (int) $unread ?> unread
    </div>
  </header>

  <?php if ($flash !== ''): ?>
    <?php
    $ok = str_starts_with($flash, 'ok:');
    $txt = preg_replace('/^(ok|err):/', '', $flash) ?? $flash;
    ?>
    <div class="mail-flash <?= $ok ? 'is-ok' : 'is-err' ?>"><?= $h($txt) ?></div>
  <?php endif; ?>

  <nav class="mail-tabs" aria-label="Mailbox">
    <a class="<?= $box === 'inbox' && $openId === '' ? 'is-on' : '' ?>" href="<?= $h(sdk_href('mail', ['box' => 'inbox'])) ?>">INBOX</a>
    <a class="<?= $box === 'sent' && $openId === '' ? 'is-on' : '' ?>" href="<?= $h(sdk_href('mail', ['box' => 'sent'])) ?>">SENT</a>
    <a class="<?= $box === 'compose' ? 'is-on' : '' ?>" href="<?= $h(sdk_href('mail', ['box' => 'compose'])) ?>">COMPOSE</a>
  </nav>

  <?php if ($box === 'compose'): ?>
    <form class="mail-compose" method="post" action="<?= $h(sdk_href('mail')) ?>">
      <input type="hidden" name="mail_action" value="send">
      <label>
        <span>To</span>
        <select name="to" required>
          <?php foreach ($recipients as $r): ?>
            <option value="<?= $h($r['id']) ?>"><?= $h($r['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        <span>Subject</span>
        <input type="text" name="subject" maxlength="200" placeholder="subject line" autocomplete="off">
      </label>
      <label>
        <span>Body</span>
        <textarea name="body" rows="12" placeholder="type the wire…"></textarea>
      </label>
      <div class="mail-actions">
        <button type="submit" class="mail-btn">Send on the wire</button>
        <span class="muted small">stations on the wire · DESK → the.archivist chip (Sophia must be running)</span>
      </div>
    </form>

  <?php elseif ($open !== null): ?>
    <article class="mail-read">
      <div class="mail-read-meta">
        <div><span class="k">From</span> <?= $h($open['from_label']) ?><?= !empty($open['external']) ? ' <em class="ext">(outside)</em>' : '' ?></div>
        <div><span class="k">To</span> <?= $h(strtoupper($open['to'])) ?></div>
        <div><span class="k">When</span> <?= $h($open['created_at']) ?></div>
        <div><span class="k">Id</span> <code><?= $h($open['id']) ?></code></div>
      </div>
      <h2 class="mail-subject"><?= $h($open['subject']) ?></h2>
      <pre class="mail-body"><?= $h($open['body']) ?></pre>
      <p class="mail-back"><a href="<?= $h(sdk_href('mail', ['box' => $box === 'sent' ? 'sent' : 'inbox'])) ?>">← back</a></p>
    </article>

  <?php else: ?>
    <div class="mail-list">
      <?php if (!$list): ?>
        <p class="muted mail-empty">No messages in <?= $h($box) ?>.</p>
      <?php else: ?>
        <ul>
          <?php foreach ($list as $m): ?>
            <?php
            $isUnread = $box === 'inbox' && empty($m['read'][$station]);
            $peer = $box === 'sent' ? $m['to'] : $m['from_label'];
            ?>
            <li class="<?= $isUnread ? 'is-unread' : '' ?>">
              <a href="<?= $h(sdk_href('mail', ['box' => $box, 'id' => $m['id']])) ?>">
                <span class="mail-peer"><?= $h((string) $peer) ?><?= !empty($m['external']) ? ' · out' : '' ?></span>
                <span class="mail-subj"><?= $h($m['subject']) ?></span>
                <span class="mail-when"><?= $h(substr($m['created_at'], 0, 16)) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <footer class="mail-foot muted small">
    Wire open · station to station.
    <?php /* Outside inject stays in tools + API; never print token on the glass (Chester's bootstrap trash). */ ?>
  </footer>
</div>
