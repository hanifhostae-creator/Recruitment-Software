<?php
// Page layout (staff + portals) and line icons.

function icon($name, $size = 18)
{
    static $p = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'home' => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-5.5 7-5.5s7 2 7 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.8c2.4.6 4 2.4 4 5.2"/>',
        'user-plus' => '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6M19 8v6M16 11h6"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/>',
        'file' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/>',
        'file-text' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h6"/>',
        'folder' => '<path d="M3 6a2 2 0 0 1 2-2h4l2 3h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1M9 11h6M9 15h4"/>',
        'check' => '<path d="m5 12 5 5L20 7"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m14 6 4 4"/>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'archive' => '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9M10 13h4"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.9-3M4 4v4h4M4 13a8 8 0 0 0 14.9 3M20 20v-4h-4"/>',
        'download' => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
        'upload' => '<path d="M12 16V5M7 10l5-5 5 5M5 20h14"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'bell' => '<path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'map-pin' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.2 3 14.8 0 18M12 3c-3 3.2-3 14.8 0 18"/>',
        'phone' => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'message' => '<path d="M4 5h16v11H8l-4 4z"/>',
        'chat' => '<path d="M21 12a8 8 0 0 1-11.8 7L4 20l1.2-4.6A8 8 0 1 1 21 12z"/>',
        'send' => '<path d="M21 3 10 14M21 3l-7 18-4-7-7-4z"/>',
        'dollar' => '<path d="M12 2v20M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
        'book' => '<path d="M4 4h6a3 3 0 0 1 3 3v14a2 2 0 0 0-2-2H4zM20 4h-6a3 3 0 0 0-3 3v14a2 2 0 0 1 2-2h7z"/>',
        'bar-chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'pie' => '<path d="M21 12A9 9 0 1 1 12 3v9z"/><path d="M15 3.5A9 9 0 0 1 20.5 9H15z"/>',
        'activity' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'logout' => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 17l5-5-5-5M15 12H3"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'moon' => '<path d="M21 13A9 9 0 1 1 11 3a7 7 0 0 0 10 10z"/>',
        'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'zap' => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
        'truck' => '<path d="M2 6h12v10H2zM14 10h4l3 3v3h-7"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'plane' => '<path d="M10.5 13.5 3 11l1.5-1.5 8 1 4-4.5a2.1 2.1 0 0 1 3 3l-4.5 4 1 8L14.5 22 12 14.5 8 18v3l-1.5 1-1.5-3.5L1.5 17 3 15.5h3z"/>',
        'heart' => '<path d="M12 20s-8-5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 9c0 6-8 11-8 11z"/>',
        'cart' => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.5 12h11L21 7H6"/>',
        'box' => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
        'code' => '<path d="m8 6-6 6 6 6M16 6l6 6-6 6"/>',
        'monitor' => '<rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
        'headphones' => '<path d="M4 15v-3a8 8 0 0 1 16 0v3"/><rect x="2" y="14" width="5" height="7" rx="1.5"/><rect x="17" y="14" width="5" height="7" rx="1.5"/>',
        'star' => '<path d="m12 3 2.8 5.8 6.2.9-4.5 4.4 1 6.2L12 17.3l-5.5 3 1-6.2L3 9.7l6.2-.9z"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'inbox' => '<path d="M3 13h5l1.5 3h5L16 13h5"/><path d="M5 5h14l2 8v6a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-6z"/>',
        'alert' => '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17v.5"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'award' => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14 7 22l5-3 5 3-1.5-8"/>',
        'tag' => '<path d="M3 12V3h9l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'video' => '<rect x="2" y="6" width="14" height="12" rx="2"/><path d="m16 10 6-3v10l-6-3z"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m21 16-5-5-9 9"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 4.9.8c0 1.7-2.4 2.2-2.4 3.7M12 17v.5"/>',
        'building' => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1M10 21v-3h4v3"/>',
        'id' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2.5"/><path d="M5.5 16.5c.5-1.5 2-2.5 3.5-2.5s3 1 3.5 2.5M15 10h3M15 14h3"/>',
        'login' => '<path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4M14 17l5-5-5-5M19 12H8"/>',
        'filter' => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
        'percent' => '<path d="M19 5 5 19"/><circle cx="7" cy="7" r="2.5"/><circle cx="17" cy="17" r="2.5"/>',
        'printer' => '<path d="M6 9V3h12v6M6 18H4v-7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v7h-2"/><rect x="6" y="14" width="12" height="7"/>',
        'copy' => '<rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'volume' => '<path d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/>',
        'volume-x' => '<path d="M11 5 6 9H3v6h3l5 4z"/><path d="m16 9 5 6M21 9l-5 6"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
    ];
    $d = $p[$name] ?? $p['grid'];
    return '<svg class="i" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

