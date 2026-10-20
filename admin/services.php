<?php
// ABAD Digital Platform — trainee services (staff/admin): search a trainee, grant / revoke exams, materials, lab seats.
// Writes go to abd_entitlements (migration 005). Rows from abd_exam_access / abd_lab_seats are shown and can be revoked too;
// bookings with status 'booked' open their exam automatically (manage those from the bookings tab).
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/api/_services_lib.php';

abd_session();
$me = abd_user();
if (!$me || !in_array($me['role'], ['admin', 'staff'], true)) { header('Location: index.php', true, 303); exit; }
function sv_back(int $uid, string $msg, bool $err = false): void {
  $_SESSION['flash'] = [$msg, $err]; header('Location: services.php' . ($uid ? '?uid=' . $uid : ''), true, 303); exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $uid = (int)($_POST['uid'] ?? 0);
  if (!abd_csrf_ok((string)($_POST['csrf'] ?? ''))) sv_back($uid, 'انتهت صلاحية النموذج، أعد المحاولة.', true);
  $do = (string)($_POST['do'] ?? '');
  try {
    $ok = $uid > 0 && abd_q('SELECT 1 FROM abd_users WHERE id = ? AND is_active = 1', [$uid])->fetchColumn();
    if (!$ok) sv_back(0, 'المتدرب غير موجود.', true);
    if ($do === 'grant') {
      $kind = (string)($_POST['kind'] ?? '');
      $ref = abd_str($_POST, 'ref_' . $kind, 60);
      if (!isset(ABD_ENT_KINDS[$kind])) sv_back($uid, 'اختر نوع الخدمة.', true);
      $refs = abd_ent_refs($kind);
      if (!isset($refs[$ref])) sv_back($uid, 'اختر العنصر.', true);
      $dup = abd_q('SELECT 1 FROM abd_entitlements WHERE user_id = ? AND kind = ? AND ref_id = ? AND revoked_at IS NULL', [$uid, $kind, $ref])->fetchColumn();
      if ($dup) sv_back($uid, 'هذه الخدمة مفتوحة بالفعل.', true);
      if ($kind === 'lab') abd_q("UPDATE abd_entitlements SET revoked_at = NOW() WHERE user_id = ? AND kind = 'lab' AND revoked_at IS NULL", [$uid]); // one seat per trainee
      abd_q("INSERT INTO abd_entitlements (user_id, kind, ref_id, source, granted_by, note) VALUES (?, ?, ?, 'manual', ?, ?)",
        [$uid, $kind, $ref, (int)$me['id'], abd_str($_POST, 'note', 255) ?: null]);
      sv_back($uid, 'تم فتح ' . ABD_ENT_KINDS[$kind] . ': ' . $refs[$ref] . '.');
    }
    if ($do === 'revoke') {
      $id = (int)($_POST['id'] ?? 0); $t = (string)($_POST['t'] ?? '');
      if ($t === 'ent') abd_q('UPDATE abd_entitlements SET revoked_at = NOW() WHERE id = ? AND user_id = ? AND revoked_at IS NULL', [$id, $uid]);
      elseif ($t === 'xa') abd_q('UPDATE abd_exam_access SET revoked_at = NOW() WHERE id = ? AND user_id = ? AND revoked_at IS NULL', [$id, $uid]);
      elseif ($t === 'seat') abd_q('DELETE FROM abd_lab_seats WHERE id = ? AND user_id = ?', [$id, $uid]);
      else sv_back($uid, 'إجراء غير معروف.', true);
      sv_back($uid, 'تم إلغاء الخدمة.');
    }
  } catch (Throwable $e) { error_log('[abad-services] ' . $e->getMessage()); sv_back($uid, 'تعذّر الحفظ. تأكد من تشغيل ترحيل 005.', true); }
  sv_back($uid, 'إجراء غير معروف.', true);
}

