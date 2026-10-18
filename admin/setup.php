<?php
// One-time setup: creates the FIRST admin account. Works only while no admin exists.
declare(strict_types=1);
require __DIR__ . '/_layout.php';

try { $exists = abd_admin_exists(); }
catch (Throwable $e) { error_log('[abad-setup] ' . $e->getMessage()); abd_admin_fatal('قاعدة البيانات غير مهيأة بعد. شغّل ترحيل قاعدة البيانات (api/bin/migrate.php) ثم أعد المحاولة.'); }

abd_session();
$err = ''; $v = ['full_name_ar' => '', 'full_name_en' => '', 'email' => ''];
if (!$exists && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  foreach ($v as $k => $_) $v[$k] = abd_str($_POST, $k);
  $email = abd_email($v['email']);
  $p = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
  $p2 = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';
  if (!abd_csrf_ok((string)($_POST['csrf'] ?? ''))) $err = 'انتهت صلاحية النموذج، أعد المحاولة.';
  elseif (!abd_rate('setup', abd_ip_hash(), 10, 3600)) $err = 'محاولات كثيرة، حاول لاحقاً.';
  elseif ($v['full_name_ar'] === '') $err = 'يرجى إدخال الاسم.';
  elseif (!$email) $err = 'البريد الإلكتروني غير صحيح.';
  elseif (mb_strlen($p) < 10 || mb_strlen($p) > 200) $err = 'كلمة المرور يجب ألا تقل عن 10 أحرف.';
  elseif ($p !== $p2) $err = 'كلمتا المرور غير متطابقتين.';
  else {
    $db = abd_db();
    $db->query("SELECT GET_LOCK('abd_admin_setup', 10)");
    try {
      if (abd_admin_exists()) $err = 'تم إنشاء حساب المدير مسبقاً.';
      elseif (abd_q('SELECT 1 FROM abd_users WHERE email = ?', [$email])->fetchColumn()) $err = 'هذا البريد مسجّل مسبقاً كحساب متدرب. استخدم بريداً آخر.';
      else {
        abd_q("INSERT INTO abd_users (full_name_ar, full_name_en, email, password_hash, role) VALUES (?, ?, ?, ?, 'admin')",
          [$v['full_name_ar'], $v['full_name_en'], $email, password_hash($p, PASSWORD_DEFAULT)]);
        $id = (int)$db->lastInsertId();
        $db->query("SELECT RELEASE_LOCK('abd_admin_setup')");
        abd_login_session($id);
        header('Location: index.php', true, 303); exit;
      }
    } finally { $db->query("SELECT RELEASE_LOCK('abd_admin_setup')"); }
  }
}

abd_admin_top('إعداد حساب المدير');
if ($exists): ?>
<div class="card solo lg"><h1>✅ تم الإعداد</h1><p>تم إنشاء حساب المدير مسبقاً، ولم تعد صفحة الإعداد متاحة.</p><a class="btn" href="index.php">الدخول إلى لوحة الإدارة</a></div>
<?php else: ?>
<form class="card solo lg" method="post" autocomplete="off">
  <h1>🔐 إعداد حساب المدير الأول</h1>
  <p class="mut">هذه الصفحة تعمل مرة واحدة فقط — قبل إنشاء أي حساب مدير. بعد الإنشاء تُغلق تلقائياً.</p>
  <?php if ($err): ?><div class="err"><?=h($err)?></div><?php endif; ?>
  <input type="hidden" name="csrf" value="<?=h(abd_csrf())?>">
  <label>الاسم الكامل (بالعربية) *<input name="full_name_ar" required maxlength="190" value="<?=h($v['full_name_ar'])?>"></label>
  <label>الاسم بالإنجليزية<input name="full_name_en" dir="ltr" maxlength="190" value="<?=h($v['full_name_en'])?>"></label>
  <label>البريد الإلكتروني *<input name="email" type="email" dir="ltr" required maxlength="190" value="<?=h($v['email'])?>"></label>
  <label>كلمة المرور (10 أحرف على الأقل) *<input name="password" type="password" required minlength="10" autocomplete="new-password"></label>
  <label>تأكيد كلمة المرور *<input name="password2" type="password" required minlength="10" autocomplete="new-password"></label>
  <button class="btn g">إنشاء حساب المدير</button>
</form>
<?php endif;
abd_admin_bottom();
