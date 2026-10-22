<?php
// ABAD Digital Platform — «أداء المدربين» (admin only). All figures are computed from abd_course_sessions:
//   delay      = max(0, started_at − starts_at) in minutes, for started sessions (first «ابدأ المحاضرة» click in admin/trainer.php)
//   late       = delay > 5 min;  on-time rate = started with delay ≤ 5 / started
//   run rate   = sessions started / sessions already ended (scheduled) in the period
//   hours      = scheduled duration of started sessions / scheduled duration of all ended sessions
// Ratings: the course-evaluation survey (certificate.html) posts to a Google Sheet, not to this database, so no ratings are shown here.
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/api/_lectures_lib.php';

abd_session();
$me = abd_user();
if (!$me || $me['role'] !== 'admin') {
  $to = ['zoom_staff' => 'lectures.php', 'trainer' => 'trainer.php'][$me['role'] ?? ''] ?? 'index.php';
  header('Location: ' . $to, true, 303); exit;
}
const TP_LATE = 5;
const TP_PERIODS = [30 => 'آخر 30 يومًا', 90 => 'آخر 3 أشهر', 180 => 'آخر 6 أشهر', 365 => 'آخر سنة'];
$days = (int)($_GET['p'] ?? 90); if (!isset(TP_PERIODS[$days])) $days = 90;
$fT = (int)($_GET['t'] ?? 0); $fC = (int)($_GET['c'] ?? 0);

$trainers = abd_q("SELECT id, full_name_ar, full_name_en, email FROM abd_users WHERE role = 'trainer' ORDER BY full_name_ar")->fetchAll();
$coursesAll = abd_q('SELECT id, title_ar, trainer_id FROM abd_courses WHERE trainer_id IS NOT NULL ORDER BY title_ar')->fetchAll();

/** Per-trainer metrics for ended sessions in [NOW − $from days, NOW − $to days). Trainer = course's assigned trainer. */
function tp_metrics(int $from, int $to, int $fT, int $fC): array {
  $w = ['s.ends_at <= NOW()', 's.starts_at >= NOW() - INTERVAL ? DAY', 's.starts_at < NOW() - INTERVAL ? DAY', 'c.trainer_id IS NOT NULL']; $p = [$from, $to];
  if ($fT) { $w[] = 'c.trainer_id = ?'; $p[] = $fT; }
  if ($fC) { $w[] = 'c.id = ?'; $p[] = $fC; }
  $rows = abd_q('SELECT c.trainer_id tid, COUNT(*) past, SUM(s.started_at IS NOT NULL) started,
      SUM(IF(s.started_at IS NULL, 0, GREATEST(0, TIMESTAMPDIFF(SECOND, s.starts_at, s.started_at)))) delay_s,
      SUM(s.started_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, s.starts_at, s.started_at) > ' . (TP_LATE * 60) . ') late,
      SUM(TIMESTAMPDIFF(SECOND, s.starts_at, s.ends_at)) plan_s, SUM(IF(s.started_at IS NULL, 0, TIMESTAMPDIFF(SECOND, s.starts_at, s.ends_at))) done_s,
      GROUP_CONCAT(DISTINCT c.title_ar ORDER BY c.title_ar SEPARATOR "، ") courses
    FROM abd_course_sessions s JOIN abd_courses c ON c.id = s.course_id WHERE ' . implode(' AND ', $w) . ' GROUP BY c.trainer_id', $p)->fetchAll();
  $out = []; $tot = ['past' => 0, 'started' => 0, 'delay_s' => 0, 'late' => 0, 'plan_s' => 0, 'done_s' => 0];
  foreach ($rows as $r) { $out[(int)$r['tid']] = tp_derive($r); foreach ($tot as $k => $_) $tot[$k] += (float)$r[$k]; }
  return [$out, tp_derive($tot)];
}
function tp_derive(array $r): array {
  $st = (int)$r['started']; $past = (int)$r['past'];
  return $r + ['avg_delay' => $st ? $r['delay_s'] / 60 / $st : null, 'run' => $past ? 100 * $st / $past : null,
    'ontime' => $st ? 100 * ($st - (int)$r['late']) / $st : null, 'hours_done' => $r['done_s'] / 3600, 'hours_plan' => $r['plan_s'] / 3600];
}
function tp_status(array $m): array {
  if (!(int)$m['past']) return ['—', 'p-up'];
  if ($m['run'] >= 95 && ($m['ontime'] ?? 0) >= 90) return ['ممتاز', 'p-ok'];
  if ($m['run'] >= 85 && ($m['ontime'] ?? 0) >= 70) return ['جيد', 'p-wait'];
  return ['يحتاج متابعة', 'p-live'];
}
function tp_n(?float $v, int $d = 1): string { if ($v === null) return '—'; $x = number_format($v, $d, '.', ''); return $d > 0 ? rtrim(rtrim($x, '0'), '.') : $x; }

