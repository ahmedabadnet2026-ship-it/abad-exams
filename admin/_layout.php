<?php
// Shared admin chrome (Arabic RTL, ABAD branding). Not directly accessible.
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/api/_db.php';

function abd_admin_headers(): void {
  header('Content-Type: text/html; charset=utf-8');
  header('Cache-Control: no-store, private');
  header('X-Frame-Options: DENY');
  header('X-Content-Type-Options: nosniff');
  header('X-Robots-Tag: noindex, nofollow');
  header('Referrer-Policy: same-origin');
  header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
}

function abd_admin_top(string $title, ?array $user = null, string $tab = ''): void {
  abd_admin_headers();
  $tabs = ['home' => '🏠 الرئيسية', 'crm' => '🧲 العملاء (CRM)', 'tasks' => '⏰ المتابعات', 'reports' => '📈 التقارير',
    'bookings' => '📋 الحجوزات', 'courses' => '🎓 الدورات والتسجيل', 'results' => '📊 نتائج الاختبارات', 'users' => '👥 المستخدمون'];
  if (!$user || $user['role'] !== 'admin') unset($tabs['users']);
  ?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#10293b"><title><?=h($title)?> — منصة آباد الرقمية</title>
<link rel="icon" href="../icons/icon-192.png">
<style>
@font-face{font-family:Cairo;src:url('../assets/cairo.ttf') format('truetype');font-display:swap}
:root{--n:#10293b;--b:#3f86b3;--g:#F5BD2D;--bg:#f3f6f9;--mut:#5d7183;--line:#e2eaf1}
*{box-sizing:border-box}body{margin:0;font-family:Cairo,system-ui,sans-serif;background:var(--bg);color:var(--n);font-size:15px}
a{color:var(--b)}
header.top{background:linear-gradient(90deg,#0a1c29,var(--n) 60%,#16405e);color:#fff;padding:10px 18px;display:flex;gap:14px;align-items:center;flex-wrap:wrap;border-bottom:4px solid var(--g)}
header.top img{height:40px;background:#fff;border-radius:8px;padding:3px 6px}header.top .t{flex:1;min-width:180px}header.top b{display:block;font-size:17px}header.top small{opacity:.75}
header.top a{color:#fff;text-decoration:none}.who{font-size:13px;opacity:.9}
nav.tabs{display:flex;gap:6px;flex-wrap:wrap;max-width:1280px;margin:16px auto 0;padding:0 14px}
nav.tabs a{padding:8px 14px;border-radius:10px 10px 0 0;background:#dfe8f0;color:var(--n);text-decoration:none;font-weight:700}
nav.tabs a.on{background:#fff;box-shadow:0 -2px 0 var(--g) inset}
main{max-width:1280px;margin:0 auto 40px;padding:0 14px}
.card{background:#fff;border-radius:0 14px 14px 14px;box-shadow:0 2px 10px rgba(16,41,59,.08);padding:18px;margin-bottom:16px;overflow-x:auto}
.card.solo{border-radius:14px}
h1{font-size:20px;margin:0 0 12px}h2{font-size:17px;margin:0 0 10px}
table{width:100%;border-collapse:collapse;font-size:14px}th,td{padding:8px;border-bottom:1px solid var(--line);text-align:start;vertical-align:top}
th{background:#eef4f9;white-space:nowrap;font-weight:800}tr:hover td{background:#fafcfe}
.ltr{direction:ltr;unicode-bidi:embed;display:inline-block}
.btn{font-family:inherit;border:0;border-radius:9px;padding:7px 14px;cursor:pointer;background:var(--b);color:#fff;font-weight:700;font-size:14px;text-decoration:none;display:inline-block}
.btn.g{background:var(--g);color:var(--n)}.btn.o{background:transparent;border:1px solid #fff;color:#fff}.btn.r{background:#c0392b}.btn.s{padding:4px 10px;font-size:13px}
.btn.l{background:#eef4f9;color:var(--n)}
input,select,textarea{font:inherit;padding:7px 10px;border:1px solid #c9d6e2;border-radius:9px;background:#fff;color:var(--n);max-width:100%}
textarea{width:100%;min-height:70px}
form.f{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:12px}form.f label{display:flex;flex-direction:column;font-size:13px;color:var(--mut);gap:2px}
form.i{display:inline-flex;gap:4px;align-items:center;margin:0}
.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}.grid2 label{display:flex;flex-direction:column;font-size:13px;color:var(--mut);gap:3px}
.lg{max-width:420px;margin:50px auto}.lg label{display:block}.lg input{width:100%;margin:4px 0 12px}
.err{background:#fde8e6;color:#922;padding:10px 12px;border-radius:9px;margin-bottom:12px}.okm{background:#e6f6ec;color:#17603a;padding:10px 12px;border-radius:9px;margin-bottom:12px}
.mut{color:var(--mut);font-size:13px}
.st{display:inline-block;border-radius:999px;padding:1px 10px;font-size:12px;font-weight:800}
.st-new{background:#fff4d6;color:#8a5a00}.st-contacted{background:#e3f0fa;color:#1d5f8c}.st-booked{background:#e6f6ec;color:#17603a}.st-cancelled{background:#f4e4e4;color:#922}
.st-admin{background:var(--n);color:var(--g)}.st-trainee{background:#eef4f9;color:var(--n)}
.stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}.stats div{background:#eef4f9;border-radius:10px;padding:6px 14px;font-size:13px}.stats b{font-size:18px;display:block}
.pager{display:flex;gap:6px;margin-top:12px;align-items:center}
.kb{display:grid;grid-auto-flow:column;grid-auto-columns:minmax(230px,1fr);gap:10px;overflow-x:auto;padding-bottom:8px}
.kc{background:#eef4f9;border-radius:12px;padding:8px;min-height:120px}.kc h3{margin:0 0 8px;font-size:14px;display:flex;justify-content:space-between;border-bottom:3px solid var(--b);padding-bottom:4px}
.kc[data-stage=paid] h3,.kc[data-stage=completed] h3{border-color:#17803d}.kc[data-stage=lost] h3{border-color:#c0392b}.kc[data-stage=new] h3{border-color:var(--g)}
.kc.over{outline:2px dashed var(--b)}
.kd{background:#fff;border-radius:10px;padding:8px 10px;margin-bottom:8px;box-shadow:0 1px 4px rgba(16,41,59,.1);cursor:grab;font-size:13px}
.kd b a{color:var(--n);text-decoration:none}.kd .mut{font-size:12px}.kd select{font-size:12px;padding:2px 6px;margin-top:4px}
.tag{display:inline-block;background:#fff4d6;color:#8a5a00;border-radius:999px;padding:0 8px;font-size:11px;margin:2px 2px 0 0}
.due-over{color:#c0392b;font-weight:800}.due-today{color:#8a5a00;font-weight:800}
.tl{list-style:none;margin:0;padding:0}.tl li{border-inline-start:3px solid var(--b);padding:6px 12px;margin-bottom:8px;background:#fafcfe;border-radius:0 8px 8px 0}
.tl li.system,.tl li.stage{border-color:#c9d6e2;color:var(--mut)}
.bars div{display:flex;align-items:center;gap:8px;margin:4px 0;font-size:13px}.bars i{display:block;height:14px;background:var(--b);border-radius:4px;min-width:2px}
.bars i.w{background:#17803d}.cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px}
.st-lost{background:#f4e4e4;color:#922}.st-paid,.st-completed{background:#e6f6ec;color:#17603a}.st-interested,.st-registered{background:#e3f0fa;color:#1d5f8c}.st-staff{background:#e3f0fa;color:#1d5f8c}
details summary{cursor:pointer;color:var(--b);font-weight:700}
footer{text-align:center;color:var(--mut);font-size:12px;padding:10px 0 30px}
</style></head><body>
<header class="top"><img src="../assets/abad-logo.png" alt="ABAD"><div class="t"><b>لوحة إدارة منصة آباد الرقمية</b><small>معهد شبكة آباد للتدريب</small></div>
<a class="btn o s" href="labs.php">🧪 التحكم في المختبر</a>
<?php if ($user && in_array($user['role'], ['admin', 'staff'], true)): ?><a class="btn o s" href="seats.php">💺 مقاعد المعمل</a><?php if (function_exists('abd_book_any_on') ? abd_book_any_on() : ((require_once dirname(__DIR__) . '/api/_booking_lib.php') && abd_book_any_on())): ?><a class="btn o s" href="seat-bookings.php">📅 حجوزات المقاعد</a><?php endif; ?><a class="btn o s" href="services.php">🧾 خدمات المتدربين</a><a class="btn o s" href="exam-access.php">🎟️ صلاحيات الاختبارات</a><?php endif; ?>
<?php if ($user && $user['role'] === 'admin'): ?><a class="btn o s" href="stats.php">📊 الإحصائيات</a><a class="btn o s" href="enrollments.php">🗓️ تسجيل الدورات</a><?php endif; ?>
<a class="btn o s" href="../dashboard.html">لوحة المعلومات</a>
<?php if ($user): ?><span class="who">👤 <?=h($user['full_name_ar'] ?: $user['full_name_en'] ?: $user['email'])?></span>
<form class="i" method="post" action="index.php"><input type="hidden" name="csrf" value="<?=h(abd_csrf())?>"><button class="btn o s" name="do" value="logout">خروج</button></form><?php endif; ?>
</header>
<?php if ($user && $tab !== ''): ?><nav class="tabs"><?php foreach ($tabs as $k => $v): ?><a href="?tab=<?=h($k)?>" class="<?= $k === $tab ? 'on' : '' ?>"><?=h($v)?></a><?php endforeach; ?></nav><?php endif; ?>
<main>
<?php
}

function abd_admin_bottom(): void {
  echo "</main><footer>© معهد شبكة آباد للتدريب — منصة آباد الرقمية</footer></body></html>";
}

/** Render a fatal-but-friendly error page (e.g. DB not configured yet). */
function abd_admin_fatal(string $msg): void {
  http_response_code(503);
  abd_admin_top('خطأ');
  echo '<div class="card solo lg"><div class="err">' . h($msg) . '</div></div>';
  abd_admin_bottom();
  exit;
}
