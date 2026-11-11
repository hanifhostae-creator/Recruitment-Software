# Core API — what every module uses

## Defining screens
```php
ent('my_things', [                       // entity = table + generated CRUD screens
  'label' => 'My Things', 'prefix' => 'THG',   // prefix gives THG-2026-00001 numbers
  'bl' => 'General',                       // adds Business line field + filter
  'fields' => [
    'name'   => ['Name', 'text', 'req'],
    'status' => ['Status', 'enum:Open|Done', 'default' => 'Open'],
    'kind'   => ['Kind', 'opt:my_kinds'],  // configurable list (Admin > Custom options) + "Other"
    'owner'  => ['Owner', 'user'],
    'demand_id' => ['Demand', 'ref:demands'],  // link; shows as related list on the demand
  ],
  'list' => ['ref_no','name','status'], 'filters' => ['status'],
  'before_save' => function (&$d, $old) { return null; /* or error text */ },
  'after_save'  => function ($id, $d, $old) {},
  'actions' => ['approve' => ['label'=>'Approve','perm'=>'approve','show'=>fn($r)=>..., 'form'=>fn($r)=>..., 'do'=>fn($r)=>null]],
]);
mod('things', ['label'=>'Things','icon'=>'box','area'=>'Operations','tabs'=>['all'=>'my_things', 'dash'=>['label'=>'Dashboard','fn'=>function($base,$mod){...}]]]);
widget('things', 'Open things', fn() => (int)val("SELECT COUNT(*) FROM my_things WHERE status='Open'"), 'things.php', 'box', 'blue');
search_provider('things', fn($q) => [[ 'Thing', 'title', 'detail', 'url' ]]);
report_section('things', 'Things report', 'reports', function () {...});
seeder(function () { /* demo rows */ }, 50);
```
Field types: text textarea email phone url int number money date datetime time bool password file
`opt:list` `enum:A|B` `ref:entity` `user` `users` `bl`. Flags: `req`, `req_new`, `hide`, `new_only`, `norel`.

## Helpers (inc/functions.php, inc/auth.php)
| Helper | Purpose |
|---|---|
| `q/rows/row/val/ins/upd` | database access (PDO, prepared) |
| `e()` | escape output — always |
| `csrf_field()` | in every POST form (checked automatically) |
| `can($mod, $act)`, `require_module()`, `require_action()` | permissions (actions: add edit delete approve assign export finance config) |
| `audit($action, $table, $id, $old, $new, $reason)` | audit trail |
| `next_number('PFX')` | unique record number |
| `stage_advance($table, $id, $to, $reason, $field='stage')` | forward-only stages + history |
| `record_payment($entity, $id, $amount, [direction, method, pay_type, pay_date])` | the one payment logic; issues RCT-/PAY-, posts a balanced journal |
| `reverse_payment($pid, $reason)` / `paid_total($entity, $id, $dir)` | corrections and balances |
| `post_journal($date, $memo, [[code_or_id, dr, cr], ...])` / `reverse_journal()` | double entry |
| `pay_action($entity, $label, $perm, $show, $dir)` + `payments_panel()` | ready-made Pay button and receipts list |
| `upload_file($field, $dir)` / `file_link($path)` | uploads (10 MB, PDF/DOC/DOCX/JPG/PNG), downloads via file.php |
| `notify($uid, $text, $link)` / `add_task(...)` | notifications and Tasks & Alerts |
| `options($list)` / `opt_defaults($list, [...])` | configurable dropdown values |
| `chart_bars/chart_cols/chart_donut/progress` | SVG charts, no library |
| `card_open/card_close/simple_table/badge/icon` | UI building blocks |

## Email, password reset, live alerts and private chat (v1.1)
| Piece | What it does |
|---|---|
| `send_email($to, $subject, $plain, $template, ['button'=>[label,url], 'sensitive'=>true])` | Sends a branded HTML email through the SMTP mailbox set in Settings → Email (falls back to PHP `mail()`). Every email is logged; `sensitive` hides secure links in the log. The failure reason is in `$GLOBALS['mail_error']`. |
| `app_url()` | Public address for email links. Uses Settings → System URL (set it, it prevents Host-header link tampering). |
| `reset_send($type, $id, $byUser)` | Creates a one-time reset link (60 min, only a SHA-256 hash is stored) and emails it. Types: staff, candidate, client, agent. Pages: `forgot.php`, `reset.php`. |
| `notify($uid, $text, $link)` / `notify_role('Reception', ...)` | In-app notification. `assets/app.js` polls `poll.php` every 12 s and plays a beep + pop-up for new ones (sound toggle in the top bar). |
| `chat_participants($cid)`, `chat_can($cid)`, `chat_scope()` | Private candidate chat. Only the assigned deployment officer and team can read it; the Super Admin can, and may grant others in Admin → Chat access. Non-participant views are audited. Attachments are served by `file.php` with the same check. |
