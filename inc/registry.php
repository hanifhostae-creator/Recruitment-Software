<?php
// Module registry: every inc/modules/*.php file plugs its screens in here.

$GLOBALS['MODS'] = [];
$GLOBALS['WIDGETS'] = [];
$GLOBALS['SEARCH'] = [];
$GLOBALS['REPORTS'] = [];
$GLOBALS['SEEDERS'] = [];

const AREAS = ['Home', 'Inside Hiring', 'Outside Hiring', 'Sales & Marketing', 'People', 'Money', 'Operations', 'Travel & Services', 'Communication', 'Control'];

/**
 * Register a module (screen). $d: label, icon, area, page, tabs, menu (bool), desc.
 * tabs: 'tab' => 'entity_key' | ['label'=>..,'ent'=>key, ...crud options] | ['label'=>..,'fn'=>render, 'post'=>handler]
 */
function mod($key, array $d)
{
    $d += ['label' => ucfirst($key), 'icon' => 'grid', 'area' => 'Home', 'page' => $key . '.php', 'tabs' => [], 'menu' => true, 'desc' => '', 'order' => 50];
    $d['key'] = $key;
    $GLOBALS['MODS'][$key] = $d;
}
function module_label($key) { return $GLOBALS['MODS'][$key]['label'] ?? ucfirst($key); }
function widget($mod, $label, callable $fn, $link = '', $icon = 'activity', $tone = 'blue') { $GLOBALS['WIDGETS'][] = compact('mod', 'label', 'fn', 'link', 'icon', 'tone'); }
function search_provider($mod, callable $fn) { $GLOBALS['SEARCH'][] = ['mod' => $mod, 'fn' => $fn]; }
function report_section($key, $label, $mod, callable $fn) { $GLOBALS['REPORTS'][$key] = compact('label', 'mod', 'fn'); }
function seeder(callable $fn, $order = 50) { $GLOBALS['SEEDERS'][] = [$order, $fn]; }
function run_seeders()
{
    usort($GLOBALS['SEEDERS'], function ($a, $b) { return $a[0] <=> $b[0]; });
    foreach ($GLOBALS['SEEDERS'] as $s) $s[1]();
}

function registry_load()
{
    foreach (['core', 'inside', 'outside', 'finance', 'business', 'comms'] as $m) {
        $f = __DIR__ . '/modules/' . $m . '.php';
        if (is_file($f)) require_once $f;
    }
    foreach (glob(__DIR__ . '/modules/*.php') as $f) require_once $f;
    // every entity that takes payments also gets the (hidden) reversal handler
    foreach ($GLOBALS['ENT'] as $k => $d) {
        foreach ($d['actions'] as $a) if (($a['icon'] ?? '') === 'dollar' && !isset($d['actions']['_reverse']) && function_exists('reversal_action')) { $GLOBALS['ENT'][$k]['actions']['_reverse'] = reversal_action(); break; }
    }
    // entity home pages (first module tab that shows the entity)
    foreach ($GLOBALS['MODS'] as $mk => $m) foreach ($m['tabs'] as $tk => $t) {
        $ek = is_string($t) ? $t : ($t['ent'] ?? null);
        if ($ek && !isset($GLOBALS['ENT_HOME'][$ek]) && empty($t['nohome'])) $GLOBALS['ENT_HOME'][$ek] = ['mod' => $mk, 'url' => $m['page'] . '?t=' . $tk];
    }
}

/** Render a module page with tabs. Each X.php page is just: require 'inc/boot.php'; module_page('x'); */
function module_page($key)
{
    $m = $GLOBALS['MODS'][$key];
    require_module($key);
    $tabs = $m['tabs'];
    $visible = array_filter($tabs, function ($t) use ($key) { return !is_array($t) || empty($t['perm']) || can($key, $t['perm']); });
    $tk = g('t', array_keys($visible)[0] ?? '');
    if (!isset($visible[$tk])) $tk = array_keys($visible)[0];
    $t = $visible[$tk];
    $base = $m['page'] . '?t=' . $tk;
    $ek = is_string($t) ? $t : ($t['ent'] ?? null);
    $opt = is_array($t) ? $t : [];
    if ($ek) { if (crud_handle($ek, $base, $key, $opt)) return; }
    elseif (!empty($t['post']) && (is_post() || g('a') === 'export')) { $t['post']($base, $key); }
    page_start($m['label'], $key, false);
    echo '<div class="page-head"><div><h1>' . icon($m['icon'], 26) . ' ' . e($m['label']) . '</h1>' . ($m['desc'] ? '<p class="muted">' . e($m['desc']) . '</p>' : '') . '</div></div>';
    if (count($visible) > 1) {
        echo '<nav class="tabs" aria-label="' . e($m['label']) . ' sections">';
        foreach ($visible as $k => $x) {
            $lbl = is_string($x) ? entdef($x)['label'] : ($x['label'] ?? entdef($x['ent'])['label']);
            echo '<a class="' . ($k === $tk ? 'on' : '') . '" href="' . e($m['page'] . '?t=' . $k) . '">' . e($lbl) . '</a>';
        }
        echo '</nav>';
    }
    foreach (flash() as $f) echo '<div class="alert ' . e($f[0]) . '" role="status">' . $f[1] . '</div>';
    if ($ek) crud_render($ek, $base, $key, $opt);
    else ($t['fn'])($base, $key);
    page_end();
}

/** Dashboard widgets for modules the user can open. */
function render_widgets(array $only = null)
{
    echo '<div class="stats">';
    $i = 0;
    foreach ($GLOBALS['WIDGETS'] as $w) {
        if (!can($w['mod']) || ($only !== null && !in_array($w['mod'], $only, true))) continue;
        $v = ($w['fn'])();
        echo '<a class="stat t-' . e($w['tone']) . '" style="--d:' . ($i++ * 40) . 'ms" href="' . e($w['link'] ?: ($GLOBALS['MODS'][$w['mod']]['page'] ?? '#')) . '"><span class="stat-i">' . icon($w['icon'], 20) . '</span><span class="stat-v" data-count="' . e(is_numeric($v) ? $v : '') . '">' . e(is_numeric($v) ? num($v) : $v) . '</span><span class="stat-l">' . e($w['label']) . '</span></a>';
    }
    echo '</div>';
}
