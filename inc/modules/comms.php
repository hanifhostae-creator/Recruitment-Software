<?php
// Module 3 — Communication & content: WhatsApp, messenger, calendar, meetings, email & notification templates,
// CMS, media, knowledge base, FAQ, newsletter, support dashboard, company goals.

ent('wa_messages', ['label' => 'WhatsApp Inbox', 'one' => 'WhatsApp enquiry', 'prefix' => 'WA', 'title' => 'contact_name', 'title_ref' => true, 'icon' => 'chat', 'order' => "CASE status WHEN 'New' THEN 0 WHEN 'Needs Review' THEN 1 ELSE 2 END, id DESC",
    'fields' => ['from_number' => ['From number', 'phone', 'req'], 'contact_name' => ['Contact name', 'text'], 'body' => ['Message', 'textarea', 'req'], 'received_at' => ['Received at', 'datetime', 'default' => date('Y-m-d H:i')],
        'channel' => ['Arrived via', 'enum:Webhook|Manual entry', 'default' => 'Manual entry'], 'category' => ['Category', 'opt:enquiry_categories'], 'status' => ['Status', 'enum:New|Needs Review|Assigned|Replied|Closed', 'default' => 'New'],
        'assigned_to' => ['Assigned to', 'user'], 'candidate_id' => ['Linked candidate', 'ref:candidates'], 'company_id' => ['Linked client', 'ref:companies'], 'demand_id' => ['Linked demand', 'ref:demands'],
        'booking_id' => ['Linked travel booking', 'ref:travel_bookings'], 'project_id' => ['Linked project', 'ref:biz_projects'], 'paybook_id' => ['Linked paybook file', 'ref:paybook_files'], 'wa_message_id' => ['Provider message ID', 'text', 'hide']],
    'list' => ['ref_no', 'contact_name', 'from_number', 'category', 'status', 'assigned_to', 'received_at'], 'filters' => ['status', 'category', 'assigned_to', 'channel'],
    'cols' => ['Message' => function ($r) { return '<span class="muted">' . e(mb_strimwidth((string)$r['body'], 0, 60, '…')) . '</span>'; }],
    'after_save' => function ($id, $d, $old) { if (!empty($d['assigned_to']) && (!$old || $old['assigned_to'] != $d['assigned_to'])) { notify($d['assigned_to'], 'WhatsApp enquiry assigned to you', ent_url('wa_messages', $id)); if (($d['status'] ?? '') === 'New') q("UPDATE wa_messages SET status='Assigned' WHERE id=?", [$id]); } },
    'actions' => ['reply' => ['label' => 'Reply note', 'icon' => 'send', 'perm' => 'edit', 'form' => function ($r) { echo '<div class="alert info">Replies are stored as conversation notes. Sending through a WhatsApp provider is a separate extension (see Settings).</div><div class="fld"><label>Reply / note *</label><textarea name="note" required rows="3"></textarea></div><div class="form-actions"><button class="btn">Save reply note</button></div>'; },
        'do' => function ($r) { if (!p('note')) return 'Write the reply.'; ins('wa_replies', ['message_id' => $r['id'], 'note' => p('note'), 'created_by' => me()['id'], 'created_at' => now(), 'archived' => 0]); q("UPDATE wa_messages SET status='Replied', updated_at=? WHERE id=?", [now(), $r['id']]); audit('reply', 'wa_messages', $r['id']); return null; }]]]);
ent('wa_replies', ['label' => 'Reply Notes', 'one' => 'Reply note', 'title' => 'note', 'icon' => 'message', 'fields' => ['message_id' => ['WhatsApp enquiry', 'ref:wa_messages', 'req'], 'note' => ['Reply / note', 'textarea', 'req']], 'list' => ['message_id', 'note', 'created_at']]);

