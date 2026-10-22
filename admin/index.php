<?php
// ABAD Digital Platform — admin panel (bookings, users, courses & enrollments, exam results).
declare(strict_types=1);
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/api/bookings.php';
require_once dirname(__DIR__) . '/api/courses.php';
require_once dirname(__DIR__) . '/api/results.php';
require_once dirname(__DIR__) . '/api/_crm.php';
require_once __DIR__ . '/_crm_views.php';

try { $hasAdmin = abd_admin_exists(); }
catch (Throwable $e) { error_log('[abad-admin] ' . $e->getMessage()); abd_admin_fatal('قاعدة البيانات غير مهيأة بعد. شغّل ترحيل قاعدة البيانات (api/bin/migrate.php).'); }
if (!$hasAdmin) { header('Location: setup.php', true, 303); exit; }

abd_session();
const ABD_TABS = ['home', 'crm', 'tasks', 'reports', 'bookings', 'users', 'courses', 'results'];
$tab = (string)($_GET['tab'] ?? 'home');
if (!in_array($tab, ABD_TABS, true)) $tab = 'home';
$STATUS = ['new' => 'جديد', 'contacted' => 'تم التواصل', 'booked' => 'تم الحجز', 'cancelled' => 'ملغى'];
$ENR = ['active' => 'نشط', 'completed' => 'مكتمل', 'cancelled' => 'ملغى'];

function back(string $tab, string $msg = '', bool $err = false, array $extra = []): void {
  $_SESSION['flash'] = [$msg, $err];
  $q = array_merge(['tab' => $tab], $extra);
  header('Location: index.php?' . http_build_query($q), true, 303); exit;
}
/** Keep current filters in pagination / redirect links. */
function qs(array $over): string { return '?' . http_build_query(array_filter(array_merge($_GET, $over), fn($v) => $v !== '' && $v !== null)); }

