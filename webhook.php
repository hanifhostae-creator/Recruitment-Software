<?php
// WhatsApp Business webhook (Meta Cloud API format; also accepts the same shape from 360dialog / Interakt).
define('NO_CSRF', true);
require __DIR__ . '/inc/boot.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $mode = $_GET['hub_mode'] ?? ($_GET['hub.mode'] ?? '');
    $token = $_GET['hub_verify_token'] ?? ($_GET['hub.verify_token'] ?? '');
    $challenge = $_GET['hub_challenge'] ?? ($_GET['hub.challenge'] ?? '');
    $ok = $mode === 'subscribe' && setting('wa_verify_token') && hash_equals((string)setting('wa_verify_token'), (string)$token);
    ins('wa_webhook_log', ['at' => now(), 'method' => 'GET', 'body' => 'verify mode=' . $mode, 'result' => $ok ? 'verified' : 'rejected']);
    if ($ok) { header('Content-Type: text/plain'); echo $challenge; } else { http_response_code(403); echo 'Verification failed'; }
    exit;
}
if ($method !== 'POST') { http_response_code(405); exit; }

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$stored = 0;
if (setting('wa_enabled') === '1' && is_array($data)) {
    foreach ($data['entry'] ?? [] as $entry) foreach ($entry['changes'] ?? [] as $ch) {
        $v = $ch['value'] ?? [];
        $names = [];
        foreach ($v['contacts'] ?? [] as $c) $names[$c['wa_id'] ?? ''] = $c['profile']['name'] ?? '';
        foreach ($v['messages'] ?? [] as $m) {
            if (!empty($m['id']) && val('SELECT id FROM wa_messages WHERE wa_message_id=?', [$m['id']])) continue;
            $from = '+' . ltrim((string)($m['from'] ?? ''), '+');
            $body = $m['text']['body'] ?? ($m['button']['text'] ?? ($m['interactive']['button_reply']['title'] ?? '[' . ($m['type'] ?? 'message') . ']'));
            $digits = substr(preg_replace('/\D/', '', $from), -9);
            $cand = $digits ? val("SELECT id FROM candidates WHERE replace(replace(phone,' ',''),'-','') LIKE ? OR replace(whatsapp,' ','') LIKE ? LIMIT 1", ['%' . $digits, '%' . $digits]) : null;
            ins('wa_messages', ['ref_no' => next_number('WA'), 'from_number' => $from, 'contact_name' => $names[$m['from'] ?? ''] ?? '', 'body' => $body, 'received_at' => isset($m['timestamp']) ? date('Y-m-d H:i:s', (int)$m['timestamp']) : now(),
                'channel' => 'Webhook', 'status' => $cand ? 'New' : 'Needs Review', 'candidate_id' => $cand ?: null, 'wa_message_id' => $m['id'] ?? null, 'created_at' => now(), 'archived' => 0]);
            $stored++;
        }
    }
}
ins('wa_webhook_log', ['at' => now(), 'method' => 'POST', 'body' => mb_substr((string)$raw, 0, 4000), 'result' => setting('wa_enabled') === '1' ? "stored $stored message(s)" : 'integration off — ignored']);
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'stored' => $stored]);
