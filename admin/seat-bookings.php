<?php
// ABAD Digital Platform — seat bookings per seat × time slot (staff/admin). Times Asia/Riyadh.
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/api/_booking_lib.php';

if (!abd_book_any_on()) { http_response_code(404); exit; }   // SEAT_BOOKING off: page not usable
abd_session();
$me = abd_user();
if (!$me || !in_array($me['role'], ['admin', 'staff'], true)) { header('Location: index.php', true, 303); exit; }
$lab = isset(ABD_BOOK_RESOURCES[$_GET['lab'] ?? '']) ? (string)$_GET['lab'] : 'eve';
$day = abd_date((string)($_GET['date'] ?? '')) ?? date('Y-m-d');
$self = 'seat-bookings.php?lab=' . urlencode($lab) . '&date=' . urlencode($day);
function sb_back(string $to, string $msg, bool $err = false): void { $_SESSION['flash'] = [$msg, $err]; header('Location: ' . $to, true, 303); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!abd_csrf_ok((string)($_POST['csrf'] ?? ''))) sb_back($self, 'انتهت صلاحية النموذج، أعد المحاولة.', true);
  $do = (string)($_POST['do'] ?? '');
  $E = ['taken' => 'الخانة محجوزة بالفعل.', 'have_slot' => 'لدى المتدرب حجز آخر في نفس الفترة.', 'past' => 'الفترة انتهت.', 'slot' => 'فترة غير صحيحة.', 'seat' => 'مقعد غير صحيح.'];
  try {
    if ($do === 'book') {
      $em = abd_email(abd_str($_POST, 'email'));
      $uid = $em ? (int)abd_q('SELECT id FROM abd_users WHERE email = ? AND is_active = 1', [$em])->fetchColumn() : 0;
      if ($uid <= 0) sb_back($self, 'لا يوجد حساب نشط بهذا البريد.', true);
      [$ok, $e] = abd_book_create($uid, $lab, (int)($_POST['seat'] ?? 0), abd_str($_POST, 'start', 19), (int)$me['id'], true, abd_str($_POST, 'note', 255));
      sb_back($self, $ok ? 'تم الحجز لـ ' . $em . '.' : ($E[$e] ?? 'تعذّر الحجز (' . $e . ').'), !$ok);
    }
    if ($do === 'cancel') sb_back($self, abd_book_cancel((int)($_POST['id'] ?? 0), (int)$me['id'], null) ? 'تم إلغاء الحجز.' : 'الحجز غير موجود.');
  } catch (Throwable $e) { error_log('[abad-book-admin] ' . $e->getMessage()); sb_back($self, 'تعذّر الحفظ.', true); }
  sb_back($self, 'إجراء غير معروف.', true);
}
try { $by = abd_book_day($lab, $day, true); }
catch (Throwable $e) { error_log('[abad-book-admin] ' . $e->getMessage()); abd_admin_fatal('جدول الحجوزات غير موجود بعد. شغّل ترحيل قاعدة البيانات (api/bin/migrate.php) — 014_seat_bookings.sql.'); }
$slots = abd_book_day_slots($day); $seats = abd_book_seats($lab); $req = abd_book_required($lab);
$cnt = 0; foreach ($by as $r) $cnt += count($r);
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$tok = abd_csrf();
$hm = fn(string $s) => (($h = (int)substr($s, 11, 2)) % 12 ?: 12) . ':' . substr($s, 14, 2) . ($h >= 12 ? ' م' : ' ص');
abd_admin_top('حجوزات المقاعد', $me);
?>
<style>.bt{border-collapse:separate;border-spacing:4px;min-width:max-content}.bt th,.bt td{border:0;padding:6px 8px;border-radius:8px;font-size:13px}.bt td{background:#f3f7fa;min-width:104px}.bt td .ltr{font-size:11.5px}
.bt td.b{background:#fff4d6;border:1px solid #f5d77f}.bt td.p{opacity:.5}.bt td b{display:block}.bt .tm{direction:ltr;white-space:nowrap;font-weight:800}.flag{display:inline-block;border-radius:999px;padding:2px 12px;font-size:12.5px;font-weight:800}
.flag.on{background:#e6f6ec;color:#17603a}.flag.off{background:#f4e4e4;color:#922}.lt{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px}.lt a{padding:6px 12px;border-radius:9px;background:#eef4f9;text-decoration:none;color:var(--n);font-weight:700}.lt a.on{background:var(--n);color:var(--g)}</style>
<div class="card solo">
  <h1>📅 حجوزات المقاعد <span class="mut" style="font-size:14px;font-weight:400"><?=h(ABD_BOOK_RESOURCES[$lab])?> · <?=h($day)?> · <?=$cnt?> حجز</span></h1>
  <p class="mut">الحجز بالموعد: <span class="flag <?= $req ? 'on' : 'off' ?>"><?= $req ? 'مفعّل — الرمز وبيانات الدخول لصاحب حجز الفترة الحالية فقط' : 'غير مفعّل — النظام الحالي (التعيين/التسجيل)' ?></span>
    · يُفعَّل من lab.php الخاص (SEAT_BOOKING). المشرفون يدخلون دائمًا. الأوقات بتوقيت الرياض.</p>
  <?php if ($flash): ?><div class="<?= $flash[1] ? 'err' : 'okm' ?>"><?=h($flash[0])?></div><?php endif; ?>
  <div class="lt"><?php foreach (ABD_BOOK_RESOURCES as $id => $t): ?><a class="<?= $id === $lab ? 'on' : '' ?>" href="?lab=<?=h($id)?>&date=<?=h($day)?>"><?=h($t)?></a><?php endforeach; ?></div>
  <form class="f" method="get"><input type="hidden" name="lab" value="<?=h($lab)?>"><label>اليوم<input type="date" name="date" value="<?=h($day)?>"></label><button class="btn l">عرض</button>
    <a class="btn l" href="?lab=<?=h($lab)?>&date=<?=h(date('Y-m-d', strtotime($day) - 86400))?>">‹ السابق</a><a class="btn l" href="?lab=<?=h($lab)?>&date=<?=h(date('Y-m-d', strtotime($day) + 86400))?>">التالي ›</a></form>
  <form class="f" method="post" action="<?=h($self)?>"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="book">
    <label>المقعد<select name="seat"><?php foreach ($seats as $n): ?><option value="<?=$n?>"><?=$n?></option><?php endforeach; ?></select></label>
    <label>الفترة<select name="start"><?php foreach ($slots as $s): ?><option value="<?=h($s['start'])?>"><?=h($hm($s['start']) . ' – ' . $hm($s['end']))?></option><?php endforeach; ?></select></label>
    <label>بريد المتدرب<input type="email" name="email" required dir="ltr" placeholder="trainee@example.com"></label>
    <label>ملاحظة<input name="note" maxlength="255"></label><button class="btn g">حجز للمتدرب</button></form>
  <div style="overflow-x:auto"><table class="bt"><thead><tr><th>المقعد</th><?php foreach ($slots as $s): ?><th class="tm"><?=h($hm($s['start']))?></th><?php endforeach; ?></tr></thead><tbody>
  <?php foreach ($seats as $n): ?><tr><th>مقعد <?=$n?><?php if ($lab === 'eve'): ?> <span class="mut ltr">eve<?=$n?></span><?php endif; ?></th>
    <?php foreach ($slots as $s): $r = $by[$n][$s['start']] ?? null; $past = strtotime($s['end']) <= time(); ?>
      <td class="<?= $r ? 'b' : ($past ? 'p' : '') ?>"><?php if ($r): ?><b><?=h($r['full_name_ar'] ?: $r['full_name_en'])?></b><span class="mut ltr"><?=h($r['email'])?></span><?= $r['note'] ? '<br><span class="mut">' . h($r['note']) . '</span>' : '' ?>
        <form class="i" method="post" action="<?=h($self)?>" onsubmit="return confirm('إلغاء هذا الحجز؟')"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="cancel"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn r s">إلغاء</button></form>
      <?php else: ?><span class="mut"><?= $past ? '—' : 'متاح' ?></span><?php endif; ?></td>
    <?php endforeach; ?></tr><?php endforeach; ?>
  </tbody></table></div>
  <p><a href="seats.php">💺 تعيين المقاعد</a> · <a href="index.php">→ لوحة الإدارة</a></p>
</div>
<?php abd_admin_bottom();
