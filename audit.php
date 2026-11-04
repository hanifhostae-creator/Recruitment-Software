<?php
require __DIR__ . '/inc/boot.php';
require_module('audit');
$w = ['1=1'];
$pp = [];
foreach (['actor' => 'actor LIKE ?', 'action' => 'action=?', 'tbl' => 'tbl=?'] as $k => $sql) if (g($k) !== '') { $w[] = $sql; $pp[] = $k === 'actor' ? '%' . g($k) . '%' : g($k); }
if (g('from')) { $w[] = 'at>=?'; $pp[] = g('from'); }
if (g('to')) { $w[] = 'at<=?'; $pp[] = g('to') . ' 23:59:59'; }
if (g('export')) {
    $all = rows('SELECT * FROM audit WHERE ' . implode(' AND ', $w) . ' ORDER BY id DESC', $pp);
    csv_out('audit-' . date('Ymd') . '.csv', ['When', 'Actor', 'Type', 'Action', 'Table', 'Record', 'Old', 'New', 'Reason', 'IP'], array_map(function ($a) { return [$a['at'], $a['actor'], $a['actor_type'], $a['action'], $a['tbl'], $a['rec_id'], $a['old_v'], $a['new_v'], $a['reason'], $a['ip']]; }, $all), 'audit');
}
$page = max(1, (int)g('page', 1));
$total = (int)val('SELECT COUNT(*) FROM audit WHERE ' . implode(' AND ', $w), $pp);
$list = rows('SELECT * FROM audit WHERE ' . implode(' AND ', $w) . ' ORDER BY id DESC LIMIT 50 OFFSET ' . (($page - 1) * 50), $pp);
page_start('Audit Report', 'audit');
page_head('Audit Report', 'eye', 'Who created, viewed, updated, approved, rejected, deleted or restored what — with old and new values. Super Admin only.');
echo '<form class="toolbar filters" method="get"><div class="search-in">' . icon('user', 15) . '<input name="actor" value="' . e(g('actor')) . '" placeholder="User"></div><select name="action" data-autosubmit><option value="">All actions</option>';
foreach (array_column(rows('SELECT DISTINCT action FROM audit ORDER BY action'), 'action') as $x) echo '<option' . (g('action') === $x ? ' selected' : '') . '>' . e($x) . '</option>';
echo '</select><select name="tbl" data-autosubmit><option value="">All records</option>';
foreach (array_filter(array_column(rows('SELECT DISTINCT tbl FROM audit ORDER BY tbl'), 'tbl')) as $x) echo '<option' . (g('tbl') === $x ? ' selected' : '') . '>' . e($x) . '</option>';
echo '</select><input type="date" name="from" value="' . e(g('from')) . '"><input type="date" name="to" value="' . e(g('to')) . '"><button class="btn ghost sm">Filter</button><a class="btn ghost sm" href="?' . e(http_build_query(array_merge($_GET, ['export' => 1]))) . '">' . icon('download', 14) . ' Export</a></form>';
card_open('', '<span class="muted small">' . $total . ' entries</span>', 'flush');
echo simple_table(['When', 'Who', 'Action', 'Record', 'Change', 'Reason', 'IP'], array_map(function ($a) {
    $chg = '';
    if ($a['new_v']) $chg = '<small>' . ($a['old_v'] ? '<s class="muted">' . e(mb_strimwidth($a['old_v'], 0, 80, '…')) . '</s><br>' : '') . e(mb_strimwidth($a['new_v'], 0, 120, '…')) . '</small>';
    $url = $a['tbl'] && $a['rec_id'] ? ent_url(array_search($a['tbl'], array_column($GLOBALS['ENT'], 'table', 'key')) ?: $a['tbl'], $a['rec_id']) : null;
    return [e(dtfmt($a['at'])), e($a['actor']), badge($a['action']), $a['tbl'] ? ($url ? '<a href="' . e($url) . '">' . e($a['tbl'] . ' #' . $a['rec_id']) . '</a>' : e($a['tbl'] . ($a['rec_id'] ? ' #' . $a['rec_id'] : ''))) : '', $chg, e($a['reason']), '<small class="muted">' . e($a['ip']) . '</small>'];
}, $list), 'No audit entries.');
$pages = (int)ceil($total / 50);
if ($pages > 1) { echo '<div class="table-foot"><span></span><div class="pager">'; for ($i = max(1, $page - 4); $i <= min($pages, $page + 4); $i++) echo '<a class="' . ($i === $page ? 'on' : '') . '" href="?' . e(http_build_query(array_merge($_GET, ['page' => $i]))) . '">' . $i . '</a>'; echo '</div></div>'; }
card_close();
page_end();
