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
