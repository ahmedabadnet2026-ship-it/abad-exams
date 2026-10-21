<?php
// ABAD Digital Platform — trainee course enrollments with per-trainee dates + exam-booking email status (admin only).
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/api/courses.php';

abd_session();
$me = abd_user();
if (!$me || $me['role'] !== 'admin') { header('Location: index.php', true, 303); exit; }

function enr_back(string $msg, bool $err = false, string $q = ''): void { $_SESSION['flash'] = [$msg, $err]; header('Location: enrollments.php' . $q, true, 303); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!abd_csrf_ok((string)($_POST['csrf'] ?? ''))) enr_back('انتهت صلاحية النموذج، أعد المحاولة.', true);
  $do = (string)($_POST['do'] ?? '');
  try {
    if ($do === 'save') {
      $eid = (int)($_POST['id'] ?? 0);
      [$sd, $ed, $bad] = abd_enr_dates($_POST);
      if ($bad) enr_back('تواريخ غير صحيحة (تاريخ النهاية يجب أن يكون بعد البداية).', true);
      $st = abd_str($_POST, 'status', 20); if (!in_array($st, ['active', 'completed', 'cancelled'], true)) $st = 'active';
      if ($eid > 0) {
        $old = abd_q('SELECT end_date FROM abd_enrollments WHERE id = ?', [$eid])->fetch();
        if (!$old) enr_back('التسجيل غير موجود.', true);
        // changing the end date re-arms the exam email for the new date
        $rearm = ($old['end_date'] ?? null) !== $ed;
        abd_q('UPDATE abd_enrollments SET start_date = ?, end_date = ?, status = ?' . ($rearm ? ', exam_mail_sent_at = NULL, exam_mail_attempts = 0, exam_mail_last_error = NULL' : '') . ' WHERE id = ?',
          [$sd, $ed, $st, $eid]);
        enr_back('تم حفظ التسجيل.');
      }
      $uid = (int)($_POST['user_id'] ?? 0); $cid = (int)($_POST['course_id'] ?? 0);
      $r = abd_course_action('enroll', ['id' => $cid, 'user_id' => $uid, 'start_date' => $sd ?? '', 'end_date' => $ed ?? '']);
      if (!$r[0]) enr_back($r[1] === 'user_not_found' ? 'المتدرب غير موجود.' : ($r[1] === 'not_found' ? 'الدورة غير موجودة.' : 'تعذّر الحفظ.'), true);
      enr_back('تم تسجيل المتدرب في الدورة.');
    }
    if ($do === 'resend') {
      abd_q('UPDATE abd_enrollments SET exam_mail_sent_at = NULL, exam_mail_attempts = 0, exam_mail_last_error = NULL WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
      enr_back('سيُعاد إرسال بريد حجز الاختبار في تاريخ نهاية الدورة.');
    }
    if ($do === 'delete') {
      abd_q('DELETE FROM abd_enrollments WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
      enr_back('تم حذف التسجيل.');
    }
  } catch (Throwable $e) { error_log('[abad-enr] ' . $e->getMessage()); enr_back('تعذّر الحفظ.', true); }
  enr_back('إجراء غير معروف.', true);
}

try {
  $users = abd_q("SELECT id, full_name_ar, full_name_en, email FROM abd_users WHERE is_active = 1 AND role = 'trainee' ORDER BY full_name_ar, email")->fetchAll();
  $courses = abd_q('SELECT id, title_ar, title_en, start_date, end_date FROM abd_courses ORDER BY is_active DESC, COALESCE(start_date, \'9999-12-31\') DESC')->fetchAll();
  $rows = abd_q("SELECT e.*, u.full_name_ar, u.full_name_en, u.email, c.title_ar, c.title_en,
      c.start_date AS c_start, c.end_date AS c_end, COALESCE(e.end_date, c.end_date) AS eff_end, COALESCE(e.start_date, c.start_date) AS eff_start
    FROM abd_enrollments e JOIN abd_users u ON u.id = e.user_id JOIN abd_courses c ON c.id = e.course_id
    ORDER BY COALESCE(e.end_date, c.end_date, '9999-12-31') ASC, e.id DESC")->fetchAll();
} catch (Throwable $e) { error_log('[abad-enr] ' . $e->getMessage()); abd_admin_fatal('أعمدة التسجيل غير موجودة بعد. شغّل ترحيل قاعدة البيانات (api/bin/migrate.php).'); }
$edit = null; $eid = (int)($_GET['edit'] ?? 0); foreach ($rows as $r) if ((int)$r['id'] === $eid) $edit = $r;
$today = date('Y-m-d');
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$tok = abd_csrf();
$ENR = ['active' => 'نشط', 'completed' => 'مكتمل', 'cancelled' => 'ملغى'];
$due = count(array_filter($rows, fn($r) => $r['eff_end'] === $today && !$r['exam_mail_sent_at'] && $r['status'] !== 'cancelled'));
abd_admin_top('تسجيل المتدربين في الدورات', $me);
?>
<div class="card solo">
  <h1>🗓️ تسجيل المتدربين في الدورات وبريد حجز الاختبار</h1>
  <p class="mut">حدّد دورة كل متدرب وتاريخي البداية والنهاية. في <b>تاريخ نهاية الدورة</b> (بتوقيت الرياض) يُرسَل للمتدرب تلقائيًا بريد «حجز الاختبار» مرة واحدة فقط، وفيه زر يفتح صفحة حجز الاختبار في المنصة. اترك التاريخ فارغًا لاعتماد تاريخ الدورة.</p>
  <div class="stats"><div><b><?= count($rows) ?></b>تسجيل</div><div><b><?= $due ?></b>بريد مستحق اليوم</div><div><b><?= count(array_filter($rows, fn($r) => (bool)$r['exam_mail_sent_at'])) ?></b>بريد مُرسل</div></div>
  <?php if ($flash): ?><div class="<?= $flash[1] ? 'err' : 'okm' ?>"><?=h($flash[0])?></div><?php endif; ?>
  <h2><?= $edit ? '✏️ تعديل تسجيل' : '➕ تسجيل متدرب في دورة' ?></h2>
  <form class="f" method="post"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="save">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
      <label>المتدرب<input value="<?=h($edit['full_name_ar'] ?: $edit['email'])?>" disabled></label><label>الدورة<input value="<?=h($edit['title_ar'])?>" disabled></label>
    <?php else: ?>
      <label>المتدرب<select name="user_id" required><option value="">— اختر المتدرب —</option><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?=h(($u['full_name_ar'] ?: $u['full_name_en']) . ' — ' . $u['email'])?></option><?php endforeach; ?></select></label>
      <label>الدورة<select name="course_id" required><option value="">— اختر الدورة —</option><?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?=h($c['title_ar'] . ($c['start_date'] ? ' (' . $c['start_date'] . ' → ' . ($c['end_date'] ?? '—') . ')' : ''))?></option><?php endforeach; ?></select></label>
    <?php endif; ?>
    <label>تاريخ البداية<input type="date" name="start_date" value="<?=h($edit['start_date'] ?? '')?>"></label>
    <label>تاريخ النهاية (يوم إرسال البريد)<input type="date" name="end_date" value="<?=h($edit['end_date'] ?? '')?>"></label>
    <label>الحالة<select name="status"><?php foreach ($ENR as $k => $v): ?><option value="<?=h($k)?>" <?= ($edit['status'] ?? 'active') === $k ? 'selected' : '' ?>><?=h($v)?></option><?php endforeach; ?></select></label>
    <button class="btn g"><?= $edit ? 'حفظ التعديلات' : 'تسجيل' ?></button><?php if ($edit): ?><a class="btn l" href="enrollments.php">إلغاء</a><?php endif; ?>
  </form>
  <table><thead><tr><th>المتدرب</th><th>الدورة</th><th>البداية</th><th>النهاية</th><th>الحالة</th><th>بريد حجز الاختبار</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r):
    if ($r['exam_mail_sent_at']) $ms = '<span class="st st-booked">أُرسل ' . h(substr((string)$r['exam_mail_sent_at'], 0, 16)) . '</span>';
    elseif ($r['status'] === 'cancelled') $ms = '<span class="mut">—</span>';
    elseif (!$r['eff_end']) $ms = '<span class="st st-cancelled">لا يوجد تاريخ نهاية</span>';
    elseif ($r['eff_end'] === $today) $ms = '<span class="st st-new">يُرسل اليوم</span>';
    elseif ($r['eff_end'] > $today) $ms = '<span class="st st-contacted">مجدول ' . h($r['eff_end']) . '</span>';
    else $ms = '<span class="st st-cancelled">فات الموعد دون إرسال</span>';
    if ($r['exam_mail_last_error']) $ms .= '<br><span class="mut">آخر خطأ: ' . h($r['exam_mail_last_error']) . '</span>'; ?>
    <tr><td><b><?=h($r['full_name_ar'] ?: $r['full_name_en'])?></b><br><span class="mut ltr"><?=h($r['email'])?></span></td>
      <td><?=h($r['title_ar'])?><br><span class="mut ltr"><?=h($r['title_en'])?></span></td>
      <td class="ltr"><?=h($r['eff_start'] ?? '—')?><?= $r['start_date'] ? '' : '<br><span class="mut">من الدورة</span>' ?></td>
      <td class="ltr"><?=h($r['eff_end'] ?? '—')?><?= $r['end_date'] ? '' : '<br><span class="mut">من الدورة</span>' ?></td>
      <td><?=h($ENR[$r['status']] ?? $r['status'])?></td><td><?=$ms?></td>
      <td style="white-space:nowrap"><a class="btn s" href="?edit=<?= (int)$r['id'] ?>">تعديل</a>
        <?php if ($r['exam_mail_sent_at']): ?><form class="i" method="post" onsubmit="return confirm('إعادة جدولة البريد؟ سيُرسل مجددًا في تاريخ النهاية.')"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="resend"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn s l">إعادة جدولة</button></form><?php endif; ?>
        <form class="i" method="post" onsubmit="return confirm('حذف هذا التسجيل؟')"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn s r">حذف</button></form></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="mut">لا توجد تسجيلات بعد.</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?php abd_admin_bottom();
