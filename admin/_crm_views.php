<?php
// CRM screens for the admin panel (home dashboard, kanban/list, contact page, follow-ups, reports).
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/api/_crm.php';

function abd_crm_handle_post(string $do, array $me): void {
  $uid = (int)$me['id']; $id = (int)($_POST['id'] ?? 0);
  $toC = fn(int $cid) => ['id' => $cid];
  $ret = (string)($_POST['ret'] ?? 'crm');
  if (!in_array($ret, ['home', 'crm', 'tasks'], true)) $ret = 'crm';
  switch ($do) {
    case 'crm_create':
      [$d, $bad] = abd_crm_validate($_POST);
      if ($bad) back('crm', 'بيانات غير صحيحة: ' . implode(', ', $bad), true);
      if (($dup = abd_crm_find($d['phone'] ?? null, $d['email'] ?? null)) && empty($_POST['allow_duplicate']))
        back('crm', 'يوجد عميل بنفس الجوال أو البريد — تم فتح سجله.', true, $toC($dup));
      $cid = abd_crm_create($d, $uid);
      $first = abd_str($_POST, 'first_note', 4000);
      if ($first !== '') abd_crm_log($cid, 'note', $first, $uid);
      back('crm', 'تمت إضافة العميل.', false, $toC($cid));
    case 'crm_update':
      [$d, $bad] = abd_crm_validate($_POST, true);
      if ($bad) back('crm', 'بيانات غير صحيحة: ' . implode(', ', $bad), true, $toC($id));
      abd_crm_update($id, $d, $uid) ? back('crm', 'تم حفظ بيانات العميل.', false, $toC($id)) : back('crm', 'غير موجود.', true);
    case 'crm_stage':
      [$d, $bad] = abd_crm_validate(array_intersect_key($_POST, ['stage' => 1, 'lost_reason' => 1]), true);
      if ($bad || !isset($d['stage'])) back($ret, 'مرحلة غير صحيحة.', true);
      abd_crm_update($id, $d, $uid);
      back($ret, 'تم نقل العميل إلى «' . ABD_CRM_STAGES[$d['stage']] . '».', false, $ret === 'crm' && !empty($_POST['stay']) ? $toC($id) : array_intersect_key($_POST, ['view' => 1]));
    case 'crm_activity':
      $type = (string)($_POST['type'] ?? ''); $body = abd_str($_POST, 'body', 4000);
      if (!in_array($type, ['call', 'whatsapp', 'note', 'meeting', 'email'], true) || $body === '') back('crm', 'اكتب تفاصيل النشاط.', true, $toC($id));
      if (!abd_q('SELECT 1 FROM abd_crm_contacts WHERE id = ?', [$id])->fetchColumn()) back('crm', 'غير موجود.', true);
      abd_crm_log($id, $type, $body, $uid);
      abd_q('UPDATE abd_crm_contacts SET updated_at = NOW() WHERE id = ?', [$id]);
      if (($_POST['mark_contacted'] ?? '') === '1') abd_q("UPDATE abd_crm_contacts SET stage = 'contacted', stage_changed_at = NOW() WHERE id = ? AND stage = 'new'", [$id]);
      back('crm', 'تم تسجيل النشاط.', false, $toC($id));
    case 'crm_task_add':
      $r = abd_crm_task_add($id > 0 ? $id : null, abd_str($_POST, 'title', 255), abd_str($_POST, 'due_at', 20),
        (int)($_POST['assigned_to'] ?? 0) > 0 ? (int)$_POST['assigned_to'] : null, $uid);
      $extra = $id > 0 && $ret === 'crm' ? $toC($id) : [];
      $r[0] ? back($ret, 'تمت إضافة مهمة المتابعة.', false, $extra) : back($ret, 'تحقق من عنوان المهمة وموعدها.', true, $extra);
    case 'crm_task_done':
      abd_crm_task_done((int)($_POST['task_id'] ?? 0), ($_POST['done'] ?? '1') === '1', $uid);
      back($ret, 'تم تحديث المهمة.', false, $ret === 'crm' && $id ? $toC($id) : []);
    case 'crm_delete':
      if ($me['role'] !== 'admin') back('crm', 'الحذف للمدير فقط.', true, $toC($id));
      abd_q('DELETE FROM abd_crm_contacts WHERE id = ?', [$id]);
      back('crm', 'تم حذف العميل وسجلّه.');
  }
  back('crm', 'عملية غير معروفة.', true);
}