ent('meetings', ['label' => 'Meetings', 'prefix' => 'MTG', 'title' => 'title', 'title_ref' => true, 'icon' => 'video', 'bl' => 'General', 'order' => 'meeting_at DESC',
    'fields' => ['title' => ['Meeting', 'text', 'req'], 'meeting_at' => ['Date & time', 'datetime', 'req'], 'duration' => ['Duration (minutes)', 'int', 'default' => 30], 'attendees' => ['Attendees', 'users'], 'external' => ['External attendees', 'text'],
        'location' => ['Location', 'text'], 'zoom_link' => ['Zoom / meeting link (paste the link)', 'url', 'help' => 'The system does not create Zoom meetings — paste the link you created.'], 'agenda' => ['Agenda', 'textarea'], 'minutes' => ['Minutes / outcome', 'textarea']],
    'list' => ['ref_no', 'title', 'meeting_at', 'business_line', 'location'],
    'cols' => ['Join' => function ($r) { return $r['zoom_link'] ? '<a href="' . e($r['zoom_link']) . '" target="_blank" rel="noopener">' . icon('video', 14) . ' Join</a>' : ''; }],
    'after_save' => function ($id, $d, $old) { if (!$old && !empty($d['attendees'])) foreach (explode(',', $d['attendees']) as $uid) notify((int)$uid, 'Meeting: ' . $d['title'] . ' on ' . dtfmt($d['meeting_at']), ent_url('meetings', $id)); }]);
ent('email_templates', ['label' => 'Email Templates', 'one' => 'Email template', 'title' => 'name', 'icon' => 'mail', 'fields' => ['name' => ['Template name', 'text', 'req'], 'subject' => ['Subject', 'text', 'req'], 'body' => ['Body (use {name}, {link}, {ref})', 'textarea', 'req'], 'active' => ['Active', 'bool', 'default' => 1]], 'list' => ['name', 'subject', 'active'],
    'actions' => ['send' => ['label' => 'Send test / send', 'icon' => 'send', 'perm' => 'edit', 'form' => function ($r) { echo '<div class="grid-form"><div class="fld"><label>To (email) *</label><input type="email" name="to" required></div><div class="fld"><label>Name</label><input name="name"></div></div><div class="form-actions"><button class="btn">Send</button></div>'; },
        'do' => function ($r) { $body = str_replace(['{name}', '{link}', '{ref}'], [p('name'), setting('site_url'), ''], $r['body']); $ok = send_email(p('to'), $r['subject'], $body, $r['name']); return ['msg' => $ok ? 'Email sent and logged.' : 'The hosting server did not accept the email. The attempt is in the email log.']; }]]]);
ent('email_log', ['label' => 'Email Log', 'one' => 'Email', 'title' => 'subject', 'icon' => 'inbox', 'immutable' => true,
    'fields' => ['to_addr' => ['To', 'email', 'req'], 'subject' => ['Subject', 'text', 'req'], 'body' => ['Body', 'textarea'], 'template' => ['Template', 'text'], 'status' => ['Status', 'enum:Sent|Failed|Logged', 'default' => 'Logged'], 'sent_at' => ['Sent at', 'datetime', 'default' => date('Y-m-d H:i')]],
    'list' => ['sent_at', 'to_addr', 'subject', 'template', 'status'], 'filters' => ['status']]);
ent('notification_templates', ['label' => 'Notification Templates', 'one' => 'Notification template', 'title' => 'event', 'icon' => 'bell', 'fields' => ['event' => ['Event', 'text', 'req'], 'text' => ['Notification text', 'textarea', 'req'], 'channel' => ['Channel', 'enum:In-app|Email|Both', 'default' => 'In-app'], 'active' => ['Active', 'bool', 'default' => 1]], 'list' => ['event', 'channel', 'active']]);
ent('cms_pages', ['label' => 'Content Pages', 'one' => 'Page', 'title' => 'title', 'icon' => 'file-text', 'fields' => ['title' => ['Title', 'text', 'req'], 'slug' => ['Slug', 'text', 'req'], 'body' => ['Content', 'textarea', 'req'], 'status' => ['Status', 'enum:Draft|Published', 'default' => 'Draft'], 'show_in' => ['Show in', 'enum:Staff help|Candidate portal|Client portal|Agent portal', 'default' => 'Staff help']], 'list' => ['title', 'slug', 'show_in', 'status'], 'filters' => ['status', 'show_in'],
    'before_save' => function (&$d, $old) { $d['slug'] = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $d['slug']), '-')); return null; }]);
