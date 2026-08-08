<?php
/**
 * dossierDesk · AB · person-first (JSON bag)
 */
declare(strict_types=1);

require_once __DIR__ . '/dossier_lib.php';

$bag = dd_load();
$tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'people';
if (!in_array($tab, ['people', 'factions', 'person', 'faction', 'new_person', 'new_faction'], true)) {
    $tab = 'people';
}
$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$err = $GLOBALS['DOSSIER_ERROR'] ?? null;
$ok = isset($_GET['ok']) ? (string) $_GET['ok'] : '';
$leaderWarn = isset($_GET['leader_warn']) ? (int) $_GET['leader_warn'] : 0;

$persons = dd_filter_list($bag['persons'], $q);
$factions = dd_filter_list($bag['factions'], $q);

$focus = null;
if ($id !== '' && $tab === 'person') {
    $focus = dd_person($bag, $id);
}
if ($id !== '' && $tab === 'faction') {
    $focus = dd_faction($bag, $id);
}

$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$badge = static function (string $st) use ($h): string {
    $st = dd_norm_status($st);
    return '<span class="dd-badge st-' . $h($st) . '">' . $h($st) . '</span>';
};
$moralBadge = static function (string $m) use ($h): string {
    $m = dd_norm_morality($m);
    return '<span class="dd-badge mor-' . $h($m) . '">' . $h($m) . '</span>';
};
$selfPeople = $h(dd_href(['tab' => 'people']));
$selfFactions = $h(dd_href(['tab' => 'factions']));
?>
<link rel="stylesheet" href="/dossierDesk.css" />
<div class="ddesk" id="dossier-desk">
  <div class="ddesk-layout">
    <aside class="ddesk-side">
      <div class="ddesk-tabs">
        <a class="<?= in_array($tab, ['people', 'person', 'new_person'], true) ? 'is-on' : '' ?>"
           href="<?= $selfPeople ?>">People</a>
        <a class="<?= in_array($tab, ['factions', 'faction', 'new_faction'], true) ? 'is-on' : '' ?>"
           href="<?= $selfFactions ?>">Factions</a>
      </div>
      <form method="get" action="" class="dd-form" style="padding:0.35rem;margin:0">
        <input type="hidden" name="room" value="dossier">
        <input type="hidden" name="station" value="ab">
        <input type="hidden" name="tab" value="<?= $h(
            in_array($tab, ['factions', 'faction', 'new_faction'], true) ? 'factions' : 'people'
        ) ?>">
        <input type="text" name="q" value="<?= $h($q) ?>" placeholder="search…" style="font-size:0.85rem">
      </form>
      <p class="dd-hint">
        <a class="dd-btn" href="<?= $h(dd_href(['tab' => 'new_person'])) ?>">+ person</a>
        <a class="dd-btn" href="<?= $h(dd_href(['tab' => 'new_faction'])) ?>">+ faction</a>
      </p>
      <ul class="ddesk-list">
        <?php if (in_array($tab, ['factions', 'faction', 'new_faction'], true)): ?>
          <?php if (!$factions): ?>
            <li class="dd-empty">no factions · map blank on purpose</li>
          <?php endif; ?>
          <?php foreach ($factions as $f):
              $on = ($id === ($f['id'] ?? ''));
              $leaders = dd_faction_leaders($bag, (string) $f['id']);
              $nMem = count(dd_mems_for_faction($bag, (string) $f['id']));
              ?>
            <li>
              <a class="<?= $on ? 'is-on' : '' ?>"
                 href="<?= $h(dd_href(['tab' => 'faction', 'id' => $f['id']])) ?>">
                <span class="dd-list-name"><?= $h($f['name'] ?? '?') ?></span>
                <span class="dd-chip-row">
                  <?= $badge((string) ($f['status'] ?? 'unsure')) ?>
                  <?= $moralBadge((string) ($f['morality'] ?? 'unsure')) ?>
                </span>
                <span class="dd-meta">
                  <?= (int) $nMem ?> members
                  <?php if (count($leaders) >= 2): ?> · multi-leader<?php endif; ?>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        <?php else: ?>
          <?php if (!$persons): ?>
            <li class="dd-empty">no persons logged</li>
          <?php endif; ?>
          <?php foreach ($persons as $p):
              $on = ($id === ($p['id'] ?? ''));
              $akas = $p['akas'] ?? [];
              ?>
            <li>
              <a class="<?= $on ? 'is-on' : '' ?>"
                 href="<?= $h(dd_href(['tab' => 'person', 'id' => $p['id']])) ?>">
                <?= $h($p['name'] ?? '?') ?>
                <?= $badge((string) ($p['status'] ?? 'unsure')) ?>
                <?php if (is_array($akas) && $akas): ?>
                  <span class="dd-meta">aka <?= $h(implode(', ', array_slice($akas, 0, 3))) ?></span>
                <?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        <?php endif; ?>
      </ul>
    </aside>

    <section class="ddesk-panel">
      <?php if ($err): ?>
        <p class="dd-status dd-err"><strong>error:</strong> <?= $h($err) ?></p>
      <?php elseif ($ok !== ''): ?>
        <p class="dd-status"><strong>saved</strong> · <?= $h($ok) ?></p>
      <?php endif; ?>
      <?php if ($leaderWarn >= 2): ?>
        <div class="dd-warn">Faction believes it has <strong><?= (int) $leaderWarn ?></strong> leaders.</div>
      <?php endif; ?>

      <?php if ($tab === 'new_person' || ($tab === 'person' && !$focus)): ?>
        <h2 class="ddesk-title">New person</h2>
        <form method="post" class="dd-form">
          <input type="hidden" name="dd_action" value="save_person">
          <label>Name / face</label>
          <input name="dd_name" type="text" required placeholder="primary name">
          <label>AKAs <span class="muted">(comma-separated)</span></label>
          <input name="dd_akas" type="text" placeholder="handle, code name, …">
          <label>Status</label>
          <select name="dd_status">
            <?php foreach (['unsure', 'active', 'inactive'] as $k): ?>
              <option value="<?= $k ?>"<?= $k === 'unsure' ? ' selected' : '' ?>><?= $k ?></option>
            <?php endforeach; ?>
          </select>
          <label>Blurb</label>
          <textarea name="dd_body" placeholder="who they are in one breath"></textarea>
          <label>Tags</label>
          <input name="dd_tags" type="text">
          <div class="dd-row"><button type="submit" class="dd-btn dd-btn-primary">Save person</button></div>
        </form>

      <?php elseif ($tab === 'new_faction' || ($tab === 'faction' && !$focus)): ?>
        <h2 class="ddesk-title">New faction</h2>
        <form method="post" class="dd-form">
          <input type="hidden" name="dd_action" value="save_faction">
          <label>Name</label>
          <input name="dd_name" type="text" required>
          <label>Status</label>
          <select name="dd_status">
            <?php foreach (['unsure', 'active', 'inactive', 'dissolved'] as $k): ?>
              <option value="<?= $k ?>"<?= $k === 'unsure' ? ' selected' : '' ?>><?= $k ?></option>
            <?php endforeach; ?>
          </select>
          <label>Morality</label>
          <select name="dd_morality">
            <?php foreach (dd_morality_opts() as $k): ?>
              <option value="<?= $k ?>"<?= $k === 'unsure' ? ' selected' : '' ?>><?= $k ?></option>
            <?php endforeach; ?>
          </select>
          <label>Summary</label>
          <textarea name="dd_body"></textarea>
          <div class="dd-row"><button type="submit" class="dd-btn dd-btn-primary">Save faction</button></div>
        </form>

      <?php elseif ($tab === 'person' && $focus): ?>
        <?php
        $akas = is_array($focus['akas'] ?? null) ? $focus['akas'] : [];
        $mems = dd_mems_for_person($bag, (string) $focus['id']);
        $notes = dd_notes_for($bag, (string) $focus['id'], null);
        ?>
        <h2 class="ddesk-title">
          <?= $h($focus['name']) ?>
          <?= $badge((string) ($focus['status'] ?? 'unsure')) ?>
        </h2>
        <?php if ($akas): ?>
          <p class="dd-hint">aka <?= $h(implode(' · ', $akas)) ?></p>
        <?php endif; ?>
        <?php if (trim((string) ($focus['body'] ?? '')) !== ''): ?>
          <div class="dd-note-body"><?= nl2br($h($focus['body'])) ?></div>
        <?php endif; ?>

        <h3 class="dd-section">Edit person</h3>
        <form method="post" class="dd-form">
          <input type="hidden" name="dd_action" value="save_person">
          <input type="hidden" name="dd_c_uid" value="<?= $h($focus['id']) ?>">
          <label>Name</label>
          <input name="dd_name" type="text" required value="<?= $h($focus['name']) ?>">
          <label>AKAs</label>
          <input name="dd_akas" type="text" value="<?= $h(implode(', ', $akas)) ?>">
          <label>Status</label>
          <select name="dd_status">
            <?php foreach (['unsure', 'active', 'inactive'] as $k): ?>
              <option value="<?= $k ?>"<?= ($focus['status'] ?? 'unsure') === $k ? ' selected' : '' ?>><?= $k ?></option>
            <?php endforeach; ?>
          </select>
          <label>Blurb</label>
          <textarea name="dd_body"><?= $h($focus['body'] ?? '') ?></textarea>
          <div class="dd-row"><button type="submit" class="dd-btn dd-btn-primary">Update</button></div>
        </form>

        <h3 class="dd-section">Memberships</h3>
        <?php if (!$mems): ?>
          <p class="dd-empty">no factions yet</p>
        <?php else: ?>
          <table class="dd-table">
            <thead><tr><th>Faction</th><th>Status</th><th>Role</th><th>Leader</th></tr></thead>
            <tbody>
            <?php foreach ($mems as $m):
                $f = dd_faction($bag, (string) ($m['faction_id'] ?? ''));
                ?>
              <tr>
                <td>
                  <?php if ($f): ?>
                    <a href="<?= $h(dd_href(['tab' => 'faction', 'id' => $f['id']])) ?>"><?= $h($f['name']) ?></a>
                  <?php else: ?>?<?php endif; ?>
                </td>
                <td><?= $badge((string) ($m['status'] ?? 'unsure')) ?></td>
                <td><?= $h($m['role'] ?? '') ?></td>
                <td><?= !empty($m['is_leader']) ? '<span class="dd-leader">★</span>' : '—' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <form method="post" class="dd-form">
          <input type="hidden" name="dd_action" value="save_membership">
          <input type="hidden" name="dd_person" value="<?= $h($focus['id']) ?>">
          <input type="hidden" name="dd_return_tab" value="person">
          <input type="hidden" name="dd_return_id" value="<?= $h($focus['id']) ?>">
          <label>Add / update membership</label>
          <select name="dd_faction" required>
            <option value="">— faction —</option>
            <?php foreach ($bag['factions'] as $f): ?>
              <option value="<?= $h($f['id']) ?>"><?= $h($f['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <label>Status</label>
          <select name="dd_status">
            <?php foreach (['unsure', 'active', 'inactive'] as $k): ?>
              <option value="<?= $k ?>"<?= $k === 'unsure' ? ' selected' : '' ?>><?= $k ?></option>
            <?php endforeach; ?>
          </select>
          <label>Role</label>
          <input name="dd_role" type="text" placeholder="handler · cutout · …">
          <div class="dd-row">
            <label><input type="checkbox" name="dd_is_leader" value="1"> mark as leader claim</label>
            <button type="submit" class="dd-btn dd-btn-primary">Save membership</button>
          </div>
        </form>

        <h3 class="dd-section">Field notes</h3>
        <?php foreach ($notes as $n): ?>
          <article class="dd-note">
            <h4><?= $h($n['title'] ?? 'note') ?></h4>
            <div class="dd-when">
              <?= !empty($n['created']) ? $h(date('Y-m-d H:i', (int) $n['created'])) : '' ?>
              · <?= $h($n['confidence'] ?? '') ?>
            </div>
            <div class="dd-note-body"><?= nl2br($h($n['body'] ?? '')) ?></div>
            <?php if (!empty($n['context'])): ?>
              <p class="dd-hint">context: <?= $h($n['context']) ?></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
        <?php if (!$notes): ?><p class="dd-empty">no notes yet</p><?php endif; ?>

        <form method="post" class="dd-form">
          <input type="hidden" name="dd_action" value="add_note">
          <input type="hidden" name="dd_person" value="<?= $h($focus['id']) ?>">
          <input type="hidden" name="dd_return_tab" value="person">
          <input type="hidden" name="dd_return_id" value="<?= $h($focus['id']) ?>">
          <label>New field note</label>
          <input name="dd_title" type="text" placeholder="title">
          <textarea name="dd_body" required placeholder="what you saw · involvement · behavior"></textarea>
          <label>Context</label>
          <input name="dd_context" type="text" placeholder="source · rumor · call">
          <label>Confidence</label>
          <select name="dd_confidence">
            <option value="rumor">rumor</option>
            <option value="confirmed">confirmed</option>
            <option value="contested">contested</option>
          </select>
          <div class="dd-row"><button type="submit" class="dd-btn dd-btn-primary">Add note</button></div>
        </form>

      <?php elseif ($tab === 'faction' && $focus): ?>
        <?php
        $mems = dd_mems_for_faction($bag, (string) $focus['id']);
        $leaders = dd_faction_leaders($bag, (string) $focus['id']);
        $notes = dd_notes_for($bag, null, (string) $focus['id']);
        ?>
        <h2 class="ddesk-title">
          <?= $h($focus['name']) ?>
          <?= $badge((string) ($focus['status'] ?? 'unsure')) ?>
          <?= $moralBadge((string) ($focus['morality'] ?? 'unsure')) ?>
        </h2>
        <?php if (count($leaders) >= 2): ?>
          <div class="dd-warn">
            Faction believes it has <strong><?= count($leaders) ?></strong> leaders:
            <?= $h(implode(', ', array_map(static fn($L) => $L['name'], $leaders))) ?>
          </div>
        <?php elseif (count($leaders) === 1): ?>
          <p class="dd-hint">Leader claim: <strong><?= $h($leaders[0]['name']) ?></strong></p>
        <?php endif; ?>
        <?php if (trim((string) ($focus['body'] ?? '')) !== ''): ?>
          <div class="dd-note-body"><?= nl2br($h($focus['body'])) ?></div>
        <?php endif; ?>

        <h3 class="dd-section">Edit faction</h3>
        <form method="post" class="dd-form">
          <input type="hidden" name="dd_action" value="save_faction">
          <input type="hidden" name="dd_c_uid" value="<?= $h($focus['id']) ?>">
          <label>Name</label>
          <input name="dd_name" type="text" required value="<?= $h($focus['name']) ?>">
          <label>Status</label>
          <select name="dd_status">
            <?php foreach (['unsure', 'active', 'inactive', 'dissolved'] as $k): ?>
              <option value="<?= $k ?>"<?= ($focus['status'] ?? 'unsure') === $k ? ' selected' : '' ?>><?= $k ?></option>
            <?php endforeach; ?>
          </select>
          <label>Morality</label>
          <select name="dd_morality">
            <?php
            $curMor = dd_norm_morality((string) ($focus['morality'] ?? 'unsure'));
            foreach (dd_morality_opts() as $k): ?>
              <option value="<?= $k ?>"<?= $curMor === $k ? ' selected' : '' ?>><?= $k ?></option>
            <?php endforeach; ?>
          </select>
          <label>Summary</label>
          <textarea name="dd_body"><?= $h($focus['body'] ?? '') ?></textarea>
          <div class="dd-row"><button type="submit" class="dd-btn dd-btn-primary">Update</button></div>
        </form>

        <h3 class="dd-section">Members</h3>
        <?php if (!$mems): ?>
          <p class="dd-empty">no members logged</p>
        <?php else: ?>
          <table class="dd-table">
            <thead><tr><th>Person</th><th>Status</th><th>Role</th><th>Leader</th></tr></thead>
            <tbody>
            <?php foreach ($mems as $m):
                $p = dd_person($bag, (string) ($m['person_id'] ?? ''));
                ?>
              <tr>
                <td>
                  <?php if ($p): ?>
                    <a href="<?= $h(dd_href(['tab' => 'person', 'id' => $p['id']])) ?>"><?= $h($p['name']) ?></a>
                  <?php else: ?>?<?php endif; ?>
                </td>
                <td><?= $badge((string) ($m['status'] ?? 'unsure')) ?></td>
                <td><?= $h($m['role'] ?? '') ?></td>
                <td><?= !empty($m['is_leader']) ? '<span class="dd-leader">★</span>' : '—' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <form method="post" class="dd-form">
          <input type="hidden" name="dd_action" value="save_membership">
          <input type="hidden" name="dd_faction" value="<?= $h($focus['id']) ?>">
          <input type="hidden" name="dd_return_tab" value="faction">
          <input type="hidden" name="dd_return_id" value="<?= $h($focus['id']) ?>">
          <label>Add / update member</label>
          <select name="dd_person" required>
            <option value="">— person —</option>
            <?php foreach ($bag['persons'] as $p): ?>
              <option value="<?= $h($p['id']) ?>"><?= $h($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <label>Status</label>
          <select name="dd_status">
            <?php foreach (['unsure', 'active', 'inactive'] as $k): ?>
              <option value="<?= $k ?>"<?= $k === 'unsure' ? ' selected' : '' ?>><?= $k ?></option>
            <?php endforeach; ?>
          </select>
          <label>Role</label>
          <input name="dd_role" type="text">
          <div class="dd-row">
            <label><input type="checkbox" name="dd_is_leader" value="1"> leader claim</label>
            <button type="submit" class="dd-btn dd-btn-primary">Save membership</button>
          </div>
        </form>

        <h3 class="dd-section">Field notes</h3>
        <?php foreach ($notes as $n): ?>
          <article class="dd-note">
            <h4><?= $h($n['title'] ?? 'note') ?></h4>
            <div class="dd-note-body"><?= nl2br($h($n['body'] ?? '')) ?></div>
          </article>
        <?php endforeach; ?>
        <?php if (!$notes): ?><p class="dd-empty">no notes yet</p><?php endif; ?>
        <form method="post" class="dd-form">
          <input type="hidden" name="dd_action" value="add_note">
          <input type="hidden" name="dd_faction" value="<?= $h($focus['id']) ?>">
          <input type="hidden" name="dd_return_tab" value="faction">
          <input type="hidden" name="dd_return_id" value="<?= $h($focus['id']) ?>">
          <label>New field note</label>
          <input name="dd_title" type="text">
          <textarea name="dd_body" required></textarea>
          <select name="dd_confidence">
            <option value="rumor">rumor</option>
            <option value="confirmed">confirmed</option>
            <option value="contested">contested</option>
          </select>
          <div class="dd-row"><button type="submit" class="dd-btn dd-btn-primary">Add note</button></div>
        </form>
      <?php else: ?>
        <h2 class="ddesk-title">Dossier</h2>
        <p class="dd-hint">Pick a person or faction · evidence shelf for who is in which structure.</p>
      <?php endif; ?>
    </section>
  </div>
</div>