function html_head($title)
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($title) . ' · ' . APP_NAME . '</title><meta name="robots" content="noindex,nofollow">'
        . '<link rel="icon" href="assets/img/rh-mark.png"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="assets/app.css?v=' . APP_VERSION . '">'
        . '<script nonce="' . csp_nonce() . '">try{var t=localStorage.getItem("rh-theme");if(t)document.documentElement.setAttribute("data-theme",t)}catch(e){}</script></head>';
}

function page_start($title, $active = null, $showFlash = true)
{
    $u = me();
    html_head($title);
    $since = $u ? (int)val('SELECT COALESCE(MAX(id),0) FROM notifications WHERE user_id=?', [$u['id']]) : 0;
    echo '<body class="app"' . ($u ? ' data-poll="poll.php" data-since="' . $since . '"' : '') . '><a class="skip" href="#main">Skip to content</a><div class="shell">';
    // sidebar
    echo '<aside class="side" id="side"><a class="brand" href="index.php"><img src="assets/img/rh-mark.png" alt=""><span><b>RH Management</b><small>Business Management System</small></span></a><nav class="nav">';
    $by = [];
    foreach ($GLOBALS['MODS'] as $k => $m) if ($m['menu'] && can($k) && ($k !== 'chat' || (function_exists('chat_menu_visible') && chat_menu_visible()))) $by[$m['area']][] = $m;
    foreach (AREAS as $area) {
        if (empty($by[$area])) continue;
        usort($by[$area], function ($a, $b) { return $a['order'] <=> $b['order']; });
        echo '<div class="nav-g"><span class="nav-t">' . e($area) . '</span>';
        foreach ($by[$area] as $m) echo '<a class="' . ($active === $m['key'] ? 'on' : '') . '" href="' . e($m['page']) . '">' . icon($m['icon'], 18) . '<span>' . e($m['label']) . '</span>' . ($m['key'] === 'chat' ? '<span class="count" data-chat-badge style="margin-left:auto"' . (($cu = chat_unread_total()) ? '' : ' hidden') . '>' . $cu . '</span>' : '') . '</a>';
        echo '</div>';
    }
    echo '</nav><div class="side-foot">v' . APP_VERSION . ' · ' . e(setting('company_short', 'RH Management')) . '</div></aside><div class="scrim" data-close-side></div>';
    // top bar
    $unread = $u ? (int)val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$u['id']]) : 0;
    $att = $u ? attendance_today($u['id']) : null;
    echo '<div class="main-col"><header class="top"><button class="icon-btn only-m" data-open-side aria-label="Open menu">' . icon('menu', 20) . '</button>'
        . '<form class="top-search" action="search.php" method="get" role="search">' . icon('search', 17) . '<input name="q" value="' . e($active === 'search' ? g('q') : '') . '" placeholder="Search candidates, files, demands, receipts…" aria-label="Global search"></form><div class="top-r">';
    if ($u) {
        echo '<form method="post" class="att-mini">' . csrf_field();
        if (!$att) echo '<button class="btn sm" name="_att" value="in">' . icon('login', 15) . '<span> Check in</span></button>';
        elseif (!$att['check_out']) echo '<span class="att-on" title="Checked in at ' . e(substr($att['check_in'], 11, 5)) . '"><i></i>' . e(substr($att['check_in'], 11, 5)) . '</span><button class="btn ghost sm" name="_att" value="out">' . icon('logout', 15) . '<span> Check out</span></button>';
        else echo '<span class="att-done">' . icon('check-circle', 15) . ' ' . e(substr($att['check_in'], 11, 5)) . '–' . e(substr($att['check_out'], 11, 5)) . '</span>';
        echo '</form>';
        echo sound_button() . '<button class="icon-btn" data-theme-toggle aria-label="Toggle dark mode">' . icon('moon', 19) . '</button>'
            . '<a class="icon-btn" href="notifications.php" aria-label="Notifications">' . icon('bell', 19) . '<span class="dot" data-bell-dot' . ($unread ? '' : ' hidden') . '>' . ($unread > 99 ? '99+' : $unread) . '</span></a>'
            . '<details class="user-menu"><summary><span class="avatar">' . e(strtoupper(substr($u['name'], 0, 1))) . '</span><span class="um-n"><b>' . e($u['name']) . '</b><small>' . e($u['role_name']) . '</small></span>' . icon('chevron-down', 14) . '</summary>'
            . '<div class="um-d"><a href="mydash.php">' . icon('user', 15) . ' My Dashboard</a><a href="login.php?a=change">' . icon('lock', 15) . ' Change password</a><a href="logout.php">' . icon('logout', 15) . ' Log out</a></div></details>';
    }
    echo '</div></header><main id="main" class="content">';
    if ($showFlash) foreach (flash() as $f) echo '<div class="alert ' . e($f[0]) . '" role="status">' . $f[1] . '</div>';
}

function page_end()
{
    echo '</main><footer class="foot">© ' . date('Y') . ' ' . e(setting('company_name', 'RH Business Solutions & Marketing Management')) . ' · Dubai, UAE</footer></div></div><div id="toasts" class="toasts" aria-live="polite"></div><div id="confirm" class="modal" hidden><div class="modal-box" role="dialog" aria-modal="true"><h3>Please confirm</h3><p data-msg></p><div class="modal-a"><button class="btn ghost" data-no>Cancel</button><button class="btn danger" data-yes>Confirm</button></div></div></div>'
        . '<script src="assets/app.js?v=' . APP_VERSION . '"></script></body></html>';
}