ent('media', ['label' => 'Media Library', 'one' => 'Media file', 'title' => 'title', 'icon' => 'image', 'fields' => ['title' => ['Title', 'text', 'req'], 'file' => ['File', 'file', 'req_new', 'dir' => 'media', 'accept' => '.jpg,.jpeg,.png,.pdf,.doc,.docx'], 'kind' => ['Type', 'enum:Image|Document|Logo|Other', 'default' => 'Image'], 'alt' => ['Description', 'text']], 'list' => ['title', 'kind', 'file', 'created_at'], 'filters' => ['kind']]);
ent('kb_articles', ['label' => 'Knowledge Base', 'one' => 'Article', 'title' => 'title', 'icon' => 'book', 'bl' => 'General', 'fields' => ['title' => ['Title', 'text', 'req'], 'category' => ['Category', 'text'], 'body' => ['Article', 'textarea', 'req'], 'status' => ['Status', 'enum:Draft|Published', 'default' => 'Published']], 'list' => ['title', 'category', 'business_line', 'status'], 'filters' => ['status']]);
ent('faqs', ['label' => 'FAQ', 'one' => 'FAQ', 'title' => 'question', 'icon' => 'help', 'bl' => 'General', 'fields' => ['question' => ['Question', 'text', 'req'], 'answer' => ['Answer', 'textarea', 'req'], 'show_in' => ['Show in', 'enum:Staff|Candidate portal|Client portal|Agent portal', 'default' => 'Candidate portal'], 'sort' => ['Order', 'int']], 'list' => ['question', 'business_line', 'show_in', 'sort']]);
ent('subscribers', ['label' => 'Newsletter Subscribers', 'one' => 'Subscriber', 'title' => 'email', 'icon' => 'mail', 'dupe' => ['email'], 'fields' => ['email' => ['Email', 'email', 'req'], 'name' => ['Name', 'text'], 'source' => ['Source', 'opt:sources'], 'status' => ['Status', 'enum:Subscribed|Unsubscribed', 'default' => 'Subscribed']], 'list' => ['email', 'name', 'source', 'status', 'created_at'], 'filters' => ['status']]);
ent('company_goals', ['label' => 'Company Goals', 'one' => 'Goal', 'title' => 'title', 'icon' => 'target', 'bl' => 'General', 'fields' => ['title' => ['Goal', 'text', 'req'], 'target' => ['Target / measure', 'text'], 'target_date' => ['Target date', 'date'], 'owner' => ['Owner', 'user'], 'status' => ['Status', 'enum:On Track|At Risk|Achieved|Missed', 'default' => 'On Track']],
    'list' => ['title', 'business_line', 'target_date', 'owner', 'status'], 'filters' => ['status'], 'cols' => ['Progress' => function ($r) { return progress(goal_progress($r['id'])); }]]);
ent('goal_milestones', ['label' => 'Goal Milestones', 'one' => 'Goal milestone', 'title' => 'name', 'icon' => 'flag', 'fields' => ['goal_id' => ['Goal', 'ref:company_goals', 'req'], 'name' => ['Milestone', 'text', 'req'], 'due_date' => ['Due', 'date'], 'done' => ['Done', 'bool']], 'list' => ['goal_id', 'name', 'due_date', 'done']]);
function goal_progress($gid) { $t = row('SELECT COUNT(*) n, SUM(done) d FROM goal_milestones WHERE goal_id=? AND archived=0', [$gid]); return $t['n'] ? round($t['d'] / $t['n'] * 100) : 0; }

