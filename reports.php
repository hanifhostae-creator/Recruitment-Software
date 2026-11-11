<?php
require __DIR__ . '/inc/boot.php';
require_module('reports');
$secs = $GLOBALS['REPORTS'];
$r = g('r', 'kpi');
if (!isset($secs[$r])) $r = 'kpi';
page_start('Reports', 'reports');
page_head('Reports', 'bar-chart', 'Executive KPIs, funnels, productivity, finance aging, document &amp; visa tracking, demand progress and the Candidate Journey.', '<button class="btn ghost sm no-print" data-print>' . icon('printer', 14) . ' Print</button>');
echo '<nav class="tabs">';
foreach ($secs as $k => $s) echo '<a class="' . ($k === $r ? 'on' : '') . '" href="?r=' . e($k) . '">' . e($s['label']) . '</a>';
echo '</nav>';
($secs[$r]['fn'])();
page_end();
