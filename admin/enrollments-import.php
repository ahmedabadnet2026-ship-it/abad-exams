<?php
// ABAD Digital Platform — bulk import of course enrollments from .xlsx / .csv (admin only).
// upload → preview (nothing written) → confirm (single transaction). The parsed rows live in the session between steps
// and are re-analysed against the DB on confirm, so the preview can never go stale.
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/api/_enroll_import.php';

abd_session();
$me = abd_user();
if (!$me || $me['role'] !== 'admin') { header('Location: index.php', true, 303); exit; }

// ---- template download
if (($t = (string)($_GET['template'] ?? '')) !== '') {
  $rows = [['اسم الدورة', 'تاريخ البداية', 'تاريخ النهاية', 'البريد الإلكتروني'],
           ['دورة CCNA — شبكات سيسكو', '2026-11-01', '2026-11-19', 'trainee@example.com'],
           ['Security+', '01/12/2026', '17/12/2026', 'trainee2@example.com']];
  header('Cache-Control: no-store, private'); header('X-Content-Type-Options: nosniff');
  if ($t === 'xlsx') {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="abad-enrollments-template.xlsx"');
    echo abd_xlsx_build($rows, 'التسجيل'); exit;
  }
  header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="abad-enrollments-template.csv"');
  $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF"); foreach ($rows as $r) fputcsv($o, $r, ',', '"', ''); exit;
}

function imp_back(string $msg, bool $err = false): void { $_SESSION['flash'] = [$msg, $err]; header('Location: enrollments-import.php', true, 303); exit; }
$ERR = ['too_large' => 'حجم الملف أكبر من 2 ميجابايت.', 'bad_xlsx' => 'ملف Excel غير صالح. احفظه بصيغة .xlsx أو CSV.', 'empty' => 'الملف فارغ.',
  'too_many' => 'الحد الأقصى ' . ABD_IMP_MAX_ROWS . ' صف في الملف الواحد.', 'headers' => 'لم يتم التعرف على أعمدة الملف.'];
