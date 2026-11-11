<?php
// Password reset for staff and the three portals. Links work once, expire in 60 minutes, and only a hash is stored.

const RESET_TYPES = ['staff' => 'Staff', 'candidate' => 'Candidate', 'client' => 'Client', 'agent' => 'Agent'];

function reset_table($type) { return ['staff' => 'users', 'candidate' => 'candidate_logins', 'client' => 'client_users', 'agent' => 'agent_users'][$type] ?? 'users'; }
function reset_login_url($type) { return ['staff' => 'login.php', 'candidate' => 'portal.php', 'client' => 'client_portal.php', 'agent' => 'agent_portal.php'][$type] ?? 'login.php'; }

function reset_account($type, $id)
{
    switch ($type) {
        case 'staff': return row('SELECT id, name, email, username FROM users WHERE id=?', [$id]);
        case 'candidate': return row('SELECT l.id, c.full_name AS name, c.email AS email, l.username FROM candidate_logins l JOIN candidates c ON c.id=l.candidate_id WHERE l.id=?', [$id]);
        case 'client': return row('SELECT id, name, email, username FROM client_users WHERE id=?', [$id]);
        case 'agent': return row('SELECT id, name, email, username FROM agent_users WHERE id=?', [$id]);
    }
    return null;
}
/** Find an active account by username or email. */
function reset_find($type, $ident)
{
    $i = strtolower(trim((string)$ident));
    if ($i === '' || strlen($i) > 190) return null;
    switch ($type) {
        case 'staff': return row('SELECT id, name, email, username FROM users WHERE active=1 AND (lower(username)=? OR lower(email)=?)', [$i, $i]);
        case 'candidate': return row('SELECT l.id, c.full_name AS name, c.email AS email, l.username FROM candidate_logins l JOIN candidates c ON c.id=l.candidate_id WHERE c.archived=0 AND (lower(l.username)=? OR lower(c.email)=?)', [$i, $i]);
        case 'client': return row('SELECT id, name, email, username FROM client_users WHERE active=1 AND archived=0 AND (lower(username)=? OR lower(email)=?)', [$i, $i]);
        case 'agent': return row('SELECT id, name, email, username FROM agent_users WHERE active=1 AND archived=0 AND (lower(username)=? OR lower(email)=?)', [$i, $i]);
    }
    return null;
}

/** Create a one-time link (older unused links of the same account stop working). */
function reset_issue($type, $id, $byUser = null)
{
    q('UPDATE password_resets SET used_at=? WHERE acct_type=? AND acct_id=? AND used_at IS NULL', [now(), $type, $id]);
    $token = bin2hex(random_bytes(32));
    ins('password_resets', ['acct_type' => $type, 'acct_id' => $id, 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        'created_at' => now(), 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli', 'requested_by' => $byUser]);
    return app_url() . 'reset.php?token=' . $token;
}
/** Create a link and email it. Returns [url, emailed (bool), address]. */
function reset_send($type, $id, $byUser = null, $intro = null)
{
    $a = reset_account($type, $id);
    $url = reset_issue($type, $id, $byUser);
    $emailed = false;
    if ($a && !empty($a['email'])) {
        $body = 'Dear ' . $a['name'] . ",\n\n" . ($intro ?: 'We received a request to reset the password of your RH Management account.')
            . "\n\nUse the button below to choose a new password. The link works once and expires in 60 minutes.\n\nIf you did not ask for this, you can ignore this email. Your password stays the same.";
        $emailed = send_email($a['email'], 'Reset your RH Management password', $body, 'Password reset', ['button' => ['Choose a new password', $url], 'sensitive' => true]);
    }
    audit('password-reset-link', reset_table($type), $id, null, ['type' => $type, 'emailed' => $emailed ? 'yes' : 'no'], $byUser ? 'Issued by staff' : 'Requested by the user');
    return [$url, $emailed, $a['email'] ?? null];
}
/** Valid, unused, unexpired link row or null. */
function reset_lookup($token)
{
    if (!preg_match('/^[a-f0-9]{64}$/', (string)$token)) return null;
    return row('SELECT * FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>=?', [hash('sha256', $token), now()]) ?: null;
}
function reset_apply($r, $password)
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    switch ($r['acct_type']) {
        case 'staff': q('UPDATE users SET password_hash=?, must_change=0 WHERE id=?', [$hash, $r['acct_id']]); break;
        case 'candidate': q('UPDATE candidate_logins SET password_hash=?, token=NULL, token_expires=NULL WHERE id=?', [$hash, $r['acct_id']]); break;
        case 'client': q('UPDATE client_users SET password_hash=? WHERE id=?', [$hash, $r['acct_id']]); break;
        case 'agent': q('UPDATE agent_users SET password_hash=? WHERE id=?', [$hash, $r['acct_id']]); break;
    }
    q('UPDATE password_resets SET used_at=? WHERE id=?', [now(), $r['id']]);
    audit('password-reset', reset_table($r['acct_type']), $r['acct_id'], null, ['type' => $r['acct_type']], 'Password changed with a reset link');
    $a = reset_account($r['acct_type'], $r['acct_id']);
    if ($a && !empty($a['email'])) send_email($a['email'], 'Your RH Management password was changed', 'Dear ' . $a['name'] . ",\n\nThe password of your RH Management account was just changed.\n\nIf this was not you, please contact us immediately.", 'Password changed');
}
