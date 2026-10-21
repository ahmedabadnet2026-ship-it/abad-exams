<?php
// ABAD Digital Platform — admin statistics dashboard (admin only, read-only). Dependency-free charts (CSS bars + inline SVG).
declare(strict_types=1);
require __DIR__ . '/_layout.php';

const ABD_STATS_PASS_PCT = 70;   // pass threshold (%) for exams and graded labs
const ABD_STATS_WEEKS = 12;

/** Collect all dashboard numbers (read-only SELECTs). */
function abd_stats_data(): array {
  $p = ABD_STATS_PASS_PCT;
  $one = fn(string $sql, array $a = []) => (int)abd_q($sql, $a)->fetchColumn();
  $d = [];
  $d['trainees'] = $one("SELECT COUNT(*) FROM abd_users WHERE role = 'trainee'");
  $d['trainees_active'] = $one("SELECT COUNT(*) FROM abd_users WHERE role = 'trainee' AND is_active = 1");
  $d['active_30d'] = $one("SELECT COUNT(*) FROM abd_users WHERE role = 'trainee' AND last_login_at >= NOW() - INTERVAL 30 DAY");
  $d['new_30d'] = $one("SELECT COUNT(*) FROM abd_users WHERE role = 'trainee' AND created_at >= NOW() - INTERVAL 30 DAY");
  $since = (new DateTimeImmutable('monday this week'))->modify('-' . (ABD_STATS_WEEKS - 1) . ' weeks');
  $rows = abd_q("SELECT DATE(DATE_SUB(created_at, INTERVAL WEEKDAY(created_at) DAY)) wk, COUNT(*) n FROM abd_users
                 WHERE role = 'trainee' AND created_at >= ? GROUP BY wk", [$since->format('Y-m-d')])->fetchAll(PDO::FETCH_KEY_PAIR);
  $d['weeks'] = [];
  for ($i = 0; $i < ABD_STATS_WEEKS; $i++) { $w = $since->modify("+$i weeks")->format('Y-m-d'); $d['weeks'][$w] = (int)($rows[$w] ?? 0); }
  $d['labs'] = abd_q("SELECT lab, COUNT(*) subs, COUNT(DISTINCT user_id) users, SUM(status = 'graded') graded,
                        SUM(status = 'graded' AND max_score > 0 AND score * 100 >= $p * max_score) passed,
                        ROUND(AVG(CASE WHEN status = 'graded' AND max_score > 0 THEN score * 100 / max_score END), 1) avg_pct
                      FROM abd_lab_attempts GROUP BY lab ORDER BY subs DESC")->fetchAll();
  $d['exams'] = abd_q("SELECT exam_id, COUNT(*) attempts, COUNT(DISTINCT user_id) users, SUM(percent >= $p) passed, ROUND(AVG(percent), 1) avg_pct
                       FROM abd_exam_results GROUP BY exam_id ORDER BY attempts DESC")->fetchAll();
  $d['exam_attempts'] = array_sum(array_column($d['exams'], 'attempts'));
  $d['exam_30d'] = $one('SELECT COUNT(*) FROM abd_exam_results WHERE created_at >= NOW() - INTERVAL 30 DAY');
  $d['lab_subs'] = array_sum(array_column($d['labs'], 'subs'));
  return $d;
}

if (!defined('ABD_STATS_MOCK')) {
  abd_session();
  $me = abd_user();
  if (!$me || $me['role'] !== 'admin') { header('Location: index.php', true, 303); exit; }
  try { $S = abd_stats_data(); } catch (Throwable $e) { abd_admin_fatal('تعذّر تحميل الإحصائيات.'); }
} else { $me = $GLOBALS['ABD_STATS_MOCK_USER']; $S = $GLOBALS['ABD_STATS_MOCK_DATA']; }

$pct = fn($a, $b) => $b > 0 ? round($a * 100 / $b) : 0;
$labName = fn(string $l) => preg_replace('/\.unl$/', '', str_replace(['/', '-'], [' › ', ' '], $l));
$sumPassL = array_sum(array_column($S['labs'], 'passed')); $sumGradedL = array_sum(array_column($S['labs'], 'graded'));
$sumPassE = array_sum(array_column($S['exams'], 'passed'));

abd_admin_top('الإحصائيات', $me, '');
?>
<style>
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:16px 0}
.kpi{background:#fff;border-radius:14px;padding:14px 16px;box-shadow:0 2px 10px rgba(16,41,59,.08);border-top:4px solid var(--b)}
.kpi.g{border-color:var(--g)}.kpi.n{border-color:var(--n)}.kpi b{display:block;font-size:28px;line-height:1.2}.kpi span{color:var(--mut);font-size:13px}
.hb{display:grid;grid-template-columns:minmax(120px,38%) 1fr auto;gap:8px;align-items:center;margin:7px 0;font-size:13px}
.hb .tr{background:#eef4f9;border-radius:6px;height:16px;overflow:hidden;display:flex}.hb .tr i{display:block;height:100%;background:var(--b)}.hb .tr i.p{background:var(--g)}
.lg2{display:flex;gap:14px;font-size:12px;color:var(--mut);margin-bottom:6px}.lg2 i{display:inline-block;width:12px;height:12px;border-radius:3px;vertical-align:middle;margin-inline-end:4px}
svg text{font-family:Cairo,sans-serif}.pill{display:inline-block;min-width:44px;text-align:center;border-radius:999px;padding:0 8px;font-weight:800;font-size:12px}
.pill.ok{background:#e6f6ec;color:#17603a}.pill.mid{background:#fff4d6;color:#8a5a00}.pill.lo{background:#fde8e6;color:#922}
.rank{display:inline-grid;place-items:center;width:24px;height:24px;border-radius:50%;background:var(--n);color:var(--g);font-weight:800;font-size:12px}
</style>
<h1 style="margin-top:16px">📊 إحصائيات المنصة</h1>
<div class="kpis">
  <div class="kpi n"><b><?=number_format($S['trainees'])?></b><span>إجمالي المتدربين (نشط: <?=number_format($S['trainees_active'])?>)</span></div>
  <div class="kpi g"><b><?=number_format($S['new_30d'])?></b><span>تسجيلات جديدة آخر 30 يومًا</span></div>
  <div class="kpi"><b><?=number_format($S['active_30d'])?></b><span>دخلوا خلال 30 يومًا</span></div>
  <div class="kpi"><b><?=number_format($S['lab_subs'])?></b><span>تسليمات المعامل · نجاح <?=$pct($sumPassL, $sumGradedL)?>٪</span></div>
  <div class="kpi g"><b><?=number_format($S['exam_attempts'])?></b><span>محاولات الاختبارات · نجاح <?=$pct($sumPassE, $S['exam_attempts'])?>٪</span></div>
</div>
<div class="cols">
<section class="card solo"><h2>التسجيلات الجديدة أسبوعيًا (آخر <?=ABD_STATS_WEEKS?> أسبوعًا)</h2>
<?php $wk = $S['weeks']; $mx = max(1, max($wk)); $n = count($wk); $W = 560; $H = 200; $bw = ($W - 30) / $n; $i = 0; ?>
<svg viewBox="0 0 <?=$W?> <?=$H + 34?>" width="100%" role="img" aria-label="التسجيلات أسبوعيًا" style="direction:ltr">
  <?php foreach ([0, .5, 1] as $g): $y = 10 + $H - $g * $H; ?><line x1="26" x2="<?=$W?>" y1="<?=$y?>" y2="<?=$y?>" stroke="#e2eaf1"/><text x="20" y="<?=$y + 4?>" font-size="11" text-anchor="end" fill="#5d7183"><?=round($mx * $g)?></text><?php endforeach; ?>
  <?php foreach ($wk as $w => $v): $h = $v / $mx * $H; $x = 30 + $i * $bw; ?>
    <rect x="<?=$x + 4?>" y="<?=10 + $H - $h?>" width="<?=$bw - 8?>" height="<?=max($h, 1)?>" rx="4" fill="<?= $i === $n - 1 ? '#F5BD2D' : '#3f86b3' ?>"><title><?=h($w)?>: <?=$v?></title></rect>
    <?php if ($v): ?><text x="<?=$x + $bw / 2?>" y="<?=6 + $H - $h?>" font-size="11" text-anchor="middle" fill="#10293b" font-weight="700"><?=$v?></text><?php endif; ?>
    <text x="<?=$x + $bw / 2?>" y="<?=$H + 28?>" font-size="10" text-anchor="middle" fill="#5d7183"><?=h(date('d/m', strtotime($w)))?></text>
  <?php $i++; endforeach; ?>
</svg><p class="mut">كل عمود = أسبوع يبدأ الاثنين؛ العمود الذهبي هو الأسبوع الحالي.</p></section>

<section class="card solo"><h2>🏆 أكثر المعامل استخدامًا</h2>
<?php $top = array_slice($S['labs'], 0, 6); $mx = max(1, (int)($top[0]['subs'] ?? 1)); ?>
<div class="lg2"><span><i style="background:var(--b)"></i>التسليمات</span><span><i style="background:var(--g)"></i>منها ناجحة</span></div>
<?php foreach ($top as $k => $l): ?><div class="hb"><span><span class="rank"><?=$k + 1?></span> <?=h($labName($l['lab']))?></span>
<span class="tr"><i class="p" style="width:<?=$l['passed'] / $mx * 100?>%"></i><i style="width:<?=($l['subs'] - $l['passed']) / $mx * 100?>%"></i></span><b><?=$l['subs']?></b></div><?php endforeach; ?>
<?php if (!$top): ?><p class="mut">لا توجد تسليمات بعد.</p><?php endif; ?></section>
</div>

<?php $pill = fn($v) => '<span class="pill ' . ($v >= 70 ? 'ok' : ($v >= 50 ? 'mid' : 'lo')) . '">' . $v . '٪</span>'; ?>
<section class="card solo"><h2>🧪 تسليمات المعامل ونسب النجاح</h2>
<table><tr><th>المعمل</th><th>التسليمات</th><th>متدربون</th><th>مُقيّمة</th><th>ناجحة (≥<?=ABD_STATS_PASS_PCT?>٪)</th><th>نسبة النجاح</th><th>متوسط الدرجة</th></tr>
<?php foreach ($S['labs'] as $l): ?><tr><td><?=h($labName($l['lab']))?></td><td><?=$l['subs']?></td><td><?=$l['users']?></td><td><?=$l['graded']?></td><td><?=$l['passed']?></td><td><?=$pill($pct($l['passed'], $l['graded']))?></td><td><?=h($l['avg_pct'] ?? '—')?>٪</td></tr><?php endforeach; ?>
<?php if (!$S['labs']): ?><tr><td colspan="7" class="mut">لا توجد بيانات.</td></tr><?php endif; ?></table></section>

<section class="card solo"><h2>📝 محاولات الاختبارات</h2>
<table><tr><th>الاختبار</th><th>المحاولات</th><th>متدربون</th><th>ناجحة (≥<?=ABD_STATS_PASS_PCT?>٪)</th><th>نسبة النجاح</th><th>متوسط النتيجة</th><th style="width:30%"></th></tr>
<?php $mx = max(1, (int)($S['exams'][0]['attempts'] ?? 1)); foreach ($S['exams'] as $e): ?><tr><td class="ltr"><?=h($e['exam_id'])?></td><td><?=$e['attempts']?></td><td><?=$e['users']?></td><td><?=$e['passed']?></td><td><?=$pill($pct($e['passed'], $e['attempts']))?></td><td><?=h($e['avg_pct'])?>٪</td>
<td><span class="hb" style="grid-template-columns:1fr;margin:0"><span class="tr"><i class="p" style="width:<?=$e['passed'] / $mx * 100?>%"></i><i style="width:<?=($e['attempts'] - $e['passed']) / $mx * 100?>%"></i></span></span></td></tr><?php endforeach; ?>
<?php if (!$S['exams']): ?><tr><td colspan="7" class="mut">لا توجد بيانات.</td></tr><?php endif; ?></table>
<p class="mut">آخر 30 يومًا: <?=number_format($S['exam_30d'])?> محاولة.</p></section>
<?php abd_admin_bottom();
