<?php
// ABAD Digital Platform — Zoom lecture links per course (staff + admin).
// Generate numbered sessions from a date range + weekdays + time (Asia/Riyadh), edit/add/delete single sessions,
// paste a Zoom join URL (+ optional passcode) per session, or import links from .xlsx / .csv (preview → confirm).
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/api/_lectures_lib.php';
require_once dirname(__DIR__) . '/api/_enroll_import.php';   // abd_imp_read / abd_imp_norm / abd_imp_date (shared file parsing)

abd_session();
$me = abd_user();
if (!$me || !in_array($me['role'], ['admin', 'staff'], true)) { header('Location: index.php', true, 303); exit; }

const LEC_DAYS = [0 => 'الأحد', 1 => 'الاثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت'];
const LEC_HDR = ['no' => ['no', 'number', 'session', 'lecture', '#', 'رقم', 'رقم المحاضره', 'المحاضره', 'رقم الجلسه', 'الجلسه'],
  'url' => ['url', 'link', 'zoom', 'zoom link', 'zoom url', 'join url', 'الرابط', 'رابط', 'رابط الزوم', 'رابط المحاضره', 'زوم'],
  'pass' => ['passcode', 'password', 'pass', 'code', 'رمز الدخول', 'الرمز', 'كلمه المرور', 'رمز المرور'],
  'date' => ['date', 'التاريخ', 'تاريخ', 'تاريخ المحاضره']];

$cid = (int)($_GET['course'] ?? $_POST['course'] ?? 0);
function lec_back(int $cid, string $msg, bool $err = false, string $q = ''): void {
  $_SESSION['flash'] = [$msg, $err]; header('Location: lectures.php' . ($cid ? '?course=' . $cid . $q : ''), true, 303); exit; }