// ------------------------------------------------------------------ POST handling
$loginErr = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $do = (string)($_POST['do'] ?? '');
  $csrfOk = abd_csrf_ok((string)($_POST['csrf'] ?? ''));
  $ret = (string)($_POST['ret'] ?? $tab);
  if (!in_array($ret, ABD_TABS, true)) $ret = 'home';
  if ($do === 'logout') { if ($csrfOk) abd_logout(); header('Location: index.php', true, 303); exit; }
  if ($do === 'login') {
    $email = abd_email(abd_str($_POST, 'email')) ?? '';
    $pw = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $ip = abd_ip_hash();
    if (!$csrfOk) $loginErr = 'انتهت صلاحية النموذج، أعد المحاولة.';
    elseif (!abd_rate('login-ip', $ip, 30, 900) || !abd_rate('login', $ip . '|' . $email, 6, 900)) $loginErr = 'محاولات كثيرة، انتظر 15 دقيقة ثم أعد المحاولة.';
    else {
      $row = $email !== '' ? abd_q("SELECT id, password_hash, role, is_active FROM abd_users WHERE email = ?", [$email])->fetch() : false;
      if ($row && (int)$row['is_active'] && in_array($row['role'], ['admin', 'staff', 'trainer'], true) && password_verify($pw, $row['password_hash'])) {
        abd_rate_clear('login', $ip . '|' . $email);
        abd_login_session((int)$row['id']);
        header('Location: ' . ($row['role'] === 'trainer' ? 'trainer.php' : 'index.php'), true, 303); exit;
      }
      usleep(300000); $loginErr = 'بيانات الدخول غير صحيحة أو الحساب ليس حساب إدارة.';
    }
  } else {
    if (!abd_is_staff()) { header('Location: index.php', true, 303); exit; }
    if (!$csrfOk) back($ret, 'انتهت صلاحية النموذج، أعد المحاولة.', true);
    $me = abd_user();
    switch ($do) {
      case 'booking_status':
        $ok = abd_booking_set_status((int)($_POST['id'] ?? 0), (string)($_POST['status'] ?? ''), abd_str($_POST, 'admin_note', 2000));
        back('bookings', $ok ? 'تم تحديث حالة الحجز.' : 'تعذّر تحديث الحجز.', !$ok);
      case 'user_role':
        if ($me['role'] !== 'admin') back('home', 'هذه العملية للمدير فقط.', true);
        $uid = (int)($_POST['id'] ?? 0); $role = (string)($_POST['role'] ?? '');
        if ($uid === (int)$me['id'] || !in_array($role, ['trainee', 'staff', 'trainer'], true)) back('users', 'لا يمكن تنفيذ هذا التغيير.', true);
        abd_q("UPDATE abd_users SET role = ? WHERE id = ? AND role <> 'admin'", [$role, $uid]);
        back('users', 'تم تحديث الدور.');
      case 'user_active':
        if ($me['role'] !== 'admin') back('home', 'هذه العملية للمدير فقط.', true);
        $uid = (int)($_POST['id'] ?? 0);
        if ($uid === (int)$me['id']) back('users', 'لا يمكنك تعطيل حسابك.', true);
        abd_q('UPDATE abd_users SET is_active = ? WHERE id = ?', [($_POST['active'] ?? '') === '1' ? 1 : 0, $uid]);
        back('users', 'تم تحديث المستخدم.');
      case 'course_create': case 'course_update': case 'course_delete':
      case 'enroll': case 'unenroll': case 'enrollment_status':
        $action = str_replace('course_', '', $do);
        $r = abd_course_action($action, $_POST);
        $msgs = ['create' => 'تمت إضافة الدورة.', 'update' => 'تم حفظ الدورة.', 'delete' => 'تم حذف الدورة.', 'enroll' => 'تم تسجيل المتدرب في الدورة.',
                 'unenroll' => 'تم إلغاء تسجيل المتدرب.', 'enrollment_status' => 'تم تحديث حالة التسجيل.'];
        $errs = ['invalid' => 'بيانات غير صحيحة', 'slug_taken' => 'المعرّف (slug) مستخدم مسبقاً', 'not_found' => 'غير موجود',
                 'user_not_found' => 'لا يوجد مستخدم بهذا البريد — يجب أن يسجّل المتدرب حساباً أولاً'];
        $extra = in_array($action, ['enroll', 'unenroll', 'enrollment_status'], true) ? ['course' => (int)($_POST['course_id'] ?? 0)] : [];
        if ($r[0]) back('courses', $msgs[$action], false, $extra);
        back('courses', ($errs[$r[1]] ?? 'تعذّر تنفيذ العملية') . (isset($r[2]) ? ' (' . implode(', ', $r[2]) . ')' : ''), true, $extra);
      default:
        if (str_starts_with($do, 'crm_')) abd_crm_handle_post($do, $me);
    }
    back($ret, 'عملية غير معروفة.', true);
  }
}

$me = abd_user();
if ($me && $me['role'] === 'trainer') { header('Location: trainer.php', true, 303); exit; }
if (!$me || !in_array($me['role'], ['admin', 'staff'], true)) {
  abd_admin_top('دخول الإدارة'); ?>
<form class="card solo lg" method="post" action="index.php">
  <h1>🔐 دخول لوحة الإدارة</h1>
  <?php if ($loginErr): ?><div class="err"><?=h($loginErr)?></div><?php endif; ?>
  <?php if ($me): ?><div class="err">أنت مسجّل الدخول بحساب متدرب. سجّل الدخول بحساب مدير أو موظف.</div><?php endif; ?>
  <input type="hidden" name="csrf" value="<?=h(abd_csrf())?>">
  <label>البريد الإلكتروني<input name="email" type="email" dir="ltr" required autocomplete="username"></label>
  <label>كلمة المرور<input name="password" type="password" required autocomplete="current-password"></label>
  <button class="btn g" name="do" value="login">دخول</button>
</form>
<?php abd_admin_bottom(); exit; }

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$csrf = abd_csrf();
$tok = '<input type="hidden" name="csrf" value="' . h($csrf) . '">';
$page = max(1, (int)($_GET['page'] ?? 1));
function pager(int $total, int $page, int $per): void {
  $pages = max(1, (int)ceil($total / $per)); if ($pages <= 1) return;
  echo '<div class="pager">';
  if ($page > 1) echo '<a class="btn l s" href="' . h(qs(['page' => $page - 1])) . '">→ السابق</a>';
  echo '<span class="mut">صفحة ' . $page . ' من ' . $pages . '</span>';
  if ($page < $pages) echo '<a class="btn l s" href="' . h(qs(['page' => $page + 1])) . '">التالي ←</a>';
  echo '</div>';
}
function wa(string $phone): string { return 'https://wa.me/' . preg_replace('/\D/', '', $phone); }