$COLS = ['course' => 'اسم الدورة', 'start' => 'تاريخ البداية', 'end' => 'تاريخ النهاية', 'email' => 'البريد الإلكتروني'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!abd_csrf_ok((string)($_POST['csrf'] ?? ''))) imp_back('انتهت صلاحية النموذج، أعد المحاولة.', true);
  $do = (string)($_POST['do'] ?? '');
  if ($do === 'cancel') { unset($_SESSION['imp']); imp_back('تم إلغاء الاستيراد.'); }
  if ($do === 'upload') {
    $f = $_FILES['file'] ?? null;
    if (!$f || !is_array($f) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) imp_back(($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($f['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE ? $ERR['too_large'] : 'اختر ملفًا لرفعه.', true);
    $name = (string)$f['name'];
    if (!preg_match('/\.(xlsx|csv|txt)$/i', $name)) imp_back('الصيغ المقبولة: .xlsx أو .csv فقط.', true);
    if ((int)$f['size'] > ABD_IMP_MAX_BYTES) imp_back($ERR['too_large'], true);
    try { $rows = abd_imp_read((string)file_get_contents((string)$f['tmp_name']), $name); }
    catch (Throwable $e) { error_log('[abad-import] read ' . $e->getMessage()); imp_back($ERR[$e->getMessage()] ?? 'تعذّرت قراءة الملف.', true); }
    $_SESSION['imp'] = ['rows' => $rows, 'name' => mb_substr($name, 0, 120), 'nonce' => bin2hex(random_bytes(8))];
    header('Location: enrollments-import.php?step=preview', true, 303); exit;
  }
  if ($do === 'confirm') {
    $s = $_SESSION['imp'] ?? null;
    if (!$s || !hash_equals((string)$s['nonce'], (string)($_POST['nonce'] ?? ''))) imp_back('لا يوجد استيراد قيد المراجعة، ارفع الملف من جديد.', true);
    $a = abd_imp_analyse($s['rows']);
    if (!$a['ok']) imp_back($ERR[$a['error']] ?? 'تعذّر الاستيراد.', true);
    try { $r = abd_imp_commit($a); }
    catch (Throwable $e) { error_log('[abad-import] commit ' . $e->getMessage()); imp_back('تعذّر الحفظ ولم يُحفظ أي سطر (تم التراجع عن العملية كاملة).', true); }
    unset($_SESSION['imp']);
    $_SESSION['flash'] = ['تم الاستيراد: ' . $r['enrolled'] . ' تسجيل جديد، ' . $r['updated'] . ' تحديث، ' . $r['courses'] . ' دورة جديدة. سيُرسل بريد حجز الاختبار لكل متدرب في تاريخ نهاية دورته.', false];
    header('Location: enrollments.php', true, 303); exit;
  }
  imp_back('إجراء غير معروف.', true);
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$tok = abd_csrf();
$imp = ($_GET['step'] ?? '') === 'preview' ? ($_SESSION['imp'] ?? null) : null;
$a = null;
if ($imp) { try { $a = abd_imp_analyse($imp['rows']); } catch (Throwable $e) { error_log('[abad-import] ' . $e->getMessage()); abd_admin_fatal('تعذّر تحليل الملف. تأكد من تشغيل ترحيل قاعدة البيانات.'); } }
abd_admin_top('استيراد التسجيلات', $me);
?>
<style>.drop{border:2px dashed #c9d6e2;border-radius:14px;padding:22px;background:#fafcfe;text-align:center}.drop input[type=file]{margin:10px auto;display:block}
.hl{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px;margin:12px 0}.hl div{background:#eef4f9;border-radius:10px;padding:8px 12px;font-size:13px}.hl b{display:block;color:var(--n)}
tr.s-error td{background:#fdf3f2}tr.s-no_user td{background:#fff8e6}tr.s-dup td{background:#f4f4f4;color:var(--mut)}
.st-ok{background:#e6f6ec;color:#17603a}.st-update{background:#e3f0fa;color:#1d5f8c}.st-error{background:#f4e4e4;color:#922}.st-no_user{background:#fff4d6;color:#8a5a00}.st-dup{background:#e8e8e8;color:#555}
.chip{display:inline-block;border-radius:6px;padding:0 6px;font-size:12px;font-weight:700}.chip.n{background:var(--g);color:var(--n)}.chip.e{background:#eef4f9;color:var(--n)}</style>
<div class="card solo">
  <p><a href="enrollments.php">→ تسجيل الدورات</a></p>
  <h1>📥 استيراد التسجيلات من ملف Excel أو CSV</h1>
  <?php if ($flash): ?><div class="<?= $flash[1] ? 'err' : 'okm' ?>"><?=h($flash[0])?></div><?php endif; ?>
<?php if (!$a): ?>
  <p class="mut">ارفع ملفًا بأربعة أعمدة (بأي ترتيب، والعناوين بالعربية أو الإنجليزية). ستظهر لك معاينة كاملة قبل حفظ أي شيء.</p>
  <div class="hl"><?php foreach ($COLS as $k => $v): ?><div><b><?=h($v)?></b><span class="ltr"><?=h(implode(' · ', array_slice(array_filter(ABD_IMP_HEADERS[$k], fn($x) => !preg_match('/\p{Arabic}/u', $x)), 0, 3)))?></span></div><?php endforeach; ?></div>
  <p class="mut">صيغ التاريخ المقبولة: <span class="ltr">2026-10-22</span> · <span class="ltr">22/10/2026</span> (اليوم أولًا) · <span class="ltr">22-10-2026</span> · <span class="ltr">22 Oct 2026</span> · «22 أكتوبر 2026» · تاريخ Excel. الأرقام العربية مقبولة.<br>
  الدورة غير الموجودة تُنشأ تلقائيًا بتواريخ أول سطر لها. البريد غير المسجّل في المنصة يظهر في التقرير ولا يُنشأ له حساب — يجب أن يسجّل المتدرب أولًا.</p>
  <form method="post" enctype="multipart/form-data" class="drop"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="upload">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= ABD_IMP_MAX_BYTES ?>">
    <b>📄 اختر ملف .xlsx أو .csv</b><span class="mut"> (حتى 2 ميجابايت · <?= ABD_IMP_MAX_ROWS ?> صف)</span>
    <input type="file" name="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
    <button class="btn g">رفع ومعاينة</button></form>
  <p style="margin-top:14px">⬇️ تحميل القالب: <a class="btn s" href="?template=xlsx">Excel (.xlsx)</a> <a class="btn s l" href="?template=csv">CSV</a></p>
<?php elseif (!$a['ok']): ?>
  <div class="err"><?=h($ERR[$a['error']] ?? 'تعذّر قراءة الملف.')?><?php if (!empty($a['missing'])): ?> الأعمدة الناقصة: <b><?=h(implode('، ', array_map(fn($k) => $COLS[$k], $a['missing'])))?></b>.<?php endif; ?></div>
  <p><a class="btn" href="enrollments-import.php">رفع ملف آخر</a> <a class="btn s l" href="?template=xlsx">تحميل القالب</a></p>
<?php else: $S = $a['summary']; $go = $S['ok'] + $S['update']; ?>
  <p class="mut">الملف: <b class="ltr"><?=h($imp['name'])?></b> — معاينة فقط، لم يُحفظ شيء بعد.</p>
  <div class="stats"><div><b><?= $S['total'] ?></b>سطر</div><div><b><?= $S['ok'] ?></b>تسجيل جديد</div><div><b><?= $S['update'] ?></b>تحديث تواريخ</div><div><b><?= $S['new_courses'] ?></b>دورة جديدة</div>
    <div><b><?= $S['no_user'] ?></b>بريد غير مسجّل</div><div><b><?= $S['error'] ?></b>أخطاء</div><div><b><?= $S['dup'] ?></b>مكرر</div></div>
  <table><thead><tr><th>#</th><th>الدورة</th><th>البداية</th><th>النهاية</th><th>المتدرب</th><th>النتيجة</th></tr></thead><tbody>
  <?php $L = ['ok' => 'سيُسجَّل', 'update' => 'تحديث', 'error' => 'خطأ — لن يُستورد', 'no_user' => 'غير مسجّل — لن يُستورد', 'dup' => 'مكرر — سيُتجاهل'];
  foreach ($a['items'] as $it): ?>
    <tr class="s-<?=h($it['status'])?>"><td class="mut"><?= (int)$it['line'] ?></td>
      <td><?=h($it['course'] ?: '—')?><br><?php if ($it['course'] !== ''): ?><span class="chip <?= $it['course_new'] ? 'n' : 'e' ?>"><?= $it['course_new'] ? '＋ دورة جديدة' : '✓ دورة موجودة' ?></span><?php endif; ?></td>
      <td class="ltr"><?= $it['start'] ? h($it['start']) : '<span style="color:#c0392b">' . h($it['start_raw'] ?: '—') . '</span>' ?></td>
      <td class="ltr"><?= $it['end'] ? h($it['end']) : '<span style="color:#c0392b">' . h($it['end_raw'] ?: '—') . '</span>' ?></td>
      <td><span class="ltr"><?=h($it['email'] ?? $it['email_raw'])?></span><br><?php if ($it['user_id']): ?><span class="mut">✓ <?=h($it['user_name'])?></span><?php elseif ($it['email']): ?><span style="color:#8a5a00">✗ غير مسجّل في المنصة</span><?php endif; ?></td>
      <td><span class="st st-<?=h($it['status'])?>"><?=h($L[$it['status']])?></span><?php foreach (array_merge($it['errors'], $it['notes']) as $n): ?><br><span class="mut"><?=h($n)?></span><?php endforeach; ?></td></tr>
  <?php endforeach; ?></tbody></table>
  <?php if ($S['no_user']): ?><div class="err" style="margin-top:12px;background:#fff8e6;color:#8a5a00">⚠️ <?= $S['no_user'] ?> بريد غير مسجّل في المنصة:
    <span class="ltr"><?=h(implode(', ', array_unique(array_map(fn($x) => $x['email'], array_filter($a['items'], fn($x) => $x['status'] === 'no_user')))))?></span> — اطلب منهم إنشاء حساب ثم أعد استيراد هذه الأسطر.</div><?php endif; ?>
  <div style="display:flex;gap:8px;margin-top:14px">
    <form method="post" onsubmit="return confirm('تأكيد استيراد <?= $go ?> سطر؟')"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="confirm"><input type="hidden" name="nonce" value="<?=h($imp['nonce'])?>">
      <button class="btn g" <?= $go ? '' : 'disabled' ?>>✔ تأكيد الاستيراد (<?= $go ?> سطر<?= $S['new_courses'] ? ' · ' . $S['new_courses'] . ' دورة جديدة' : '' ?>)</button></form>
    <form method="post"><input type="hidden" name="csrf" value="<?=h($tok)?>"><input type="hidden" name="do" value="cancel"><button class="btn l">إلغاء</button></form></div>
<?php endif; ?>
</div>
<?php abd_admin_bottom();