function lec_dt(string $d, string $t): ?string { return abd_date($d) && abd_lec_time($t) ? "$d $t:00" : null; }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!abd_csrf_ok((string)($_POST['csrf'] ?? ''))) lec_back($cid, 'انتهت صلاحية النموذج، أعد المحاولة.', true);
  $do = (string)($_POST['do'] ?? '');
  try {
    if ($cid <= 0 || !abd_q('SELECT 1 FROM abd_courses WHERE id = ?', [$cid])->fetchColumn()) lec_back(0, 'اختر دورة أولًا.', true);
    if ($do === 'generate') {
      $from = abd_str($_POST, 'from', 10); $to = abd_str($_POST, 'to', 10);
      $st = abd_lec_time(abd_str($_POST, 'st', 5)); $et = abd_lec_time(abd_str($_POST, 'et', 5));
      $days = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['days'] ?? [])), fn($d) => $d >= 0 && $d <= 6)));
      if (!abd_date($from) || !abd_date($to) || $to < $from) lec_back($cid, 'تاريخا البداية والنهاية غير صحيحين.', true);
      if ((strtotime($to) - strtotime($from)) > 400 * 86400) lec_back($cid, 'المدة طويلة جدًا (الحد سنة واحدة).', true);
      if (!$days) lec_back($cid, 'اختر يومًا واحدًا على الأقل من أيام الأسبوع.', true);
      if (!$st || !$et || $et <= $st) lec_back($cid, 'وقت النهاية يجب أن يكون بعد وقت البداية.', true);
      $n = abd_lec_generate($cid, $from, $to, $days, $st, $et, (int)$me['id'], ($_POST['mode'] ?? '') === 'replace');
      lec_back($cid, "تم إنشاء $n محاضرة وترقيم المحاضرات حسب التاريخ.");
    }
    if ($do === 'add') {
      $d = abd_str($_POST, 'date', 10); $s = lec_dt($d, abd_str($_POST, 'st', 5)); $e = lec_dt($d, abd_str($_POST, 'et', 5));
      if (!$s || !$e || $e <= $s) lec_back($cid, 'تاريخ أو وقت غير صحيح.', true);
      $url = abd_str($_POST, 'url', 500); $z = $url === '' ? null : abd_zoom_url($url);
      if ($url !== '' && !$z) lec_back($cid, 'رابط الزوم غير مقبول: يجب أن يبدأ بـ https:// وعلى نطاق zoom.us.', true);
      abd_q('INSERT INTO abd_course_sessions (course_id, session_no, title, starts_at, ends_at, zoom_url, zoom_passcode, created_by) VALUES (?, 0, ?, ?, ?, ?, ?, ?)',
        [$cid, abd_str($_POST, 'title') ?: null, $s, $e, $z, abd_str($_POST, 'pass', 64) ?: null, (int)$me['id']]);
      abd_lec_renumber($cid);
      lec_back($cid, 'تمت إضافة المحاضرة وإعادة الترقيم.');
    }
    if ($do === 'delete') {
      abd_q('DELETE FROM abd_course_sessions WHERE id = ? AND course_id = ?', [(int)($_POST['id'] ?? 0), $cid]);
      abd_lec_renumber($cid);
      lec_back($cid, 'تم حذف المحاضرة وإعادة ترقيم الباقي.');
    }
    if ($do === 'delete_all') {
      abd_q('DELETE FROM abd_course_sessions WHERE course_id = ?', [$cid]);
      lec_back($cid, 'تم حذف جميع محاضرات الدورة.');
    }
    if ($do === 'save_all') {
      $rows = abd_q('SELECT id FROM abd_course_sessions WHERE course_id = ?', [$cid])->fetchAll(PDO::FETCH_COLUMN);
      $bad = []; $upd = [];
      foreach ($rows as $id) {
        $f = fn($k, $max = 500) => mb_substr(trim((string)(($_POST[$k] ?? [])[$id] ?? '')), 0, $max);
        $d = $f('date', 10); $s = lec_dt($d, $f('st', 5)); $e = lec_dt($d, $f('et', 5)); $url = $f('url'); $z = $url === '' ? null : abd_zoom_url($url);
        if (!$s || !$e || $e <= $s || ($url !== '' && !$z)) { $bad[] = $id; continue; }
        $upd[] = [$f('title', 190) ?: null, $s, $e, $z, $f('pass', 64) ?: null, $id, $cid];
      }
      if ($bad) lec_back($cid, 'لم يُحفظ شيء: توجد ' . count($bad) . ' محاضرة بتاريخ/وقت غير صحيح أو رابط غير مقبول (https على zoom.us فقط).', true);
      $pdo = abd_db(); $pdo->beginTransaction();
      foreach ($upd as $p) abd_q('UPDATE abd_course_sessions SET title = ?, starts_at = ?, ends_at = ?, zoom_url = ?, zoom_passcode = ? WHERE id = ? AND course_id = ?', $p);
      $pdo->commit();
      abd_lec_renumber($cid);
      lec_back($cid, 'تم حفظ المحاضرات والروابط.');
    }
    if ($do === 'import_upload') {
      $f = $_FILES['file'] ?? null;
      if (!$f || !is_array($f) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) lec_back($cid, 'اختر ملفًا لرفعه.', true);
      if (!preg_match('/\.(xlsx|csv|txt)$/i', (string)$f['name'])) lec_back($cid, 'الصيغ المقبولة: .xlsx أو .csv فقط.', true);
      if ((int)$f['size'] > ABD_IMP_MAX_BYTES) lec_back($cid, 'حجم الملف أكبر من 2 ميجابايت.', true);
      $raw = abd_imp_read((string)file_get_contents((string)$f['tmp_name']), (string)$f['name']);
      $hdr = array_shift($raw) ?? []; $map = [];
      foreach ($hdr as $i => $h) { $n = abd_imp_norm((string)$h); foreach (LEC_HDR as $k => $al) if (!isset($map[$k]) && in_array($n, array_map('abd_imp_norm', $al), true)) { $map[$k] = $i; break; } }
      if (!isset($map['url']) || (!isset($map['no']) && !isset($map['date']))) lec_back($cid, 'لم يتم التعرف على الأعمدة: يلزم عمود «رابط الزوم» وعمود «رقم المحاضرة» أو «التاريخ».', true);
      $sess = abd_q('SELECT id, session_no, DATE(starts_at) d FROM abd_course_sessions WHERE course_id = ?', [$cid])->fetchAll();
      $byNo = []; $byD = []; foreach ($sess as $s) { $byNo[(int)$s['session_no']] = (int)$s['id']; $byD[$s['d']][] = (int)$s['id']; }
      $out = [];
      foreach ($raw as $i => $r) {
        $url = trim((string)($r[$map['url']] ?? '')); $no = isset($map['no']) ? (int)abd_latin_digits((string)($r[$map['no']] ?? '')) : 0;
        $d = isset($map['date']) ? abd_imp_date((string)($r[$map['date']] ?? '')) : null;
        if ($url === '' && !$no && !$d) continue;
        $sid = $no && isset($byNo[$no]) ? $byNo[$no] : ($d && count($byD[$d] ?? []) === 1 ? $byD[$d][0] : 0);
        $z = abd_zoom_url($url);
        $out[] = ['line' => $i + 2, 'no' => $no, 'date' => $d, 'url' => $url, 'pass' => mb_substr(trim((string)($r[$map['pass'] ?? -1] ?? '')), 0, 64),
          'sid' => $sid, 'err' => !$z ? 'رابط غير مقبول' : (!$sid ? 'لا توجد محاضرة مطابقة' : '')];
      }
      $_SESSION['lec_imp'] = ['cid' => $cid, 'rows' => $out, 'nonce' => bin2hex(random_bytes(8))];
      lec_back($cid, 'راجع المعاينة ثم أكّد الاستيراد.', false, '&step=preview');
    }
    if ($do === 'import_confirm') {
      $s = $_SESSION['lec_imp'] ?? null;
      if (!$s || $s['cid'] !== $cid || !hash_equals((string)$s['nonce'], (string)($_POST['nonce'] ?? ''))) lec_back($cid, 'لا يوجد استيراد قيد المراجعة.', true);
      $pdo = abd_db(); $pdo->beginTransaction(); $n = 0;
      foreach ($s['rows'] as $r) if (!$r['err'] && ($z = abd_zoom_url($r['url']))) {
        $n += abd_q('UPDATE abd_course_sessions SET zoom_url = ?, zoom_passcode = COALESCE(?, zoom_passcode) WHERE id = ? AND course_id = ?', [$z, $r['pass'] ?: null, $r['sid'], $cid])->rowCount() ? 1 : 0; }
      $pdo->commit(); unset($_SESSION['lec_imp']);
      lec_back($cid, "تم استيراد روابط $n محاضرة.");
    }
    if ($do === 'import_cancel') { unset($_SESSION['lec_imp']); lec_back($cid, 'تم إلغاء الاستيراد.'); }
  } catch (Throwable $e) { if (abd_db()->inTransaction()) abd_db()->rollBack(); error_log('[abad-lec] ' . $e->getMessage()); lec_back($cid, 'تعذّر الحفظ.', true); }
  lec_back($cid, 'إجراء غير معروف.', true);
}

