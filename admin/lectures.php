<?php
// ABAD Digital Platform — «لوحة مسؤول الزوم»: Zoom lecture links per course (zoom_staff + admin only).
// zoom_staff: schedules, join links, passcodes, recording links. Titles are read-only for them (written by the trainer); no host start.
// Generate numbered sessions from a date range + weekdays + time (Asia/Riyadh), edit/add/delete single sessions,
// paste a Zoom join URL (+ optional passcode) per session, or import links from .xlsx / .csv (preview → confirm).
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/api/_lectures_lib.php';
require_once dirname(__DIR__) . '/api/_enroll_import.php';   // abd_imp_read / abd_imp_norm / abd_imp_date (shared file parsing)

abd_session();
$me = abd_user();
if (!$me || !abd_lec_can_links($me)) { header('Location: ' . ($me && $me['role'] === 'trainer' ? 'trainer.php' : 'index.php'), true, 303); exit; }
$isAdmin = $me['role'] === 'admin';   // only admins may edit titles here (trainers edit their own in trainer.php)

const LEC_DAYS = [0 => 'الأحد', 1 => 'الاثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت'];
const LEC_HDR = ['no' => ['no', 'number', 'session', 'lecture', '#', 'رقم', 'رقم المحاضره', 'المحاضره', 'رقم الجلسه', 'الجلسه'],
  'url' => ['url', 'link', 'zoom', 'zoom link', 'zoom url', 'join url', 'الرابط', 'رابط', 'رابط الزوم', 'رابط المحاضره', 'زوم'],
  'pass' => ['passcode', 'password', 'pass', 'code', 'رمز الدخول', 'الرمز', 'كلمه المرور', 'رمز المرور'],
  'rec' => ['recording', 'recording url', 'recording link', 'record', 'رابط التسجيل', 'التسجيل', 'تسجيل المحاضره'],
  'recpass' => ['recording passcode', 'recording password', 'رمز التسجيل', 'رمز دخول التسجيل', 'كلمه مرور التسجيل'],
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
    if ($do === 'set_trainer') {
      if ($me['role'] !== 'admin') lec_back($cid, 'تعيين المدرّب للمدير فقط.', true);
      $tid = (int)($_POST['trainer_id'] ?? 0);
      if ($tid && !abd_q("SELECT 1 FROM abd_users WHERE id = ? AND role = 'trainer' AND is_active = 1", [$tid])->fetchColumn()) lec_back($cid, 'المدرّب غير موجود.', true);
      abd_q('UPDATE abd_courses SET trainer_id = ? WHERE id = ?', [$tid ?: null, $cid]);
      lec_back($cid, $tid ? 'تم تعيين مدرّب الدورة.' : 'تم إلغاء تعيين المدرّب.');
    }
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
        [$cid, $isAdmin ? (abd_str($_POST, 'title') ?: null) : null, $s, $e, $z, abd_str($_POST, 'pass', 64) ?: null, (int)$me['id']]);
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
      $titles = abd_q('SELECT id, title FROM abd_course_sessions WHERE course_id = ?', [$cid])->fetchAll(PDO::FETCH_KEY_PAIR); $rows = array_keys($titles);
      $bad = []; $upd = [];
      foreach ($rows as $id) {
        $f = fn($k, $max = 500) => mb_substr(trim((string)(($_POST[$k] ?? [])[$id] ?? '')), 0, $max);
        $d = $f('date', 10); $s = lec_dt($d, $f('st', 5)); $e = lec_dt($d, $f('et', 5)); $url = $f('url'); $z = $url === '' ? null : abd_zoom_url($url);
        $ru = $f('rec'); $rv = $ru === '' ? null : abd_rec_url($ru); $until = $f('recuntil', 10);
        if (!$s || !$e || $e <= $s || ($url !== '' && !$z) || ($ru !== '' && !$rv) || ($until !== '' && !abd_date($until))) { $bad[] = $id; continue; }
        $upd[] = [$isAdmin ? ($f('title', 190) ?: null) : $titles[$id], $s, $e, $z, $f('pass', 64) ?: null, $rv['url'] ?? null, $f('recpass', 64) ?: null, $until ?: null, $id, $cid];
      }
      if ($bad) lec_back($cid, 'لم يُحفظ شيء: توجد ' . count($bad) . ' محاضرة بتاريخ/وقت غير صحيح أو رابط غير مقبول (المحاضرة: https على zoom.us فقط؛ التسجيل: zoom.us أو YouTube أو Google Drive).', true);
      $pdo = abd_db(); $pdo->beginTransaction();
      foreach ($upd as $p) abd_q('UPDATE abd_course_sessions SET title = ?, starts_at = ?, ends_at = ?, zoom_url = ?, zoom_passcode = ?, rec_url = ?, rec_passcode = ?, rec_until = ? WHERE id = ? AND course_id = ?', $p);
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
      if ((!isset($map['url']) && !isset($map['rec'])) || (!isset($map['no']) && !isset($map['date']))) lec_back($cid, 'لم يتم التعرف على الأعمدة: يلزم عمود «رابط الزوم» أو «رابط التسجيل» وعمود «رقم المحاضرة» أو «التاريخ».', true);
      $sess = abd_q('SELECT id, session_no, DATE(starts_at) d FROM abd_course_sessions WHERE course_id = ?', [$cid])->fetchAll();
      $byNo = []; $byD = []; foreach ($sess as $s) { $byNo[(int)$s['session_no']] = (int)$s['id']; $byD[$s['d']][] = (int)$s['id']; }
      $out = [];
      foreach ($raw as $i => $r) {
        $url = isset($map['url']) ? trim((string)($r[$map['url']] ?? '')) : ''; $rec = isset($map['rec']) ? trim((string)($r[$map['rec']] ?? '')) : ''; $no = isset($map['no']) ? (int)abd_latin_digits((string)($r[$map['no']] ?? '')) : 0;
        $d = isset($map['date']) ? abd_imp_date((string)($r[$map['date']] ?? '')) : null;
        if ($url === '' && $rec === '' && !$no && !$d) continue;
        $sid = $no && isset($byNo[$no]) ? $byNo[$no] : ($d && count($byD[$d] ?? []) === 1 ? $byD[$d][0] : 0);
        $badU = $url !== '' && !abd_zoom_url($url); $badR = $rec !== '' && !abd_rec_url($rec);
        $out[] = ['line' => $i + 2, 'no' => $no, 'date' => $d, 'url' => $url, 'pass' => mb_substr(trim((string)($r[$map['pass'] ?? -1] ?? '')), 0, 64),
          'rec' => $rec, 'recpass' => mb_substr(trim((string)($r[$map['recpass'] ?? -1] ?? '')), 0, 64), 'sid' => $sid,
          'err' => $url === '' && $rec === '' ? 'لا يوجد رابط' : ($badU ? 'رابط الزوم غير مقبول' : ($badR ? 'رابط التسجيل غير مقبول' : (!$sid ? 'لا توجد محاضرة مطابقة' : '')))];
      }
      $_SESSION['lec_imp'] = ['cid' => $cid, 'rows' => $out, 'nonce' => bin2hex(random_bytes(8))];
      lec_back($cid, 'راجع المعاينة ثم أكّد الاستيراد.', false, '&step=preview');
    }
    if ($do === 'import_confirm') {
      $s = $_SESSION['lec_imp'] ?? null;
      if (!$s || $s['cid'] !== $cid || !hash_equals((string)$s['nonce'], (string)($_POST['nonce'] ?? ''))) lec_back($cid, 'لا يوجد استيراد قيد المراجعة.', true);
      $pdo = abd_db(); $pdo->beginTransaction(); $n = 0;
      foreach ($s['rows'] as $r) if (!$r['err']) {
        $z = $r['url'] !== '' ? abd_zoom_url($r['url']) : null; $rv = ($r['rec'] ?? '') !== '' ? abd_rec_url($r['rec']) : null;
        if (($r['url'] !== '' && !$z) || (($r['rec'] ?? '') !== '' && !$rv)) continue;
        abd_q('UPDATE abd_course_sessions SET zoom_url = COALESCE(?, zoom_url), zoom_passcode = COALESCE(?, zoom_passcode), rec_url = COALESCE(?, rec_url), rec_passcode = COALESCE(?, rec_passcode) WHERE id = ? AND course_id = ?',
          [$z, $z ? ($r['pass'] ?: null) : null, $rv['url'] ?? null, $rv ? ($r['recpass'] ?: null) : null, $r['sid'], $cid]); $n++; }
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
  foreach ([['رقم المحاضرة', 'رابط الزوم', 'رمز الدخول', 'رابط التسجيل', 'رمز التسجيل'], ['1', 'https://us06web.zoom.us/j/81234567890?pwd=XXXX', '123456', 'https://us06web.zoom.us/rec/share/XXXX', 'Ab1!xy'], ['2', 'https://zoom.us/j/81234567891', '', 'https://youtu.be/XXXXXXXXXXX', '']] as $r) fputcsv($o, $r, ',', '"', '');
  exit;
}

