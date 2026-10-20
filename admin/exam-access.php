<?php
// ABAD Digital Platform — exam access (staff/admin): automatic access from booked bookings + manual grants.
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/api/_exam_lib.php';

abd_session();
$me = abd_user();
if (!$me || !in_array($me['role'], ['admin', 'staff'], true)) { header('Location: index.php', true, 303); exit; }
$CERTS = abd_exam_certs();
function ea_back(string $msg, bool $err = false): void { $_SESSION['flash'] = [$msg, $err]; header('Location: exam-access.php', true, 303); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!abd_csrf_ok((string)($_POST['csrf'] ?? ''))) ea_back('انتهت صلاحية النموذج، أعد المحاولة.', true);
  $do = (string)($_POST['do'] ?? '');
  try {
    if ($do === 'grant') {
      $cert = abd_str($_POST, 'cert_id', 40);
      $em = abd_email(abd_str($_POST, 'email'));
      $uid = $em ? (int)abd_q('SELECT id FROM abd_users WHERE email = ? AND is_active = 1', [$em])->fetchColumn() : 0;
      $exp = abd_str($_POST, 'expires_at', 19); $exp = $exp === '' ? null : (($d = date_create($exp)) ? $d->format('Y-m-d H:i:s') : false);
      if (!isset($CERTS[$cert])) ea_back('اختر الاختبار.', true);
      if ($uid <= 0) ea_back('لا يوجد حساب نشط بهذا البريد. يجب أن يسجّل المتدرب في المنصة أولًا.', true);
      if ($exp === false) ea_back('تاريخ الانتهاء غير صحيح.', true);
      abd_q("INSERT INTO abd_exam_access (user_id, cert_id, source, granted_by, note, expires_at) VALUES (?, ?, 'manual', ?, ?, ?)",
        [$uid, $cert, (int)$me['id'], abd_str($_POST, 'note', 255) ?: null, $exp]);
      ea_back('تم فتح ' . $CERTS[$cert]['title'] . ' لـ ' . $em . '.');
    }
    if ($do === 'revoke') { abd_q('UPDATE abd_exam_access SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL', [(int)($_POST['id'] ?? 0)]); ea_back('تم إلغاء الصلاحية.'); }
  } catch (Throwable $e) { error_log('[abad-exam-access] ' . $e->getMessage()); ea_back('تعذّر الحفظ.', true); }
  ea_back('إجراء غير معروف.', true);
}

