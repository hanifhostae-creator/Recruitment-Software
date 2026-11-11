<?php
// Outgoing email. Uses SMTP when it is configured in Settings (recommended: the mailbox you create on Hostinger),
// otherwise falls back to PHP mail(). Every attempt is written to the email log.

function mail_cfg()
{
    return ['host' => trim((string)setting('smtp_host', '')), 'port' => (int)(setting('smtp_port', '465') ?: 465), 'sec' => (string)(setting('smtp_sec', 'ssl') ?: 'ssl'),
        'user' => (string)setting('smtp_user', ''), 'pass' => secret_get('smtp_pass'),
        'from' => (string)(setting('smtp_from', '') ?: setting('company_email', 'info@rhmanagements.com')),
        'name' => (string)(setting('smtp_name', '') ?: setting('company_short', 'RH Management'))];
}
function mail_enc($s) { return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s; }

/** Public address of the system. Uses the System URL from Settings (safe); falls back to the request host. */
function app_url()
{
    $s = trim((string)setting('site_url', ''));
    if ($s !== '' && preg_match('#^https?://[A-Za-z0-9.-]+(:\d+)?(/[^\s]*)?$#', $s)) return rtrim($s, '/') . '/';
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir . '/';
}

/** Branded HTML version of a plain-text email. */
function email_html($title, $plain, $button = null)
{
    $body = preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#3d6bff">$1</a>', nl2br(e($plain)));
    $btn = $button ? '<p style="margin:26px 0"><a href="' . e($button[1]) . '" style="background:#3d6bff;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:9px;font-weight:700;display:inline-block">' . e($button[0]) . '</a></p><p style="font-size:12px;color:#78819c">If the button does not work, copy this link into your browser:<br><a href="' . e($button[1]) . '" style="color:#3d6bff;word-break:break-all">' . e($button[1]) . '</a></p>' : '';
    return '<!doctype html><html><body style="margin:0;background:#f3f5fa;font-family:Segoe UI,Arial,sans-serif;color:#3d4766"><table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px"><tr><td align="center">'
        . '<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e4e8f1"><tr><td style="background:#0b1430;color:#ffffff;padding:18px 26px;font-size:17px;font-weight:700">RH Management <span style="color:#dcaa48">&middot;</span> <span style="font-weight:400;font-size:13px;color:#c6cee6">' . e(setting('company_name', 'RH Business Solutions & Marketing Management')) . '</span></td></tr>'
        . '<tr><td style="padding:26px;font-size:15px;line-height:1.6"><h2 style="margin:0 0 14px;color:#0f1a3a;font-size:20px">' . e($title) . '</h2>' . $body . $btn . '</td></tr>'
        . '<tr><td style="padding:14px 26px;background:#f8f9fc;font-size:12px;color:#78819c;border-top:1px solid #e4e8f1">' . e(setting('company_name', 'RH Business Solutions & Marketing Management')) . ' &middot; ' . e(setting('company_address', 'Dubai, UAE')) . '</td></tr></table></td></tr></table></body></html>';
}

/** Build MIME headers and body. Returns [headers (no To/Subject), body]. */
function mail_build($plain, $html, array $c)
{
    $b = 'rh_' . bin2hex(random_bytes(8));
    $host = preg_replace('/[^A-Za-z0-9.-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    $name = preg_match('/[^\x20-\x7E]/', $c['name']) ? mail_enc($c['name']) : '"' . str_replace(['"', '\\', "\r", "\n"], '', $c['name']) . '"';
    $h = 'Date: ' . date('r') . "\r\nFrom: $name <{$c['from']}>\r\nReply-To: <{$c['from']}>\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . "@$host>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$b\"";
    $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($plain))
        . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$b--\r\n";
    return [$h, $body];
}