try {
  $trainers = abd_q("SELECT id, full_name_ar, full_name_en, email FROM abd_users WHERE role = 'trainer' AND is_active = 1 ORDER BY full_name_ar")->fetchAll();
  $courses = abd_q("SELECT c.id, c.title_ar, c.start_date, c.end_date, c.trainer_id, (SELECT COUNT(*) FROM abd_course_sessions s WHERE s.course_id = c.id) n,
      (SELECT COUNT(*) FROM abd_course_sessions s WHERE s.course_id = c.id AND s.zoom_url IS NOT NULL) nl,
      (SELECT COUNT(*) FROM abd_enrollments e WHERE e.course_id = c.id AND e.status = 'active') ne
    FROM abd_courses c ORDER BY c.is_active DESC, COALESCE(c.start_date, '9999-12-31') DESC")->fetchAll();
  $course = null; foreach ($courses as $c) if ((int)$c['id'] === $cid) $course = $c;
  $sess = $course ? abd_q('SELECT s.*, u.full_name_ar AS title_by_name FROM abd_course_sessions s LEFT JOIN abd_users u ON u.id = s.title_by WHERE s.course_id = ? ORDER BY s.starts_at, s.id', [$cid])->fetchAll() : [];
} catch (Throwable $e) { error_log('[abad-lec] ' . $e->getMessage()); abd_admin_fatal('جدول المحاضرات غير موجود بعد. شغّل ترحيل قاعدة البيانات (api/bin/migrate.php).'); }
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$tok = abd_csrf();
$imp = ($_GET['step'] ?? '') === 'preview' && ($_SESSION['lec_imp']['cid'] ?? 0) === $cid ? $_SESSION['lec_imp'] : null;
const LEC_MON = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
function lec_ar(string $dt): string { $t = strtotime($dt); return LEC_DAYS[(int)date('w', $t)] . ' ' . (int)date('j', $t) . ' ' . LEC_MON[(int)date('n', $t)]; }
$now = date('Y-m-d H:i:s');
$cnt = abd_q("SELECT SUM(zoom_url IS NULL AND ends_at > NOW()) nolink, SUM(rec_url IS NULL AND ends_at <= NOW()) norec, SUM(DATE(starts_at) = CURDATE()) today FROM abd_course_sessions")->fetch() ?: [];
function lec_t(string $dt): string { $t = strtotime($dt); $h = (int)date('G', $t); return ($h % 12 ?: 12) . ':' . date('i', $t) . ($h < 12 ? ' ص' : ' م'); }
$today = date('Y-m-d');
$ic = fn(string $p) => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
$icCam = '<rect x="2" y="6" width="14" height="12" rx="3"/><path d="M16 10l6-3v10l-6-3z"/>';
abd_admin_top('لوحة مسؤول الزوم', $me);
?>
<style>
main{max-width:1240px}
.zh{background:linear-gradient(120deg,#0a1c29,var(--n) 55%,#16405e);color:#fff;border-radius:18px;padding:24px 26px 64px;margin-top:16px}
.zh h1{margin:0;font-size:26px}.zh h1 span{color:var(--g)}.zh p{margin:4px 0 0;opacity:.8}
.zs{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:-44px 14px 18px}
.zs>div{background:#fff;border-radius:16px;padding:16px 18px;box-shadow:0 4px 18px rgba(16,41,59,.08);border-right:5px solid var(--b);display:flex;align-items:center;gap:14px}
.zs .ic{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:#eef4f9;color:var(--b);flex:none}
.zs .r{border-color:#e07a3f}.zs .r .ic{background:#fdeee4;color:#e07a3f}.zs .g{border-color:var(--g)}.zs .g .ic{background:#fff4d6;color:#9a6a00}
.zs b{display:block;font-size:28px;line-height:1.2}.zs small{color:var(--mut);font-weight:700}
.zc{background:#fff;border-radius:18px;box-shadow:0 4px 18px rgba(16,41,59,.08);padding:20px 22px;margin-bottom:18px}
.zc.gt{border-top:5px solid var(--g)}
.zc h2{display:flex;align-items:center;gap:8px;font-size:19px;margin:0 0 14px}
.gen{display:grid;grid-template-columns:1fr 1fr 2.6fr .8fr .8fr 1.1fr;gap:12px;align-items:end}.gen .crs{grid-column:1/-1;max-width:560px}
.gen label,.zl{font-size:12px;font-weight:800;color:var(--mut);display:block;margin-bottom:4px}
.gen input,.gen select{width:100%}
.days{display:flex;gap:5px}.days label{flex:1;margin:0;position:relative}.days input{position:absolute;opacity:0;pointer-events:none}
.days span{display:block;text-align:center;border:1px solid #cfdbe6;border-radius:8px;padding:7px 0;font-size:12px;font-weight:800;color:var(--n);cursor:pointer;background:#fff}
.days input:checked+span{background:var(--n);color:#fff;border-color:var(--n)}.days input:focus-visible+span{outline:2px solid var(--b)}
.hd{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px}.hd h2{margin:0}.hd .acts{display:flex;gap:8px;flex-wrap:wrap}
.btn{border-radius:11px;padding:9px 18px;display:inline-flex;align-items:center;gap:8px}
.btn.n{background:var(--n)}.btn.ol{background:#fff;color:var(--n);border:1.5px solid #cfdbe6}
.zt{width:100%;border-collapse:collapse;font-size:14px}.zt th{background:#f3f7fb;color:var(--mut);padding:10px;font-weight:800;border:0}
.zt td{padding:10px;vertical-align:middle}.zt input{width:100%}.zt .lk{direction:ltr;text-align:left}
.zt .sub{display:flex;gap:6px;margin-top:6px}.zt .sub input{font-size:12px;padding:5px 8px}
.zt input.miss{border-color:#e07a3f;background:#fff8f3}.zt input.miss::placeholder{color:#b75d27}
.zt input[readonly]{background:#f6f8fa;color:var(--mut)}
.zt tr.hl td{background:#fffaeb}.zt tr.hl td:first-child{box-shadow:inset -4px 0 var(--g)}
.zt tr.past td{color:#3c5366}.nm{display:inline-grid;place-items:center;width:30px;height:30px;border-radius:50%;background:var(--n);color:var(--g);font-weight:800}
.pill{display:inline-flex;align-items:center;gap:5px;border-radius:99px;padding:2px 11px;font-size:12px;font-weight:800;white-space:nowrap}
.p-ok{background:#e4f5ea;color:#1d7a43}.p-wait{background:#fff4d6;color:#9a6a00}.p-done{background:#e8f0f7;color:var(--b)}.p-up{background:#eef1f4;color:var(--mut)}.p-live{background:#fde4e4;color:#c0392b}.p-exp{background:#f4e4e4;color:#922}
.ttl b{display:block}.ttl i{color:var(--mut)}
.zt details summary{font-size:12px;color:var(--b);font-weight:700;margin-top:4px}.zt details .sub input{width:auto}
.del{background:none;border:0;color:#c0392b;cursor:pointer;font:700 12px Cairo;padding:2px 0}
.drawer{background:#f6f9fb;border:1px solid var(--line);border-radius:12px;padding:12px 14px;margin:0 0 14px}
.drawer h3{margin:0 0 8px;font-size:15px}
@media (max-width:980px){.gen{grid-template-columns:1fr 1fr}.gen .wide{grid-column:1/-1}}
.zt td:nth-child(2){min-width:150px}
@media (max-width:760px){
 .zh{padding:18px 16px 56px;border-radius:14px}.zh h1{font-size:21px}
 .zs{grid-template-columns:1fr;margin:-40px 6px 14px;gap:10px}.zs>div{padding:12px 14px}.zs b{font-size:22px}
 .zc{padding:14px;border-radius:14px}.gen{grid-template-columns:1fr 1fr}.days span{font-size:11px;padding:6px 0}
 .zt thead{display:none}.zt,.zt tbody,.zt tr,.zt td{display:block;width:100%}
 .zt tr{border:1px solid var(--line);border-radius:12px;margin-bottom:12px;padding:8px 10px;background:#fff}
 .zt td{border:0;padding:6px 0}.zt td[data-l]::before{content:attr(data-l);display:block;font-size:11px;font-weight:800;color:var(--mut);margin-bottom:2px}
 .zt tr.hl td:first-child{box-shadow:none}.zt tr.hl{border-color:var(--g);box-shadow:inset -4px 0 var(--g)}
 .zt td.top{display:flex;justify-content:space-between;align-items:center}
}
</style>
<div class="zh"><h1>لوحة <span>مسؤول الزوم</span></h1><p>إدارة روابط المحاضرات والتسجيلات والجداول لجميع الدورات — جميع الأوقات بتوقيت الرياض</p></div>
<div class="zs">
  <div class="r"><div class="ic"><?= $ic($icCam) ?></div><div><b><?= (int)($cnt['nolink'] ?? 0) ?></b><small>محاضرات بدون رابط زوم</small></div></div>
  <div class="g"><div class="ic"><?= $ic('<path d="M6 2h12M6 22h12M7 2v4l5 6-5 6v4M17 2v4l-5 6 5 6v4"/>') ?></div><div><b><?= (int)($cnt['norec'] ?? 0) ?></b><small>تسجيلات بانتظار الرفع</small></div></div>
  <div><div class="ic"><?= $ic('<rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/>') ?></div><div><b><?= (int)($cnt['today'] ?? 0) ?></b><small>محاضرات اليوم</small></div></div>
</div>
<?php if ($flash): ?><div class="<?= $flash[1] ? 'err' : 'okm' ?>"><?=h($flash[0])?></div><?php endif; ?>

<div class="zc gt"><h2>توليد جدول المحاضرات</h2>
  <form id="pick" method="get"></form>
  <form method="post" onsubmit="return this.mode.value!=='replace'||confirm('سيتم حذف كل محاضرات الدورة الحالية وروابطها وإنشاؤها من جديد. متابعة؟')">
  <input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="generate"><input type="hidden" name="course" value="<?= $cid ?>">
  <div class="gen">
    <div class="crs"><label for="cpick">الدورة</label><select id="cpick" name="course" form="pick" onchange="this.form.submit()"><option value="">— اختر الدورة —</option>
      <?php foreach ($courses as $c): $tn = array_values(array_filter($trainers, fn($t) => (int)$t['id'] === (int)$c['trainer_id'])); ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $cid ? 'selected' : '' ?>><?=h($c['title_ar'] . ($tn ? ' — ' . $tn[0]['full_name_ar'] : '') . ' (' . $c['n'] . ')')?></option><?php endforeach; ?></select></div>
    <?php if ($course): ?>
    <div><label>تاريخ البداية</label><input type="date" name="from" required value="<?=h($course['start_date'] ?? '')?>"></div>
    <div><label>تاريخ النهاية</label><input type="date" name="to" required value="<?=h($course['end_date'] ?? '')?>"></div>
    <div class="wide"><span class="zl">أيام الأسبوع</span><div class="days"><?php foreach (['أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'] as $k => $v): ?><label><input type="checkbox" name="days[]" value="<?= $k ?>" <?= in_array($k, [0, 4], true) ? 'checked' : '' ?>><span><?= $v ?></span></label><?php endforeach; ?></div></div>
    <div><label>من</label><input type="time" name="st" value="19:00" required></div>
    <div><label>إلى</label><input type="time" name="et" value="21:00" required></div>
    <div><label>الطريقة</label><select name="mode"><option value="add">إضافة الناقص</option><option value="replace">إعادة التوليد</option></select><button class="btn g" style="margin-top:8px;width:100%;justify-content:center">توليد المحاضرات</button></div>
    <?php endif; ?>
  </div></form>
  <noscript><button class="btn" form="pick">عرض</button></noscript>
  <?php if ($course): $tn = array_values(array_filter($trainers, fn($t) => (int)$t['id'] === (int)$course['trainer_id'])); ?>
  <p class="mut" style="margin:12px 0 0">🎓 مدرّب الدورة: <b><?= $tn ? h($tn[0]['full_name_ar']) : 'لم يُعيَّن' ?></b> · <?= (int)$course['ne'] ?> متدرب مسجّل · يكتب المدرّب عنوان كل محاضرة من صفحته، ويظهر هنا للقراءة فقط.</p>
  <?php if ($isAdmin): ?><form class="f" method="post" style="margin:10px 0 0"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="set_trainer"><input type="hidden" name="course" value="<?= $cid ?>">
    <label>تعيين المدرّب (للمدير)<select name="trainer_id"><option value="0">— بدون مدرّب —</option><?php foreach ($trainers as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)$course['trainer_id'] === (int)$t['id'] ? 'selected' : '' ?>><?=h(($t['full_name_ar'] ?: $t['full_name_en']) . ' — ' . $t['email'])?></option><?php endforeach; ?></select></label><button class="btn l">حفظ</button></form><?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($course): ?>
<div class="zc">
  <div class="hd"><h2><?= $ic($icCam) ?> جلسات: <?=h($course['title_ar'])?></h2>
    <div class="acts"><button type="button" class="btn ol" onclick="var d=document.getElementById('imp');d.hidden=!d.hidden">⬆ استيراد من Excel</button><button type="button" class="btn ol" onclick="var d=document.getElementById('one');d.hidden=!d.hidden">＋ محاضرة</button><?php if ($sess): ?><button class="btn" form="saveall">حفظ التغييرات</button><?php endif; ?></div></div>

  <div class="drawer" id="imp" <?= $imp ? '' : 'hidden' ?>><h3>📥 استيراد الروابط من Excel / CSV</h3>
  <form class="f" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="import_upload"><input type="hidden" name="course" value="<?= $cid ?>">
    <label>الملف (.xlsx أو .csv)<input type="file" name="file" accept=".xlsx,.csv,.txt" required></label><button class="btn">رفع ومعاينة</button></form>
  <p class="mut">الأعمدة: «رقم المحاضرة» (أو «التاريخ») + «رابط الزوم» و/أو «رابط التسجيل» + «رمز الدخول» / «رمز التسجيل» اختياريًا. <a href="?template=csv">تحميل نموذج CSV</a>. لا يُحفظ شيء قبل التأكيد.</p>
  <?php if ($imp): $okn = count(array_filter($imp['rows'], fn($r) => !$r['err'])); ?>
  <p><b><?= $okn ?></b> سطر صالح من <?= count($imp['rows']) ?>. الأسطر التي بها خطأ ستُتجاهل.</p>
  <div style="overflow-x:auto"><table><thead><tr><th>السطر</th><th>رقم المحاضرة</th><th>التاريخ</th><th>الرابط</th><th>الرمز</th><th>التسجيل</th><th>الحالة</th></tr></thead><tbody>
  <?php foreach ($imp['rows'] as $r): ?><tr><td><?= (int)$r['line'] ?></td><td><?= $r['no'] ?: '—' ?></td><td class="ltr"><?=h($r['date'] ?? '—')?></td><td class="ltr" style="max-width:320px;overflow-wrap:anywhere"><?=h($r['url'])?></td><td class="ltr"><?=h($r['pass'])?></td><td class="ltr" style="max-width:280px;overflow-wrap:anywhere"><?=h($r['rec'] ?? '')?><?= ($r['recpass'] ?? '') !== '' ? ' · ' . h($r['recpass']) : '' ?></td>
    <td><?= $r['err'] ? '<span class="pill p-live">' . h($r['err']) . '</span>' : '<span class="pill p-ok">جاهز</span>' ?></td></tr><?php endforeach; ?></tbody></table></div>
  <form class="f" method="post" style="margin-top:10px"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="course" value="<?= $cid ?>"><input type="hidden" name="nonce" value="<?=h($imp['nonce'])?>">
    <button class="btn g" name="do" value="import_confirm" <?= $okn ? '' : 'disabled' ?>>تأكيد الاستيراد</button><button class="btn l" name="do" value="import_cancel">إلغاء</button></form>
  <?php endif; ?></div>

  <div class="drawer" id="one" hidden><h3>➕ إضافة محاضرة واحدة</h3>
  <form class="f" method="post"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="add"><input type="hidden" name="course" value="<?= $cid ?>">
    <label>التاريخ<input type="date" name="date" required></label><label>من<input type="time" name="st" value="19:00" required></label><label>إلى<input type="time" name="et" value="21:00" required></label>
    <label>رابط الزوم<input type="url" name="url" placeholder="https://zoom.us/j/…" style="direction:ltr"></label><label>رمز الدخول<input name="pass" maxlength="64" style="width:110px"></label>
    <button class="btn">إضافة</button></form><p class="mut">تُرقَّم المحاضرات تلقائيًا حسب التاريخ والوقت.</p></div>

  <?php if ($sess): ?>
  <form method="post" id="saveall"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="save_all"><input type="hidden" name="course" value="<?= $cid ?>">
  <table class="zt"><thead><tr><th>#</th><th>التاريخ والوقت</th><th style="width:25%">رابط الزوم</th><th>عنوان المحاضرة <?= $isAdmin ? '' : '(للقراءة فقط)' ?></th><th style="width:25%">رابط التسجيل</th><th>الحالة</th></tr></thead><tbody>
  <?php foreach ($sess as $s): $id = (int)$s['id']; $past = $s['ends_at'] <= $now; $live = !$past && $s['starts_at'] <= $now; $isToday = substr((string)$s['starts_at'], 0, 10) === $today;
    $exp = $s['rec_until'] && $today > $s['rec_until']; $await = $past && !$s['rec_url']; $hasT = trim((string)$s['title']) !== '';
    $pill = $past ? ($s['rec_url'] ? ($exp ? '<span class="pill p-exp">منتهي الإتاحة</span>' : '<span class="pill p-ok">● متاح</span>') : '<span class="pill p-wait">⏳ بانتظار التسجيل</span>')
      : ($live ? '<span class="pill p-live">● مباشر الآن</span>' : ($isToday ? '<span class="pill p-up">اليوم</span>' : '<span class="pill p-up">قادمة</span>')); ?>
    <tr class="<?= $await ? 'hl' : ($past ? 'past' : '') ?>">
      <td class="top"><span class="nm"><?= (int)$s['session_no'] ?></span><span class="m-only"></span></td>
      <td data-l="التاريخ والوقت"><span style="white-space:nowrap"><?=h(lec_ar((string)$s['starts_at']) . ' · ' . lec_t((string)$s['starts_at']))?></span>
        <details><summary>تعديل الموعد</summary><div class="sub"><input type="date" name="date[<?= $id ?>]" value="<?=h(substr((string)$s['starts_at'], 0, 10))?>" required><input type="time" name="st[<?= $id ?>]" value="<?=h(substr((string)$s['starts_at'], 11, 5))?>" required><input type="time" name="et[<?= $id ?>]" value="<?=h(substr((string)$s['ends_at'], 11, 5))?>" required></div>
        <button class="del" form="del<?= $id ?>" onclick="return confirm('حذف المحاضرة <?= (int)$s['session_no'] ?>؟ سيُعاد ترقيم الباقي.')">🗑 حذف المحاضرة</button></details></td>
      <td data-l="رابط الزوم"><input type="url" class="lk<?= $s['zoom_url'] ? '' : ' miss' ?>" name="url[<?= $id ?>]" value="<?=h($s['zoom_url'] ?? '')?>" placeholder="⚠ أضف رابط الزوم" pattern="https://([A-Za-z0-9-]+\.)*zoom\.us(/.*)?" aria-label="رابط الزوم">
        <div class="sub"><input class="lk" name="pass[<?= $id ?>]" value="<?=h($s['zoom_passcode'] ?? '')?>" maxlength="64" placeholder="رمز الدخول" aria-label="رمز الدخول"></div></td>
      <td data-l="عنوان المحاضرة" class="ttl"><?php if ($isAdmin): ?><input name="title[<?= $id ?>]" value="<?=h($s['title'] ?? '')?>" maxlength="190"><?php elseif ($hasT): ?><b><?=h($s['title'])?></b><?php elseif ($past): ?><i>لم يُكتب بعد</i><?php else: ?><span class="mut">—</span><?php endif; ?>
        <?php if ($hasT): ?><span class="pill p-done" style="font-size:11px;margin-top:3px">من المدرب<?= $s['title_by_name'] ? ' · ' . h($s['title_by_name']) : '' ?></span><?php endif; ?></td>
      <td data-l="رابط التسجيل"><input type="url" class="lk" name="rec[<?= $id ?>]" value="<?=h($s['rec_url'] ?? '')?>" placeholder="<?= $past ? 'الصق رابط التسجيل…' : 'بعد انتهاء المحاضرة' ?>" <?= $past || $s['rec_url'] ? '' : 'readonly' ?> aria-label="رابط التسجيل">
        <?php if ($past || $s['rec_url']): ?><div class="sub"><input class="lk" name="recpass[<?= $id ?>]" value="<?=h($s['rec_passcode'] ?? '')?>" maxlength="64" placeholder="رمز التسجيل"><input type="date" name="recuntil[<?= $id ?>]" value="<?=h($s['rec_until'] ?? '')?>" title="متاح حتى (اختياري)"></div><?php endif; ?></td>
      <td data-l="الحالة"><?= $pill ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <p style="margin:14px 0 0"><button class="btn">💾 حفظ التغييرات</button></p></form>
  <?php foreach ($sess as $s): ?><form id="del<?= (int)$s['id'] ?>" method="post" hidden><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="delete"><input type="hidden" name="course" value="<?= $cid ?>"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"></form><?php endforeach; ?>
  <form method="post" style="margin-top:12px" onsubmit="return confirm('حذف جميع محاضرات هذه الدورة وروابطها؟')"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="delete_all"><input type="hidden" name="course" value="<?= $cid ?>"><button class="del">🗑️ حذف جميع محاضرات الدورة</button></form>
  <?php else: ?><p class="mut">لا توجد محاضرات لهذه الدورة بعد. استخدم «توليد جدول المحاضرات» أعلاه أو أضف محاضرة.</p><?php endif; ?>
</div>
<?php endif; ?>
<?php abd_admin_bottom();