if (($t = (string)($_GET['template'] ?? '')) === 'csv') {
  header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="abad-zoom-links-template.csv"'); header('Cache-Control: no-store, private');
  $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
  foreach ([['رقم المحاضرة', 'رابط الزوم', 'رمز الدخول'], ['1', 'https://us06web.zoom.us/j/81234567890?pwd=XXXX', '123456'], ['2', 'https://zoom.us/j/81234567891', '']] as $r) fputcsv($o, $r, ',', '"', '');
  exit;
}

try {
  $courses = abd_q("SELECT c.id, c.title_ar, c.start_date, c.end_date, (SELECT COUNT(*) FROM abd_course_sessions s WHERE s.course_id = c.id) n,
      (SELECT COUNT(*) FROM abd_course_sessions s WHERE s.course_id = c.id AND s.zoom_url IS NOT NULL) nl,
      (SELECT COUNT(*) FROM abd_enrollments e WHERE e.course_id = c.id AND e.status = 'active') ne
    FROM abd_courses c ORDER BY c.is_active DESC, COALESCE(c.start_date, '9999-12-31') DESC")->fetchAll();
  $course = null; foreach ($courses as $c) if ((int)$c['id'] === $cid) $course = $c;
  $sess = $course ? abd_q('SELECT * FROM abd_course_sessions WHERE course_id = ? ORDER BY starts_at, id', [$cid])->fetchAll() : [];
} catch (Throwable $e) { error_log('[abad-lec] ' . $e->getMessage()); abd_admin_fatal('جدول المحاضرات غير موجود بعد. شغّل ترحيل قاعدة البيانات (api/bin/migrate.php).'); }
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$tok = abd_csrf();
$imp = ($_GET['step'] ?? '') === 'preview' && ($_SESSION['lec_imp']['cid'] ?? 0) === $cid ? $_SESSION['lec_imp'] : null;
const LEC_MON = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
function lec_ar(string $dt): string { $t = strtotime($dt); return LEC_DAYS[(int)date('w', $t)] . ' ' . (int)date('j', $t) . ' ' . LEC_MON[(int)date('n', $t)]; }
$now = date('Y-m-d H:i:s');
abd_admin_top('روابط محاضرات الزوم', $me);
?>
<style>.days{display:flex;gap:6px;flex-wrap:wrap}.days label{flex-direction:row!important;align-items:center;gap:4px;background:#eef4f9;border-radius:9px;padding:6px 10px;color:var(--n)!important;cursor:pointer}
.sx td{vertical-align:middle}.sx input[type=url]{width:100%;min-width:260px;direction:ltr}.sx input.p{width:110px;direction:ltr}.sx input.t{width:150px}
.sx tr.past td{opacity:.6}.sx tr.nx td{background:#fffbea}.no{display:inline-grid;place-items:center;width:30px;height:30px;border-radius:50%;background:var(--n);color:var(--g);font-weight:800}
.sx tr.nolink .no{background:#c0392b;color:#fff}.box{background:#f6f9fb;border:1px solid var(--line);border-radius:12px;padding:12px 14px;margin-bottom:14px}</style>
<div class="card solo">
  <h1>🎥 روابط محاضرات الزوم</h1>
  <p class="mut">اختر الدورة، ولّد محاضراتها تلقائيًا من التاريخ وأيام الأسبوع والوقت (بتوقيت الرياض)، ثم الصق رابط الزوم لكل محاضرة. يرى كل متدرب مسجّل في الدورة محاضراتها في صفحة «محاضرات الزوم» بحسابه، ويُفتح زر الدخول قبل الموعد بـ 15 دقيقة حتى نهاية المحاضرة. تُقبل روابط <span class="ltr">https://…zoom.us</span> فقط.</p>
  <?php if ($flash): ?><div class="<?= $flash[1] ? 'err' : 'okm' ?>"><?=h($flash[0])?></div><?php endif; ?>
  <form class="f" method="get"><label>الدورة<select name="course" onchange="this.form.submit()"><option value="">— اختر الدورة —</option>
    <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $cid ? 'selected' : '' ?>><?=h($c['title_ar'] . ' — ' . $c['n'] . ' محاضرة' . ($c['n'] ? ' (' . $c['nl'] . ' برابط)' : ''))?></option><?php endforeach; ?></select></label>
    <noscript><button class="btn">عرض</button></noscript></form>
<?php if ($course): ?>
  <div class="stats"><div><b><?= count($sess) ?></b>محاضرة</div><div><b><?= (int)$course['nl'] ?></b>برابط زوم</div><div><b><?= count($sess) - (int)$course['nl'] ?></b>بلا رابط</div><div><b><?= (int)$course['ne'] ?></b>متدرب مسجّل</div></div>
  <div class="box"><h2>⚙️ توليد المحاضرات تلقائيًا</h2>
  <form class="f" method="post" onsubmit="return this.mode.value!=='replace'||confirm('سيتم حذف كل محاضرات الدورة الحالية وروابطها وإنشاؤها من جديد. متابعة؟')">
    <input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="generate"><input type="hidden" name="course" value="<?= $cid ?>">
    <label>من تاريخ<input type="date" name="from" required value="<?=h($course['start_date'] ?? '')?>"></label>
    <label>إلى تاريخ<input type="date" name="to" required value="<?=h($course['end_date'] ?? '')?>"></label>
    <label>أيام المحاضرات<span class="days"><?php foreach (LEC_DAYS as $k => $v): ?><label><input type="checkbox" name="days[]" value="<?= $k ?>" <?= in_array($k, [0, 1, 2, 3], true) ? 'checked' : '' ?>><?=h($v)?></label><?php endforeach; ?></span></label>
    <label>من الساعة<input type="time" name="st" value="16:00" required></label><label>إلى الساعة<input type="time" name="et" value="18:00" required></label>
    <label>الطريقة<select name="mode"><option value="add">إضافة الأيام الناقصة فقط</option><option value="replace">حذف الحالية وإعادة التوليد</option></select></label>
    <button class="btn g">توليد المحاضرات</button></form></div>

  <h2>📋 المحاضرات بالترتيب</h2>
  <?php if ($sess): ?>
  <form method="post" id="saveall"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="save_all"><input type="hidden" name="course" value="<?= $cid ?>">
  <table class="sx"><thead><tr><th>#</th><th>اليوم</th><th>التاريخ</th><th>من</th><th>إلى</th><th>رابط الزوم</th><th>رمز الدخول</th><th>عنوان (اختياري)</th><th></th></tr></thead><tbody>
  <?php $nextDone = false; foreach ($sess as $s): $id = (int)$s['id']; $past = $s['ends_at'] < $now; $isNext = !$past && !$nextDone; if ($isNext) $nextDone = true; ?>
    <tr class="<?= $past ? 'past' : ($isNext ? 'nx' : '') ?> <?= $s['zoom_url'] ? '' : 'nolink' ?>"><td><span class="no"><?= (int)$s['session_no'] ?></span></td>
      <td style="white-space:nowrap"><?=h(lec_ar((string)$s['starts_at']))?><?= $isNext ? '<br><span class="st st-new">التالية</span>' : '' ?></td>
      <td><input type="date" name="date[<?= $id ?>]" value="<?=h(substr((string)$s['starts_at'], 0, 10))?>" required></td>
      <td><input type="time" name="st[<?= $id ?>]" value="<?=h(substr((string)$s['starts_at'], 11, 5))?>" required></td>
      <td><input type="time" name="et[<?= $id ?>]" value="<?=h(substr((string)$s['ends_at'], 11, 5))?>" required></td>
      <td><input type="url" name="url[<?= $id ?>]" value="<?=h($s['zoom_url'] ?? '')?>" placeholder="https://us06web.zoom.us/j/…" pattern="https://([A-Za-z0-9-]+\.)*zoom\.us(/.*)?"></td>
      <td><input class="p" name="pass[<?= $id ?>]" value="<?=h($s['zoom_passcode'] ?? '')?>" maxlength="64"></td>
      <td><input class="t" name="title[<?= $id ?>]" value="<?=h($s['title'] ?? '')?>" maxlength="190"></td>
      <td><button class="btn s r" form="del<?= $id ?>" onclick="return confirm('حذف المحاضرة <?= (int)$s['session_no'] ?>؟ سيُعاد ترقيم الباقي.')">حذف</button></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <p><button class="btn g">💾 حفظ جميع التعديلات والروابط</button></p></form>
  <?php foreach ($sess as $s): ?><form id="del<?= (int)$s['id'] ?>" method="post" hidden><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="delete"><input type="hidden" name="course" value="<?= $cid ?>"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"></form><?php endforeach; ?>
  <?php else: ?><p class="mut">لا توجد محاضرات لهذه الدورة بعد. استخدم «توليد المحاضرات» أعلاه أو أضف محاضرة يدويًا.</p><?php endif; ?>

  <div class="cols">
  <div class="box"><h2>➕ إضافة محاضرة واحدة</h2>
  <form class="f" method="post"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="add"><input type="hidden" name="course" value="<?= $cid ?>">
    <label>التاريخ<input type="date" name="date" required></label><label>من<input type="time" name="st" value="16:00" required></label><label>إلى<input type="time" name="et" value="18:00" required></label>
    <label>رابط الزوم<input type="url" name="url" placeholder="https://zoom.us/j/…" style="direction:ltr"></label><label>رمز الدخول<input name="pass" maxlength="64" style="width:110px"></label>
    <button class="btn">إضافة</button></form><p class="mut">تُرقَّم المحاضرات تلقائيًا حسب التاريخ والوقت.</p></div>
  <div class="box"><h2>📥 استيراد الروابط من Excel / CSV</h2>
  <form class="f" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="import_upload"><input type="hidden" name="course" value="<?= $cid ?>">
    <label>الملف (.xlsx أو .csv)<input type="file" name="file" accept=".xlsx,.csv,.txt" required></label><button class="btn">رفع ومعاينة</button></form>
  <p class="mut">الأعمدة: «رقم المحاضرة» (أو «التاريخ») + «رابط الزوم» + «رمز الدخول» اختياريًا. <a href="?template=csv">تحميل نموذج CSV</a>. لا يُحفظ شيء قبل التأكيد.</p></div>
  </div>
  <?php if ($imp): $okn = count(array_filter($imp['rows'], fn($r) => !$r['err'])); ?>
  <div class="box"><h2>👀 معاينة الاستيراد</h2><p><b><?= $okn ?></b> سطر صالح من <?= count($imp['rows']) ?>. الأسطر التي بها خطأ ستُتجاهل.</p>
  <table><thead><tr><th>السطر</th><th>رقم المحاضرة</th><th>التاريخ</th><th>الرابط</th><th>الرمز</th><th>الحالة</th></tr></thead><tbody>
  <?php foreach ($imp['rows'] as $r): ?><tr><td><?= (int)$r['line'] ?></td><td><?= $r['no'] ?: '—' ?></td><td class="ltr"><?=h($r['date'] ?? '—')?></td><td class="ltr" style="max-width:360px;overflow-wrap:anywhere"><?=h($r['url'])?></td><td class="ltr"><?=h($r['pass'])?></td>
    <td><?= $r['err'] ? '<span class="st st-cancelled">' . h($r['err']) . '</span>' : '<span class="st st-booked">جاهز</span>' ?></td></tr><?php endforeach; ?></tbody></table>
  <form class="f" method="post" style="margin-top:10px"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="course" value="<?= $cid ?>"><input type="hidden" name="nonce" value="<?=h($imp['nonce'])?>">
    <button class="btn g" name="do" value="import_confirm" <?= $okn ? '' : 'disabled' ?>>تأكيد الاستيراد</button><button class="btn l" name="do" value="import_cancel">إلغاء</button></form></div>
  <?php endif; ?>
  <?php if ($sess): ?><form method="post" onsubmit="return confirm('حذف جميع محاضرات هذه الدورة وروابطها؟')"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="delete_all"><input type="hidden" name="course" value="<?= $cid ?>"><button class="btn s r">🗑️ حذف جميع محاضرات الدورة</button></form><?php endif; ?>
<?php endif; ?>
</div>
<?php abd_admin_bottom();
