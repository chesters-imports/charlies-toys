<?php
/**
 * dossierDesk POST actor — AB JSON bag.
 */
declare(strict_types=1);

require_once __DIR__ . '/dossier_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}

$action = (string) ($_POST['dd_action'] ?? '');
$bag = dd_load();
$t = time();

$go = static function (array $q): void {
    header('Location: ' . dd_href($q));
    exit;
};

if ($action === 'save_person') {
    $name = trim((string) ($_POST['dd_name'] ?? ''));
    if ($name === '') {
        $GLOBALS['DOSSIER_ERROR'] = 'name required';
        return;
    }
    $id = trim((string) ($_POST['dd_c_uid'] ?? ''));
    $row = [
        'id' => $id !== '' ? $id : dd_id('p'),
        'name' => $name,
        'akas' => dd_split_list((string) ($_POST['dd_akas'] ?? '')),
        'status' => dd_norm_status((string) ($_POST['dd_status'] ?? 'unsure')),
        'body' => (string) ($_POST['dd_body'] ?? ''),
        'tags' => dd_split_list((string) ($_POST['dd_tags'] ?? '')),
        'updated' => $t,
    ];
    $found = false;
    foreach ($bag['persons'] as $i => $p) {
        if (($p['id'] ?? '') === $row['id']) {
            $row['created'] = $p['created'] ?? $t;
            $bag['persons'][$i] = $row;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $row['created'] = $t;
        array_unshift($bag['persons'], $row);
    }
    dd_save($bag);
    $go(['tab' => 'person', 'id' => $row['id'], 'ok' => 'person']);
}

if ($action === 'save_faction') {
    $name = trim((string) ($_POST['dd_name'] ?? ''));
    if ($name === '') {
        $GLOBALS['DOSSIER_ERROR'] = 'name required';
        return;
    }
    $id = trim((string) ($_POST['dd_c_uid'] ?? ''));
    $row = [
        'id' => $id !== '' ? $id : dd_id('f'),
        'name' => $name,
        'status' => dd_norm_status((string) ($_POST['dd_status'] ?? 'unsure')),
        'morality' => dd_norm_morality((string) ($_POST['dd_morality'] ?? 'unsure')),
        'body' => (string) ($_POST['dd_body'] ?? ''),
        'updated' => $t,
    ];
    $found = false;
    foreach ($bag['factions'] as $i => $f) {
        if (($f['id'] ?? '') === $row['id']) {
            $row['created'] = $f['created'] ?? $t;
            $bag['factions'][$i] = $row;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $row['created'] = $t;
        array_unshift($bag['factions'], $row);
    }
    dd_save($bag);
    $go(['tab' => 'faction', 'id' => $row['id'], 'ok' => 'faction']);
}

if ($action === 'save_membership') {
    $pid = trim((string) ($_POST['dd_person'] ?? ''));
    $fid = trim((string) ($_POST['dd_faction'] ?? ''));
    if ($pid === '' || $fid === '' || !dd_person($bag, $pid) || !dd_faction($bag, $fid)) {
        $GLOBALS['DOSSIER_ERROR'] = 'person + faction required';
        return;
    }
    $row = [
        'id' => dd_id('m'),
        'person_id' => $pid,
        'faction_id' => $fid,
        'status' => dd_norm_status((string) ($_POST['dd_status'] ?? 'unsure')),
        'role' => trim((string) ($_POST['dd_role'] ?? '')),
        'is_leader' => !empty($_POST['dd_is_leader']),
        'updated' => $t,
    ];
    // upsert by person+faction
    $replaced = false;
    foreach ($bag['memberships'] as $i => $m) {
        if (($m['person_id'] ?? '') === $pid && ($m['faction_id'] ?? '') === $fid) {
            $row['id'] = $m['id'] ?? $row['id'];
            $row['created'] = $m['created'] ?? $t;
            $bag['memberships'][$i] = $row;
            $replaced = true;
            break;
        }
    }
    if (!$replaced) {
        $row['created'] = $t;
        $bag['memberships'][] = $row;
    }
    dd_save($bag);
    $leaders = dd_faction_leaders($bag, $fid);
    $q = [
        'tab' => (string) ($_POST['dd_return_tab'] ?? 'person'),
        'id' => (string) ($_POST['dd_return_id'] ?? $pid),
        'ok' => 'membership',
    ];
    if (count($leaders) >= 2) {
        $q['leader_warn'] = (string) count($leaders);
    }
    $go($q);
}

if ($action === 'add_note') {
    $pid = trim((string) ($_POST['dd_person'] ?? ''));
    $fid = trim((string) ($_POST['dd_faction'] ?? ''));
    $body = trim((string) ($_POST['dd_body'] ?? ''));
    if ($body === '' || ($pid === '' && $fid === '')) {
        $GLOBALS['DOSSIER_ERROR'] = 'note needs body + subject';
        return;
    }
    $title = trim((string) ($_POST['dd_title'] ?? ''));
    if ($title === '') {
        $title = 'field note';
    }
    $bag['notes'][] = [
        'id' => dd_id('n'),
        'person_id' => $pid !== '' ? $pid : null,
        'faction_id' => $fid !== '' ? $fid : null,
        'title' => $title,
        'body' => $body,
        'context' => trim((string) ($_POST['dd_context'] ?? '')),
        'confidence' => (string) ($_POST['dd_confidence'] ?? 'rumor'),
        'event' => trim((string) ($_POST['dd_event'] ?? '')),
        'created' => $t,
    ];
    dd_save($bag);
    $go([
        'tab' => (string) ($_POST['dd_return_tab'] ?? ($pid ? 'person' : 'faction')),
        'id' => (string) ($_POST['dd_return_id'] ?? ($pid !== '' ? $pid : $fid)),
        'ok' => 'note',
    ]);
}
