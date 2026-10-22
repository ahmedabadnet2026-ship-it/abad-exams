<?php
// ABAD Digital Platform — «لوحة المدرب»: only the courses assigned to the signed-in trainer (abd_courses.trainer_id).
// - «ابدأ المحاضرة»: own sessions only, from ABD_LEC_EARLY_MIN minutes before start until the end; first click stores started_at/started_by,
//   then redirects to the host start URL (host_url, entered by zoom_staff/admin) or else the join link.
// - Title/topic: editable only after the session ended, own courses only. Recording state is read-only.
// - Trainers can never add or edit join/host/recording links (zoom_staff + admin only, admin/lectures.php).
// Admins may preview a trainer's view with ?as=<trainer id> (read-only, cannot start).
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/api/_lectures_lib.php';

abd_session();
$me = abd_user();
if (!$me || !in_array($me['role'], ['trainer', 'admin'], true)) { header('Location: ' . ($me && $me['role'] === 'zoom_staff' ? 'lectures.php' : 'index.php'), true, 303); exit; }
$tid = $me['role'] === 'trainer' ? (int)$me['id'] : (int)($_GET['as'] ?? 0);
$ro = $me['role'] !== 'trainer';
$cid = (int)($_GET['course'] ?? $_POST['course'] ?? 0);
function tr_back(int $cid, string $msg, bool $err = false): void { $_SESSION['flash'] = [$msg, $err]; header('Location: trainer.php' . ($cid ? '?course=' . $cid : ''), true, 303); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if ($ro) tr_back($cid, 'العرض للقراءة فقط.', true);
  if (!abd_csrf_ok((string)($_POST['csrf'] ?? ''))) tr_back($cid, 'انتهت صلاحية النموذج، أعد المحاولة.', true);
  $do = (string)($_POST['do'] ?? '');
  try {
    if ($do === 'start') {
      $s = abd_q('SELECT s.id, s.course_id, s.starts_at, s.ends_at, s.zoom_url, s.host_url, s.started_at FROM abd_course_sessions s JOIN abd_courses c ON c.id = s.course_id
                  WHERE s.id = ? AND c.trainer_id = ?', [(int)($_POST['id'] ?? 0), $tid])->fetch();
      if (!$s) tr_back(0, 'هذه المحاضرة ليست ضمن دوراتك.', true);
      $now = time(); $st = strtotime((string)$s['starts_at']); $en = strtotime((string)$s['ends_at']);
      if ($now < $st - ABD_LEC_EARLY_MIN * 60) tr_back((int)$s['course_id'], 'يُتاح بدء المحاضرة قبل موعدها بـ ' . ABD_LEC_EARLY_MIN . ' دقيقة.', true);
      if ($now >= $en) tr_back((int)$s['course_id'], 'انتهى وقت هذه المحاضرة.', true);
      $go = $s['host_url'] ?: $s['zoom_url'];
      if (!$go || !abd_zoom_url((string)$go)) tr_back((int)$s['course_id'], 'لم يُضف رابط الزوم لهذه المحاضرة بعد — تواصل مع مسؤول الزوم.', true);
      abd_q('UPDATE abd_course_sessions SET started_at = NOW(), started_by = ? WHERE id = ? AND started_at IS NULL', [$tid, (int)$s['id']]);
      header('Referrer-Policy: no-referrer'); header('Location: ' . $go, true, 303); exit;
    }
    if ($do === 'profile') {
      $ze = abd_str($_POST, 'zoom_email'); $zm = preg_replace('/\D/', '', abd_latin_digits(abd_str($_POST, 'zoom_meeting_id', 40))) ?? '';
      if ($ze !== '' && !abd_email($ze)) tr_back($cid, 'بريد الزوم غير صحيح.', true);
      if ($zm !== '' && (strlen($zm) < 9 || strlen($zm) > 12)) tr_back($cid, 'رقم الاجتماع يجب أن يكون من 9 إلى 12 رقمًا.', true);
      abd_q('UPDATE abd_users SET zoom_email = ?, zoom_meeting_id = ? WHERE id = ?', [$ze !== '' ? abd_email($ze) : null, $zm ?: null, $tid]);
      tr_back($cid, 'تم حفظ بيانات الزوم.');
    }
    if ($do === 'save') {
      if (!abd_q('SELECT 1 FROM abd_courses WHERE id = ? AND trainer_id = ?', [$cid, $tid])->fetchColumn()) tr_back(0, 'هذه الدورة غير مسندة إليك.', true);
      // titles only, ended sessions only; any link fields in the request are ignored
      $rows = abd_q('SELECT id, title FROM abd_course_sessions WHERE course_id = ? AND ends_at <= NOW()', [$cid])->fetchAll();
      $n = 0;
      foreach ($rows as $r) {
        $id = (int)$r['id'];
        if (!isset(($_POST['title'] ?? [])[$id])) continue;
        $t = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string)$_POST['title'][$id]) ?? ''), 0, 190);
        if ($t !== (string)$r['title']) { abd_q('UPDATE abd_course_sessions SET title = ?, title_by = ?, title_at = NOW() WHERE id = ?', [$t ?: null, $tid, $id]); $n++; }
      }
      tr_back($cid, $n ? "تم حفظ $n عنوان." : 'لا توجد تغييرات.');
    }
  } catch (Throwable $e) { error_log('[abad-trainer] ' . $e->getMessage()); tr_back($cid, 'تعذّر الحفظ.', true); }
  tr_back($cid, 'إجراء غير معروف.', true);
}

