<?php
// Printable Forms: the company's own PDF forms (application form, recovery form, contracts…) printed with a running serial number.

ent('print_forms', ['label' => 'Forms', 'one' => 'Form', 'title' => 'name', 'icon' => 'printer',
    'fields' => [
        'name' => ['Form name', 'text', 'req'],
        'category' => ['Type', 'enum:Application form|Recovery form|Contract|Agreement|Receipt|Other', 'req', 'default' => 'Application form'],
        'pdf_file' => ['PDF file', 'file', 'req', 'dir' => 'forms', 'types' => ['pdf'], 'max_mb' => 25, 'help' => 'The blank form as a PDF (max 25 MB).'],
        'serial_prefix' => ['Serial prefix', 'text', 'default' => 'AF-', 'help' => 'Letters before the number, for example AF- gives AF-00001.'],
        'serial_digits' => ['Number of digits', 'int', 'default' => 5],
        'next_no' => ['Next serial number', 'int', 'req', 'default' => 1, 'help' => 'The number the next printed copy gets. If paper forms were already printed up to 4500, enter 4501.'],
        'label' => ['Text before the number', 'text', 'default' => 'Serial No.', 'help' => 'Leave empty to print only the number.'],
        'pos_x' => ['Position from left (mm)', 'int', 'req', 'default' => 150],
        'pos_y' => ['Position from top (mm)', 'int', 'req', 'default' => 12],
        'font_size' => ['Text size', 'int', 'default' => 12],
        'pages' => ['Print the number on', 'enum:First page only|Every page', 'default' => 'First page only'],
        'white_box' => ['White box behind the number', 'enum:No|Yes', 'default' => 'No', 'help' => 'Use Yes to cover an old number already on the PDF.'],
        'status' => ['Status', 'enum:Active|Inactive', 'default' => 'Active'],
        'notes' => ['Notes', 'textarea'],
    ],
    'list' => ['name', 'category', 'serial_prefix', 'next_no', 'status'], 'filters' => ['category', 'status'],
    'before_save' => function (&$d, $old) {
        $d['serial_digits'] = max(1, min(10, (int)($d['serial_digits'] ?? 5)));
        $d['font_size'] = max(6, min(40, (int)($d['font_size'] ?? 12) ?: 12));
        $d['next_no'] = max(1, (int)$d['next_no']);
        if ($old) {
            $used = (int)val("SELECT COALESCE(MAX(last_no),0) FROM print_batches WHERE form_id=? AND kind='Print'", [$old['id']]);
            if ($d['next_no'] <= $used) return 'Serial numbers up to ' . $used . ' are already printed. The next serial number must be ' . ($used + 1) . ' or higher.';
        }
        return null;
    },
    'cols' => ['Next serial' => function ($r) { return '<span class="ref">' . e(form_serial($r, $r['next_no'])) . '</span>'; }],
    'view' => function ($r) { forms_card($r, true); },
]);
ent('print_batches', ['label' => 'Print History', 'one' => 'Print record', 'title' => 'serial_from', 'icon' => 'clock', 'immutable' => true, 'related' => false,
    'fields' => [
        'form_id' => ['Form', 'ref:print_forms', 'req'],
        'kind' => ['Type', 'enum:Print|Reprint', 'req'],
        'qty' => ['Copies', 'int', 'req'],
        'first_no' => ['First number', 'int', 'hide'],
        'last_no' => ['Last number', 'int', 'hide'],
        'serial_from' => ['Serial from', 'text'],
        'serial_to' => ['Serial to', 'text'],
        'reprint_of' => ['Reprint of', 'ref:print_batches', 'norel' => true],
        'note' => ['Note / reason', 'text'],
    ],
    'list' => ['created_at', 'form_id', 'kind', 'qty', 'serial_from', 'serial_to', 'note'], 'filters' => ['form_id', 'kind'],
    'cols' => ['Printed by' => function ($r) { return e(user_name($r['created_by'])); }],
    'view' => function ($r) { echo '<div class="action-bar" style="margin-bottom:14px"><a class="btn" href="forms.php?t=print&amp;batch=' . (int)$r['id'] . '">' . icon('printer', 15) . ' Open this print again (same serial numbers)</a></div>'; },
    'actions' => ['reprint' => ['label' => 'Reprint (same numbers)', 'icon' => 'refresh', 'perm' => 'approve', 'confirm' => 'Reprint these serial numbers? A reprint record is saved.',
        'form' => function ($r) { echo '<p>Reprint <b>' . e($r['serial_from']) . '</b> to <b>' . e($r['serial_to']) . '</b> (' . (int)$r['qty'] . ' copies), for example when the printer jammed. No new numbers are used.</p><div class="fld"><label>Reason *</label><input name="note" required maxlength="200"></div><div class="form-actions"><button class="btn">' . icon('printer', 15) . ' Reprint</button></div>'; },
        'do' => function ($r) {
            if (!p('note')) return 'Write the reason for the reprint.';
            $id = ins('print_batches', ['form_id' => $r['form_id'], 'kind' => 'Reprint', 'qty' => $r['qty'], 'first_no' => $r['first_no'], 'last_no' => $r['last_no'], 'serial_from' => $r['serial_from'], 'serial_to' => $r['serial_to'], 'reprint_of' => $r['id'], 'note' => p('note'), 'created_by' => me()['id'], 'created_at' => now(), 'archived' => 0]);
            audit('form-reprint', 'print_batches', $id, null, ['of' => $r['id'], 'from' => $r['serial_from'], 'to' => $r['serial_to']], p('note'));
            return ['msg' => 'Reprint recorded.', 'go' => 'forms.php?t=print&batch=' . $id];
        }]],
]);

