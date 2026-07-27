<?php
/**
 * fileKeeper · Save / mkdir (station paper — no ledger)
 */
declare(strict_types=1);

require_once __DIR__ . '/paper_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}

$action = (string) ($_POST['filekeeper_action'] ?? '');
$tz = trim((string) ($_POST['fk_tz'] ?? ''));

if ($action === 'mkdir') {
    $folder = trim((string) ($_POST['fk_mkdir'] ?? ''));
    $result = fk_mkdir($folder);
    if (!empty($result['ok'])) {
        header('Location: ' . sdk_href('files', [
            'new' => '1',
            'folder' => $result['folder'] ?? $folder,
            'fk_mkdir_ok' => '1',
        ]));
        exit;
    }
    $GLOBALS['FILEKEEPER_ERROR'] = $result['error'] ?? 'mkdir failed';
    return;
}

if ($action === 'attach') {
    $GLOBALS['FILEKEEPER_ERROR'] = 'image attach not in this port yet — body markdown only';
    return;
}

if ($action === 'delete') {
    $stem = trim((string) ($_POST['fk_stem'] ?? ''));
    $result = fk_delete_stem($stem);
    if (!empty($result['ok'])) {
        header('Location: ' . sdk_href('files', ['fk_del_ok' => '1']));
        exit;
    }
    $GLOBALS['FILEKEEPER_ERROR'] = $result['error'] ?? 'delete failed';
    return;
}

if ($action === 'move') {
    $stem = trim((string) ($_POST['fk_stem'] ?? ''));
    $folder = trim((string) ($_POST['fk_folder'] ?? ''));
    if ($folder === '__new__') {
        $folder = trim((string) ($_POST['fk_folder_new'] ?? ''));
    }
    $result = fk_move_stem($stem, $folder);
    if (!empty($result['ok'])) {
        header('Location: ' . sdk_href('files', [
            'stem' => $stem,
            'fk_ok' => 'moved',
        ]));
        exit;
    }
    $GLOBALS['FILEKEEPER_ERROR'] = $result['error'] ?? 'move failed';
    return;
}

if ($action === 'rename_folder') {
    $from = trim((string) ($_POST['fk_folder_from'] ?? ''));
    $to = trim((string) ($_POST['fk_folder_to'] ?? ''));
    $result = fk_rename_folder($from, $to);
    if (!empty($result['ok'])) {
        header('Location: ' . sdk_href('files', [
            'folder' => $result['folder'] ?? $to,
            'new' => '1',
            'fk_ok' => 'renamed',
        ]));
        exit;
    }
    $GLOBALS['FILEKEEPER_ERROR'] = $result['error'] ?? 'rename failed';
    return;
}

if ($action !== 'save') {
    return;
}

$title = trim((string) ($_POST['fk_title'] ?? ''));
$body = (string) ($_POST['fk_body'] ?? '');
$tags = trim((string) ($_POST['fk_tags'] ?? ''));
$stem = trim((string) ($_POST['fk_stem'] ?? ''));
$eventRaw = trim((string) ($_POST['fk_event'] ?? ''));
$eventResolved = trim((string) ($_POST['fk_event_unix'] ?? ''));
$folder = trim((string) ($_POST['fk_folder'] ?? ''));
if ($folder === '__new__') {
    $folder = trim((string) ($_POST['fk_folder_new'] ?? ''));
}

$eventUnix = null;
if ($eventResolved !== '' && preg_match('/^\d{9,13}$/', $eventResolved)) {
    $eventUnix = (int) $eventResolved;
    if ($eventUnix > 9999999999) {
        $eventUnix = (int) floor($eventUnix / 1000);
    }
}
if (($eventUnix === null || $eventUnix <= 0) && $eventRaw !== '') {
    $eventUnix = fk_parse_event_time($eventRaw, $tz);
}
if ($eventUnix === null || $eventUnix <= 0) {
    $eventUnix = time();
}

$result = fk_save([
    'title' => $title,
    'body' => $body,
    'tags_raw' => $tags,
    'stem' => $stem,
    'folder' => $folder,
    'event_unix' => $eventUnix,
    'event_raw' => $eventRaw,
    'timezone' => $tz,
]);

if (!empty($result['ok'])) {
    header('Location: ' . sdk_href('files', [
        'stem' => $result['stem_c_uid'] ?? $stem,
        'fk_ok' => '1',
        'rev' => (string) ($result['rev'] ?? ''),
        'ev' => (string) $eventUnix,
    ]));
    exit;
}

$GLOBALS['FILEKEEPER_ERROR'] = $result['error'] ?? 'save failed';