mod('whatsapp', ['label' => 'WhatsApp', 'icon' => 'chat', 'area' => 'Communication', 'order' => 1, 'desc' => 'Central WhatsApp inbox: categories, assignment, links to records, reply notes, manual review queue, integration health and reports.',
    'tabs' => [
        'inbox' => 'wa_messages',
        'review' => ['label' => 'Manual review queue', 'ent' => 'wa_messages', 'nohome' => true, 'noadd' => true, 'scope' => function () { return ["status IN ('New','Needs Review') AND assigned_to IS NULL", []]; }],
        'replies' => 'wa_replies',
        'settings' => ['label' => 'Integration settings', 'perm' => 'config', 'post' => function ($base, $mod) {
            if (!is_post()) return;
            require_action($mod, 'config');
            if (p('disable')) { set_setting('wa_enabled', '0'); audit('wa-disable', 'settings'); flash('WhatsApp integration switched off.'); redirect($base); }
            if (!p('wa_token') || !p('wa_phone_id')) { flash('Paste both the access token and the Phone Number ID.', 'bad'); redirect($base); }
            set_setting('wa_token', p('wa_token')); set_setting('wa_phone_id', p('wa_phone_id')); set_setting('wa_provider', p('wa_provider'));
            if (!setting('wa_verify_token')) set_setting('wa_verify_token', bin2hex(random_bytes(12)));
            set_setting('wa_enabled', '1');
            audit('wa-enable', 'settings', null, null, ['provider' => p('wa_provider'), 'phone_id' => p('wa_phone_id')]);
            flash('WhatsApp integration is ON. Enter the webhook URL and verify token below in your provider dashboard.');
            redirect($base);
        }, 'fn' => function ($base, $mod) {
            $on = setting('wa_enabled') === '1';
            $url = (function_exists('portal_base_url') ? portal_base_url() : '') . 'webhook.php';
            echo '<div class="grid g2"><div class="card"><div class="card-h"><h3>' . icon('settings', 17) . ' Provider API details</h3>' . badge($on ? 'Active' : 'Not connected') . '</div><form method="post">' . csrf_field()
                . '<div class="fld" style="margin-bottom:12px"><label>Provider</label><select name="wa_provider">';
            foreach (['Meta (WhatsApp Cloud API)', '360dialog', 'Interakt', 'Other'] as $pv) echo '<option' . (setting('wa_provider') === $pv ? ' selected' : '') . '>' . e($pv) . '</option>';
            echo '</select></div><div class="fld" style="margin-bottom:12px"><label>API key / access token *</label><input type="password" name="wa_token" value="' . e(setting('wa_token') ? '••••••••' . substr(setting('wa_token'), -4) : '') . '" placeholder="Paste the access token" autocomplete="off"></div>'
                . '<div class="fld" style="margin-bottom:12px"><label>Phone Number ID *</label><input name="wa_phone_id" value="' . e(setting('wa_phone_id')) . '"></div><div class="form-actions"><button class="btn">Save and switch on</button>' . ($on ? ' <button class="btn ghost danger" name="disable" value="1">Switch off</button>' : '') . '</div></form></div>';
            echo '<div class="card"><div class="card-h"><h3>' . icon('link', 17) . ' Webhook (enter these at the provider)</h3></div>';
            if ($on) echo '<label>Webhook URL</label><div class="copybox"><input id="whurl" readonly value="' . e($url) . '"><button type="button" class="btn ghost" data-copy="whurl">Copy</button></div><label style="margin-top:12px">Verify token</label><div class="copybox"><input id="whtok" readonly value="' . e(setting('wa_verify_token')) . '"><button type="button" class="btn ghost" data-copy="whtok">Copy</button></div><p class="muted small" style="margin-top:10px">In the provider dashboard (Meta, 360dialog, Interakt…) paste the URL and token and subscribe to the <code>messages</code> webhook.</p>';
            else echo '<p class="muted">Save the provider details first — the webhook URL and verify token appear here.</p>';
            echo '<div class="alert warn" style="margin-top:12px"><b>Honest limitation:</b> real WhatsApp receiving needs a WhatsApp Business provider account; a personal WhatsApp number cannot be read automatically. Staff replies are stored as notes. True two-way sending (provider send API + message templates + opt-in) is a separate extension.</div></div></div>';
        }],
        'health' => ['label' => 'Integration health', 'fn' => function ($base, $mod) {
            $last = row('SELECT * FROM wa_webhook_log ORDER BY id DESC LIMIT 1');
            echo '<div class="kv" style="margin:0 0 16px"><div class="' . (setting('wa_enabled') === '1' ? 'good' : 'bad') . '"><span>Integration</span><b>' . (setting('wa_enabled') === '1' ? 'On' : 'Off') . '</b></div><div><span>Last webhook call</span><b>' . e($last ? ago($last['at']) : 'Never') . '</b></div><div><span>Webhook calls (7 days)</span><b>' . (int)val('SELECT COUNT(*) FROM wa_webhook_log WHERE at>=?', [date('Y-m-d', strtotime('-7 days'))]) . '</b></div><div><span>Messages via webhook</span><b>' . (int)val("SELECT COUNT(*) FROM wa_messages WHERE channel='Webhook'") . '</b></div></div>';
            card_open(icon('activity', 17) . ' Webhook event log');
            echo simple_table(['When', 'Method', 'Result', 'Payload'], array_map(function ($l) { return [e(dtfmt($l['at'])), e($l['method']), e($l['result']), '<code>' . e(mb_strimwidth((string)$l['body'], 0, 110, '…')) . '</code>']; }, rows('SELECT * FROM wa_webhook_log ORDER BY id DESC LIMIT 40')), 'No webhook calls yet.');
            card_close();
        }],
        'reports' => ['label' => 'Reports', 'fn' => function ($base, $mod) { $c = []; foreach (rows('SELECT category k, COUNT(*) n FROM wa_messages WHERE archived=0 GROUP BY k') as $r) $c[$r['k'] ?: 'Uncategorised'] = (int)$r['n']; $s = []; foreach (rows('SELECT status k, COUNT(*) n FROM wa_messages WHERE archived=0 GROUP BY k') as $r) $s[$r['k']] = (int)$r['n']; $a = []; foreach (rows('SELECT assigned_to k, COUNT(*) n FROM wa_messages WHERE archived=0 GROUP BY k') as $r) $a[user_name($r['k']) ?: 'Unassigned'] = (int)$r['n']; echo '<div class="grid g3"><div class="card"><div class="card-h"><h3>By category</h3></div>' . chart_donut($c) . '</div><div class="card"><div class="card-h"><h3>By status</h3></div>' . chart_bars($s) . '</div><div class="card"><div class="card-h"><h3>By staff</h3></div>' . chart_bars($a) . '</div></div>'; }],
    ]]);