abd_admin_top('لوحة الإدارة', $me, $tab);
if ($flash && $flash[0] !== '') echo '<div class="' . ($flash[1] ? 'err' : 'okm') . '" style="margin-top:12px">' . h($flash[0]) . '</div>';
if ($tab === 'users' && $me['role'] !== 'admin') $tab = 'home';
echo '<div class="card">';
if (in_array($tab, ['home', 'crm', 'tasks', 'reports'], true)) { abd_crm_render($tab, $me, $tok, $page); echo '</div>'; abd_admin_bottom(); exit; }

// ================================================================== BOOKINGS
if ($tab === 'bookings'):
  $f = ['status' => (string)($_GET['status'] ?? ''), 'q' => abd_str($_GET, 'q'), 'from' => (string)($_GET['from'] ?? ''), 'to' => (string)($_GET['to'] ?? ''), 'vendor' => abd_str($_GET, 'vendor')];
  $list = abd_bookings_list($f, $page);
  $counts = abd_q('SELECT status, COUNT(*) n FROM abd_bookings GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
  $vendors = abd_q('SELECT DISTINCT vendor FROM abd_bookings ORDER BY vendor')->fetchAll(PDO::FETCH_COLUMN); ?>
  <h1>📋 طلبات حجز الاختبارات</h1>
  <div class="stats"><?php foreach ($STATUS as $k => $v): ?><div><b><?= (int)($counts[$k] ?? 0) ?></b><?=h($v)?></div><?php endforeach; ?></div>
  <form class="f" method="get"><input type="hidden" name="tab" value="bookings">
    <label>الحالة<select name="status"><option value="">الكل</option><?php foreach ($STATUS as $k => $v): ?><option value="<?=h($k)?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?=h($v)?></option><?php endforeach; ?></select></label>
    <label>الجهة<select name="vendor"><option value="">الكل</option><?php foreach ($vendors as $v): ?><option <?= $f['vendor'] === $v ? 'selected' : '' ?>><?=h($v)?></option><?php endforeach; ?></select></label>
    <label>بحث (اسم، بريد، جوال، هوية، مرجع، شهادة)<input name="q" value="<?=h($f['q'])?>"></label>
    <label>من<input type="date" name="from" value="<?=h($f['from'])?>"></label><label>إلى<input type="date" name="to" value="<?=h($f['to'])?>"></label>
    <button class="btn">تصفية</button><a class="btn l" href="?tab=bookings">مسح</a>
  </form>
  <p class="mut"><?= (int)$list['total'] ?> طلب</p>
  <table><thead><tr><th>المرجع / التاريخ</th><th>المتقدّم</th><th>التواصل</th><th>الجهة / الشهادة</th><th>الموعد المفضّل</th><th>الدفع / ملاحظات</th><th>الحالة</th></tr></thead><tbody>
  <?php foreach ($list['items'] as $b): ?>
    <tr><td><b class="ltr"><?=h($b['ref'])?></b><br><span class="mut ltr"><?=h(substr((string)$b['created_at'], 0, 16))?></span></td>
      <td><span class="ltr"><?=h($b['name_en'])?></span><br><span class="mut">هوية: <span class="ltr"><?=h($b['national_id'])?></span></span><?= $b['user_id'] ? '<br><span class="st st-trainee">حساب #' . (int)$b['user_id'] . '</span>' : '' ?></td>
      <td><a class="ltr" href="<?=h(wa((string)$b['phone']))?>" target="_blank" rel="noopener"><?=h($b['phone'])?></a><br><a class="ltr" href="mailto:<?=h($b['email'])?>"><?=h($b['email'])?></a></td>
      <td><?=h($b['vendor'])?><br><b><?=h($b['cert'])?></b><br><span class="mut"><?=h($b['exam_type'])?></span></td>
      <td><span class="ltr"><?=h($b['preferred_date'])?></span><br><span class="mut"><?=h($b['preferred_time'])?></span></td>
      <td><?=h($b['payment_method'] ?: '—')?><?php if ($b['notes']): ?><details><summary>ملاحظات</summary><?=nl2br(h($b['notes']))?></details><?php endif; ?>
        <?php if ($b['invoice_path']): ?><br><span class="mut">فاتورة مرفقة</span><?php endif; ?></td>
      <td><span class="st st-<?=h($b['status'])?>"><?=h($STATUS[$b['status']] ?? $b['status'])?></span>
        <form method="post" style="margin-top:6px"><?=$tok?><input type="hidden" name="do" value="booking_status"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
          <select name="status"><?php foreach ($STATUS as $k => $v): ?><option value="<?=h($k)?>" <?= $b['status'] === $k ? 'selected' : '' ?>><?=h($v)?></option><?php endforeach; ?></select>
          <textarea name="admin_note" placeholder="ملاحظة داخلية" style="min-height:40px;margin-top:4px"><?=h($b['admin_note'])?></textarea>
          <button class="btn s">حفظ</button></form></td></tr>
  <?php endforeach; if (!$list['items']): ?><tr><td colspan="7" class="mut">لا توجد طلبات.</td></tr><?php endif; ?>
  </tbody></table>
  <?php pager($list['total'], $list['page'], $list['per_page']);

// ================================================================== USERS
elseif ($tab === 'users'):
  $q = abd_str($_GET, 'q'); $role = (string)($_GET['role'] ?? '');
  $w = []; $p = [];
  if ($q !== '') { $like = '%' . addcslashes($q, '%_\\') . '%'; $w[] = '(u.full_name_ar LIKE ? OR u.full_name_en LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.national_id LIKE ?)'; array_push($p, $like, $like, $like, $like, $like); }
  if (in_array($role, ['trainee', 'staff', 'trainer', 'admin'], true)) { $w[] = 'u.role = ?'; $p[] = $role; }
  $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
  $total = (int)abd_q("SELECT COUNT(*) FROM abd_users u $where", $p)->fetchColumn(); $off = ($page - 1) * 50;
  $users = abd_q("SELECT u.id, u.full_name_ar, u.full_name_en, u.email, u.phone, u.national_id, u.role, u.is_active, u.created_at, u.last_login_at,
      (SELECT COUNT(*) FROM abd_enrollments e WHERE e.user_id = u.id) enr, (SELECT COUNT(*) FROM abd_exam_results r WHERE r.user_id = u.id) res
      FROM abd_users u $where ORDER BY u.created_at DESC, u.id DESC LIMIT 50 OFFSET $off", $p)->fetchAll(); ?>
  <h1>👥 المستخدمون</h1>
  <form class="f" method="get"><input type="hidden" name="tab" value="users">
    <label>بحث<input name="q" value="<?=h($q)?>"></label>
    <label>الدور<select name="role"><option value="">الكل</option><option value="trainee" <?= $role === 'trainee' ? 'selected' : '' ?>>متدرب</option><option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>موظف</option><option value="trainer" <?= $role === 'trainer' ? 'selected' : '' ?>>مدرّب</option><option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>مدير</option></select></label>
    <button class="btn">تصفية</button><a class="btn l" href="?tab=users">مسح</a></form>
  <p class="mut"><?= $total ?> مستخدم</p>
  <table><thead><tr><th>#</th><th>الاسم</th><th>البريد / الجوال</th><th>الهوية</th><th>الدور</th><th>التسجيل / آخر دخول</th><th>دورات</th><th>نتائج</th><th>الحالة</th></tr></thead><tbody>
  <?php foreach ($users as $u): ?>
    <tr><td><?= (int)$u['id'] ?></td><td><?=h($u['full_name_ar'])?><br><span class="mut ltr"><?=h($u['full_name_en'])?></span></td>
      <td><span class="ltr"><?=h($u['email'])?></span><br><?php if ($u['phone']): ?><a class="ltr" href="<?=h(wa((string)$u['phone']))?>" target="_blank" rel="noopener"><?=h($u['phone'])?></a><?php endif; ?></td>
      <td class="ltr"><?=h($u['national_id'] ?? '—')?></td>
      <td><span class="st st-<?=h($u['role'])?>"><?= ['admin' => 'مدير', 'staff' => 'موظف', 'trainer' => 'مدرّب', 'trainee' => 'متدرب'][$u['role']] ?? h($u['role']) ?></span>
        <?php if ($u['role'] !== 'admin'): ?><form class="i" method="post" style="margin-top:4px"><?=$tok?><input type="hidden" name="do" value="user_role"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <select name="role" style="font-size:12px;padding:2px 6px"><?php foreach (['trainee' => 'متدرب', 'staff' => 'موظف', 'trainer' => 'مدرّب'] as $rk => $rv): ?><option value="<?= $rk ?>" <?= $u['role'] === $rk ? 'selected' : '' ?>><?= $rv ?></option><?php endforeach; ?></select><button class="btn l s">تغيير الدور</button></form><?php endif; ?></td>
      <td><span class="mut ltr"><?=h(substr((string)$u['created_at'], 0, 16))?></span><br><span class="mut ltr"><?=h($u['last_login_at'] ? substr((string)$u['last_login_at'], 0, 16) : '—')?></span></td>
      <td><?= (int)$u['enr'] ?></td><td><?php if ($u['res']): ?><a href="?tab=results&user_id=<?= (int)$u['id'] ?>"><?= (int)$u['res'] ?></a><?php else: ?>0<?php endif; ?></td>
      <td><?php if ((int)$u['id'] === (int)$me['id']): ?><span class="mut">أنت</span><?php else: ?>
        <form class="i" method="post"><?=$tok?><input type="hidden" name="do" value="user_active"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <input type="hidden" name="active" value="<?= (int)$u['is_active'] ? '0' : '1' ?>">
        <button class="btn s <?= (int)$u['is_active'] ? 'r' : '' ?>"><?= (int)$u['is_active'] ? 'تعطيل' : 'تفعيل' ?></button></form><?php endif; ?>
        <?= (int)$u['is_active'] ? '' : '<br><span class="st st-cancelled">معطّل</span>' ?></td></tr>
  <?php endforeach; if (!$users): ?><tr><td colspan="9" class="mut">لا يوجد مستخدمون.</td></tr><?php endif; ?>
  </tbody></table>
  <?php pager($total, $page, 50);

// ================================================================== COURSES
elseif ($tab === 'courses'):
  $courses = abd_courses_all();
  $cid = (int)($_GET['course'] ?? 0);
  $sel = null; foreach ($courses as $c) if ((int)$c['id'] === $cid) $sel = $c;
  $courseForm = function (?array $c) use ($tok): void { $c ??= []; ?>
    <form method="post"><?=$tok?><input type="hidden" name="do" value="<?= $c ? 'course_update' : 'course_create' ?>"><?php if ($c): ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><?php endif; ?>
      <div class="grid2">
        <label>العنوان بالعربية *<input name="title_ar" required maxlength="190" value="<?=h($c['title_ar'] ?? '')?>"></label>
        <label>العنوان بالإنجليزية<input name="title_en" dir="ltr" maxlength="190" value="<?=h($c['title_en'] ?? '')?>"></label>
        <label>المعرّف (slug)<input name="slug" dir="ltr" maxlength="80" pattern="[a-z0-9][a-z0-9\-]*" value="<?=h($c['slug'] ?? '')?>" placeholder="ccna-2026-10"></label>
        <label>نوع التدريب<select name="delivery"><option value="onsite" <?= ($c['delivery'] ?? '') === 'onsite' ? 'selected' : '' ?>>حضوري</option><option value="online" <?= ($c['delivery'] ?? '') === 'online' ? 'selected' : '' ?>>أونلاين</option></select></label>
        <label>تاريخ البداية<input type="date" name="start_date" value="<?=h($c['start_date'] ?? '')?>"></label>
        <label>تاريخ النهاية<input type="date" name="end_date" value="<?=h($c['end_date'] ?? '')?>"></label>
        <label>رابط الدورة (صفحة .html في المنصة أو https://)<input name="link_url" dir="ltr" maxlength="255" value="<?=h($c['link_url'] ?? '')?>" placeholder="course-files.html"></label>
        <label>مفعّلة<span><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" <?= !$c || (int)$c['is_active'] ? 'checked' : '' ?>> تظهر للمتدربين</span></label>
      </div>
      <div class="grid2" style="margin-top:8px"><label>الوصف بالعربية<textarea name="description_ar" maxlength="4000"><?=h($c['description_ar'] ?? '')?></textarea></label>
        <label>الوصف بالإنجليزية<textarea name="description_en" dir="ltr" maxlength="4000"><?=h($c['description_en'] ?? '')?></textarea></label></div>
      <button class="btn g" style="margin-top:8px"><?= $c ? 'حفظ التعديلات' : 'إضافة الدورة' ?></button>
    </form><?php };
  if ($sel):
    $enr = abd_course_enrollments($cid); ?>
    <p><a href="?tab=courses">→ كل الدورات</a></p>
    <h1>🎓 <?=h($sel['title_ar'])?> <span class="mut ltr"><?=h($sel['slug'])?></span></h1>
    <details><summary>تعديل بيانات الدورة</summary><?php $courseForm($sel); ?></details>
    <h2 style="margin-top:16px">المسجّلون (<?= count($enr) ?>)</h2>
    <form class="f" method="post"><?=$tok?><input type="hidden" name="do" value="enroll"><input type="hidden" name="course_id" value="<?= $cid ?>">
      <label>بريد المتدرب (يجب أن يكون لديه حساب)<input name="email" type="email" dir="ltr" required></label><button class="btn">تسجيل في الدورة</button></form>
    <table><thead><tr><th>المتدرب</th><th>البريد / الجوال</th><th>تاريخ التسجيل</th><th>الحالة</th><th></th></tr></thead><tbody>
    <?php foreach ($enr as $e): ?>
      <tr><td><?=h($e['full_name_ar'])?><br><span class="mut ltr"><?=h($e['full_name_en'])?></span></td><td><span class="ltr"><?=h($e['email'])?></span><br><span class="ltr mut"><?=h($e['phone'])?></span></td>
        <td class="mut ltr"><?=h(substr((string)$e['created_at'], 0, 16))?></td>
        <td><form class="i" method="post"><?=$tok?><input type="hidden" name="do" value="enrollment_status"><input type="hidden" name="course_id" value="<?= $cid ?>"><input type="hidden" name="user_id" value="<?= (int)$e['user_id'] ?>">
          <select name="status"><?php foreach ($ENR as $k => $v): ?><option value="<?=h($k)?>" <?= $e['status'] === $k ? 'selected' : '' ?>><?=h($v)?></option><?php endforeach; ?></select><button class="btn s">حفظ</button></form></td>
        <td><form class="i" method="post" onsubmit="return confirm('إلغاء تسجيل هذا المتدرب؟')"><?=$tok?><input type="hidden" name="do" value="unenroll"><input type="hidden" name="course_id" value="<?= $cid ?>"><input type="hidden" name="user_id" value="<?= (int)$e['user_id'] ?>"><button class="btn s r">إزالة</button></form></td></tr>
    <?php endforeach; if (!$enr): ?><tr><td colspan="5" class="mut">لا يوجد مسجّلون بعد.</td></tr><?php endif; ?>
    </tbody></table>
  <?php else: ?>
    <h1>🎓 الدورات والتسجيل</h1>
    <details <?= $courses ? '' : 'open' ?>><summary>➕ إضافة دورة جديدة</summary><?php $courseForm(null); ?></details>
    <table style="margin-top:14px"><thead><tr><th>الدورة</th><th>النوع</th><th>الفترة</th><th>المسجّلون</th><th>الحالة</th><th></th></tr></thead><tbody>
    <?php foreach ($courses as $c): ?>
      <tr><td><a href="?tab=courses&course=<?= (int)$c['id'] ?>"><b><?=h($c['title_ar'])?></b></a><br><span class="mut ltr"><?=h($c['title_en'])?> · <?=h($c['slug'])?></span></td>
        <td><?= $c['delivery'] === 'online' ? 'أونلاين' : 'حضوري' ?></td>
        <td class="ltr mut"><?=h(($c['start_date'] ?? '—') . ' → ' . ($c['end_date'] ?? '—'))?></td>
        <td><a href="?tab=courses&course=<?= (int)$c['id'] ?>"><?= (int)$c['enrolled'] ?></a></td>
        <td><?= (int)$c['is_active'] ? '<span class="st st-booked">مفعّلة</span>' : '<span class="st st-cancelled">مخفية</span>' ?></td>
        <td><a class="btn s" href="?tab=courses&course=<?= (int)$c['id'] ?>">إدارة</a>
          <form class="i" method="post" onsubmit="return confirm('حذف الدورة وجميع تسجيلاتها؟')"><?=$tok?><input type="hidden" name="do" value="course_delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn s r">حذف</button></form></td></tr>
    <?php endforeach; if (!$courses): ?><tr><td colspan="6" class="mut">لا توجد دورات بعد.</td></tr><?php endif; ?>
    </tbody></table>
  <?php endif;

// ================================================================== RESULTS
else:
  $f = ['exam_id' => abd_str($_GET, 'exam_id', 80), 'who' => (string)($_GET['who'] ?? ''), 'user_id' => (int)($_GET['user_id'] ?? 0)];
  $list = abd_results_list($f, $page);
  $exams = abd_q('SELECT exam_id, COUNT(*) n, ROUND(AVG(percent),1) avgp FROM abd_exam_results GROUP BY exam_id ORDER BY exam_id')->fetchAll(); ?>
  <h1>📊 نتائج الاختبارات التجريبية</h1>
  <form class="f" method="get"><input type="hidden" name="tab" value="results">
    <label>الاختبار<select name="exam_id"><option value="">الكل</option><?php foreach ($exams as $e): ?><option value="<?=h($e['exam_id'])?>" <?= $f['exam_id'] === $e['exam_id'] ? 'selected' : '' ?>><?=h($e['exam_id'])?> (<?= (int)$e['n'] ?> · متوسط <?=h($e['avgp'])?>%)</option><?php endforeach; ?></select></label>
    <label>المصدر<select name="who"><option value="">الكل</option><option value="users" <?= $f['who'] === 'users' ? 'selected' : '' ?>>متدربون مسجّلون</option><option value="anon" <?= $f['who'] === 'anon' ? 'selected' : '' ?>>زوار (بدون حساب)</option></select></label>
    <?php if ($f['user_id']): ?><input type="hidden" name="user_id" value="<?= $f['user_id'] ?>"><span class="mut">مستخدم #<?= $f['user_id'] ?></span><?php endif; ?>
    <button class="btn">تصفية</button><a class="btn l" href="?tab=results">مسح</a></form>
  <p class="mut"><?= (int)$list['total'] ?> نتيجة</p>
  <table><thead><tr><th>التاريخ</th><th>المتدرب</th><th>الاختبار</th><th>الدرجة</th><th>النسبة</th><th>المدة</th><th>تفصيل المحاور</th></tr></thead><tbody>
  <?php foreach ($list['items'] as $r): ?>
    <tr><td class="mut ltr"><?=h(substr((string)$r['created_at'], 0, 16))?></td>
      <td><?php if ($r['user_id']): ?><a href="?tab=results&user_id=<?= (int)$r['user_id'] ?>"><?=h($r['full_name_ar'] ?: $r['full_name_en'] ?: ('#' . $r['user_id']))?></a><br><span class="mut ltr"><?=h($r['email'])?></span><?php else: ?><span class="mut">زائر</span><?php endif; ?></td>
      <td class="ltr"><?=h($r['exam_id'])?></td><td class="ltr"><?= (int)$r['score'] ?> / <?= (int)$r['total'] ?></td><td><b><?=h($r['percent'])?>%</b></td>
      <td class="ltr mut"><?= $r['duration_sec'] !== null ? h(gmdate($r['duration_sec'] >= 3600 ? 'G:i:s' : 'i:s', (int)$r['duration_sec'])) : '—' ?></td>
      <td><?php if (is_array($r['domain_breakdown'])): ?><details><summary>عرض</summary><table><?php foreach ($r['domain_breakdown'] as $k => $d): ?><tr><td><?=h($k)?></td><td class="ltr"><?= (int)$d['ok'] ?>/<?= (int)$d['n'] ?></td></tr><?php endforeach; ?></table></details><?php else: ?>—<?php endif; ?></td></tr>
  <?php endforeach; if (!$list['items']): ?><tr><td colspan="7" class="mut">لا توجد نتائج بعد.</td></tr><?php endif; ?>
  </tbody></table>
  <?php pager($list['total'], $list['page'], $list['per_page']);
endif;
echo '</div>';
abd_admin_bottom();