/** Send through an SMTP server (SSL on port 465 or STARTTLS on 587). Returns true or false and sets $err. */
function smtp_send($to, $subject, $plain, $html, &$err)
{
    $c = mail_cfg();
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $c['host']]]);
    $fp = @stream_socket_client(($c['sec'] === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $c['port'], $en, $es, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { $err = 'Could not connect to ' . $c['host'] . ':' . $c['port'] . ($es ? ' (' . $es . ')' : ''); return false; }
    stream_set_timeout($fp, 20);
    $reply = function () use ($fp) { $o = ''; while (($l = fgets($fp, 1024)) !== false) { $o .= $l; if (strlen($l) < 4 || $l[3] !== '-') break; } return $o; };
    $step = function ($line, $ok) use ($fp, $reply, &$err) {
        if ($line !== null) fwrite($fp, $line . "\r\n");
        $r = $reply();
        if ($r === '' || strpos(',' . $ok . ',', ',' . substr($r, 0, 3) . ',') === false) { $err = trim($r) ?: 'The mail server did not answer.'; return false; }
        return true;
    };
    $helo = preg_replace('/[^A-Za-z0-9.-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    $ok = $step(null, '220') && $step('EHLO ' . $helo, '250');
    if ($ok && $c['sec'] === 'tls') {
        $ok = $step('STARTTLS', '220');
        if ($ok && !@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { $err = 'The secure (TLS) connection to the mail server failed.'; $ok = false; }
        $ok = $ok && $step('EHLO ' . $helo, '250');
    }
    if ($ok && $c['user'] !== '') $ok = $step('AUTH LOGIN', '334') && $step(base64_encode($c['user']), '334') && $step(base64_encode($c['pass']), '235');
    if ($ok) {
        list($h, $body) = mail_build($plain, $html, $c);
        $ok = $step('MAIL FROM:<' . $c['from'] . '>', '250') && $step('RCPT TO:<' . $to . '>', '250,251') && $step('DATA', '354');
        if ($ok) {
            fwrite($fp, $h . "\r\nTo: <$to>\r\nSubject: " . mail_enc($subject) . "\r\n\r\n" . $body . "\r\n.\r\n");
            $ok = $step(null, '250');
        }
    }
    @fwrite($fp, "QUIT\r\n");
    @fclose($fp);
    return $ok;
}

/**
 * Send an email (plain text is turned into a branded HTML mail). $o: button => [label, url], sensitive => true
 * (the email log then hides the body because it holds a secure link). Returns true on success; the reason is in $GLOBALS['mail_error'].
 */
function send_email($to, $subject, $body, $template = null, array $o = [])
{
    $to = trim((string)$to);
    $subject = trim(preg_replace('/[\r\n]+/', ' ', (string)$subject));
    $ok = false;
    $err = '';
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) $err = 'The email address is not valid.';
    else {
        $plain = $body . (!empty($o['button']) ? "\n\n" . $o['button'][0] . ': ' . $o['button'][1] : '');
        $html = email_html($subject, $body, $o['button'] ?? null);
        $c = mail_cfg();
        if ($c['host'] !== '') $ok = smtp_send($to, $subject, $plain, $html, $err);
        else {
            list($h, $mbody) = mail_build($plain, $html, $c);
            $ok = @mail($to, mail_enc($subject), $mbody, $h);
            if (!$ok) $err = 'The server could not send the email. Set up SMTP in Settings (Email section).';
        }
    }
    $GLOBALS['mail_error'] = $err;
    try {
        ins('email_log', ['to_addr' => $to, 'subject' => $subject, 'body' => !empty($o['sensitive']) ? '[Secure link hidden]' : (string)$body . ($ok ? '' : "\n[Failed: $err]"), 'template' => $template,
            'status' => $ok ? 'Sent' : 'Failed', 'sent_at' => now(), 'created_by' => me()['id'] ?? null, 'created_at' => now(), 'archived' => 0]);
    } catch (Throwable $ex) { /* logging must never break the request */ }
    return $ok;
}
