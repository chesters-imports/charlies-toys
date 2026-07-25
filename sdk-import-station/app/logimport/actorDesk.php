<?php
/**
 * logImport · WIP actions (never writes glass).
 *
 * save_wip | submit_export | split_after | unsplit_after | clear_splits
 * add_encode | add_encode_also | remove_encode | push_encode_ven
 * add_redact_phrase | add_redact_msg | remove_redact
 * apply_encode | clear_apply_encode | apply_redact | clear_apply_redact
 */
require_once __DIR__ . '/logImport_lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}

$face = trim((string) ($_POST['face'] ?? ''));
$core = $face !== '' ? logimport_core_by_face($face) : null;
if (!$core) {
    $GLOBALS['LOGIMPORT_ERROR'] = 'unknown core';
    return;
}
$faceKey = logimport_face_key((string) $core['face_id']);

// which button?
$action = (string) ($_POST['logimport_action'] ?? 'save_wip');
$afterSeq = null;
if (isset($_POST['split_after']) && $_POST['split_after'] !== '') {
    $action = 'split_after';
    $afterSeq = (int) $_POST['split_after'];
} elseif (isset($_POST['unsplit_after']) && $_POST['unsplit_after'] !== '') {
    $action = 'unsplit_after';
    $afterSeq = (int) $_POST['unsplit_after'];
} elseif (isset($_POST['clear_splits'])) {
    $action = 'clear_splits';
} elseif (isset($_POST['add_encode'])) {
    $action = 'add_encode';
} elseif (isset($_POST['add_encode_also']) && $_POST['add_encode_also'] !== '') {
    $action = 'add_encode_also';
} elseif (isset($_POST['remove_encode']) && $_POST['remove_encode'] !== '') {
    $action = 'remove_encode';
} elseif (isset($_POST['push_encode_ven']) && $_POST['push_encode_ven'] !== '') {
    $action = 'push_encode_ven';
} elseif (isset($_POST['add_redact_phrase'])) {
    $action = 'add_redact_phrase';
} elseif (isset($_POST['add_redact_msg']) && $_POST['add_redact_msg'] !== '') {
    $action = 'add_redact_msg';
} elseif (isset($_POST['remove_redact']) && $_POST['remove_redact'] !== '') {
    $action = 'remove_redact';
} elseif (isset($_POST['apply_encode'])) {
    $action = 'apply_encode';
} elseif (isset($_POST['clear_apply_encode'])) {
    $action = 'clear_apply_encode';
} elseif (isset($_POST['apply_redact'])) {
    $action = 'apply_redact';
} elseif (isset($_POST['clear_apply_redact'])) {
    $action = 'clear_apply_redact';
} elseif (isset($_POST['submit_export']) || $action === 'submit_export') {
    $action = 'submit_export';
}

$lastSeq = 0;
$conv = logimport_load_conversation($core);
if ($conv) {
    $msgs = logimport_extract_messages($conv);
    if ($msgs) {
        $lastSeq = (int) $msgs[count($msgs) - 1]['seq'];
    }
}

$existing = logimport_wip_load($faceKey);
// Merge titles/notes from the same form POST
$wip = logimport_wip_merge_form($faceKey, $_POST, $existing);
$segments = logimport_segments_from_post_and_wip($_POST, $wip, $lastSeq);

$status = 'wip_ok';
$anchorSeq = null;
$fragExtra = '';

