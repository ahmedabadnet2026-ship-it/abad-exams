<?php
// ABAD Digital Platform — trainer view: only the courses assigned to the signed-in trainer (abd_courses.trainer_id).
// After a session ends the trainer sets its title/topic. Trainers can never add or edit join/recording links (zoom_staff + admin only).
// Admins may preview a trainer's view with ?as=<trainer id> (read-only).
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/api/_lectures_lib.php';

abd_session();
$me = abd_user();
if (!$me || !in_array($me['role'], ['trainer', 'admin'], true)) { header('Location: index.php', true, 303); exit; }
$tid = $me['role'] === 'trainer' ? (int)$me['id'] : (int)($_GET['as'] ?? 0);
$ro = $me['role'] !== 'trainer';
$cid = (int)($_GET['course'] ?? $_POST['course'] ?? 0);
function tr_back(int $cid, string $msg, bool $err = false): void { $_SESSION['flash'] = [$msg, $err]; header('Location: trainer.php' . ($cid ? '?course=' . $cid : ''), true, 303); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if ($ro) tr_back($cid, 'العرض للقراءة فقط.', true);
  if (!abd_csrf_ok((string)($_POST['csrf'] ?? ''))) tr_back($cid, 'انتهت صلاحية النموذج، أعد المحاولة.', true);
  $do = (string)($_POST['do'] ?? '');
  try {
    if ($do === 'profile') {
      $ze = abd_str($_POST, 'zoom_email'); $zm = preg_replace('/\D/', '', abd_latin_digits(abd_str($_POST, 'zoom_meeting_id', 40))) ?? '';
      if ($ze !== '' && !abd_email($ze)) tr_back($cid, 'بريد الزوم غير صحيح.', true);
      if ($zm !== '' && (strlen($zm) < 9 || strlen($zm) > 12)) tr_back($cid, 'رقم الاجتماع يجب أن يكون من 9 إلى 12 رقمًا.', true);
      abd_q('UPDATE abd_users SET zoom_email = ?, zoom_meeting_id = ? WHERE id = ?', [$ze !== '' ? abd_email($ze) : null, $zm ?: null, $tid]);
      tr_back($cid, 'تم حفظ بيانات الزوم.');
    }
    if ($do === 'save') {
      if (!abd_q('SELECT 1 FROM abd_courses WHERE id = ? AND trainer_id = ?', [$cid, $tid])->fetchColumn()) tr_back(0, 'هذه الدورة غير مسندة إليك.', true);
      $rows = abd_q('SELECT id, title, ends_at FROM abd_course_sessions WHERE course_id = ? AND ends_at <= NOW()', [$cid])->fetchAll();
      $n = 0; $bad = 0;
      foreach ($rows as $r) {
        $id = (int)$r['id'];
        $t = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string)(($_POST['title'] ?? [])[$id] ?? $r['title'] ?? '')) ?? ''), 0, 190);
        if ($t !== (string)$r['title']) { abd_q('UPDATE abd_course_sessions SET title = ?, title_by = ?, title_at = NOW() WHERE id = ?', [$t ?: null, $tid, $id]); $n++; }
      }
      tr_back($cid, 'تم الحفظ.');
    }
  } catch (Throwable $e) { error_log('[abad-trainer] ' . $e->getMessage()); tr_back($cid, 'تعذّر الحفظ.', true); }
  tr_back($cid, 'إجراء غير معروف.', true);
}

const TR_DAYS = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
const TR_MON = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
function tr_ar(string $dt): string { $t = strtotime($dt); return TR_DAYS[(int)date('w', $t)] . ' ' . (int)date('j', $t) . ' ' . TR_MON[(int)date('n', $t)]; }
function tr_t(string $dt): string { $t = strtotime($dt); $h = (int)date('G', $t); return ($h % 12 ?: 12) . ':' . date('i', $t) . ($h < 12 ? ' ص' : ' م'); }