/** Page header with optional buttons. */
function page_head($title, $iconName = null, $sub = '', $right = '')
{
    echo '<div class="page-head"><div><h1>' . ($iconName ? icon($iconName, 26) . ' ' : '') . e($title) . '</h1>' . ($sub ? '<p class="muted">' . $sub . '</p>' : '') . '</div>' . ($right ? '<div class="ph-r">' . $right . '</div>' : '') . '</div>';
}

/** Portal pages (candidate / client / agent): separate, simpler layout. */
function portal_start($title, $portalName, array $nav = [], $active = '', $logout = '')
{
    html_head($title);
    $poll = !empty($_SESSION['portal']) && $_SESSION['portal']['type'] === 'candidate';
    $since = $poll ? (int)val("SELECT COALESCE(MAX(id),0) FROM chat_messages WHERE candidate_id=? AND sender_type='Staff'", [$_SESSION['portal']['id']]) : 0;
    echo '<body class="portal"' . ($poll ? ' data-poll="poll.php" data-since="' . $since . '"' : '') . '><header class="p-top"><a class="brand" href="?"><img src="assets/img/rh-mark.png" alt=""><span><b>RH Management</b><small>' . e($portalName) . '</small></span></a>';
    if ($nav) {
        echo '<nav class="p-nav">';
        foreach ($nav as $k => $l) echo '<a class="' . ($k === $active ? 'on' : '') . '" href="?p=' . e($k) . '">' . e($l) . '</a>';
        echo '</nav>';
    }
    if ($logout) echo '<a class="btn ghost sm" href="' . e($logout) . '">' . icon('logout', 15) . ' Log out</a>';
    echo ($poll ? sound_button() : '') . '<button class="icon-btn" data-theme-toggle aria-label="Toggle dark mode">' . icon('moon', 18) . '</button></header><main class="p-main" id="main">';
    foreach (flash() as $f) echo '<div class="alert ' . e($f[0]) . '" role="status">' . $f[1] . '</div>';
}
function portal_end()
{
    echo '</main><footer class="foot">© ' . date('Y') . ' RH Business Solutions &amp; Marketing Management · Dubai, UAE · <a href="mailto:info@rhmanagements.com">info@rhmanagements.com</a></footer><div id="toasts" class="toasts" aria-live="polite"></div><script src="assets/app.js?v=' . APP_VERSION . '"></script></body></html>';
}

function sound_button()
{
    return '<button class="icon-btn" type="button" data-sound-toggle aria-label="Notification sound on or off" title="Notification sound on / off"><span class="s-on">' . icon('volume', 18) . '</span><span class="s-off">' . icon('volume-x', 18) . '</span></button>';
}

/** Small helpers for custom screens. */
function card_open($title = '', $right = '', $cls = '') { echo '<div class="card ' . $cls . '">' . ($title || $right ? '<div class="card-h"><h3>' . $title . '</h3>' . $right . '</div>' : ''); }
function card_close() { echo '</div>'; }
function simple_table(array $head, array $data, $empty = 'Nothing to show yet.', $links = [])
{
    $h = '<div class="table-wrap"><table class="table compact"><thead><tr>';
    foreach ($head as $x) $h .= '<th>' . e($x) . '</th>';
    $h .= '</tr></thead><tbody>';
    foreach ($data as $i => $r) {
        $h .= '<tr' . (!empty($links[$i]) ? ' data-href="' . e($links[$i]) . '"' : '') . '>';
        $j = 0;
        foreach ($r as $c) $h .= '<td data-label="' . e($head[$j++] ?? '') . '">' . $c . '</td>';
        $h .= '</tr>';
    }
    if (!$data) $h .= '<tr><td colspan="' . count($head) . '"><p class="muted small pad">' . e($empty) . '</p></td></tr>';
    return $h . '</tbody></table></div>';
}
function attendance_box()
{
    $u = me();
    $a = attendance_today($u['id']);
    echo '<div class="card att-box"><div class="card-h"><h3>' . icon('clock', 17) . ' My attendance · ' . date('D d M') . '</h3></div><form method="post" class="att-row">' . csrf_field();
    if (!$a) echo '<p>You have not checked in today.</p><button class="btn" name="_att" value="in">' . icon('login', 16) . ' Check in</button>';
    else {
        echo '<p>Checked in <b>' . e(substr($a['check_in'], 11, 5)) . '</b>' . ($a['check_out'] ? ' · checked out <b>' . e(substr($a['check_out'], 11, 5)) . '</b>' : '') . '</p>';
        if (!$a['check_out']) echo '<button class="btn ghost" name="_att" value="out">' . icon('logout', 16) . ' Check out</button>';
    }
    echo '</form></div>';
}
