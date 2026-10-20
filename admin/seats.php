<?php
// ABAD Digital Platform — EVE lab seat assignment (staff/admin). Login happens in index.php.
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/api/_lab_lib.php';

abd_session();
$me = abd_user();
if (!$me || !in_array($me['role'], ['admin', 'staff'], true)) { header('Location: index.php', true, 303); exit; }

function seats_back(string $msg, bool $err = false): void { $_SESSION['flash'] = [$msg, $err]; header('Location: seats.php', true, 303); exit; }
$dt = fn(string $s) => $s === '' ? null : (($d = date_create($s)) ? $d->format('Y-m-d H:i:s') : false);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!abd_csrf_ok((string)($_POST['csrf'] ?? ''))) seats_back('انتهت صلاحية النموذج، أعد المحاولة.', true);
  $do = (string)($_POST['do'] ?? '');
  try {
    if ($do === 'assign') {
      $seat = (int)($_POST['seat'] ?? 0);
      $em = abd_email(abd_str($_POST, 'email'));
      $uid = $em ? (int)abd_q('SELECT id FROM abd_users WHERE email = ? AND is_active = 1', [$em])->fetchColumn() : 0;
      $vf = $dt(abd_str($_POST, 'valid_from', 19)); $vt = $dt(abd_str($_POST, 'valid_to', 19));
      if ($seat < 1 || $seat > ABD_LAB_SEATS) seats_back('رقم المقعد غير صحيح.', true);
      if ($uid <= 0) seats_back('لا يوجد حساب نشط بهذا البريد. يجب أن يسجّل المتدرب في المنصة أولًا.', true);
      if ($vf === false || $vt === false || ($vf && $vt && $vt < $vf)) seats_back('تواريخ غير صحيحة.', true);
      abd_q('INSERT INTO abd_lab_seats (seat, user_id, valid_from, valid_to, note, created_by) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE seat = VALUES(seat), valid_from = VALUES(valid_from), valid_to = VALUES(valid_to), note = VALUES(note), created_by = VALUES(created_by)',
        [$seat, $uid, $vf, $vt, abd_str($_POST, 'note', 255) ?: null, (int)$me['id']]);
      seats_back('تم تعيين المقعد ' . $seat . ' لـ ' . $em . '.');
    }
    if ($do === 'unassign') {
      abd_q('DELETE FROM abd_lab_seats WHERE user_id = ?', [(int)($_POST['user_id'] ?? 0)]);
      seats_back('تم إلغاء التعيين.');
    }
  } catch (Throwable $e) { error_log('[abad-seats] ' . $e->getMessage()); seats_back('تعذّر الحفظ.', true); }
  seats_back('إجراء غير معروف.', true);
}

try {
  $rows = abd_q('SELECT s.*, u.full_name_ar, u.full_name_en, u.email,
      ((s.valid_from IS NULL OR s.valid_from <= NOW()) AND (s.valid_to IS NULL OR s.valid_to >= NOW())) AS live
    FROM abd_lab_seats s JOIN abd_users u ON u.id = s.user_id ORDER BY s.seat, s.valid_from')->fetchAll();
} catch (Throwable $e) { error_log('[abad-seats] ' . $e->getMessage()); abd_admin_fatal('جدول المقاعد غير موجود بعد. شغّل ترحيل قاعدة البيانات (api/bin/migrate.php).'); }
$by = []; foreach ($rows as $r) $by[(int)$r['seat']][] = $r;
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$tok = abd_csrf();
abd_admin_top('مقاعد المعمل', $me);
?>
<style>.sg{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px}.sc{border:1px solid var(--line);border-radius:12px;padding:10px 12px;background:#fafcfe}
.sc h3{margin:0 0 6px;font-size:15px;display:flex;justify-content:space-between}.sc h3 span{color:var(--g);background:var(--n);border-radius:8px;padding:0 8px}
.sc li{margin-bottom:6px}.sc ul{list-style:none;margin:0;padding:0;font-size:13px}.off{opacity:.55}</style>
<div class="card solo">
  <h1>💺 مقاعد المعمل</h1>
  <p class="mut">كل متدرب له مقعد واحد. بعد تسجيل دخوله إلى المنصة (نفس حساب الاختبارات) يفتح مقعده مباشرة من صفحة المعمل دون رمز إضافي. الأوقات بتوقيت الرياض.</p>
  <?php if ($flash): ?><div class="<?= $flash[1] ? 'err' : 'okm' ?>"><?=h($flash[0])?></div><?php endif; ?>
  <form class="f" method="post"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="assign">
    <label>المقعد<select name="seat" required><?php for ($i = 1; $i <= ABD_LAB_SEATS; $i++): ?><option value="<?=$i?>"><?=$i?></option><?php endfor; ?></select></label>
    <label>بريد المتدرب<input type="email" name="email" required dir="ltr" placeholder="trainee@example.com"></label>
    <label>من (اختياري)<input type="datetime-local" name="valid_from"></label>
    <label>إلى (اختياري)<input type="datetime-local" name="valid_to"></label>
    <label>ملاحظة<input name="note" maxlength="255"></label>
    <button class="btn g">تعيين / تحديث</button>
  </form>
  <div class="sg">
  <?php for ($i = 1; $i <= ABD_LAB_SEATS; $i++): ?>
    <div class="sc"><h3>مقعد <?=$i?> <span dir="ltr">eve<?=$i?></span></h3>
    <?php if (empty($by[$i])): ?><div class="mut">غير معيّن</div><?php else: ?><ul>
      <?php foreach ($by[$i] as $r): ?><li class="<?= $r['live'] ? '' : 'off' ?>"><b><?=h($r['full_name_ar'] ?: $r['full_name_en'])?></b> <span class="ltr mut"><?=h($r['email'])?></span><br>
        <span class="mut"><?= $r['valid_from'] || $r['valid_to'] ? h(($r['valid_from'] ? substr($r['valid_from'], 0, 16) : '…') . ' → ' . ($r['valid_to'] ? substr($r['valid_to'], 0, 16) : '…')) : 'دائم' ?><?= $r['live'] ? '' : ' (غير فعّال الآن)' ?><?= $r['note'] ? ' · ' . h($r['note']) : '' ?></span>
        <form class="i" method="post" onsubmit="return confirm('إلغاء تعيين هذا المتدرب؟')"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="unassign"><input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>"><button class="btn r s">إلغاء</button></form></li>
      <?php endforeach; ?></ul><?php endif; ?></div>
  <?php endfor; ?>
  </div>
  <p><a href="index.php">→ لوحة الإدارة</a></p>
</div>
<?php abd_admin_bottom();
