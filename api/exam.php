<?php
// Gated exam page: GET api/exam.php?id=<exam id>
// Entitled → streams the private exam page (outside web root) with <base> fixed; otherwise a branded lock page.
declare(strict_types=1);
require_once __DIR__ . '/_exam_lib.php';

$id = (string)($_GET['id'] ?? '');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
if (!isset(ABD_EXAMS[$id])) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); exit('not found'); }
$e = ABD_EXAMS[$id];
$base = abd_base_path();
try { $u = abd_user(); $ok = $u && abd_exam_allowed($u, $id); }
catch (Throwable $ex) { error_log('[abad-exam] ' . $ex->getMessage()); $u = null; $ok = false; http_response_code(503); }

if ($ok) {
  $f = abd_exam_dir() . '/' . $e['file'];
  if (!is_file($f)) { error_log('[abad-exam] missing ' . $f); http_response_code(503); header('Content-Type: text/plain; charset=utf-8'); exit('exam unavailable'); }
  header('Content-Type: text/html; charset=utf-8');
  $html = (string)file_get_contents($f);
  $inj = '<base href="' . h($base) . '"><script>window.ABAD_EXAM_ENTITLED=true;</script>';
  $html = preg_replace('/<head([^>]*)>/i', '<head$1>' . $inj, $html, 1, $n) ?? $html;
  if (!$n) $html = $inj . $html;
  echo $html;
  exit;
}

if (http_response_code() === 200) http_response_code($u ? 403 : 401);
header('Content-Type: text/html; charset=utf-8');
$book = $base . 'booking.html#v=' . rawurlencode($e['vendor']) . '&c=' . rawurlencode($e['cert']);
$login = $base . 'login.html?next=' . rawurlencode($e['file']);
?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<meta name="theme-color" content="#10293b"><title>🔒 <?=h($e['title'])?> — معهد شبكة آباد للتدريب</title><link rel="icon" href="<?=h($base)?>icons/icon-192.png">
<style>@font-face{font-family:Cairo;src:url('<?=h($base)?>assets/cairo.ttf') format('truetype');font-display:swap}
:root{--n:#10293b;--b:#3f86b3;--g:#F5BD2D}*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:Cairo,system-ui,sans-serif;color:var(--n);padding:18px;
background:radial-gradient(circle at 15% 10%,#3f86b322,transparent 45%),radial-gradient(circle at 85% 90%,#F5BD2D22,transparent 40%),#f3f6f9}
.c{width:min(460px,100%);background:#fff;border-radius:20px;box-shadow:0 18px 40px #10293b22;overflow:hidden;text-align:center}
.h{background:linear-gradient(135deg,#0a1c29,var(--n) 60%,#16405e);color:#fff;padding:22px 20px 18px;border-bottom:4px solid var(--g)}
.h img{height:40px;background:#fff;border-radius:8px;padding:3px 7px}.lk{font-size:44px;margin:10px 0 0}
.h h1{margin:4px 0 0;font-size:20px}.h small{color:#9cc7e3}
.b{padding:20px 22px 24px}.b p{margin:0 0 16px;line-height:1.9;font-size:15px}
.btn{display:block;font-weight:800;font-size:15px;text-decoration:none;border-radius:12px;padding:12px;margin:8px 0}
.g{background:var(--g);color:var(--n)}.o{background:#eef4f9;color:var(--n)}.m{color:#5d7183;font-size:13px}
</style></head><body><main class="c"><div class="h"><img src="<?=h($base)?>assets/abad-logo.png" alt="ABAD"><div class="lk">🔒</div>
<h1 dir="ltr"><?=h($e['title'])?></h1><small>اختبار تفاعلي — معهد شبكة آباد للتدريب</small></div>
<div class="b"><?php if ($u): ?><p>هذا الاختبار يفتح للمتدربين الذين حجزوا الاختبار من خلال المعهد. لم نجد حجزًا مؤكدًا لهذا الاختبار في حسابك.</p>
<?php else: ?><p>يفتح هذا الاختبار بعد تسجيل الدخول للمتدربين الذين حجزوا الاختبار من خلال المعهد.</p><?php endif; ?>
<a class="btn g" href="<?=h($book)?>">احجز من خلال المعهد</a>
<?php if (!$u): ?><a class="btn o" href="<?=h($login)?>">تسجيل الدخول</a><?php endif; ?>
<a class="btn o" href="<?=h($base)?>platform.html">← تصفّح الجهات والاختبارات</a>
<p class="m">إذا كنت قد حجزت بالفعل، يُفتح الاختبار تلقائيًا بعد تأكيد الحجز من فريق المعهد.</p></div></main></body></html>
