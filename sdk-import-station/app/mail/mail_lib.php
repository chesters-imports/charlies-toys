<?php
/**
 * Station mail — thin wire between terminals + outside inject.
 * Store: mail/messages/{id}.json  (shared; filtered by to/from for each station)
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config.php';

function sdk_mail_root(): string {
    $p = SDK_ROOT . '/mail';
    if (!is_dir($p)) {
        @mkdir($p, 0775, true);
    }
    $m = $p . '/messages';
    if (!is_dir($m)) {
        @mkdir($m, 0775, true);
    }
    return $p;
}

function sdk_mail_messages_dir(): string {
    return sdk_mail_root() . '/messages';
}

/** Inject token — local narrative door. Override with env SDK_MAIL_INJECT_TOKEN. */
function sdk_mail_inject_token(): string {
    $t = getenv('SDK_MAIL_INJECT_TOKEN');
    if (is_string($t) && trim($t) !== '') {
        return trim($t);
    }
    return 'wire-whisper';
}

function sdk_mail_id(): string {
    return 'mail.' . bin2hex(random_bytes(6));
}

/**
 * @return array{id:string,from:string,from_label:string,to:string,subject:string,body:string,created_at:string,external:bool,read:array<string,bool>}
 */
function sdk_mail_normalize(array $row): array {
    $id = (string) ($row['id'] ?? '');
    $from = strtolower(trim((string) ($row['from'] ?? '')));
    $to = strtolower(trim((string) ($row['to'] ?? '')));
    $read = $row['read'] ?? [];
    if (!is_array($read)) {
        $read = [];
    }
    $readOut = [];
    foreach ($read as $k => $v) {
        $readOut[strtolower((string) $k)] = (bool) $v;
    }
    return [
        'id' => $id !== '' ? $id : sdk_mail_id(),
        'from' => $from,
        'from_label' => (string) ($row['from_label'] ?? $from),
        'to' => $to,
        'subject' => (string) ($row['subject'] ?? '(no subject)'),
        'body' => (string) ($row['body'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? gmdate('c')),
        'external' => !empty($row['external']),
        'read' => $readOut,
    ];
}

function sdk_mail_path(string $id): string {
    $safe = preg_replace('/[^\w.\-]+/', '', $id) ?: 'bad';
    return sdk_mail_messages_dir() . '/' . $safe . '.json';
}

/** @return array<string,mixed>|null */
function sdk_mail_load(string $id): ?array {
    $p = sdk_mail_path($id);
    if (!is_file($p)) {
        return null;
    }
    $j = json_decode((string) file_get_contents($p), true);
    if (!is_array($j)) {
        return null;
    }
    return sdk_mail_normalize($j);
}

/** @param array<string,mixed> $msg */
function sdk_mail_save(array $msg): array {
    $msg = sdk_mail_normalize($msg);
    $p = sdk_mail_path($msg['id']);
    file_put_contents(
        $p,
        json_encode($msg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        LOCK_EX
    );
    return $msg;
}

/**
 * @return list<array<string,mixed>>
 */
function sdk_mail_list_for(string $station, string $box = 'inbox'): array {
    $station = strtolower($station);
    $dir = sdk_mail_messages_dir();
    $out = [];
    foreach (glob($dir . '/mail.*.json') ?: [] as $file) {
        $j = json_decode((string) file_get_contents($file), true);
        if (!is_array($j)) {
            continue;
        }
        $m = sdk_mail_normalize($j);
        // carry desk flags for UI
        if (!empty($j['desk'])) {
            $m['desk'] = true;
            $m['desk_leaf'] = (string) ($j['desk_leaf'] ?? '');
        }
        if ($box === 'sent') {
            // station-sent only (not external injects, unless from=station)
            if ($m['from'] !== $station) {
                continue;
            }
        } else {
            // inbox: to=station (desk addresses never appear as station inbox)
            if ($m['to'] !== $station) {
                continue;
            }
        }
        $out[] = $m;
    }
    usort($out, static function ($a, $b) {
        return strcmp((string) $b['created_at'], (string) $a['created_at']);
    });
    return $out;
}

function sdk_mail_unread_count(string $station): int {
    $n = 0;
    foreach (sdk_mail_list_for($station, 'inbox') as $m) {
        if (empty($m['read'][$station])) {
            $n++;
        }
    }
    return $n;
}

/**
 * Desk / Hermes destinations (Sophia inbox addresses).
 * Not station ids — bridge via Sophia /api/mail/send.
 */
function sdk_mail_is_desk_address(string $to): bool {
    $to = strtolower(trim($to));
    if ($to === '') {
        return false;
    }
    // explicit desk mailbox
    if (in_array($to, [
        'the.archivist',
        'archivist',
        'desk',
        'sophia',
        'sophiadesk',
        'adm',
        'adm.casestreet',
        'hermes.desk',
    ], true)) {
        return true;
    }
    // dotted addresses that are not station ids (the.archivist, ADM.something)
    if (str_contains($to, '.') && !sdk_station_valid($to)) {
        return true;
    }
    return false;
}

/** Canonical desk address for deliver_mail. */
function sdk_mail_desk_address(string $to): string {
    $to = strtolower(trim($to));
    $map = [
        'archivist' => 'the.archivist',
        'desk' => 'the.archivist',
        'sophia' => 'the.archivist',
        'sophiadesk' => 'the.archivist',
        'adm' => 'the.archivist',
        'adm.casestreet' => 'the.archivist',
        'hermes.desk' => 'the.archivist',
    ];
    return $map[$to] ?? $to;
}

/**
 * POST a chip-letter into SophiaDesk (same path Hermes uses).
 * @return array{ok:bool,error?:string,leaf_id?:string,address?:string}
 */
function sdk_mail_bridge_to_desk(
    string $deskAddress,
    string $title,
    string $body,
    string $fromLabel
): array {
    $url = getenv('SOPHIA_DESK_URL') ?: getenv('POCKET_DESKTOP_URL') ?: 'http://127.0.0.1:43167';
    $url = rtrim(str_replace('\\', '/', (string) $url), '/') . '/api/mail/send';
    $payload = json_encode([
        'address' => $deskAddress,
        'title' => $title,
        'body' => $body,
        'from' => $fromLabel,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return ['ok' => false, 'error' => 'encode failed'];
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $payload,
            'timeout' => 12,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return [
            'ok' => false,
            'error' => 'SophiaDesk unreachable · is it running on ' . preg_replace('#/api/mail/send$#', '', $url) . '?',
        ];
    }
    $j = json_decode($raw, true);
    if (!is_array($j)) {
        return ['ok' => false, 'error' => 'bad desk response'];
    }
    if (empty($j['ok'])) {
        return ['ok' => false, 'error' => (string) ($j['error'] ?? 'desk mail failed')];
    }
    return [
        'ok' => true,
        'leaf_id' => (string) ($j['leaf_id'] ?? ''),
        'address' => (string) ($j['address'] ?? $deskAddress),
    ];
}

/**
 * Send station → station, or station → desk (the.archivist via Sophia/Hermes path).
 * @return array{ok:bool,error?:string,mail?:array<string,mixed>,desk?:array<string,mixed>}
 */
function sdk_mail_send(string $fromStation, string $toStation, string $subject, string $body): array {
    $fromStation = strtolower(trim($fromStation));
    $toRaw = strtolower(trim($toStation));
    if (!sdk_station_valid($fromStation)) {
        return ['ok' => false, 'error' => 'bad from station'];
    }
    $subject = trim($subject);
    $body = trim($body);
    if ($subject === '' && $body === '') {
        return ['ok' => false, 'error' => 'empty mail'];
    }
    if ($subject === '') {
        $subject = '(no subject)';
    }
    $fromMeta = sdk_station_meta($fromStation);
    $fromLabel = ($fromMeta['user'] ?? strtoupper($fromStation)) . ' · ' . ($fromMeta['chip'] ?? $fromStation);

    // Desk bridge — keep a local SENT copy + mint chip on Sophia
    if (sdk_mail_is_desk_address($toRaw)) {
        $deskAddr = sdk_mail_desk_address($toRaw);
        $bridge = sdk_mail_bridge_to_desk($deskAddr, $subject, $body, $fromLabel);
        if (empty($bridge['ok'])) {
            return ['ok' => false, 'error' => $bridge['error'] ?? 'desk bridge failed'];
        }
        $msg = sdk_mail_save([
            'id' => sdk_mail_id(),
            'from' => $fromStation,
            'from_label' => $fromLabel,
            'to' => $deskAddr,
            'subject' => $subject,
            'body' => $body,
            'created_at' => gmdate('c'),
            'external' => false,
            'desk' => true,
            'desk_leaf' => $bridge['leaf_id'] ?? '',
            'read' => [
                $fromStation => true,
            ],
        ]);
        return ['ok' => true, 'mail' => $msg, 'desk' => $bridge];
    }

    $toStation = $toRaw;
    // aliases already resolved by compose ids; tolerate chip codes
    if (!sdk_station_valid($toStation)) {
        $aliases = [
            'iox' => 'io',
            'abx' => 'ab',
            'cux' => 'cu',
            'drx' => 'dr',
            'dco' => 'dc',
            'rx' => 'osx',
            'oriel' => 'osx',
        ];
        $toStation = $aliases[$toStation] ?? $toStation;
    }
    if (!sdk_station_valid($toStation)) {
        return ['ok' => false, 'error' => 'bad to station (or desk address)'];
    }
    if ($fromStation === $toStation) {
        return ['ok' => false, 'error' => 'cannot mail self (use paper)'];
    }
    $msg = sdk_mail_save([
        'id' => sdk_mail_id(),
        'from' => $fromStation,
        'from_label' => $fromLabel,
        'to' => $toStation,
        'subject' => $subject,
        'body' => $body,
        'created_at' => gmdate('c'),
        'external' => false,
        'read' => [
            $fromStation => true,
            $toStation => false,
        ],
    ]);
    return ['ok' => true, 'mail' => $msg];
}

/**
 * Outside / narrative inject (fake email from the wire).
 * @return array{ok:bool,error?:string,mail?:array<string,mixed>}
 */
function sdk_mail_inject(
    string $toStation,
    string $fromLabel,
    string $subject,
    string $body,
    ?string $fromKey = null
): array {
    $toStation = strtolower(trim($toStation));
    if (!sdk_station_valid($toStation)) {
        return ['ok' => false, 'error' => 'bad to station'];
    }
    $fromLabel = trim($fromLabel) !== '' ? trim($fromLabel) : 'unknown@outside.wire';
    $fromKey = $fromKey !== null && trim($fromKey) !== ''
        ? strtolower(trim($fromKey))
        : 'external';
    $subject = trim($subject);
    $body = (string) $body;
    if ($subject === '') {
        $subject = '(no subject)';
    }
    $msg = sdk_mail_save([
        'id' => sdk_mail_id(),
        'from' => $fromKey,
        'from_label' => $fromLabel,
        'to' => $toStation,
        'subject' => $subject,
        'body' => $body,
        'created_at' => gmdate('c'),
        'external' => true,
        'read' => [
            $toStation => false,
        ],
    ]);
    return ['ok' => true, 'mail' => $msg];
}

function sdk_mail_mark_read(string $id, string $station): bool {
    $m = sdk_mail_load($id);
    if ($m === null) {
        return false;
    }
    $station = strtolower($station);
    $m['read'][$station] = true;
    sdk_mail_save($m);
    return true;
}

/** Station options for compose (everyone except self) + desk archivist. @return list<array{id:string,label:string}> */
function sdk_mail_recipients(?string $except = null): array {
    $except = $except ?? sdk_station();
    $out = [];
    // Desk first — Hermes path → the.archivist leaf
    $out[] = [
        'id' => 'the.archivist',
        'label' => 'DESK · the.archivist (Sophia / Hermes)',
    ];
    foreach (sdk_station_order() as $id) {
        if ($id === $except) {
            continue;
        }
        $meta = sdk_station_meta($id);
        $out[] = [
            'id' => $id,
            'label' => ($meta['chip'] ?? strtoupper($id)) . ' · ' . ($meta['user'] ?? $id),
        ];
    }
    return $out;
}