function abd_crm_opts(array $opts, ?string $sel, string $empty = ''): string {
  $o = $empty !== '' ? '<option value="">' . h($empty) . '</option>' : '';
  foreach ($opts as $k => $v) $o .= '<option value="' . h($k) . '"' . ((string)$sel === (string)$k ? ' selected' : '') . '>' . h($v) . '</option>';
  return $o;
}
function abd_crm_staff_opts(?int $sel, string $empty = '— بدون —'): string {
  $m = []; foreach (abd_crm_staff() as $s) $m[$s['id']] = $s['full_name_ar'] ?: $s['email'];
  return abd_crm_opts($m, $sel === null ? '' : (string)$sel, $empty);
}
function abd_crm_due(?string $due): string {
  if (!$due) return '';
  $t = strtotime($due); $cls = $t < time() ? 'due-over' : (date('Y-m-d', $t) === date('Y-m-d') ? 'due-today' : 'mut');
  return '<span class="' . $cls . ' ltr">⏰ ' . h(date('Y-m-d H:i', $t)) . '</span>';
}
function abd_crm_tags_html(string $tags): string {
  $o = ''; foreach (array_filter(explode(',', $tags)) as $t) $o .= '<a class="tag" href="?tab=crm&tag=' . rawurlencode($t) . '">' . h($t) . '</a>';
  return $o;
}
function abd_crm_tasks_table(array $tasks, string $tok, string $ret, bool $showContact = true): void { ?>
  <table><thead><tr><th>الموعد</th><th>المهمة</th><?php if ($showContact): ?><th>العميل</th><?php endif; ?><th>المسؤول</th><th></th></tr></thead><tbody>
  <?php foreach ($tasks as $t): ?>
    <tr><td><?= $t['done_at'] ? '<span class="mut">✔ ' . h(substr((string)$t['done_at'], 0, 16)) . '</span>' : abd_crm_due($t['due_at']) ?></td><td><?=h($t['title'])?></td>
      <?php if ($showContact): ?><td><?php if ($t['contact_id']): ?><a href="?tab=crm&id=<?= (int)$t['contact_id'] ?>"><?=h($t['contact_name'])?></a><br><span class="mut ltr"><?=h($t['contact_phone'])?></span><?php else: ?><span class="mut">عام</span><?php endif; ?></td><?php endif; ?>
      <td><?=h($t['assignee'] ?? '—')?></td>
      <td><form class="i" method="post"><?=$tok?><input type="hidden" name="do" value="crm_task_done"><input type="hidden" name="ret" value="<?=h($ret)?>">
        <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="id" value="<?= (int)$t['contact_id'] ?>"><input type="hidden" name="done" value="<?= $t['done_at'] ? '0' : '1' ?>">
        <button class="btn s <?= $t['done_at'] ? 'l' : '' ?>"><?= $t['done_at'] ? 'إعادة فتح' : '✔ تم' ?></button></form></td></tr>
  <?php endforeach; if (!$tasks): ?><tr><td colspan="5" class="mut">لا توجد مهام.</td></tr><?php endif; ?>
  </tbody></table>
<?php }

function abd_crm_task_form(string $tok, int $cid, string $ret): void { ?>
  <form class="f" method="post"><?=$tok?><input type="hidden" name="do" value="crm_task_add"><input type="hidden" name="ret" value="<?=h($ret)?>"><input type="hidden" name="id" value="<?= $cid ?>">
    <label>مهمة متابعة<input name="title" required maxlength="255" placeholder="مثال: الاتصال لتأكيد موعد الاختبار"></label>
    <label>الموعد<input type="datetime-local" name="due_at" required value="<?=h(date('Y-m-d\TH:i', time() + 86400))?>"></label>
    <label>المسؤول<select name="assigned_to"><?= abd_crm_staff_opts(null, '— الجميع —') ?></select></label>
    <button class="btn">➕ إضافة</button></form>
<?php }