mod('messenger', ['label' => 'Messenger', 'icon' => 'message', 'area' => 'Home', 'order' => 6]);
mod('calendar', ['label' => 'Calendar', 'icon' => 'calendar', 'area' => 'Home', 'order' => 7]);
mod('meetings', ['label' => 'Meetings', 'icon' => 'video', 'area' => 'Home', 'order' => 8, 'tabs' => ['upcoming' => ['label' => 'Upcoming', 'ent' => 'meetings', 'nohome' => true, 'scope' => function () { return ['meeting_at>=?', [date('Y-m-d')]]; }], 'all' => 'meetings']]);
mod('email', ['label' => 'Email & Notifications', 'icon' => 'mail', 'area' => 'Communication', 'order' => 2, 'tabs' => ['templates' => 'email_templates', 'log' => 'email_log', 'notif' => 'notification_templates']]);
mod('cms', ['label' => 'CMS Pages', 'icon' => 'file-text', 'area' => 'Communication', 'order' => 3, 'tabs' => ['pages' => 'cms_pages']]);
mod('media', ['label' => 'Media Library', 'icon' => 'image', 'area' => 'Communication', 'order' => 4, 'tabs' => ['media' => 'media']]);
mod('kb', ['label' => 'Knowledge Base', 'icon' => 'book', 'area' => 'Communication', 'order' => 5, 'tabs' => ['articles' => 'kb_articles', 'faq' => 'faqs', 'subscribers' => 'subscribers']]);
mod('support', ['label' => 'Support', 'icon' => 'headphones', 'area' => 'Communication', 'order' => 6, 'desc' => 'Support dashboard and tickets. Visible to the Super Admin by default; others only when the module is ticked in Staff & Roles.',
    'tabs' => ['dash' => ['label' => 'Support dashboard', 'fn' => function ($base, $mod) {
        render_widgets(['support', 'it']);
        $p = []; foreach (rows("SELECT priority k, COUNT(*) n FROM support_tickets WHERE archived=0 AND status NOT IN ('Resolved','Closed') GROUP BY k") as $r) $p[$r['k']] = (int)$r['n'];
        $a = []; foreach (rows("SELECT assigned_to k, COUNT(*) n FROM support_tickets WHERE archived=0 AND status NOT IN ('Resolved','Closed') GROUP BY k") as $r) $a[user_name($r['k']) ?: 'Unassigned'] = (int)$r['n'];
        echo '<div class="grid g2"><div class="card"><div class="card-h"><h3>Open tickets by priority</h3></div>' . chart_donut($p) . '</div><div class="card"><div class="card-h"><h3>Open tickets by owner</h3></div>' . chart_bars($a) . '</div></div>';
    }], 'tickets' => ['label' => 'Tickets', 'ent' => 'support_tickets', 'nohome' => true]]]);