function form_serial($f, $n) { return (string)$f['serial_prefix'] . str_pad((string)(int)$n, max(1, (int)$f['serial_digits']), '0', STR_PAD_LEFT); }
/** Settings the browser needs to stamp the PDF. */
function form_cfg($f)
{
    return ['pdf' => 'file.php?f=' . rawurlencode($f['pdf_file']), 'label' => (string)$f['label'], 'x' => (float)$f['pos_x'], 'y' => (float)$f['pos_y'], 'size' => (int)$f['font_size'] ?: 12,
        'every' => $f['pages'] === 'Every page', 'box' => $f['white_box'] === 'Yes', 'name' => preg_replace('/[^A-Za-z0-9]+/', '-', $f['name'])];
}
/** Reserve the next serial numbers and save the print record. Returns the batch id or an error. */
function forms_reserve($formId, $qty, $note = '')
{
    $qty = (int)$qty;
    if ($qty < 1 || $qty > 500) return 'Choose between 1 and 500 copies.';
    $f = row("SELECT * FROM print_forms WHERE id=? AND archived=0 AND status='Active'", [$formId]);
    if (!$f || !$f['pdf_file']) return 'This form is not available.';
    db()->beginTransaction();
    try {
        q('UPDATE print_forms SET next_no=next_no+?, updated_at=? WHERE id=?', [$qty, now(), $f['id']]);
        $next = (int)val('SELECT next_no FROM print_forms WHERE id=?', [$f['id']]);
        $first = $next - $qty; $last = $next - 1;
        $id = ins('print_batches', ['form_id' => $f['id'], 'kind' => 'Print', 'qty' => $qty, 'first_no' => $first, 'last_no' => $last, 'serial_from' => form_serial($f, $first), 'serial_to' => form_serial($f, $last), 'note' => $note ?: null, 'created_by' => me()['id'], 'created_at' => now(), 'archived' => 0]);
        db()->commit();
    } catch (Exception $e) { db()->rollBack(); throw $e; }
    audit('form-print', 'print_batches', $id, null, ['form' => $f['name'], 'qty' => $qty, 'from' => form_serial($f, $first), 'to' => form_serial($f, $last)]);
    return (int)$id;
}
function forms_card($f, $single = false)
{
    $tot = (int)val("SELECT COALESCE(SUM(qty),0) FROM print_batches WHERE form_id=? AND kind='Print' AND archived=0", [$f['id']]);
    $last = row("SELECT * FROM print_batches WHERE form_id=? AND archived=0 ORDER BY id DESC LIMIT 1", [$f['id']]);
    echo '<div class="card form-card"><div class="card-h"><h3>' . icon('file-text', 18) . ' ' . e($f['name']) . '</h3>' . badge($f['category']) . '</div>'
        . '<div class="kv"><div class="hl"><span>Next serial</span><b class="ref">' . e(form_serial($f, $f['next_no'])) . '</b></div><div><span>Copies printed</span><b>' . number_format($tot) . '</b></div>'
        . '<div><span>Last print</span><b>' . ($last ? e(dfmt($last['created_at'])) . ' · ' . e(user_name($last['created_by'])) : '—') . '</b></div></div>';
    if ($f['status'] !== 'Active' || !$f['pdf_file']) { echo '<p class="muted small">This form is inactive.</p></div>'; return; }
    echo '<form method="post" action="forms.php?t=print&amp;a=print" class="print-row">' . csrf_field() . '<input type="hidden" name="form_id" value="' . (int)$f['id'] . '">'
        . '<div class="fld"><label>How many copies?</label><input type="number" name="qty" min="1" max="500" value="10" required></div>'
        . '<div class="fld" style="flex:2"><label>Note (optional)</label><input name="note" maxlength="200" placeholder="For example: for Reception desk"></div>'
        . '<button class="btn big">' . icon('printer', 16) . ' Print</button></form>'
        . '<div class="action-bar" style="margin-top:8px"><button type="button" class="btn ghost sm" data-print-test="' . e(json_encode(form_cfg($f) + ['serials' => [form_serial($f, 0) . ' TEST']])) . '">' . icon('eye', 14) . ' Test the number position</button>'
        . (!$single && can('forms', 'edit') ? '<a class="btn ghost sm" href="forms.php?t=forms&amp;a=edit&amp;id=' . (int)$f['id'] . '">' . icon('edit', 14) . ' Settings</a>' : '') . '</div><div class="print-out" hidden></div></div>';
}