if ($action === 'save_wip') {
    $wip['segments'] = logimport_segments_collapse_trivial($segments, $lastSeq);
} elseif ($action === 'split_after' && $afterSeq !== null) {
    $wip['segments'] = logimport_segments_add_cut($segments, $afterSeq, $lastSeq);
    $status = 'split_ok';
    $anchorSeq = $afterSeq;
} elseif ($action === 'unsplit_after' && $afterSeq !== null) {
    $wip['segments'] = logimport_segments_remove_cut($segments, $afterSeq, $lastSeq);
    $status = 'unsplit_ok';
    $anchorSeq = $afterSeq;
} elseif ($action === 'clear_splits') {
    $wip['segments'] = [];
    $status = 'clear_ok';
} elseif ($action === 'add_encode') {
    $wip['segments'] = logimport_segments_collapse_trivial($segments, $lastSeq);
    $orig = logimport_sanitize_paste(trim((string) ($_POST['enc_original'] ?? '')));
    $alias = logimport_sanitize_paste(trim((string) ($_POST['enc_alias'] ?? '')));
    $codeIn = logimport_sanitize_paste(trim((string) ($_POST['enc_code'] ?? '')));
    $alsoRaw = trim((string) ($_POST['enc_also'] ?? ''));
    $also = [];
    if ($alsoRaw !== '') {
        foreach (preg_split('/\s*,\s*/', $alsoRaw) ?: [] as $a) {
            $a = logimport_sanitize_paste(trim((string) $a));
            if ($a !== '' && strcasecmp($a, $orig) !== 0) {
                $also[] = $a;
            }
        }
        $also = array_values(array_unique($also));
    }
    if ($orig === '' || $alias === '') {
        $GLOBALS['LOGIMPORT_ERROR'] = 'encode needs original + alias';
        return;
    }
    $book = logimport_encodes_list($wip);
    // skip exact dup original (or also-spelling already claimed)
    $claimed = [];
    foreach ($book as $e) {
        $claimed[strtolower($e['original'])] = true;
        foreach ($e['also'] as $a) {
            $claimed[strtolower($a)] = true;
        }
    }
    if (!empty($claimed[strtolower($orig)])) {
        $GLOBALS['LOGIMPORT_ERROR'] = 'original (or also-spelling) already in encode book';
        return;
    }
    foreach ($also as $a) {
        if (!empty($claimed[strtolower($a)])) {
            $GLOBALS['LOGIMPORT_ERROR'] = 'also-spelling already used: ' . $a;
            return;
        }
    }
    $code = $codeIn !== '' ? $codeIn : logimport_encode_mint_code($orig, $book);
    $book[] = [
        'id' => logimport_new_id('e'),
        'code' => $code,
        'alias' => $alias,
        'original' => $orig,
        'also' => $also,
        'created_at' => time(),
    ];
    $wip['encodes'] = $book;
    $status = 'enc_ok';
    $fragExtra = '#li-encode';
} elseif ($action === 'add_encode_also') {
    $wip['segments'] = logimport_segments_collapse_trivial($segments, $lastSeq);
    $rid = (string) ($_POST['add_encode_also'] ?? '');
    // PHP mangles '.' in field names → use alnum form key (see logimport_encode_form_key)
    $formKey = logimport_encode_form_key($rid);
    $alsoRaw = trim((string) (
        $_POST['enc_also_' . $formKey]
        ?? $_POST['enc_also_for_' . $rid]
        ?? $_POST['enc_also_for_' . str_replace('.', '_', $rid)]
        ?? ''
    ));
    if ($rid === '' || $alsoRaw === '') {
        $GLOBALS['LOGIMPORT_ERROR'] = 'add also: type spellings in the row field, then click add also';
        return;
    }
    $add = [];
    foreach (preg_split('/\s*,\s*/', $alsoRaw) ?: [] as $a) {
        $a = logimport_sanitize_paste(trim((string) $a));
        if ($a !== '') {
            $add[] = $a;
        }
    }
    $add = array_values(array_unique($add));
    if ($add === []) {
        $GLOBALS['LOGIMPORT_ERROR'] = 'no also-spellings to add';
        return;
    }
    $book = logimport_encodes_list($wip);
    $claimedOther = [];
    foreach ($book as $e) {
        if (($e['id'] ?? '') === $rid) {
            continue;
        }
        $claimedOther[strtolower($e['original'])] = true;
        foreach ($e['also'] as $a) {
            $claimedOther[strtolower($a)] = true;
        }
    }
    $found = false;
    $addedN = 0;
    $skipped = [];
    foreach ($book as &$e) {
        if (($e['id'] ?? '') !== $rid) {
            continue;
        }
        $found = true;
        $onRow = [strtolower($e['original']) => true];
        foreach ($e['also'] as $a) {
            $onRow[strtolower($a)] = true;
        }
        foreach ($add as $a) {
            $lk = strtolower($a);
            if (!empty($onRow[$lk])) {
                $skipped[] = $a . ' (already on this row)';
                continue;
            }
            if (!empty($claimedOther[$lk])) {
                $skipped[] = $a . ' (used on another encode)';
                continue;
            }
            $e['also'][] = $a;
            $onRow[$lk] = true;
            $addedN++;
        }
        $e['also'] = array_values(array_unique($e['also']));
        break;
    }
    unset($e);
    if (!$found) {
        $GLOBALS['LOGIMPORT_ERROR'] = 'encode row not found';
        return;
    }
    if ($addedN === 0) {
        $GLOBALS['LOGIMPORT_ERROR'] = 'nothing new added'
            . ($skipped ? (': ' . implode('; ', $skipped)) : '');
        return;
    }
    $wip['encodes'] = $book;
    $status = 'enc_also_ok';
    $fragExtra = '#li-encode';
} elseif ($action === 'push_encode_ven') {
    $wip['segments'] = logimport_segments_collapse_trivial($segments, $lastSeq);
    $rid = (string) ($_POST['push_encode_ven'] ?? '');
    $enc = null;
    foreach (logimport_encodes_list($wip) as $e) {
        if (($e['id'] ?? '') === $rid) {
            $enc = $e;
            break;
        }
    }
    if (!$enc) {
        $GLOBALS['LOGIMPORT_ERROR'] = 'encode row not found for VEN push';
        return;
    }
    $push = logimport_encode_push_ven($enc, $faceKey);
    if (empty($push['ok'])) {
        $GLOBALS['LOGIMPORT_ERROR'] = $push['error'] ?? 'VEN push failed';
        return;
    }
    if (!empty($push['c_uid'])) {
        $GLOBALS['LOGIMPORT_VEN_CUID'] = (string) $push['c_uid'];
    }
    // stamp kven back onto encode if code was empty/minted
    if (!empty($push['kven'])) {
        $book = logimport_encodes_list($wip);
        foreach ($book as &$e) {
            if (($e['id'] ?? '') === $rid) {
                $e['code'] = $push['kven'];
                break;
            }
        }
        unset($e);
        $wip['encodes'] = $book;
    }
    $status = 'enc_ven_ok';
    $fragExtra = '#li-encode';
} elseif ($action === 'remove_encode') {
    $wip['segments'] = logimport_segments_collapse_trivial($segments, $lastSeq);
    $rid = (string) ($_POST['remove_encode'] ?? '');
    $wip['encodes'] = array_values(array_filter(
        logimport_encodes_list($wip),
        static fn($e) => ($e['id'] ?? '') !== $rid
    ));
    $status = 'enc_rm';
    $fragExtra = '#li-encode';
} elseif ($action === 'add_redact_phrase') {
    $wip['segments'] = logimport_segments_collapse_trivial($segments, $lastSeq);
    $orig = logimport_sanitize_paste(trim((string) ($_POST['red_original'] ?? '')));
    $label = logimport_sanitize_paste(trim((string) ($_POST['red_label'] ?? '')));
    if ($orig === '') {
        $GLOBALS['LOGIMPORT_ERROR'] = 'redact needs a phrase';
        return;
    }
    $book = logimport_redactions_list($wip);
    foreach ($book as $r) {
        if (($r['kind'] ?? '') === 'phrase' && strcasecmp((string) ($r['original'] ?? ''), $orig) === 0) {
            $GLOBALS['LOGIMPORT_ERROR'] = 'phrase already redacted';
            return;
        }
    }
    $book[] = [
        'id' => logimport_new_id('r'),
        'kind' => 'phrase',
        'original' => $orig,
        'label' => $label,
        'created_at' => time(),
    ];
    $wip['redactions'] = $book;
    $status = 'red_ok';
    $fragExtra = '#li-redact';
} elseif ($action === 'add_redact_msg') {
    $wip['segments'] = logimport_segments_collapse_trivial($segments, $lastSeq);
    $seq = (int) ($_POST['add_redact_msg'] ?? -1);
    if ($seq < 0 || $seq > $lastSeq) {
        $GLOBALS['LOGIMPORT_ERROR'] = 'bad message seq for redact';
        return;
    }
    $book = logimport_redactions_list($wip);
    foreach ($book as $r) {
        if (($r['kind'] ?? '') === 'message' && (int) ($r['seq'] ?? -1) === $seq) {
            $GLOBALS['LOGIMPORT_ERROR'] = 'message already redacted';
            return;
        }
    }
    $book[] = [
        'id' => logimport_new_id('r'),
        'kind' => 'message',
        'seq' => $seq,
        'label' => '',
        'created_at' => time(),
    ];
    $wip['redactions'] = $book;
    $status = 'red_ok';
    $anchorSeq = $seq;
} elseif ($action === 'remove_redact') {
    $wip['segments'] = logimport_segments_collapse_trivial($segments, $lastSeq);
    $rid = (string) ($_POST['remove_redact'] ?? '');
    $wip['redactions'] = array_values(array_filter(
        logimport_redactions_list($wip),
        static fn($r) => ($r['id'] ?? '') !== $rid
    ));
    $status = 'red_rm';
    $fragExtra = '#li-redact';
} elseif ($action === 'apply_encode') {
    $wip['segments'] = logimport_segments_collapse_trivial($segments, $lastSeq);
    $wip['apply_encode'] = true;
    $status = 'apply_enc';
} elseif ($action === 'clear_apply_encode') {
    $wip['segments'] = logimport_segments_collapse_trivial($segments, $lastSeq);
    $wip['apply_encode'] = false;
    $status = 'raw_enc';
} elseif ($action === 'apply_redact') {
    $wip['segments'] = logimport_segments_collapse_trivial($segments, $lastSeq);
    $wip['apply_redact'] = true;
    $status = 'apply_red';
} elseif ($action === 'clear_apply_redact') {
    $wip['segments'] = logimport_segments_collapse_trivial($segments, $lastSeq);
    $wip['apply_redact'] = false;
    $status = 'raw_red';
} elseif ($action === 'submit_export') {
    $wip['segments'] = logimport_segments_collapse_trivial($segments, $lastSeq);
    if (!logimport_wip_save($faceKey, $wip)) {
        $GLOBALS['LOGIMPORT_ERROR'] = 'could not write wip before export';
        return;
    }
    $ex = logimport_export_submit($faceKey, $core, $wip);
    if (empty($ex['ok'])) {
        $GLOBALS['LOGIMPORT_ERROR'] = $ex['error'] ?? 'export failed';
        return;
    }
    $status = 'export_ok';
    $partsN = max(1, (int) ($ex['parts'] ?? 1));
    $exportHref = function_exists('sdk_href')
        ? sdk_href('exports', [
            'face' => $faceKey,
            'export_ok' => '1',
            'parts' => (string) $partsN,
        ])
        : ('/?room=exports&face=' . rawurlencode($faceKey) . '&export_ok=1&parts=' . $partsN);
    header('Location: ' . $exportHref);
    exit;
} else {
    $wip['segments'] = logimport_segments_collapse_trivial($segments, $lastSeq);
}

// Always keep yard_title / notes from merge
if (!logimport_wip_save($faceKey, $wip)) {
    $GLOBALS['LOGIMPORT_ERROR'] = 'could not write wip';
    return;
}

// sdk-import-station: always bounce to desk room (no alphabet path)
$q = 'room=desk&face=' . rawurlencode($faceKey) . '&' . $status . '=1';
$frag = $anchorSeq !== null ? '#li-msg-' . (int) $anchorSeq : $fragExtra;
header('Location: /?' . $q . $frag);
exit;