try {
  $grants = abd_q('SELECT a.*, u.email, u.full_name_ar, u.full_name_en,
      (a.revoked_at IS NULL AND (a.expires_at IS NULL OR a.expires_at >= NOW())) AS live
    FROM abd_exam_access a JOIN abd_users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 300')->fetchAll();
  $booked = abd_q("SELECT b.ref, b.cert, b.cert_id, b.email, b.name_en, b.preferred_date, COALESCE(b.user_id, u2.id) AS uid, COALESCE(u1.email, u2.email) AS acct
    FROM abd_bookings b LEFT JOIN abd_users u1 ON u1.id = b.user_id LEFT JOIN abd_users u2 ON b.user_id IS NULL AND u2.email = b.email
    WHERE b.status = 'booked' ORDER BY b.updated_at DESC LIMIT 300")->fetchAll();
} catch (Throwable $e) { error_log('[abad-exam-access] ' . $e->getMessage()); abd_admin_fatal('جدول صلاحيات الاختبارات غير موجود بعد. شغّل ترحيل قاعدة البيانات (api/bin/migrate.php).'); }
$certOf = fn(array $b): ?string => abd_exam_cert_of_booking($b['cert_id'] ?? null, (string)$b['cert']);
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$tok = abd_csrf();
$SRC = ['manual' => 'يدوي', 'booking' => 'حجز', 'webhook' => 'الموقع الرسمي'];
abd_admin_top('صلاحيات الاختبارات', $me);
?>
<div class="card solo">
  <h1>🎟️ صلاحيات الاختبارات</h1>
  <p class="mut">يفتح الاختبار تلقائيًا للمتدرب عندما تكون حالة حجزه «تم الحجز» ويكون الحجز مرتبطًا بحسابه (أو بنفس البريد). ويمكنك أيضًا فتح اختبار يدويًا.</p>
  <?php if ($flash): ?><div class="<?= $flash[1] ? 'err' : 'okm' ?>"><?=h($flash[0])?></div><?php endif; ?>
  <h2>فتح اختبار يدويًا</h2>
  <form class="f" method="post"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="grant">
    <label>بريد المتدرب<input type="email" name="email" required dir="ltr" placeholder="trainee@example.com"></label>
    <label>الاختبار<select name="cert_id" required><option value="">—</option><?php foreach ($CERTS as $id => $c): ?><option value="<?=h($id)?>"><?=h($c['title'])?></option><?php endforeach; ?></select></label>
    <label>ينتهي (اختياري)<input type="datetime-local" name="expires_at"></label>
    <label>ملاحظة<input name="note" maxlength="255"></label>
    <button class="btn g">فتح الاختبار</button>
  </form>
</div>
<div class="card solo"><h2>الصلاحيات اليدوية / الخارجية</h2>
  <table><thead><tr><th>المتدرب</th><th>الاختبار</th><th>المصدر</th><th>ينتهي</th><th>الحالة</th><th></th></tr></thead><tbody>
  <?php if (!$grants): ?><tr><td colspan="6" class="mut">لا توجد صلاحيات بعد.</td></tr><?php endif; ?>
  <?php foreach ($grants as $g): ?><tr><td><b><?=h($g['full_name_ar'] ?: $g['full_name_en'])?></b><br><span class="ltr mut"><?=h($g['email'])?></span></td>
    <td><?=h($CERTS[$g['cert_id']]['title'] ?? $g['cert_id'])?></td><td><?=h($SRC[$g['source']] ?? $g['source'])?><?= $g['note'] ? '<br><span class="mut">' . h($g['note']) . '</span>' : '' ?></td>
    <td class="mut"><?= $g['expires_at'] ? h(substr($g['expires_at'], 0, 16)) : 'بلا انتهاء' ?></td>
    <td><?= $g['live'] ? '<span class="st st-booked">مفتوح</span>' : '<span class="st st-cancelled">' . ($g['revoked_at'] ? 'ملغى' : 'منتهي') . '</span>' ?></td>
    <td><?php if ($g['live']): ?><form class="i" method="post" onsubmit="return confirm('إلغاء الصلاحية؟')"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="revoke"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="btn r s">إلغاء</button></form><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<div class="card solo"><h2>تلقائي من الحجوزات المؤكدة («تم الحجز»)</h2>
  <table><thead><tr><th>المرجع</th><th>المتقدّم</th><th>الشهادة في الحجز</th><th>الاختبار المفتوح</th><th>حساب المنصة</th></tr></thead><tbody>
  <?php if (!$booked): ?><tr><td colspan="5" class="mut">لا توجد حجوزات مؤكدة.</td></tr><?php endif; ?>
  <?php foreach ($booked as $b): $c = $certOf($b); ?><tr><td class="ltr"><?=h($b['ref'])?></td><td><?=h($b['name_en'])?><br><span class="ltr mut"><?=h($b['email'])?></span></td>
    <td><?=h($b['cert'])?></td><td><?= $c ? h($CERTS[$c]['title']) : '<span class="mut">لا يوجد اختبار تفاعلي مطابق</span>' ?></td>
    <td><?= $b['uid'] ? '✅ <span class="ltr">' . h($b['acct']) . '</span>' : '<span class="due-today">⚠️ لا يوجد حساب بهذا البريد — يفتح بعد تسجيل المتدرب</span>' ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php abd_admin_bottom();