$prof = abd_q('SELECT id, full_name_ar, email, zoom_email, zoom_meeting_id FROM abd_users WHERE id = ?', [$tid])->fetch() ?: null;
$courses = abd_q("SELECT c.id, c.title_ar, (SELECT COUNT(*) FROM abd_course_sessions s WHERE s.course_id = c.id) n FROM abd_courses c WHERE c.trainer_id = ? ORDER BY c.id", [$tid])->fetchAll();
if (!$cid && $courses) $cid = (int)$courses[0]['id'];
$course = null; foreach ($courses as $c) if ((int)$c['id'] === $cid) $course = $c;
$sess = $course ? abd_q('SELECT * FROM abd_course_sessions WHERE course_id = ? ORDER BY starts_at, id', [$cid])->fetchAll() : [];
$now = date('Y-m-d H:i:s');
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$tok = abd_csrf();
abd_admin_top('صفحة المدرّب', $me);
?>
<style>.tg{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}.box{background:#f6f9fb;border:1px solid var(--line);border-radius:12px;padding:12px 14px;margin-bottom:14px}
.ch{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}.ch a{padding:6px 14px;border-radius:999px;background:#eef4f9;color:var(--n);text-decoration:none;font-weight:700}.ch a.on{background:var(--n);color:#fff}
.sx td{vertical-align:middle}.sx input.t{width:100%;min-width:220px}.sx input[type=url]{width:100%;min-width:220px;direction:ltr}
.no{display:inline-grid;place-items:center;width:30px;height:30px;border-radius:50%;background:var(--n);color:var(--g);font-weight:800}
.sx tr.up td{opacity:.65}.sx tr.need td{background:#fffbea}.sx tr.live td{background:#fff3f3}</style>
<div class="card solo">
  <h1>🎓 صفحة المدرّب<?= $prof ? ' — ' . h($prof['full_name_ar']) : '' ?></h1>
  <p class="mut">تظهر هنا دوراتك فقط. بعد انتهاء كل محاضرة اكتب عنوانها أو موضوعها، فيظهر للمتدربين على المحاضرة وتسجيلها. يضيف مسؤول الزوم روابط المحاضرات والتسجيلات. جميع الأوقات بتوقيت الرياض.</p>
  <?php if ($ro): ?><div class="err">معاينة المدير (للقراءة فقط).</div><?php endif; ?>
  <?php if ($flash): ?><div class="<?= $flash[1] ? 'err' : 'okm' ?>"><?=h($flash[0])?></div><?php endif; ?>
  <?php if (!$courses): ?><p class="mut">لا توجد دورات مسندة إليك بعد. يُسند المدير الدورة إليك من صفحة «محاضرات الزوم».</p><?php else: ?>
  <div class="ch"><?php foreach ($courses as $c): ?><a class="<?= (int)$c['id'] === $cid ? 'on' : '' ?>" href="?course=<?= (int)$c['id'] ?><?= $ro ? '&as=' . $tid : '' ?>"><?=h($c['title_ar'])?> (<?= (int)$c['n'] ?>)</a><?php endforeach; ?></div>
  <?php if ($sess): ?>
  <form method="post"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="save"><input type="hidden" name="course" value="<?= $cid ?>">
  <table class="sx"><thead><tr><th>#</th><th>اليوم والتاريخ</th><th>الوقت</th><th>الحالة</th><th>عنوان / موضوع المحاضرة</th><th>التسجيل</th></tr></thead><tbody>
  <?php foreach ($sess as $s): $id = (int)$s['id']; $ended = $s['ends_at'] <= $now; $live = !$ended && $s['starts_at'] <= $now; $need = $ended && trim((string)$s['title']) === ''; ?>
    <tr class="<?= $ended ? ($need ? 'need' : '') : ($live ? 'live' : 'up') ?>"><td><span class="no"><?= (int)$s['session_no'] ?></span></td>
      <td style="white-space:nowrap"><?=h(tr_ar((string)$s['starts_at']))?></td><td style="white-space:nowrap"><?=h(tr_t((string)$s['starts_at']) . ' – ' . tr_t((string)$s['ends_at']))?></td>
      <td><?= $ended ? ($need ? '<span class="st st-new">اكتب العنوان</span>' : '<span class="st st-booked">✓ منتهية</span>') : ($live ? '<span class="st st-cancelled">● مباشر الآن</span>' : '<span class="st st-contacted">قادمة</span>') ?></td>
      <td><?php if ($ended): ?><input class="t" name="title[<?= $id ?>]" value="<?=h($s['title'] ?? '')?>" maxlength="190" placeholder="مثال: إعداد VLAN و Trunking" <?= $ro ? 'disabled' : '' ?>><?php else: ?><span class="mut">يُتاح بعد انتهاء المحاضرة</span><?php endif; ?></td>
      <td><?php if ($s['rec_url']): ?><span class="st st-booked">✓ أُضيف التسجيل</span><?php elseif ($ended): ?><span class="mut">يضيفه مسؤول الزوم</span><?php else: ?><span class="mut">—</span><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table>
  <?php if (!$ro): ?><p><button class="btn g">💾 حفظ العناوين</button></p><?php endif; ?></form>
  <?php else: ?><p class="mut">لم تُنشأ محاضرات لهذه الدورة بعد.</p><?php endif; endif; ?>
  <div class="box" style="margin-top:14px"><h2>⚙️ بيانات الزوم (اختياري — للجلب التلقائي للتسجيلات لاحقًا)</h2>
  <form class="f" method="post"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="profile"><input type="hidden" name="course" value="<?= $cid ?>">
    <label>بريد حساب الزوم<input type="email" name="zoom_email" value="<?=h($prof['zoom_email'] ?? '')?>" dir="ltr" <?= $ro ? 'disabled' : '' ?>></label>
    <label>رقم الاجتماع الثابت (Meeting ID)<input name="zoom_meeting_id" value="<?=h($prof['zoom_meeting_id'] ?? '')?>" dir="ltr" inputmode="numeric" <?= $ro ? 'disabled' : '' ?>></label>
    <?php if (!$ro): ?><button class="btn">حفظ</button><?php endif; ?></form></div>
</div>
<?php abd_admin_bottom();