const TR_DAYS = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
const TR_MON = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
function tr_ar(string $dt): string { $t = strtotime($dt); return TR_DAYS[(int)date('w', $t)] . ' ' . (int)date('j', $t) . ' ' . TR_MON[(int)date('n', $t)]; }
function tr_t(string $dt): string { $t = strtotime($dt); $h = (int)date('G', $t); return ($h % 12 ?: 12) . ':' . date('i', $t) . ($h < 12 ? ' ص' : ' م'); }
function tr_hm(string $dt): string { $t = strtotime($dt); $h = (int)date('G', $t); return ($h % 12 ?: 12) . ':' . date('i', $t); }

$prof = abd_q('SELECT id, full_name_ar, email, zoom_email, zoom_meeting_id FROM abd_users WHERE id = ?', [$tid])->fetch() ?: null;
$courses = abd_q("SELECT c.id, c.title_ar, (SELECT COUNT(*) FROM abd_course_sessions s WHERE s.course_id = c.id) n,
    (SELECT COUNT(*) FROM abd_enrollments e WHERE e.course_id = c.id AND e.status = 'active') ne FROM abd_courses c WHERE c.trainer_id = ? ORDER BY c.id", [$tid])->fetchAll();
$cids = array_map(fn($c) => (int)$c['id'], $courses);
if (!$cid && $courses) $cid = $cids[0];
$course = null; foreach ($courses as $c) if ((int)$c['id'] === $cid) $course = $c;
$sess = $course ? abd_q('SELECT id, session_no, title, starts_at, ends_at, rec_url, rec_until, started_at, (zoom_url IS NOT NULL OR host_url IS NOT NULL) has_link FROM abd_course_sessions WHERE course_id = ? ORDER BY starts_at, id', [$cid])->fetchAll() : [];
$now = date('Y-m-d H:i:s'); $nowTs = time(); $today = date('Y-m-d');
$st = ['courses' => count($courses), 'trainees' => 0, 'done' => 0, 'norec' => 0, 'today' => 0];
$hero = null;
if ($cids) {
  $in = implode(',', $cids);
  $st['trainees'] = (int)abd_q("SELECT COUNT(DISTINCT user_id) FROM abd_enrollments WHERE status = 'active' AND course_id IN ($in)")->fetchColumn();
  $agg = abd_q("SELECT SUM(ends_at <= NOW()) done, SUM(ends_at <= NOW() AND rec_url IS NULL) norec, SUM(DATE(starts_at) = CURDATE()) today FROM abd_course_sessions WHERE course_id IN ($in)")->fetch();
  foreach (['done', 'norec', 'today'] as $k) $st[$k] = (int)($agg[$k] ?? 0);
  // hero: the session that is live / in its start window, else the next upcoming one
  $hero = abd_q("SELECT s.id, s.course_id, s.session_no, s.starts_at, s.ends_at, s.started_at, (s.zoom_url IS NOT NULL OR s.host_url IS NOT NULL) has_link, c.title_ar,
      (SELECT COUNT(*) FROM abd_course_sessions x WHERE x.course_id = s.course_id) total,
      (SELECT COUNT(*) FROM abd_course_sessions x WHERE x.course_id = s.course_id AND x.ends_at <= NOW()) done,
      (SELECT COUNT(*) FROM abd_enrollments e WHERE e.course_id = s.course_id AND e.status = 'active') ne
    FROM abd_course_sessions s JOIN abd_courses c ON c.id = s.course_id WHERE s.course_id IN ($in) AND s.ends_at > NOW() ORDER BY s.starts_at, s.id LIMIT 1")->fetch() ?: null;
}
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$tok = abd_csrf();
$first = $prof ? preg_split('/\s+/u', trim((string)$prof['full_name_ar']))  : [];
$hello = $prof ? (in_array($first[0] ?? '', ['م.', 'د.', 'أ.'], true) ? ($first[0] . ' ' . ($first[1] ?? '')) : ($first[0] ?? '')) : '';
$cam = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="14" height="12" rx="3"/><path d="M16 10l6-3v10l-6-3z"/></svg>';
$GLOBALS['abd_form_action_extra'] = 'https://zoom.us https://*.zoom.us';
abd_admin_top('لوحة المدرب', $me);
if ($hero) { $hs = strtotime((string)$hero['starts_at']); $he = strtotime((string)$hero['ends_at']); $hLive = $nowTs >= $hs || $hero['started_at']; $hOpen = $nowTs >= $hs - ABD_LEC_EARLY_MIN * 60; $hToday = substr((string)$hero['starts_at'], 0, 10) === $today; }
?>
<style>
main{max-width:1180px}
.zh{background:linear-gradient(120deg,#0a1c29,var(--n) 55%,#16405e);color:#fff;border-radius:18px;padding:24px 26px 70px;margin-top:16px}
.zh h1{margin:0;font-size:26px}.zh h1 span{color:var(--g)}.zh p{margin:4px 0 0;opacity:.8}
.zc{background:#fff;border-radius:18px;box-shadow:0 4px 18px rgba(16,41,59,.08);padding:20px 22px;margin-bottom:18px}
.zc h2{display:flex;align-items:center;gap:8px;font-size:19px;margin:0 0 14px}
.lec{margin:-52px 14px 18px;background:linear-gradient(135deg,#fff 60%,#fdf6e3);border-top:5px solid var(--g);display:grid;grid-template-columns:1.4fr 1fr;gap:22px;align-items:center;padding:26px}
.lec.live{border-top-color:#c0392b;background:linear-gradient(135deg,#fff 60%,#fdeceb)}
.lec .ltag{color:var(--b);font-weight:800;font-size:13px}.lec h3{margin:4px 0 8px;font-size:25px}
.meta{display:flex;gap:18px;flex-wrap:wrap;color:var(--mut);font-weight:700;font-size:14px}.meta b{color:var(--n)}
.prog{height:8px;background:#e6edf3;border-radius:9px;margin-top:12px;overflow:hidden}.prog i{display:block;height:100%;background:var(--g);border-radius:9px}
.cd{display:flex;gap:10px;justify-content:center}.cd div{background:var(--n);color:#fff;border-radius:14px;width:76px;padding:8px 0;text-align:center}.cd b{display:block;font-size:28px;color:var(--g)}.cd small{font-size:12px;opacity:.8}
.big{width:100%;justify-content:center;font-size:18px;padding:14px;margin-top:14px;border-radius:12px;display:flex;align-items:center;gap:8px;background:var(--b);box-shadow:0 8px 20px rgba(63,134,179,.35)}
.big.ret{background:#c0392b;box-shadow:0 8px 20px rgba(192,57,43,.3)}.big[disabled]{background:#9fb3c3;box-shadow:none;cursor:not-allowed}
.lvb{background:#c0392b;color:#fff;border-radius:99px;padding:3px 12px;font-size:13px;font-weight:800;display:inline-flex;align-items:center;gap:6px;margin-inline-start:8px}.lvb i{width:8px;height:8px;border-radius:50%;background:#fff;box-shadow:0 0 0 4px rgba(255,255,255,.3)}
.el{display:flex;justify-content:space-between;font-weight:800;font-size:14px;margin-bottom:6px}.eb{height:12px;background:#f1dede;border-radius:9px;overflow:hidden}.eb i{display:block;height:100%;background:linear-gradient(90deg,#c0392b,#e8735f)}
.sts{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px}
.sts div{background:#fff;border-radius:16px;padding:16px 18px;box-shadow:0 4px 18px rgba(16,41,59,.07);border-right:5px solid var(--b)}.sts b{display:block;font-size:28px;line-height:1.2}.sts small{color:var(--mut);font-weight:700}
.sts .g{border-color:var(--g)}.sts .n{border-color:var(--n)}.sts .r{border-color:#e07a3f}
.ctabs{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap}.ctabs a{white-space:nowrap;padding:5px 14px;border-radius:99px;background:#eef3f8;font-weight:800;font-size:13px;color:var(--n);text-decoration:none}.ctabs a.a{background:var(--n);color:#fff}
.zt{width:100%;border-collapse:collapse;font-size:14px}.zt th{background:#f3f7fb;color:var(--mut);padding:10px;font-weight:800;border:0}.zt td{padding:10px;vertical-align:middle}.zt input{width:100%}
.zt input[disabled]{background:#f6f8fa}
.pill{display:inline-flex;align-items:center;gap:5px;border-radius:99px;padding:2px 11px;font-size:12px;font-weight:800;white-space:nowrap}
.p-ok{background:#e4f5ea;color:#1d7a43}.p-wait{background:#fff4d6;color:#9a6a00}.p-done{background:#e8f0f7;color:var(--b)}.p-up{background:#eef1f4;color:var(--mut)}.p-live{background:#fde4e4;color:#c0392b}
.nm{display:inline-grid;place-items:center;width:28px;height:28px;border-radius:50%;background:var(--n);color:var(--g);font-weight:800;font-size:13px}
.empty{text-align:center;padding:30px;color:var(--mut)}
@media(max-width:760px){.zh{padding:18px 16px 60px;border-radius:14px}.zh h1{font-size:21px}.lec{grid-template-columns:1fr;padding:18px;margin:-46px 4px 14px}.lec h3{font-size:20px}.cd div{width:64px}
 .sts{grid-template-columns:1fr 1fr;gap:10px}.sts b{font-size:22px}.zc{padding:14px}.ctabs{flex-wrap:nowrap;overflow-x:auto}
 .zt thead{display:none}.zt,.zt tbody,.zt tr,.zt td{display:block;width:100%}.zt tr{border:1px solid var(--line);border-radius:12px;margin-bottom:10px;padding:6px 8px}.zt td{border:0;padding:5px 2px}
 .zt td[data-l]::before{content:attr(data-l);display:block;font-size:11px;font-weight:800;color:var(--mut)}}
</style>
<div class="zh"><h1>لوحة المدرب — <span>أهلاً <?=h($hello)?></span></h1><p><?=h(tr_ar($now) . ' ' . date('Y'))?> · <?= $st['today'] ? ($st['today'] === 1 ? 'لديك محاضرة واحدة اليوم' : 'لديك ' . $st['today'] . ' محاضرات اليوم') : 'لا توجد محاضرات اليوم' ?><?= $ro ? ' · معاينة المدير (للقراءة فقط)' : '' ?></p></div>
<?php if ($hero): ?>
<div class="zc lec<?= $hLive ? ' live' : '' ?>" id="hero" data-start="<?= $hs * 1000 ?>" data-end="<?= $he * 1000 ?>" data-now="<?= $nowTs * 1000 ?>">
  <div><div class="ltag"><?= $hToday ? 'محاضرة اليوم' : 'المحاضرة القادمة · ' . h(tr_ar((string)$hero['starts_at'])) ?><?php if ($hLive): ?><span class="lvb"><i></i>مباشر الآن</span><?php endif; ?></div>
    <h3><?=h($hero['title_ar'])?></h3>
    <div class="meta"><span>📘 <b>المحاضرة <?= (int)$hero['session_no'] ?> من <?= (int)$hero['total'] ?></b></span><span>🕖 <b><?=h(tr_hm((string)$hero['starts_at']) . ' – ' . tr_t((string)$hero['ends_at']))?></b> (بتوقيت الرياض)</span><span>👥 <b><?= (int)$hero['ne'] ?></b> متدربًا مسجلًا</span></div>
    <div class="prog"><i style="width:<?= $hero['total'] ? round(100 * $hero['done'] / $hero['total']) : 0 ?>%"></i></div><small style="color:var(--mut);font-weight:700">تقدم الدورة: <?= (int)$hero['done'] ?> من <?= (int)$hero['total'] ?> محاضرة منجزة</small></div>
  <div>
    <?php if ($hLive): $el = max(0, $nowTs - ($hero['started_at'] ? min($hs, strtotime((string)$hero['started_at'])) : $hs)); $dur = max(1, $he - $hs); ?>
    <div class="el"><span>الوقت المنقضي <span id="elapsed"><?= gmdate('G:i:s', min($el, $dur)) ?></span></span><span style="color:var(--mut)">من <?= gmdate('G:i:s', $dur) ?></span></div>
    <div class="eb"><i id="ebar" style="width:<?= min(100, round(100 * $el / $dur)) ?>%"></i></div>
    <small style="color:var(--mut);font-weight:700"><?= $hero['started_at'] ? 'بدأت ' . h(tr_t((string)$hero['started_at'])) : 'الموعد ' . h(tr_t((string)$hero['starts_at'])) . ' · لم تُبدأ بعد' ?></small>
    <?php else: $left = max(0, $hs - $nowTs); ?>
    <div class="cd" id="cd"><div><b><?= sprintf('%02d', intdiv($left, 3600)) ?></b><small>ساعة</small></div><div><b><?= sprintf('%02d', intdiv($left % 3600, 60)) ?></b><small>دقيقة</small></div><div><b><?= sprintf('%02d', $left % 60) ?></b><small>ثانية</small></div></div>
    <?php endif; ?>
    <form method="post" target="_blank" rel="noopener" onsubmit="setTimeout(function(){location.reload()},1500)"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="start"><input type="hidden" name="id" value="<?= (int)$hero['id'] ?>">
    <?php if ($ro): ?><button class="btn big" type="button" disabled><?= $cam ?> ابدأ المحاضرة (للمدرّب فقط)</button>
    <?php elseif (!$hero['has_link']): ?><button class="btn big" type="button" disabled><?= $cam ?> لم يُضف رابط الزوم بعد</button>
    <?php elseif ($hero['started_at']): ?><button class="btn big ret"><?= $cam ?> العودة للمحاضرة</button>
    <?php else: ?><button class="btn big" id="startb" <?= $hOpen ? '' : 'disabled' ?>><?= $cam ?> ابدأ المحاضرة (كمضيف)</button>
      <small id="starthint" class="mut" style="display:<?= $hOpen ? 'none' : 'block' ?>;text-align:center;margin-top:6px">يُتاح الزر قبل الموعد بـ <?= ABD_LEC_EARLY_MIN ?> دقيقة</small><?php endif; ?>
    </form>
  </div>
</div>
<?php endif; ?>
<div class="sts"<?= $hero ? '' : ' style="margin-top:-46px;position:relative"' ?>><div><b><?= $st['courses'] ?></b><small>دوراتي</small></div><div class="g"><b><?= $st['trainees'] ?></b><small>إجمالي المتدربين</small></div><div class="n"><b><?= $st['done'] ?></b><small>محاضرات منجزة</small></div><div class="r"><b><?= $st['norec'] ?></b><small>تسجيلات بانتظار الرفع</small></div></div>
<?php if ($flash): ?><div class="<?= $flash[1] ? 'err' : 'okm' ?>"><?=h($flash[0])?></div><?php endif; ?>
<div class="zc"><h2><?= $cam ?> جلسات الدورة</h2>
<?php if (!$courses): ?><p class="empty">لا توجد دورات مسندة إليك بعد. يُسند المدير الدورة إليك من «لوحة مسؤول الزوم».</p><?php else: ?>
  <div class="ctabs"><?php foreach ($courses as $c): ?><a class="<?= (int)$c['id'] === $cid ? 'a' : '' ?>" href="?course=<?= (int)$c['id'] ?><?= $ro ? '&as=' . $tid : '' ?>"><?=h($c['title_ar'])?></a><?php endforeach; ?></div>
  <?php if ($sess): ?>
  <form method="post"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="save"><input type="hidden" name="course" value="<?= $cid ?>">
  <table class="zt"><thead><tr><th>#</th><th>التاريخ</th><th>الحالة</th><th style="width:38%">عنوان المحاضرة</th><th>التسجيل</th></tr></thead><tbody>
  <?php foreach ($sess as $s): $id = (int)$s['id']; $ended = $s['ends_at'] <= $now; $live = !$ended && $s['starts_at'] <= $now; $isT = substr((string)$s['starts_at'], 0, 10) === $today;
    $exp = $s['rec_until'] && $today > $s['rec_until']; ?>
    <tr><td data-l="رقم"><span class="nm"><?= (int)$s['session_no'] ?></span></td>
      <td data-l="التاريخ" style="white-space:nowrap"><?=h(tr_ar((string)$s['starts_at']))?><br><span class="mut"><?=h(tr_t((string)$s['starts_at']))?><?= $s['started_at'] ? ' · بدأت ' . h(tr_t((string)$s['started_at'])) : '' ?></span></td>
      <td data-l="الحالة"><?= $ended ? '<span class="pill p-done">منتهية</span>' : ($live ? '<span class="pill p-live">● مباشر الآن</span>' : ($isT ? '<span class="pill p-up">اليوم ' . h(tr_t((string)$s['starts_at'])) . '</span>' : '<span class="pill p-up">قادمة</span>')) ?></td>
      <td data-l="عنوان المحاضرة"><?php if ($ended && !$ro): ?><input name="title[<?= $id ?>]" value="<?=h($s['title'] ?? '')?>" maxlength="190" placeholder="أضف عنوان المحاضرة…"><?php else: ?><input value="<?=h($s['title'] ?? '')?>" disabled placeholder="<?= $ended ? '—' : 'يتاح بعد انتهاء المحاضرة' ?>"><?php endif; ?></td>
      <td data-l="التسجيل"><?= !$ended ? '<span class="pill p-up">—</span>' : ($s['rec_url'] ? ($exp ? '<span class="pill p-up">انتهت الإتاحة</span>' : '<span class="pill p-ok">● متاح</span>') : '<span class="pill p-wait">⏳ بانتظار التسجيل</span>') ?></td></tr>
  <?php endforeach; ?></tbody></table>
  <?php if (!$ro): ?><p style="margin:14px 0 0"><button class="btn g">💾 حفظ العناوين</button> <span class="mut">يضيف مسؤول الزوم روابط المحاضرات والتسجيلات.</span></p><?php endif; ?></form>
  <?php else: ?><p class="empty">لم تُنشأ محاضرات لهذه الدورة بعد.</p><?php endif; endif; ?>
</div>
<details class="zc"><summary>⚙️ بيانات الزوم (اختياري — للجلب التلقائي للتسجيلات لاحقًا)</summary>
  <form class="f" method="post" style="margin-top:12px"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="profile"><input type="hidden" name="course" value="<?= $cid ?>">
    <label>بريد حساب الزوم<input type="email" name="zoom_email" value="<?=h($prof['zoom_email'] ?? '')?>" dir="ltr" <?= $ro ? 'disabled' : '' ?>></label>
    <label>رقم الاجتماع الثابت (Meeting ID)<input name="zoom_meeting_id" value="<?=h($prof['zoom_meeting_id'] ?? '')?>" dir="ltr" inputmode="numeric" <?= $ro ? 'disabled' : '' ?>></label>
    <?php if (!$ro): ?><button class="btn">حفظ</button><?php endif; ?></form></details>
<script>
(function(){var h=document.getElementById('hero');if(!h)return;var S=+h.dataset.start,E=+h.dataset.end,off=+h.dataset.now-Date.now(),EARLY=<?= ABD_LEC_EARLY_MIN ?>*60000;
 function p(n){return (n<10?'0':'')+n}
 function tick(){var n=Date.now()+off,cd=document.getElementById('cd');
  if(cd){var l=Math.max(0,Math.floor((S-n)/1000));var b=cd.querySelectorAll('b');b[0].textContent=p(Math.floor(l/3600));b[1].textContent=p(Math.floor(l%3600/60));b[2].textContent=p(l%60);if(l===0)location.reload();}
  var sb=document.getElementById('startb');if(sb&&sb.disabled&&n>=S-EARLY){sb.disabled=false;var hi=document.getElementById('starthint');if(hi)hi.style.display='none';}
  var el=document.getElementById('elapsed');if(el){var d=Math.max(0,Math.min(E-S,n-S))/1000|0;el.textContent=Math.floor(d/3600)+':'+p(Math.floor(d%3600/60))+':'+p(d%60);document.getElementById('ebar').style.width=Math.min(100,100*(n-S)/(E-S))+'%';}
  if(n>=E)location.reload();}
 setInterval(tick,1000);})();
</script>
<?php abd_admin_bottom();
