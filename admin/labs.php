<?php
declare(strict_types=1);
require dirname(__DIR__) . '/api/_labs_lib.php';
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex');
abad_session();
$pwf = abad_pw_file();
$hasPw = is_file($pwf) && ($pw = json_decode((string)file_get_contents($pwf), true)) && !empty($pw['hash']);
$err = '';
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$csrfOk = $post && hash_equals(abad_csrf(), (string)($_POST['csrf'] ?? ''));

if ($post && !$csrfOk) $err = 'انتهت صلاحية النموذج، أعد المحاولة.';
elseif ($post && ($_POST['do'] ?? '') === 'setup' && !$hasPw) {
  $p = (string)($_POST['p'] ?? ''); $p2 = (string)($_POST['p2'] ?? '');
  if ((function_exists("mb_strlen") ? mb_strlen($p) : strlen($p)) < 10) $err = 'كلمة المرور يجب ألا تقل عن 10 أحرف.';
  elseif ($p !== $p2) $err = 'كلمتا المرور غير متطابقتين.';
  else {
    $fh = fopen($pwf, 'x'); // fails if created concurrently
    if ($fh) { fwrite($fh, json_encode(['hash' => password_hash($p, PASSWORD_DEFAULT), 'created' => time()])); fclose($fh); @chmod($pwf, 0600);
      session_regenerate_id(true); $_SESSION['abad_admin'] = 1; header('Location: labs.php'); exit; }
    $err = 'تم إنشاء كلمة المرور مسبقًا.';
  }
} elseif ($post && ($_POST['do'] ?? '') === 'login' && $hasPw) {
  $fails = $_SESSION['fails'] ?? 0;
  if ($fails >= 5 && time() - ($_SESSION['failt'] ?? 0) < 300) $err = 'محاولات كثيرة، انتظر 5 دقائق.';
  elseif (password_verify((string)($_POST['p'] ?? ''), $pw['hash'])) {
    session_regenerate_id(true); $_SESSION['abad_admin'] = 1; unset($_SESSION['fails']); header('Location: labs.php'); exit;
  } else { $_SESSION['fails'] = $fails + 1; $_SESSION['failt'] = time(); usleep(400000); $err = 'كلمة المرور غير صحيحة.'; }
} elseif ($post && ($_POST['do'] ?? '') === 'logout') {
  $_SESSION = []; session_destroy(); header('Location: labs.php'); exit;
}
$admin = abad_is_admin();
$csrf = abad_csrf();
$data = $admin ? abad_labs_tx() : null;
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>التحكم في المختبر — آباد</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800&display=swap" rel="stylesheet">
<style>
:root{--n:#10293b;--b:#3f86b3;--g:#F5BD2D}*{box-sizing:border-box}body{margin:0;font-family:Cairo,system-ui,sans-serif;background:#f3f6f9;color:var(--n)}
header{background:var(--n);color:#fff;padding:14px 20px;display:flex;gap:12px;align-items:center;justify-content:space-between;border-bottom:4px solid var(--g)}
header a{color:#fff}h1{font-size:20px;margin:0}main{max-width:960px;margin:24px auto;padding:0 14px}
.card{background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(16,41,59,.08);padding:20px}
table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #e6edf3;text-align:start}th{background:#eef4f9}
.btn{font-family:inherit;border:0;border-radius:9px;padding:7px 14px;cursor:pointer;background:var(--b);color:#fff;font-weight:700}
.btn.g{background:var(--g);color:var(--n)}.btn.o{background:transparent;border:1px solid #fff;color:#fff}.btn.r{background:#c0392b}
input[type=password]{width:100%;font:inherit;padding:10px;border:1px solid #c9d6e2;border-radius:9px;margin:6px 0 12px}
.lg{max-width:380px;margin:60px auto}.err{background:#fde8e6;color:#922;padding:10px;border-radius:9px;margin-bottom:10px}
.on{color:#17803d;font-weight:800}.off{color:#c0392b;font-weight:800}.mut{color:#678;font-size:13px}form.i{display:inline}
</style></head><body>
<header><h1>🧪 <span data-t="التحكم في المختبر|Lab control">التحكم في المختبر</span></h1>
<div><a href="../dashboard.html#labs" data-t="← لوحة الإدارة|← Admin dashboard">← لوحة الإدارة</a>
<?php if ($admin): ?> <form class="i" method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><button class="btn o" name="do" value="logout" data-t="خروج|Log out">خروج</button></form><?php endif; ?></div></header>
<main>
<?php if ($err): ?><div class="err"><?=h($err)?></div><?php endif; ?>
<?php if (!$hasPw): ?>
<form class="card lg" method="post"><h2 data-t="إعداد كلمة مرور المدير|Set admin password">إعداد كلمة مرور المدير</h2>
<p class="mut" data-t="أول زيارة: أنشئ كلمة مرور (10 أحرف على الأقل). تُحفظ مشفّرة خارج مجلد الموقع.|First visit: create a password (min 10 chars). Stored hashed outside the web root.">أول زيارة: أنشئ كلمة مرور (10 أحرف على الأقل). تُحفظ مشفّرة خارج مجلد الموقع.</p>
<input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="do" value="setup">
<label data-t="كلمة المرور|Password">كلمة المرور</label><input type="password" name="p" minlength="10" required autocomplete="new-password">
<label data-t="تأكيد كلمة المرور|Confirm">تأكيد كلمة المرور</label><input type="password" name="p2" minlength="10" required autocomplete="new-password">
<button class="btn g" data-t="حفظ ودخول|Save & sign in">حفظ ودخول</button></form>
<?php elseif (!$admin): ?>
<form class="card lg" method="post"><h2>🔒 <span data-t="دخول الإدارة|Admin login">دخول الإدارة</span></h2>
<input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="do" value="login">
<label data-t="كلمة المرور|Password">كلمة المرور</label><input type="password" name="p" required autocomplete="current-password" autofocus>
<button class="btn" data-t="دخول|Sign in">دخول</button></form>
<?php else: ?>
<div class="card"><table><thead><tr><th data-t="المختبر|Lab">المختبر</th><th data-t="الحالة|Status">الحالة</th><th data-t="مرات الفتح|Opens">مرات الفتح</th><th data-t="آخر فتح|Last opened">آخر فتح</th><th></th></tr></thead><tbody>
<?php foreach (ABAD_LABS as $id => $m): $l = $data['labs'][$id]; ?>
<tr data-id="<?=h($id)?>"><td><b data-t="<?=h($m['ar'].'|'.$m['en'])?>"><?=h($m['ar'])?></b><div class="mut" dir="ltr"><a href="../<?=h($m['href'])?>" target="_blank" rel="noopener"><?=h($m['href'])?></a> · <?=h($id)?></div></td>
<td><span class="st <?=$l['visible']?'on':'off'?>"><?=$l['visible']?'ظاهر':'مخفي'?></span></td>
<td class="op"><b><?= (int)$l['opens'] ?></b></td>
<td class="ls mut"><?= $l['last'] ? h(date('Y-m-d H:i', (int)$l['last'])).' (الرياض)' : '—' ?></td>
<td><button class="btn tg <?=$l['visible']?'g':''?>" type="button"><?=$l['visible']?'إخفاء':'إظهار'?></button> <button class="btn r rs" type="button" data-t="تصفير|Reset">تصفير</button></td></tr>
<?php endforeach; ?></tbody></table>
<p class="mut" data-t="يُحسب الفتح مرة واحدة لكل زائر ولكل مختبر كل 10 دقائق. الأوقات بتوقيت الرياض.|Opens counted once per visitor per lab per 10 min. Times in Riyadh time.">يُحسب الفتح مرة واحدة لكل زائر ولكل مختبر كل 10 دقائق. الأوقات بتوقيت الرياض.</p></div>
<script>
(function(){var CSRF=<?=json_encode($csrf)?>;
function fmt(t){if(!t)return"—";return new Date(t*1000).toLocaleString("sv-SE",{timeZone:"Asia/Riyadh"}).slice(0,16)+" (الرياض)"}
function post(p){var f=new FormData();for(var k in p)f.append(k,p[k]);f.append("csrf",CSRF);return fetch("../api/labs.php",{method:"POST",body:f,credentials:"same-origin"}).then(function(r){return r.json()})}
function paint(tr,l){tr.querySelector(".st").className="st "+(l.visible?"on":"off");tr.querySelector(".st").textContent=l.visible?"ظاهر":"مخفي";
 var b=tr.querySelector(".tg");b.textContent=l.visible?"إخفاء":"إظهار";b.classList.toggle("g",l.visible);tr.querySelector(".op b").textContent=l.opens;tr.querySelector(".ls").textContent=fmt(l.last)}
document.querySelectorAll("tr[data-id]").forEach(function(tr){var id=tr.dataset.id;
 tr.querySelector(".tg").onclick=function(){post({action:"toggle",id:id}).then(function(d){if(d.labs)paint(tr,d.labs[id]);else alert(d.error)})};
 tr.querySelector(".rs").onclick=function(){if(confirm("تصفير العداد؟"))post({action:"reset",id:id}).then(function(d){if(d.labs)paint(tr,d.labs[id]);else alert(d.error)})}});
})();
</script>
<?php endif; ?>
</main>
<script>try{if(localStorage.getItem("abad-lang")==="en"){document.documentElement.dir="ltr";document.documentElement.lang="en";document.querySelectorAll("[data-t]").forEach(function(e){var p=e.getAttribute("data-t").split("|");if(p[1])e.textContent=p[1]})}}catch(e){}</script>
</body></html>