mod('forms', ['label' => 'Printable Forms', 'icon' => 'printer', 'area' => 'Control', 'order' => 3,
    'desc' => 'Your own PDF forms (application form, recovery form, contracts). Print any number of copies; each copy gets the next serial number and every print is recorded.',
    'tabs' => [
        'print' => ['label' => 'Print', 'post' => function ($base, $mod) {
            if (g('a') !== 'print') return;
            if (!can($mod, 'add') && !is_super()) deny('Your role cannot print forms.');
            $res = forms_reserve((int)p('form_id'), (int)p('qty'), mb_substr(trim(p('note')), 0, 200));
            if (is_string($res)) { flash($res, 'bad'); redirect($base); }
            redirect($base . '&batch=' . $res);
        }, 'fn' => function ($base, $mod) {
            echo '<script src="assets/vendor/pdf-lib.min.js?v=1.17.1"></script><script src="assets/forms.js?v=' . APP_VERSION . '"></script>';
            if (g('batch')) {
                $b = row('SELECT * FROM print_batches WHERE id=?', [(int)g('batch')]);
                $f = $b ? row('SELECT * FROM print_forms WHERE id=?', [$b['form_id']]) : null;
                if ($b && $f && $f['pdf_file']) {
                    $serials = [];
                    for ($n = (int)$b['first_no']; $n <= (int)$b['last_no']; $n++) $serials[] = form_serial($f, $n);
                    echo '<div class="card batch-card" data-print-batch="' . e(json_encode(form_cfg($f) + ['serials' => $serials, 'file' => $f['name'] . ' ' . $b['serial_from'] . ' to ' . $b['serial_to']])) . '"><div class="card-h"><h3>' . icon('printer', 18) . ' ' . e($f['name']) . ' — ' . (int)$b['qty'] . ' copies' . ($b['kind'] === 'Reprint' ? ' (reprint)' : '') . '</h3><a class="btn ghost sm" href="' . e($base) . '">Back</a></div>'
                        . '<p>Serial numbers <b class="ref">' . e($b['serial_from']) . '</b> to <b class="ref">' . e($b['serial_to']) . '</b> · recorded ' . e(dtfmt($b['created_at'])) . ' by ' . e(user_name($b['created_by'])) . '.</p><div class="print-out"><p class="muted">' . icon('clock', 15) . ' Preparing the PDF…</p></div></div>';
                }
            }
            $forms = rows("SELECT * FROM print_forms WHERE archived=0 ORDER BY status, name");
            if (!$forms) { echo '<div class="card"><div class="empty">' . icon('printer', 32) . '<p>No forms yet. Add your first PDF form (for example the candidate application form).</p>' . (can($mod, 'add') ? '<a class="btn" href="forms.php?t=forms&amp;a=new">' . icon('plus', 15) . ' Add form</a>' : '') . '</div></div>'; return; }
            echo '<div class="grid g2">';
            foreach ($forms as $f) forms_card($f);
            echo '</div>';
        }],
        'forms' => ['ent' => 'print_forms', 'label' => 'Forms', 'perm' => 'edit'],
        'history' => ['ent' => 'print_batches', 'label' => 'Print history', 'noadd' => true],
    ]]);