mod('goals', ['label' => 'Company Goals', 'icon' => 'target', 'area' => 'Control', 'order' => 6, 'tabs' => ['goals' => 'company_goals', 'milestones' => 'goal_milestones']]);

widget('whatsapp', 'WhatsApp waiting', function () { return (int)val("SELECT COUNT(*) FROM wa_messages WHERE status IN ('New','Needs Review') AND archived=0"); }, 'whatsapp.php', 'chat', 'green');
widget('support', 'Open support tickets', function () { return (int)val("SELECT COUNT(*) FROM support_tickets WHERE status NOT IN ('Resolved','Closed') AND archived=0"); }, 'support.php?t=tickets', 'headphones', 'amber');

seeder(function () {
    $u = function ($n) { return (int)val('SELECT id FROM users WHERE username=?', [$n]); };
    $b = ['created_at' => now(), 'archived' => 0];
    $ali = (int)val("SELECT id FROM candidates WHERE full_name='Ali Khan'");
    ins('wa_messages', $b + ['ref_no' => next_number('WA'), 'from_number' => '+971521112233', 'contact_name' => 'Ali Khan', 'body' => 'Salam, what time is my interview tomorrow at Gulf Logistics?', 'received_at' => date('Y-m-d H:i:s', strtotime('-2 hours')), 'channel' => 'Manual entry', 'category' => 'Job Enquiry', 'status' => 'Assigned', 'assigned_to' => $u('deploy'), 'candidate_id' => $ali]);
    ins('wa_messages', $b + ['ref_no' => next_number('WA'), 'from_number' => '+971509876543', 'contact_name' => 'Unknown', 'body' => 'Do you have visa for my brother? He is in Pakistan.', 'received_at' => date('Y-m-d H:i:s', strtotime('-40 minutes')), 'channel' => 'Manual entry', 'status' => 'Needs Review']);
    ins('wa_messages', $b + ['ref_no' => next_number('WA'), 'from_number' => '+971567770001', 'contact_name' => 'Faisal Khan', 'body' => 'Can I add one more person to the Baku trip?', 'received_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'channel' => 'Manual entry', 'category' => 'Travel & Tour', 'status' => 'New', 'booking_id' => (int)val("SELECT id FROM travel_bookings WHERE customer='Faisal Khan'")]);
    ins('meetings', $b + ['ref_no' => next_number('MTG'), 'title' => 'Weekly management meeting', 'meeting_at' => date('Y-m-d 10:00:00', strtotime('next monday')), 'duration' => 60, 'attendees' => implode(',', [$u('admin'), $u('hr'), $u('deploy'), $u('finance')]), 'location' => 'Board room', 'zoom_link' => 'https://zoom.us/j/0000000000', 'agenda' => "1. Hiring targets\n2. Collections\n3. Outside demands", 'business_line' => 'General']);
    ins('meetings', $b + ['ref_no' => next_number('MTG'), 'title' => 'Gulf Construction progress call', 'meeting_at' => date('Y-m-d 15:00:00', strtotime('+2 days')), 'duration' => 30, 'attendees' => (string)$u('ops'), 'external' => 'Khalid Al Mansoori', 'business_line' => 'Outside Hiring']);
    ins('email_templates', $b + ['name' => 'Candidate registration link', 'subject' => 'Your RH Management portal registration', 'body' => "Dear {name},\n\nPlease complete your registration here: {link}\nThe link is valid for 7 days.\n\nRH Management", 'active' => 1]);
    ins('email_templates', $b + ['name' => 'Payment reminder', 'subject' => 'Payment reminder', 'body' => "Dear {name},\n\nThis is a reminder that your balance is due.\n\nRH Management", 'active' => 1]);
    ins('notification_templates', $b + ['event' => 'Candidate assigned to HR', 'text' => 'New candidate assigned to you: {name}', 'channel' => 'In-app', 'active' => 1]);
    ins('cms_pages', $b + ['title' => 'How the candidate journey works', 'slug' => 'candidate-journey', 'body' => "1. Register at Reception with your CV.\n2. HR interview.\n3. File opening.\n4. Deployment sends interview locations.\n5. Selection and joining.", 'status' => 'Published', 'show_in' => 'Candidate portal']);
    ins('kb_articles', $b + ['title' => 'How to open an inside file', 'category' => 'HR', 'body' => 'Open the candidate, click Interview, record Selected, then File decision → Yes and choose the fee plan.', 'status' => 'Published', 'business_line' => 'Inside Hiring']);
    ins('kb_articles', $b + ['title' => 'Closing an outside demand', 'category' => 'Operations', 'body' => 'Categorise every worker slot first, then use Close demand.', 'status' => 'Published', 'business_line' => 'Outside Hiring']);
    ins('faqs', $b + ['question' => 'When do I pay the balance?', 'answer' => 'The balance is due after you are selected, within the days written on your fee plan.', 'show_in' => 'Candidate portal', 'sort' => 1, 'business_line' => 'Inside Hiring']);
    ins('faqs', $b + ['question' => 'How do I report a problem?', 'answer' => 'Use the Help centre in your portal. Every message is answered by the deployment team.', 'show_in' => 'Candidate portal', 'sort' => 2, 'business_line' => 'Inside Hiring']);
    ins('subscribers', $b + ['email' => 'newsletter.fan@example.com', 'name' => 'Ahmad', 'source' => 'Website', 'status' => 'Subscribed']);
    $g = ins('company_goals', $b + ['title' => 'Place 300 inside candidates in 2026', 'target' => '300 placements', 'target_date' => date('Y-12-31'), 'owner' => $u('admin'), 'status' => 'On Track', 'business_line' => 'Inside Hiring']);
    foreach ([['Q1: 60 placements', 1], ['Q2: 140 placements', 1], ['Q3: 220 placements', 1], ['Q4: 300 placements', 0]] as $m) ins('goal_milestones', $b + ['goal_id' => $g, 'name' => $m[0], 'done' => $m[1]]);
    $g2 = ins('company_goals', $b + ['title' => 'Supply 500 outside workers', 'target' => '500 joined', 'target_date' => date('Y-12-31'), 'owner' => $u('ops'), 'status' => 'At Risk', 'business_line' => 'Outside Hiring']);
    foreach ([['Sign 5 new clients', 1], ['Approve 3 new agencies', 1], ['250 joined by June', 0], ['500 joined by December', 0]] as $m) ins('goal_milestones', $b + ['goal_id' => $g2, 'name' => $m[0], 'done' => $m[1]]);
    // sample CV so demo download links work
    $dir = ROOT . '/uploads/cv';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_file($dir . '/sample-cv.pdf')) @file_put_contents($dir . '/sample-cv.pdf', demo_pdf('Sample CV - RH Management demo'));
}, 60);

/** Minimal valid one-page PDF (used for demo CV files). */
function demo_pdf($text)
{
    $t = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    $objs = ["<< /Type /Catalog /Pages 2 0 R >>", "<< /Type /Pages /Kids [3 0 R] /Count 1 >>", "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>"];
    $stream = "BT /F1 20 Tf 60 760 Td ($t) Tj ET";
    $objs[] = "<< /Length " . strlen($stream) . " >>\nstream\n$stream\nendstream";
    $objs[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $pdf = "%PDF-1.4\n";
    $off = [];
    foreach ($objs as $i => $o) { $off[] = strlen($pdf); $pdf .= ($i + 1) . " 0 obj\n$o\nendobj\n"; }
    $x = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($off as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
    return $pdf . "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$x\n%%EOF";
}