$q = trim(abd_str($_GET, 'q', 120));
$uid = (int)($_GET['uid'] ?? 0);
$found = []; $tr = null; $rows = []; $auto = [];
try {
  if ($q !== '') {
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    $found = abd_q("SELECT id, email, full_name_ar, full_name_en, phone, role FROM abd_users WHERE is_active = 1
      AND (email LIKE ? OR full_name_ar LIKE ? OR full_name_en LIKE ? OR phone LIKE ?) ORDER BY role = 'trainee' DESC, id DESC LIMIT 50", [$like, $like, $like, $like])->fetchAll();
  }
  if ($uid > 0) {
    $tr = abd_q('SELECT id, email, full_name_ar, full_name_en, phone, role FROM abd_users WHERE id = ? AND is_active = 1', [$uid])->fetch() ?: null;
    if ($tr) {
      foreach (abd_q('SELECT id, kind, ref_id, source, note, created_at FROM abd_entitlements WHERE user_id = ? AND revoked_at IS NULL ORDER BY created_at DESC', [$uid])->fetchAll() as $r)
        $rows[] = ['t' => 'ent'] + $r;
      foreach (abd_q('SELECT id, cert_id AS ref_id, source, note, created_at FROM abd_exam_access WHERE user_id = ? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at >= NOW())', [$uid])->fetchAll() as $r)
        $rows[] = ['t' => 'xa', 'kind' => 'exam'] + $r;
      foreach (abd_q('SELECT id, seat AS ref_id, note, created_at FROM abd_lab_seats WHERE user_id = ?', [$uid])->fetchAll() as $r)
        $rows[] = ['t' => 'seat', 'kind' => 'lab', 'source' => 'seats'] + $r;
      $auto = abd_q("SELECT ref, cert, cert_id FROM abd_bookings WHERE status = 'booked' AND (user_id = ? OR (user_id IS NULL AND email = ?))", [$uid, mb_strtolower((string)$tr['email'])])->fetchAll();
    }
  }
} catch (Throwable $e) { error_log('[abad-services] ' . $e->getMessage()); abd_admin_fatal('جدول الخدمات غير موجود بعد. شغّل ترحيل قاعدة البيانات (api/bin/migrate.php).'); }
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$tok = abd_csrf();
$SRC = ['manual' => 'يدوي', 'booking' => 'حجز', 'website' => 'الموقع الرسمي', 'webhook' => 'الموقع الرسمي', 'seats' => 'مقاعد المعمل'];
$REFS = []; foreach (ABD_ENT_KINDS as $k => $_) $REFS[$k] = abd_ent_refs($k);
$nm = fn(array $u): string => (string)($u['full_name_ar'] ?: $u['full_name_en'] ?: $u['email']);
abd_admin_top('خدمات المتدربين', $me);
?>
<div class="card solo">
  <h1>🧾 خدمات المتدربين</h1>
  <p class="mut">ابحث عن المتدرب ثم افتح له أي خدمة (اختبار، ماتريال، لاب) أو ألغِها. تظهر الخدمات المفتوحة للمتدرب في صفحة «خدماتي».</p>
  <?php if ($flash): ?><div class="<?= $flash[1] ? 'err' : 'okm' ?>"><?=h($flash[0])?></div><?php endif; ?>
  <form class="f" method="get"><label>بحث (البريد، الاسم، الجوال)<input name="q" value="<?=h($q)?>" placeholder="trainee@example.com" style="min-width:300px" autofocus></label><button class="btn">بحث</button></form>
  <?php if ($q !== ''): ?>
  <table><thead><tr><th>المتدرب</th><th>البريد</th><th>الجوال</th><th>الدور</th><th></th></tr></thead><tbody>
  <?php if (!$found): ?><tr><td colspan="5" class="mut">لا توجد نتائج.</td></tr><?php endif; ?>
  <?php foreach ($found as $f): ?><tr><td><b><?=h($nm($f))?></b></td><td><span class="ltr"><?=h($f['email'])?></span></td><td><span class="ltr"><?=h($f['phone'])?></span></td>
    <td><span class="st st-<?=h($f['role'])?>"><?=h($f['role'])?></span></td><td><a class="btn s <?= (int)$f['id'] === $uid ? 'g' : 'l' ?>" href="?q=<?=h(urlencode($q))?>&amp;uid=<?=(int)$f['id']?>">إدارة الخدمات</a></td></tr>
  <?php endforeach; ?></tbody></table>
  <?php endif; ?>
</div>
<?php if ($tr): ?>
<div class="card solo">
  <h2>👤 <?=h($nm($tr))?> <span class="ltr mut"><?=h($tr['email'])?></span></h2>
  <form class="f" method="post"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="grant"><input type="hidden" name="uid" value="<?=(int)$tr['id']?>">
    <label>نوع الخدمة<select name="kind" id="kind" required><?php foreach (ABD_ENT_KINDS as $k => $v): ?><option value="<?=h($k)?>"><?=h($v)?></option><?php endforeach; ?></select></label>
    <?php foreach ($REFS as $k => $opts): ?><label data-kind="<?=h($k)?>">العنصر<select name="ref_<?=h($k)?>"><?php foreach ($opts as $id => $t): ?><option value="<?=h($id)?>"><?=h($t)?></option><?php endforeach; ?></select></label><?php endforeach; ?>
    <label>ملاحظة<input name="note" maxlength="255"></label>
    <button class="btn g">فتح الخدمة</button>
  </form>
  <table><thead><tr><th>النوع</th><th>العنصر</th><th>المصدر</th><th>منذ</th><th></th></tr></thead><tbody>
  <?php if (!$rows && !$auto): ?><tr><td colspan="5" class="mut">لا توجد خدمات مفتوحة لهذا المتدرب.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?><tr><td><?=h(ABD_ENT_KINDS[$r['kind']])?></td><td><?=h($REFS[$r['kind']][(string)$r['ref_id']] ?? $r['ref_id'])?></td>
    <td><?=h($SRC[$r['source']] ?? $r['source'])?><?= $r['note'] ? '<br><span class="mut">' . h($r['note']) . '</span>' : '' ?></td><td class="mut"><?=h(substr((string)$r['created_at'], 0, 16))?></td>
    <td><form class="i" method="post" onsubmit="return confirm('إلغاء هذه الخدمة؟')"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="revoke"><input type="hidden" name="uid" value="<?=(int)$tr['id']?>"><input type="hidden" name="t" value="<?=h($r['t'])?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn r s">إلغاء</button></form></td></tr>
  <?php endforeach; ?>
  <?php foreach ($auto as $b): $c = abd_exam_cert_of_booking($b['cert_id'] ?? null, (string)$b['cert']); if (!$c) continue; ?><tr><td><?=h(ABD_ENT_KINDS['exam'])?></td><td><?=h($REFS['exam'][$c] ?? $c)?></td><td>حجز مؤكد <span class="ltr mut"><?=h($b['ref'])?></span></td><td class="mut">تلقائي</td><td class="mut">من الحجوزات</td></tr><?php endforeach; ?>
  </tbody></table>
</div>
<script>(function(){var k=document.getElementById('kind');function s(){document.querySelectorAll('[data-kind]').forEach(function(l){var on=l.getAttribute('data-kind')===k.value;l.style.display=on?'':'none';l.querySelector('select').disabled=!on})}k.onchange=s;s()})();</script>
<?php endif; abd_admin_bottom();
