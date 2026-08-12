<?php
/**
 * POST handler for station mail (compose + mark read).
 */
declare(strict_types=1);

require_once __DIR__ . '/mail_lib.php';

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$station = sdk_station();
$action = isset($_POST['mail_action']) ? (string) $_POST['mail_action'] : '';

if ($action === 'send') {
    $to = isset($_POST['to']) ? strtolower(trim((string) $_POST['to'])) : '';
    $subject = isset($_POST['subject']) ? (string) $_POST['subject'] : '';
    $body = isset($_POST['body']) ? (string) $_POST['body'] : '';
    $r = sdk_mail_send($station, $to, $subject, $body);
    if (!$r['ok']) {
        $_SESSION['mail_flash'] = 'err:' . ($r['error'] ?? 'send failed');
    } else {
        $extra = '';
        if (!empty($r['desk']['leaf_id'])) {
            $extra = ' · desk ' . $r['desk']['leaf_id'] . ' → ' . ($r['desk']['address'] ?? 'archivist');
        }
        $_SESSION['mail_flash'] = 'ok:sent ' . ($r['mail']['id'] ?? '') . $extra;
    }
    header('Location: ' . sdk_href('mail', ['box' => 'sent']));
    exit;
}

if ($action === 'read') {
    $id = isset($_POST['id']) ? (string) $_POST['id'] : '';
    if ($id !== '') {
        sdk_mail_mark_read($id, $station);
    }
    header('Location: ' . sdk_href('mail', ['id' => $id]));
    exit;
}

header('Location: ' . sdk_href('mail'));
exit;