[$per, $tot] = tp_metrics($days, 0, $fT, $fC);
[, $prev] = tp_metrics(2 * $days, $days, $fT, $fC);
$tName = []; foreach ($trainers as $t) $tName[(int)$t['id']] = $t['full_name_ar'] ?: $t['full_name_en'] ?: $t['email'];

// CSV export of the trainer table
if (($_GET['export'] ?? '') === 'csv') {
  header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="abad-trainer-performance-' . date('Y-m-d') . '.csv"'); header('Cache-Control: no-store, private');
  $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
  fputcsv($o, ['المدرب', 'الدورات', 'محاضرات منتهية', 'محاضرات بدأت', 'متوسط التأخر (د)', 'متأخرة +5 د', 'نسبة التنفيذ %', 'نسبة الالتزام %', 'ساعات منفذة', 'ساعات مخططة'], ',', '"', '');
  foreach ($per as $tid => $m) fputcsv($o, [$tName[$tid] ?? $tid, $m['courses'], $m['past'], $m['started'], tp_n($m['avg_delay']), $m['late'], tp_n($m['run'], 0), tp_n($m['ontime'], 0), tp_n($m['hours_done']), tp_n($m['hours_plan'])], ',', '"', '');
  exit;
}

// detail trainer: filter, else the one with most ended sessions
$dT = $fT ?: (int)(array_key_first(array_filter($per, fn($m) => $m['past'] === max(array_column($per, 'past') ?: [0]))) ?? 0);
$recent = $weeks = [];
if ($dT) {
  $p = [$dT]; $cw = ''; if ($fC) { $cw = ' AND c.id = ?'; $p[] = $fC; }
  $recent = abd_q("SELECT s.session_no, s.title, s.title_note, s.starts_at, s.ends_at, s.started_at, c.title_ar,
      IF(s.started_at IS NULL, NULL, TIMESTAMPDIFF(SECOND, s.starts_at, s.started_at)) d_s
    FROM abd_course_sessions s JOIN abd_courses c ON c.id = s.course_id WHERE c.trainer_id = ? $cw AND s.ends_at <= NOW() ORDER BY s.starts_at DESC LIMIT 10", $p)->fetchAll();
  $wk = abd_q("SELECT YEARWEEK(s.starts_at, 0) wk, MIN(DATE(s.starts_at)) d0, SUM(TIMESTAMPDIFF(SECOND, s.starts_at, s.ends_at)) plan_s, SUM(IF(s.started_at IS NULL, 0, TIMESTAMPDIFF(SECOND, s.starts_at, s.ends_at))) done_s
    FROM abd_course_sessions s JOIN abd_courses c ON c.id = s.course_id WHERE c.trainer_id = ? $cw AND s.ends_at <= NOW() AND s.starts_at >= NOW() - INTERVAL 8 WEEK GROUP BY wk ORDER BY wk", $p)->fetchAll();
  $weeks = $wk;
}
const TP_MON = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
function tp_d(string $dt): string { $t = strtotime($dt); return (int)date('j', $t) . ' ' . TP_MON[(int)date('n', $t)]; }
function tp_t(string $dt): string { $t = strtotime($dt); $h = (int)date('G', $t); return ($h % 12 ?: 12) . ':' . date('i', $t) . ($h < 12 ? ' ص' : ' م'); }
function tp_trend(?float $now, ?float $before, string $unit, bool $lowerBetter): string {
  if ($now === null || $before === null) return '<span class="mut">لا توجد بيانات للفترة السابقة</span>';
  $d = $now - $before; if (abs($d) < 0.05) return '<span class="mut">بدون تغيير عن الفترة السابقة</span>';
  $good = $lowerBetter ? $d < 0 : $d > 0;
  return '<span class="tr ' . ($good ? 'up2' : 'dn') . '">' . ($d < 0 ? '▼ ' : '▲ ') . tp_n(abs($d)) . $unit . ' عن الفترة السابقة</span>';
}
abd_admin_top('أداء المدربين', $me);
?>
<style>
main{max-width:1240px}
.zh{background:linear-gradient(120deg,#0a1c29,var(--n) 55%,#16405e);color:#fff;border-radius:18px;padding:24px 26px 64px;margin-top:16px}
.zh h1{margin:0;font-size:26px}.zh h1 span{color:var(--g)}.zh p{margin:4px 0 0;opacity:.8}
.zc{background:#fff;border-radius:18px;box-shadow:0 4px 18px rgba(16,41,59,.08);padding:20px 22px;margin-bottom:18px;min-width:0}
.zc h2{display:flex;align-items:center;gap:8px;font-size:18px;margin:0 0 12px}
.flt{margin:-46px 14px 18px;display:grid;grid-template-columns:repeat(3,1fr) auto;gap:12px;align-items:end}.flt label{font-size:12px;font-weight:800;color:var(--mut);display:block;margin-bottom:4px}.flt select{width:100%}
.ks{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:18px}
.ks>div{background:#fff;border-radius:16px;padding:14px 16px;box-shadow:0 4px 18px rgba(16,41,59,.07);border-right:5px solid var(--b);min-width:0}
.ks small{color:var(--mut);font-weight:700;display:block}.ks b{display:block;font-size:26px;line-height:1.3}.ks em{font-style:normal;font-size:13px;color:var(--mut);font-weight:700}
.ks .g{border-color:var(--g)}.ks .r{border-color:#e07a3f}.ks .n{border-color:var(--n)}
.tr{font-size:12px;font-weight:800}.up2{color:#1d7a43}.dn{color:#c0392b}
.zt{width:100%;border-collapse:collapse;font-size:14px}.zt th{background:#f3f7fb;color:var(--mut);padding:10px;font-weight:800;border:0;white-space:normal}.zt td{padding:10px;vertical-align:middle}
.zt tr.hl td{background:#fffaeb}.zt tr.hl td:first-child{box-shadow:inset -4px 0 var(--g)}.zt a.tn{display:flex;align-items:center;gap:9px;font-weight:800;color:var(--n);text-decoration:none}
.av{width:32px;height:32px;border-radius:50%;background:var(--b);color:#fff;display:grid;place-items:center;font-weight:800;font-size:13px;flex:none}
.hb{display:inline-block;width:80px;height:7px;background:#e6edf3;border-radius:9px;vertical-align:middle;margin-inline-start:6px;overflow:hidden}.hb i{display:block;height:100%;border-radius:9px;background:var(--b)}
.late{color:#c0392b;font-weight:800}
.pill{display:inline-flex;align-items:center;gap:5px;border-radius:99px;padding:2px 11px;font-size:12px;font-weight:800;white-space:nowrap}
.p-ok{background:#e4f5ea;color:#1d7a43}.p-wait{background:#fff4d6;color:#9a6a00}.p-up{background:#eef1f4;color:var(--mut)}.p-live{background:#fde4e4;color:#c0392b}
.g3{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:18px;margin-bottom:18px}.g2{display:grid;grid-template-columns:1.3fr 1fr;gap:18px;margin-bottom:18px}.g3>.zc,.g2>.zc{margin:0}
.sub{color:var(--mut);font-size:12px;font-weight:700;margin:-6px 0 10px}
svg text{font-family:Cairo,sans-serif;font-size:11px;fill:#5d7183}
.emp{border:1px dashed #c9d6e2;background:#f6f9fb;border-radius:12px;padding:22px;text-align:center;color:var(--mut);font-weight:700}
.note{background:#fffaeb;border:1px dashed var(--g);border-radius:14px;padding:13px 16px;font-size:13.5px;line-height:1.9}.kbd{background:var(--n);color:#fff;border-radius:6px;padding:1px 8px;font-size:12px}
.tw{overflow-x:auto}
@media(max-width:900px){.ks{grid-template-columns:1fr 1fr}.g3,.g2,.flt{grid-template-columns:1fr}.ks>div:last-child{grid-column:1/-1}}
@media(max-width:600px){.zh{padding:18px 16px 56px;border-radius:14px}.zh h1{font-size:22px}.flt{margin:-42px 4px 14px;padding:14px}.zc{padding:14px}.ks{gap:10px}.ks b{font-size:21px}.zt{font-size:12.5px;white-space:nowrap}}
</style>
<div class="zh"><h1>أداء <span>المدربين</span></h1><p>متابعة الالتزام بالمواعيد وتنفيذ المحاضرات — محسوبة من ضغطات «ابدأ المحاضرة» مقارنةً بالجدول</p></div>
<form class="zc flt" method="get">
  <div><label for="fp">الفترة</label><select id="fp" name="p" onchange="this.form.submit()"><?php foreach (TP_PERIODS as $k => $v): ?><option value="<?= $k ?>" <?= $k === $days ? 'selected' : '' ?>><?=h($v . ' (' . tp_d(date('Y-m-d', strtotime("-$k days"))) . ' – ' . tp_d(date('Y-m-d')) . ')')?></option><?php endforeach; ?></select></div>
  <div><label for="ft">المدرب</label><select id="ft" name="t" onchange="this.form.submit()"><option value="0">جميع المدربين</option><?php foreach ($trainers as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)$t['id'] === $fT ? 'selected' : '' ?>><?=h($tName[(int)$t['id']])?></option><?php endforeach; ?></select></div>
  <div><label for="fc">الدورة</label><select id="fc" name="c" onchange="this.form.submit()"><option value="0">جميع الدورات</option><?php foreach ($coursesAll as $c): if ($fT && (int)$c['trainer_id'] !== $fT) continue; ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $fC ? 'selected' : '' ?>><?=h($c['title_ar'])?></option><?php endforeach; ?></select></div>
  <div><noscript><button class="btn">تطبيق</button></noscript><a class="btn g" href="<?=h('?' . http_build_query(['p' => $days, 't' => $fT, 'c' => $fC, 'export' => 'csv']))?>">⬇ تصدير التقرير (CSV)</a></div>
</form>
<div class="ks">
  <div class="g"><small>متوسط التقييم</small><b>—</b><span class="mut">لا توجد تقييمات بعد</span></div>
  <div class="r"><small>متوسط تأخر البدء</small><b><?= tp_n($tot['avg_delay']) ?> <em>دقيقة</em></b><?= tp_trend($tot['avg_delay'], $prev['avg_delay'], ' د', true) ?></div>
  <div><small>نسبة تنفيذ المحاضرات</small><b><?= tp_n($tot['run'], 0) ?>%</b><span class="mut"><?= (int)$tot['started'] ?> من <?= (int)$tot['past'] ?> محاضرة مجدولة منتهية</span></div>
  <div class="n"><small>إجمالي ساعات التدريب</small><b><?= tp_n($tot['hours_done']) ?> <em>ساعة</em></b><span class="mut">من <?= tp_n($tot['hours_plan']) ?> ساعة مخططة</span></div>
  <div class="g"><small>نسبة الالتزام بالموعد</small><b><?= tp_n($tot['ontime'], 0) ?>%</b><?= tp_trend($tot['ontime'], $prev['ontime'], '%', false) ?></div>
</div>
<div class="zc"><h2>👥 المدربون</h2>
<?php if (!$per): ?><div class="emp">لا توجد محاضرات منتهية في هذه الفترة.</div><?php else: ?>
<div class="tw"><table class="zt"><thead><tr><th>المدرب</th><th>الدورات</th><th>التقييم</th><th>متوسط التأخر</th><th>محاضرات متأخرة (+5 د)</th><th>نسبة التنفيذ</th><th>الساعات (المنفذة / المخططة)</th><th>الحالة</th></tr></thead><tbody>
<?php foreach ($per as $tid => $m): [$sl, $sc] = tp_status($m); $nm = $tName[$tid] ?? ('#' . $tid); $ini = mb_substr(preg_replace('/^(م|د|أ)\.\s*/u', '', $nm), 0, 1); $hp = $m['hours_plan'] ? min(100, 100 * $m['hours_done'] / $m['hours_plan']) : 0; ?>
  <tr class="<?= $tid === $dT ? 'hl' : '' ?>"><td><a class="tn" href="<?=h('?' . http_build_query(['p' => $days, 't' => $tid]))?>"><span class="av"><?=h($ini)?></span><?=h($nm)?></a></td>
    <td><?=h($m['courses'])?></td><td><span class="mut">لا توجد تقييمات بعد</span></td>
    <td class="<?= ($m['avg_delay'] ?? 0) > TP_LATE ? 'late' : '' ?>"><?= tp_n($m['avg_delay']) ?> د</td>
    <td class="<?= $m['late'] > 0 ? 'late' : '' ?>"><?= (int)$m['late'] ?></td><td><?= tp_n($m['run'], 0) ?>%</td>
    <td style="white-space:nowrap"><?= tp_n($m['hours_done']) ?> / <?= tp_n($m['hours_plan']) ?><span class="hb"><i style="width:<?= round($hp) ?>%;<?= $hp < 90 ? 'background:#e07a3f' : ($hp >= 99 ? 'background:#1d7a43' : '') ?>"></i></span></td>
    <td><span class="pill <?= $sc ?>"><?= $sl ?></span></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>
<?php if ($dT): $dn = $tName[$dT] ?? ''; $ser = array_reverse($recent); ?>
<div class="g3">
  <div class="zc"><h2>⏱️ التأخر لكل محاضرة — <?=h($dn)?></h2><p class="sub">بالدقائق، آخر <?= count($ser) ?> محاضرات منتهية (يمين = الأقدم) · الخط المتقطع = حد <?= TP_LATE ?> دقائق · ✕ = لم تُبدأ</p>
  <?php if (!$ser): ?><div class="emp">لا توجد محاضرات منتهية.</div><?php else:
    $W = 420; $H = 190; $pad = 26; $mx = max(10, TP_LATE + 2, ...array_map(fn($r) => $r['d_s'] === null ? 0 : max(0, (int)$r['d_s']) / 60, $ser)); $mx = (int)ceil($mx / 5) * 5; $n = count($ser); $bw = ($W - 2 * $pad) / $n;
    $y = fn($v) => $H - $pad - ($H - 2 * $pad) * $v / $mx; ?>
  <svg viewBox="0 0 <?= $W ?> <?= $H ?>" width="100%" role="img" aria-label="التأخر لكل محاضرة">
    <?php foreach ([0, $mx / 2, $mx] as $g): ?><line x1="<?= $pad ?>" x2="<?= $W - 6 ?>" y1="<?= $y($g) ?>" y2="<?= $y($g) ?>" stroke="#eef2f6"/><text x="<?= $pad - 6 ?>" y="<?= $y($g) + 4 ?>" text-anchor="end"><?= tp_n($g, 0) ?></text><?php endforeach; ?>
    <line x1="<?= $pad ?>" x2="<?= $W - 6 ?>" y1="<?= $y(TP_LATE) ?>" y2="<?= $y(TP_LATE) ?>" stroke="#c0392b" stroke-dasharray="5 4"/><text x="<?= $W - 8 ?>" y="<?= $y(TP_LATE) - 4 ?>" text-anchor="end" style="fill:#c0392b"><?= TP_LATE ?> د</text>
    <?php foreach ($ser as $i => $r): $x = $W - 6 - ($i + 1) * $bw + $bw * .2; $w = $bw * .6; $v = $r['d_s'] === null ? null : max(0, (int)$r['d_s']) / 60; ?>
      <?php if ($v === null): ?><text x="<?= $x + $w / 2 ?>" y="<?= $y(0) - 6 ?>" text-anchor="middle" style="fill:#c0392b;font-weight:800">✕</text>
      <?php else: $hh = max(2, $y(0) - $y($v)); ?><rect x="<?= $x ?>" y="<?= $y(0) - $hh ?>" width="<?= $w ?>" height="<?= $hh ?>" rx="3" fill="<?= $v > TP_LATE ? '#e07a3f' : '#3f86b3' ?>"><title><?=h($r['title_ar'] . ' — المحاضرة ' . $r['session_no'] . ': ' . tp_n($v) . ' د')?></title></rect><text x="<?= $x + $w / 2 ?>" y="<?= $y(0) - $hh - 4 ?>" text-anchor="middle" style="fill:#10293b;font-weight:800"><?= tp_n($v, 0) ?></text><?php endif; ?>
      <text x="<?= $x + $w / 2 ?>" y="<?= $H - 8 ?>" text-anchor="middle"><?= (int)$r['session_no'] ?></text>
    <?php endforeach; ?></svg><?php endif; ?></div>
  <div class="zc"><h2>⭐ اتجاه التقييم الشهري</h2><p class="sub">متوسط تقييم <?=h($dn)?> من 5</p><div class="emp">لا توجد تقييمات بعد</div></div>
  <div class="zc"><h2>📊 الساعات الأسبوعية</h2><p class="sub">ساعات <?=h($dn)?> المنفذة مقابل المخططة (آخر 8 أسابيع)</p>
  <?php if (!$weeks): ?><div class="emp">لا توجد محاضرات منتهية في آخر 8 أسابيع.</div><?php else:
    $W = 300; $H = 190; $pad = 22; $mx = max(1, ...array_map(fn($w) => $w['plan_s'] / 3600, $weeks)); $mx = (int)ceil($mx); $n = count($weeks); $bw = ($W - 2 * $pad) / $n;
    $y = fn($v) => $H - $pad - ($H - 2 * $pad) * $v / $mx; ?>
  <svg viewBox="0 0 <?= $W ?> <?= $H ?>" width="100%" role="img" aria-label="الساعات الأسبوعية">
    <?php foreach ([0, $mx] as $g): ?><line x1="<?= $pad ?>" x2="<?= $W - 4 ?>" y1="<?= $y($g) ?>" y2="<?= $y($g) ?>" stroke="#eef2f6"/><text x="<?= $pad - 5 ?>" y="<?= $y($g) + 4 ?>" text-anchor="end"><?= $g ?></text><?php endforeach; ?>
    <?php foreach ($weeks as $i => $w): $x = $W - 4 - ($i + 1) * $bw + $bw * .22; $bwi = $bw * .56; $pl = $w['plan_s'] / 3600; $dn2 = $w['done_s'] / 3600; ?>
      <rect x="<?= $x ?>" y="<?= $y($pl) ?>" width="<?= $bwi ?>" height="<?= $y(0) - $y($pl) ?>" rx="3" fill="#e3ebf2"><title>مخطط <?= tp_n($pl) ?> س</title></rect>
      <?php if ($dn2 > 0): ?><rect x="<?= $x ?>" y="<?= $y($dn2) ?>" width="<?= $bwi ?>" height="<?= $y(0) - $y($dn2) ?>" rx="3" fill="#10293b"><title>منفذ <?= tp_n($dn2) ?> س</title></rect><?php endif; ?>
      <text x="<?= $x + $bwi / 2 ?>" y="<?= $H - 6 ?>" text-anchor="middle"><?=h(date('j/n', strtotime((string)$w['d0'])))?></text>
    <?php endforeach; ?></svg>
  <p class="mut" style="margin:4px 0 0"><span style="display:inline-block;width:10px;height:10px;background:#10293b;border-radius:2px"></span> منفذ &nbsp; <span style="display:inline-block;width:10px;height:10px;background:#e3ebf2;border-radius:2px"></span> مخطط</p><?php endif; ?></div>
</div>
<div class="g2">
  <div class="zc"><h2>📋 آخر محاضرات <?=h($dn)?></h2>
  <?php if (!$recent): ?><div class="emp">لا توجد محاضرات منتهية.</div><?php else: ?>
  <div class="tw"><table class="zt"><thead><tr><th>المحاضرة</th><th>الموعد المجدول</th><th>البدء الفعلي</th><th>التأخر</th><th>المدة</th></tr></thead><tbody>
  <?php foreach ($recent as $r): $d = $r['d_s'] === null ? null : (int)$r['d_s'] / 60; ?>
    <tr><td><?=h($r['title_ar'])?> — <?= ($ft = abd_lec_title_full($r['title'], $r['title_note'])) !== '' ? h($ft) : 'المحاضرة ' . (int)$r['session_no'] ?></td><td style="white-space:nowrap"><?=h(tp_d((string)$r['starts_at']) . ' · ' . tp_t((string)$r['starts_at']))?></td>
      <td><?= $r['started_at'] ? h(tp_t((string)$r['started_at'])) : '—' ?></td>
      <td><?= $d === null ? '<span class="pill p-live">لم تُبدأ</span>' : '<span class="' . ($d > TP_LATE ? 'late' : '') . '">' . ($d < 0 ? 'مبكرًا ' . tp_n(-$d, 0) : tp_n($d, 0)) . ' د</span>' ?></td>
      <td><?= tp_n((strtotime((string)$r['ends_at']) - strtotime((string)$r['starts_at'])) / 60, 0) ?> د</td></tr>
  <?php endforeach; ?></tbody></table></div><?php endif; ?></div>
  <div class="zc"><h2>💬 أحدث تعليقات المتدربين</h2><p class="sub">من استبيان تقييم الدورة</p><div class="emp">لا توجد تقييمات بعد</div></div>
</div>
<?php endif; ?>
<div class="note">ℹ️ <b>مصادر البيانات:</b> يُحسب <b>التأخر</b> من لحظة ضغط المدرب على زر <span class="kbd">ابدأ المحاضرة</span> مقارنةً بالموعد المجدول؛ البدء المبكر يُحسب صفرًا. تُعد المحاضرة <b>متأخرة</b> إذا تجاوز التأخر <?= TP_LATE ?> دقائق، و<b>غير منفذة</b> إذا انتهى موعدها دون ضغط الزر. <b>التقييمات والتعليقات</b> غير متاحة هنا بعد: استبيان تقييم الدورة يُرسل إلى Google Sheet خارجي وليس إلى قاعدة بيانات المنصة، ويُكتب اسم المدرب فيه نصًا من قائمة ثابتة لا ترتبط بحسابات المدربين.</div>
<?php abd_admin_bottom();
