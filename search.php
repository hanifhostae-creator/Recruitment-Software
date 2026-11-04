<?php
require __DIR__ . '/inc/boot.php';
require_login();
$s = trim(g('q'));
page_start('Search', 'search');
page_head('Search', 'search', 'One search box across candidates, files, clients, demands, agents, batches, workers, receipts and issues.');
echo '<form class="card" method="get"><div class="search-in" style="max-width:640px">' . icon('search', 18) . '<input name="q" value="' . e($s) . '" placeholder="Name, phone, email, passport, CAN-, IN-FILE-, DEM-, RCT-…" autofocus></div></form>';
if (strlen($s) >= 2) {
    $res = [];
    foreach ($GLOBALS['SEARCH'] as $p) if (can($p['mod'])) foreach (($p['fn'])($s) as $r) $res[] = $r;
    audit('search', null, null, null, ['q' => $s, 'results' => count($res)]);
    card_open(icon('search', 17) . ' ' . count($res) . ' result' . (count($res) === 1 ? '' : 's') . ' for “' . e($s) . '”');
    echo simple_table(['Type', 'Record', 'Detail'], array_map(function ($r) { return [badge($r[0]), '<b>' . e($r[1]) . '</b>', '<span class="muted">' . e($r[2]) . '</span>']; }, $res), 'Nothing found. Try a phone number, a record number or part of a name.', array_column($res, 3));
    card_close();
} elseif ($s !== '') echo '<div class="alert info">Type at least 2 characters.</div>';
page_end();
