<?php
/**
 * logImport · Exports bay actions.
 *
 * mark_complete | mark_in_progress  — workflow status for a parent face
 * (reopen log is a GET link to Desk, not an actor)
 */
require_once __DIR__ . '/logImport_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}

$face = trim((string) ($_POST['face'] ?? ''));
$faceKey = logimport_face_key($face);
if ($faceKey === '') {
    $GLOBALS['LOGIMPORT_ERROR'] = 'missing face';
    return;
}

$action = (string) ($_POST['logimport_export_action'] ?? '');
if ($action === '' && isset($_POST['mark_complete'])) {
    $action = 'mark_complete';
} elseif ($action === '' && isset($_POST['mark_in_progress'])) {
    $action = 'mark_in_progress';
}

$status = null;
if ($action === 'mark_complete') {
    $status = 'complete';
} elseif ($action === 'mark_in_progress') {
    $status = 'in_progress';
} else {
    $GLOBALS['LOGIMPORT_ERROR'] = 'unknown export action';
    return;
}

// Only allow status on faces that have at least one sealed export file
$has = false;
foreach (logimport_list_exports() as $ex) {
    $pf = (string) ($ex['parent_face'] ?? $ex['face_id'] ?? '');
    if ($pf === $faceKey || (string) ($ex['face_id'] ?? '') === $faceKey) {
        $has = true;
        break;
    }
}
if (!$has) {
    $GLOBALS['LOGIMPORT_ERROR'] = 'no sealed export for face ' . $faceKey;
    return;
}

if (!logimport_export_status_set($faceKey, $status)) {
    $GLOBALS['LOGIMPORT_ERROR'] = 'could not write export status';
    return;
}

$return = trim((string) ($_POST['return'] ?? ''));
// From import desk: after mark in progress, open WIP; after complete, show gate
if ($return === 'import') {
    $q = ['face' => $faceKey];
    if ($status === 'in_progress') {
        $q['reopen'] = '1';
        $q['progress_ok'] = '1';
    } else {
        $q['complete_ok'] = '1';
    }
    $importHref = function_exists('sdk_href') ? sdk_href('desk', $q) : ('/?room=desk&' . http_build_query($q));
    header('Location: ' . $importHref);
    exit;
}

$q = ['face' => $faceKey];
if ($status === 'complete') {
    $q['complete_ok'] = '1';
} else {
    $q['progress_ok'] = '1';
}
$exportHref = function_exists('sdk_href') ? sdk_href('exports', $q) : ('/?room=exports&' . http_build_query($q));
header('Location: ' . $exportHref);
exit;