function abd_crm_render(string $tab, array $me, string $tok, int $page): void {
  $uid = (int)$me['id'];
  // ---------------------------------------------------------------- HOME DASHBOARD
  if ($tab === 'home') {
    $open = abd_crm_tasks_open(null, 300);
    $over = array_filter($open, fn($t) => (int)$t['overdue']); $today = array_filter($open, fn($t) => !(int)$t['overdue'] && (int)$t['today']);
    $k = abd_q("SELECT
        (SELECT COUNT(*) FROM abd_crm_contacts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) new7,
        (SELECT COUNT(*) FROM abd_crm_contacts WHERE stage NOT IN ('paid','completed','lost')) openc,
        (SELECT COUNT(*) FROM abd_crm_contacts WHERE stage = 'new') newc,
        (SELECT COUNT(*) FROM abd_bookings WHERE status = 'new') nb,
        (SELECT COUNT(*) FROM abd_users WHERE role = 'trainee') tr,
        (SELECT COUNT(*) FROM abd_exam_results WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) r7")->fetch();
    $recent = abd_crm_list([], 1, 8)['items']; ?>
    <h1>🏠 أهلاً <?=h($me['full_name_ar'] ?: $me['email'])?></h1>
    <div class="stats">
      <div><b class="due-over"><?= count($over) ?></b>متابعات متأخرة</div><div><b class="due-today"><?= count($today) ?></b>متابعات اليوم</div>
      <div><b><?= (int)$k['newc'] ?></b>عملاء جدد لم يُتواصل معهم</div><div><b><?= (int)$k['new7'] ?></b>عملاء محتملون (7 أيام)</div>
      <div><b><?= (int)$k['openc'] ?></b>فرص مفتوحة</div><div><b><?= (int)$k['nb'] ?></b>حجوزات جديدة</div>
      <div><b><?= (int)$k['tr'] ?></b>متدربون مسجّلون</div><div><b><?= (int)$k['r7'] ?></b>نتائج اختبارات (7 أيام)</div></div>
    <div class="cols"><div><h2 class="due-over">⚠️ المتابعات المتأخرة</h2><?php abd_crm_tasks_table(array_values($over), $tok, 'home'); ?></div>
      <div><h2 class="due-today">📅 متابعات اليوم</h2><?php abd_crm_tasks_table(array_values($today), $tok, 'home'); ?></div></div>
    <h2 style="margin-top:18px">🧲 آخر العملاء</h2>
    <table><thead><tr><th>العميل</th><th>المصدر</th><th>الاهتمام</th><th>المرحلة</th><th>آخر تحديث</th></tr></thead><tbody>
    <?php foreach ($recent as $c): ?><tr><td><a href="?tab=crm&id=<?= (int)$c['id'] ?>"><b><?=h($c['full_name'])?></b></a><br><span class="mut ltr"><?=h($c['phone'] ?? $c['email'])?></span></td>
      <td><?=h(ABD_CRM_SOURCES[$c['source']] ?? $c['source'])?></td><td><?=h($c['interest'] ?? '—')?></td>
      <td><span class="st st-<?=h($c['stage'])?>"><?=h(ABD_CRM_STAGES[$c['stage']])?></span></td><td class="mut ltr"><?=h(substr((string)$c['updated_at'], 0, 16))?></td></tr>
    <?php endforeach; if (!$recent): ?><tr><td colspan="5" class="mut">لا يوجد عملاء بعد — سيُنشأ عميل محتمل تلقائياً مع كل طلب حجز.</td></tr><?php endif; ?></tbody></table>
    <?php return;
  }
  // ---------------------------------------------------------------- TASKS
  if ($tab === 'tasks') {
    $mine = !empty($_GET['mine']);
    $open = abd_crm_tasks_open($mine ? $uid : null, 500);
    $done = abd_q('SELECT t.*, c.full_name contact_name, c.phone contact_phone, u.full_name_ar assignee FROM abd_crm_tasks t
        LEFT JOIN abd_crm_contacts c ON c.id = t.contact_id LEFT JOIN abd_users u ON u.id = t.assigned_to
        WHERE t.done_at IS NOT NULL ORDER BY t.done_at DESC LIMIT 30')->fetchAll(); ?>
    <h1>⏰ مهام المتابعة</h1>
    <p><a class="btn <?= $mine ? 'l' : '' ?> s" href="?tab=tasks">كل المهام</a> <a class="btn <?= $mine ? '' : 'l' ?> s" href="?tab=tasks&mine=1">مهامي</a></p>
    <?php abd_crm_task_form($tok, 0, 'tasks'); ?>
    <h2>المفتوحة (<?= count($open) ?>)</h2><?php abd_crm_tasks_table($open, $tok, 'tasks'); ?>
    <details style="margin-top:14px"><summary>آخر المهام المنجزة</summary><?php abd_crm_tasks_table($done, $tok, 'tasks'); ?></details>
    <?php return;
  }
  // ---------------------------------------------------------------- REPORTS
  if ($tab === 'reports') {
    $r = abd_crm_reports(); $max = max(1, ...array_map('intval', array_values($r['by_stage']) ?: [1]));
    $mmax = 1; foreach ($r['monthly'] as $m) $mmax = max($mmax, (int)$m['leads']); ?>
    <h1>📈 تقارير CRM</h1>
    <div class="stats"><div><b><?= $r['total'] ?></b>إجمالي العملاء</div><div><b><?= $r['won'] ?></b>تحويلات (مدفوع/مكتمل)</div>
      <div><b><?= $r['lost'] ?></b>مفقود</div><div><b><?= h($r['conversion']) ?>%</b>معدل التحويل</div><div><b><?= h($r['win_rate']) ?>%</b>نسبة الفوز من المغلقة</div></div>
    <div class="cols">
      <div><h2>حسب المرحلة</h2><div class="bars"><?php foreach (ABD_CRM_STAGES as $k => $v): $n = (int)($r['by_stage'][$k] ?? 0); ?>
        <div><span style="width:130px"><?=h($v)?></span><i class="<?= in_array($k, ABD_CRM_WON, true) ? 'w' : '' ?>" style="width:<?= round($n / $max * 60, 1) ?>%"></i><b><?= $n ?></b></div><?php endforeach; ?></div></div>
      <div><h2>حسب المصدر</h2><table><thead><tr><th>المصدر</th><th>العملاء</th><th>تحويل</th><th>مفقود</th><th>معدل التحويل</th></tr></thead><tbody>
        <?php foreach ($r['by_source'] as $s): ?><tr><td><?=h(ABD_CRM_SOURCES[$s['source']] ?? $s['source'])?></td><td><?= (int)$s['n'] ?></td><td><?= (int)$s['won'] ?></td><td><?= (int)$s['lost'] ?></td>
          <td><b><?= $s['n'] ? round($s['won'] / $s['n'] * 100, 1) : 0 ?>%</b></td></tr><?php endforeach; if (!$r['by_source']): ?><tr><td colspan="5" class="mut">لا توجد بيانات.</td></tr><?php endif; ?></tbody></table></div>
      <div><h2>شهرياً (آخر 12 شهراً)</h2><table><thead><tr><th>الشهر</th><th>عملاء جدد</th><th>تحويلات</th><th></th></tr></thead><tbody>
        <?php foreach ($r['monthly'] as $m): ?><tr><td class="ltr"><?=h($m['ym'])?></td><td><?= (int)$m['leads'] ?></td><td><?= (int)$m['won'] ?></td>
          <td class="bars" style="width:40%"><div><i style="width:<?= round($m['leads'] / $mmax * 100, 1) ?>%"></i></div><div><i class="w" style="width:<?= round($m['won'] / $mmax * 100, 1) ?>%"></i></div></td></tr><?php endforeach; ?></tbody></table></div>
      <div><h2>حسب المسؤول</h2><table><thead><tr><th>المسؤول</th><th>العملاء</th><th>تحويل</th><th>المعدل</th></tr></thead><tbody>
        <?php foreach ($r['by_owner'] as $o): ?><tr><td><?=h($o['owner'])?></td><td><?= (int)$o['n'] ?></td><td><?= (int)$o['won'] ?></td><td><?= $o['n'] ? round($o['won'] / $o['n'] * 100, 1) : 0 ?>%</td></tr><?php endforeach; ?>
        <?php if (!$r['by_owner']): ?><tr><td colspan="4" class="mut">لا توجد بيانات.</td></tr><?php endif; ?></tbody></table></div>
    </div>
    <?php return;
  }
  // ---------------------------------------------------------------- CONTACT PAGE
  if (isset($_GET['id'])) {
    $c = abd_crm_get((int)$_GET['id']);
    if (!$c) { echo '<div class="err">العميل غير موجود.</div><a href="?tab=crm">→ العودة</a>'; return; }
    $cid = (int)$c['id']; ?>
    <p><a href="?tab=crm">→ لوحة العملاء</a></p>
    <h1>👤 <?=h($c['full_name'])?> <span class="st st-<?=h($c['stage'])?>"><?=h(ABD_CRM_STAGES[$c['stage']])?></span></h1>
    <p class="mut"><?php if ($c['phone']): ?><a class="ltr" href="https://wa.me/<?=h(preg_replace('/\D/', '', (string)$c['phone']))?>" target="_blank" rel="noopener">💬 <?=h($c['phone'])?></a> · <a class="ltr" href="tel:<?=h($c['phone'])?>">📞 اتصال</a> · <?php endif; ?>
      <?php if ($c['email']): ?><a class="ltr" href="mailto:<?=h($c['email'])?>">✉️ <?=h($c['email'])?></a> · <?php endif; ?>
      المصدر: <?=h(ABD_CRM_SOURCES[$c['source']] ?? $c['source'])?> · أُنشئ <span class="ltr"><?=h(substr((string)$c['created_at'], 0, 16))?></span>
      <?php if ($c['user_id']): ?> · <span class="st st-trainee">حساب منصة: <?=h($c['user_email'])?></span><?php endif; ?>
      <?php if ($c['booking_ref']): ?> · آخر حجز <a href="?tab=bookings&q=<?=h(rawurlencode((string)$c['booking_ref']))?>" class="ltr"><?=h($c['booking_ref'])?></a><?php endif; ?></p>
    <?= abd_crm_tags_html((string)$c['tags']) ?>
    <form class="f" method="post" style="margin-top:10px"><?=$tok?><input type="hidden" name="do" value="crm_stage"><input type="hidden" name="id" value="<?= $cid ?>"><input type="hidden" name="stay" value="1">
      <label>المرحلة<select name="stage"><?= abd_crm_opts(ABD_CRM_STAGES, $c['stage']) ?></select></label>
      <label>سبب الفقد (إن وجد)<input name="lost_reason" maxlength="255" value="<?=h($c['lost_reason'])?>"></label><button class="btn g">نقل</button></form>
    <div class="cols">
      <div><h2>📝 تسجيل نشاط</h2>
        <form method="post"><?=$tok?><input type="hidden" name="do" value="crm_activity"><input type="hidden" name="id" value="<?= $cid ?>">
          <div class="f" style="margin:0 0 6px"><label>النوع<select name="type"><?= abd_crm_opts(array_diff_key(ABD_CRM_ACTS, ['stage' => 1, 'system' => 1]), 'call') ?></select></label>
          <?php if ($c['stage'] === 'new'): ?><label><span><input type="checkbox" name="mark_contacted" value="1" checked> نقل إلى «تم التواصل»</span></label><?php endif; ?></div>
          <textarea name="body" required maxlength="4000" placeholder="ماذا حدث؟ (ملخص المكالمة، الرسالة، الاجتماع…)"></textarea><button class="btn" style="margin-top:6px">حفظ النشاط</button></form>
        <h2 style="margin-top:16px">⏰ المتابعات</h2><?php abd_crm_task_form($tok, $cid, 'crm'); abd_crm_tasks_table($c['tasks'], $tok, 'crm', false); ?>
        <?php if ($c['bookings']): ?><h2 style="margin-top:16px">📋 الحجوزات</h2><table><?php foreach ($c['bookings'] as $b): ?><tr><td class="ltr"><?=h($b['ref'])?></td><td><?=h($b['vendor'])?> — <?=h($b['cert'])?></td><td class="ltr"><?=h($b['preferred_date'])?></td><td><span class="st st-<?=h($b['status'])?>"><?=h(['new' => 'جديد', 'contacted' => 'تم التواصل', 'booked' => 'تم الحجز', 'cancelled' => 'ملغى'][$b['status']] ?? $b['status'])?></span></td></tr><?php endforeach; ?></table><?php endif; ?>
      </div>
      <div><h2>🕘 سجل الأنشطة</h2><ul class="tl"><?php foreach ($c['activities'] as $a): ?>
        <li class="<?=h($a['type'])?>"><b><?=h(ABD_CRM_ACTS[$a['type']] ?? $a['type'])?></b> <span class="mut ltr"><?=h(substr((string)$a['created_at'], 0, 16))?></span><?= $a['by_name'] ? ' <span class="mut">— ' . h($a['by_name']) . '</span>' : '' ?><br><?=nl2br(h($a['body']))?></li>
      <?php endforeach; if (!$c['activities']): ?><li class="mut">لا توجد أنشطة.</li><?php endif; ?></ul></div>
    </div>
    <details style="margin-top:14px"><summary>✏️ تعديل بيانات العميل</summary>
      <form method="post"><?=$tok?><input type="hidden" name="do" value="crm_update"><input type="hidden" name="id" value="<?= $cid ?>">
        <div class="grid2"><label>الاسم *<input name="full_name" required maxlength="190" value="<?=h($c['full_name'])?>"></label>
          <label>الجوال<input name="phone" dir="ltr" maxlength="32" value="<?=h($c['phone'])?>"></label><label>البريد<input name="email" type="email" dir="ltr" maxlength="190" value="<?=h($c['email'])?>"></label>
          <label>المصدر<select name="source"><?= abd_crm_opts(ABD_CRM_SOURCES, $c['source']) ?></select></label>
          <label>الدورة / الشهادة المهتم بها<input name="interest" maxlength="255" value="<?=h($c['interest'])?>"></label>
          <label>المسؤول<select name="owner_id"><?= abd_crm_staff_opts($c['owner_id'] !== null ? (int)$c['owner_id'] : null) ?></select></label>
          <label>الوسوم (مفصولة بفواصل)<input name="tags" maxlength="500" value="<?=h(str_replace(',', '، ', (string)$c['tags']))?>"></label></div>
        <label class="mut">ملاحظات<textarea name="notes" maxlength="4000"><?=h($c['notes'])?></textarea></label><button class="btn g">حفظ</button></form>
      <?php if ($me['role'] === 'admin'): ?><form method="post" onsubmit="return confirm('حذف العميل وكل أنشطته ومهامه نهائياً؟')" style="margin-top:10px"><?=$tok?><input type="hidden" name="do" value="crm_delete"><input type="hidden" name="id" value="<?= $cid ?>"><button class="btn r s">🗑 حذف العميل</button></form><?php endif; ?>
    </details>
    <?php return;
  }
  // ---------------------------------------------------------------- KANBAN / LIST
  $view = ($_GET['view'] ?? 'kanban') === 'list' ? 'list' : 'kanban';
  $f = ['q' => abd_str($_GET, 'q'), 'source' => (string)($_GET['source'] ?? ''), 'owner_id' => (string)($_GET['owner_id'] ?? ''), 'tag' => abd_str($_GET, 'tag', 40),
        'stage' => $view === 'list' ? (string)($_GET['stage'] ?? '') : ''];
  $list = abd_crm_list($f, $view === 'list' ? $page : 1, $view === 'list' ? 50 : 500); ?>
  <h1>🧲 العملاء ومسار المبيعات</h1>
  <p><a class="btn s <?= $view === 'kanban' ? '' : 'l' ?>" href="<?=h(qs(['view' => 'kanban', 'page' => null]))?>">لوحة كانبان</a> <a class="btn s <?= $view === 'list' ? '' : 'l' ?>" href="<?=h(qs(['view' => 'list']))?>">قائمة</a></p>
  <form class="f" method="get"><input type="hidden" name="tab" value="crm"><input type="hidden" name="view" value="<?=h($view)?>">
    <label>بحث<input name="q" value="<?=h($f['q'])?>" placeholder="اسم، جوال، بريد، اهتمام، وسم"></label>
    <label>المصدر<select name="source"><?= abd_crm_opts(ABD_CRM_SOURCES, $f['source'], 'الكل') ?></select></label>
    <?php if ($view === 'list'): ?><label>المرحلة<select name="stage"><?= abd_crm_opts(ABD_CRM_STAGES, $f['stage'], 'الكل') ?></select></label><?php endif; ?>
    <label>المسؤول<select name="owner_id"><?= abd_crm_staff_opts($f['owner_id'] !== '' && $f['owner_id'] !== 'none' ? (int)$f['owner_id'] : null, 'الكل') ?><option value="none" <?= $f['owner_id'] === 'none' ? 'selected' : '' ?>>بدون مسؤول</option></select></label>
    <label>وسم<input name="tag" value="<?=h($f['tag'])?>" size="10"></label>
    <button class="btn">تصفية</button><a class="btn l" href="?tab=crm&view=<?=h($view)?>">مسح</a></form>
  <details style="margin-bottom:12px"><summary>➕ إضافة عميل محتمل</summary>
    <form method="post"><?=$tok?><input type="hidden" name="do" value="crm_create">
      <div class="grid2"><label>الاسم *<input name="full_name" required maxlength="190"></label><label>الجوال<input name="phone" dir="ltr" maxlength="32" placeholder="05xxxxxxxx"></label>
        <label>البريد<input name="email" type="email" dir="ltr" maxlength="190"></label><label>المصدر<select name="source"><?= abd_crm_opts(ABD_CRM_SOURCES, 'whatsapp') ?></select></label>
        <label>الدورة / الشهادة المهتم بها<input name="interest" maxlength="255"></label><label>المسؤول<select name="owner_id"><?= abd_crm_staff_opts($uid) ?></select></label>
        <label>المرحلة<select name="stage"><?= abd_crm_opts(ABD_CRM_STAGES, 'new') ?></select></label><label>الوسوم<input name="tags" maxlength="500" placeholder="CCNA، خصم"></label></div>
      <label class="mut">ملاحظة أولى<textarea name="first_note" maxlength="4000"></textarea></label><button class="btn g">إضافة</button></form></details>
  <?php if ($view === 'list'): ?>
    <p class="mut"><?= (int)$list['total'] ?> عميل</p>
    <table><thead><tr><th>العميل</th><th>المصدر</th><th>الاهتمام</th><th>المرحلة</th><th>المسؤول</th><th>المتابعة القادمة</th><th>آخر نشاط</th></tr></thead><tbody>
    <?php foreach ($list['items'] as $c): ?><tr><td><a href="?tab=crm&id=<?= (int)$c['id'] ?>"><b><?=h($c['full_name'])?></b></a><br><span class="mut ltr"><?=h($c['phone'] ?? '')?> <?=h($c['email'] ?? '')?></span><br><?= abd_crm_tags_html((string)$c['tags']) ?></td>
      <td><?=h(ABD_CRM_SOURCES[$c['source']] ?? $c['source'])?></td><td><?=h($c['interest'] ?? '—')?></td><td><span class="st st-<?=h($c['stage'])?>"><?=h(ABD_CRM_STAGES[$c['stage']])?></span></td>
      <td><?=h($c['owner_name'] ?? '—')?></td><td><?= abd_crm_due($c['next_due']) ?: '—' ?></td><td class="mut ltr"><?=h(substr((string)($c['last_activity'] ?? ''), 0, 16))?></td></tr>
    <?php endforeach; if (!$list['items']): ?><tr><td colspan="7" class="mut">لا يوجد عملاء.</td></tr><?php endif; ?></tbody></table>
    <?php pager($list['total'], $list['page'], $list['per_page']); return;
  endif;
  $by = array_fill_keys(array_keys(ABD_CRM_STAGES), []);
  foreach ($list['items'] as $c) $by[$c['stage']][] = $c; ?>
  <p class="mut">اسحب البطاقة إلى عمود آخر لتغيير المرحلة (أو استخدم القائمة داخل البطاقة).<?= $list['total'] > 500 ? ' يُعرض أحدث 500 عميل — استخدم التصفية.' : '' ?></p>
  <div class="kb" id="kb">
  <?php foreach (ABD_CRM_STAGES as $sk => $sv): ?>
    <section class="kc" data-stage="<?=h($sk)?>"><h3><span><?=h($sv)?></span><span class="mut"><?= count($by[$sk]) ?></span></h3>
    <?php foreach ($by[$sk] as $c): ?>
      <div class="kd" draggable="true" data-id="<?= (int)$c['id'] ?>"><b><a href="?tab=crm&id=<?= (int)$c['id'] ?>"><?=h($c['full_name'])?></a></b>
        <div class="mut"><?=h($c['interest'] ?? '')?></div><div class="mut ltr"><?=h($c['phone'] ?? $c['email'] ?? '')?></div>
        <div class="mut"><?=h(ABD_CRM_SOURCES[$c['source']] ?? '')?><?= $c['owner_name'] ? ' · 👤 ' . h($c['owner_name']) : '' ?></div>
        <?= abd_crm_due($c['next_due']) ?> <?= abd_crm_tags_html((string)$c['tags']) ?>
        <form method="post" class="mv"><?=$tok?><input type="hidden" name="do" value="crm_stage"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="ret" value="crm">
          <select name="stage" onchange="this.form.submit()" aria-label="نقل إلى مرحلة"><?= abd_crm_opts(ABD_CRM_STAGES, $sk) ?></select></form></div>
    <?php endforeach; ?></section>
  <?php endforeach; ?>
  </div>
  <script>
  (function(){var kb=document.getElementById('kb'),drag=null,csrf=<?= json_encode(abd_csrf()) ?>;
    kb.addEventListener('dragstart',function(e){var d=e.target.closest('.kd');if(!d)return;drag=d;e.dataTransfer.effectAllowed='move';try{e.dataTransfer.setData('text/plain',d.dataset.id)}catch(x){}});
    kb.addEventListener('dragover',function(e){var c=e.target.closest('.kc');if(!c||!drag)return;e.preventDefault();kb.querySelectorAll('.kc.over').forEach(function(x){if(x!==c)x.classList.remove('over')});c.classList.add('over')});
    kb.addEventListener('dragleave',function(e){var c=e.target.closest('.kc');if(c&&!c.contains(e.relatedTarget))c.classList.remove('over')});
    kb.addEventListener('drop',function(e){var c=e.target.closest('.kc');if(!c||!drag)return;e.preventDefault();c.classList.remove('over');var d=drag;drag=null;
      var from=d.closest('.kc'),st=c.dataset.stage;if(from===c)return;c.appendChild(d);d.querySelector('select[name=stage]').value=st;cnt();
      fetch('../api/crm.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({action:'stage',id:+d.dataset.id,stage:st})})
        .then(function(r){return r.json()}).then(function(j){if(!j.ok)throw 0}).catch(function(){from.appendChild(d);d.querySelector('select[name=stage]').value=from.dataset.stage;cnt();alert('تعذّر نقل البطاقة — أعد المحاولة.')})});
    function cnt(){kb.querySelectorAll('.kc').forEach(function(c){c.querySelector('h3 .mut').textContent=c.querySelectorAll('.kd').length})}
  })();
  </script>
<?php }
