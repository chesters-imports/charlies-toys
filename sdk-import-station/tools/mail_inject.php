#!/usr/bin/env php
<?php
/**
 * Narrative inject — fake outside email into a station inbox.
 *
 *   php tools/mail_inject.php --to=io --from="Chester <out@wire>" --subject="ping" --body="hello"
 *   php tools/mail_inject.php --to=ab --from="Unknown line" --subject="watch" --body-file=note.txt
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/mail/mail_lib.php';

$opts = getopt('', ['to:', 'from:', 'subject:', 'body:', 'body-file:', 'from-key:', 'help']);
if (isset($opts['help']) || !isset($opts['to'])) {
    fwrite(STDERR, "Usage: php tools/mail_inject.php --to=io|ab --from=LABEL --subject=S --body=TEXT|--body-file=PATH\n");
    exit(isset($opts['help']) ? 0 : 1);
}

$to = (string) $opts['to'];
$from = (string) ($opts['from'] ?? 'unknown@outside.wire');
$subject = (string) ($opts['subject'] ?? '(no subject)');
$body = (string) ($opts['body'] ?? '');
if (isset($opts['body-file']) && is_file((string) $opts['body-file'])) {
    $body = (string) file_get_contents((string) $opts['body-file']);
}
$fromKey = isset($opts['from-key']) ? (string) $opts['from-key'] : null;

$r = sdk_mail_inject($to, $from, $subject, $body, $fromKey);
if (!$r['ok']) {
    fwrite(STDERR, 'inject failed: ' . ($r['error'] ?? '?') . "\n");
    exit(1);
}
$m = $r['mail'];
echo "injected {$m['id']} → {$m['to']}\n";
echo "  from: {$m['from_label']}\n";
echo "  subject: {$m['subject']}\n";
exit(0);
