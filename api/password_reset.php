<?php
// Forgot password API (JSON, CSRF via X-CSRF-Token from auth.php?action=me).
//   POST {action:"request", email}          → always {ok:true} (no account enumeration). If an active account exists,
//                                              a single-use link valid 30 min is emailed. Rate limited per IP and per email.
//   POST {action:"check",   token}          → {ok:true} if the link is still usable, else {ok:false,error:"invalid_link"}
//   POST {action:"reset",   token, password}→ sets the password, burns all reset links of the user, bumps session_ver
//                                              (signs out every existing session), confirms the email if it was unverified.
// Only SHA-256(token) is stored. Mail driver: abad-config/mail.php AUTH_MAIL_DRIVER = smtp (default, same as sign-up codes) | brevo | log.
declare(strict_types=1);
require_once __DIR__ . '/_db.php';
require_once __DIR__ . '/_exam_mail.php';

const ABD_PWR_TTL = 1800;   // 30 minutes

function abd_pwr_hash(string $t): string { return hash('sha256', 'abd-pwr|' . $t); }
function abd_pwr_find(string $t): ?array {
  if (!preg_match('/^[a-f0-9]{64}$/', $t)) return null;
  $r = abd_q('SELECT r.id, r.user_id, r.expires_at, u.email, u.is_active FROM abd_password_resets r JOIN abd_users u ON u.id = r.user_id
              WHERE r.token_hash = ? AND r.used_at IS NULL', [abd_pwr_hash($t)])->fetch();
  return ($r && (int)$r['is_active'] && strtotime((string)$r['expires_at']) >= time()) ? $r : null;
}
function abd_pwr_mail(string $to, string $name, string $link): array {
  $m = abd_mail_render(['title' => 'إعادة تعيين كلمة المرور', 'preheader' => 'رابط إعادة تعيين كلمة المرور — صالح 30 دقيقة ولمرة واحدة', 'name' => $name,
    'intro_html' => 'تلقّينا طلبًا لإعادة تعيين كلمة المرور لحسابك في <b>' . ABD_MAIL_PLATFORM . '</b>. اضغط الزر التالي لاختيار كلمة مرور جديدة:',
    'cta_text' => 'تعيين كلمة مرور جديدة', 'cta_url' => $link,
    'note_html' => '⏱ الرابط صالح لمدة <b>30 دقيقة</b> ويُستخدم <b>مرة واحدة فقط</b>. بعد تغيير كلمة المرور سيتم تسجيل خروجك من جميع الأجهزة.',
    'security' => false]);
  $sec = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#fdf3f2" style="background:#fdf3f2;border-radius:10px"><tr><td style="padding:12px 16px;font-family:Cairo,Tahoma,Arial,sans-serif;font-size:13px;line-height:1.8;color:#8a2b22">'
    . '🔒 <b>لم تطلب ذلك؟</b> تجاهل هذه الرسالة وستبقى كلمة مرورك كما هي. لا تشارك هذا الرابط مع أحد — فريق آباد لن يطلبه منك أبدًا.</td></tr></table>';
  $m['html'] = preg_replace('~(</p>)(</td></tr><tr><td bgcolor="#10293b")~', '$1' . $sec . '$2', $m['html'], 1) ?? $m['html'];
  $m['text'] .= "\nلم تطلب ذلك؟ تجاهل هذه الرسالة وستبقى كلمة مرورك كما هي.\n";
  return $m;
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') !== 'password_reset.php') return;

abd_api(function () {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') abd_err('method', 405);
  abd_require_csrf();
  $b = abd_body(); $ip = abd_ip_hash();
  $action = abd_str($b, 'action', 20);

  if ($action === 'request') {
    $t0 = microtime(true);
    if (!abd_rate('pwr-ip', $ip, 10, 3600)) abd_err('rate_limited', 429);   // per-IP limit reveals nothing about any account
    $email = abd_email(abd_str($b, 'email'));
    if (!$email) abd_err('invalid', 422, ['fields' => ['email']]);
    // per-email limit is silent: same {ok:true} answer, just no extra mail
    if (abd_rate('pwr-email', $email, 3, 3600)) {
      $u = abd_q('SELECT id, email, full_name_ar, full_name_en, is_active FROM abd_users WHERE email = ?', [$email])->fetch();
      if ($u && (int)$u['is_active']) {
        $tok = bin2hex(random_bytes(32));
        abd_q('UPDATE abd_password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$u['id']]);   // only the newest link works
        abd_q('INSERT INTO abd_password_resets (user_id, token_hash, expires_at, ip_hash) VALUES (?, ?, ?, ?)',
          [$u['id'], abd_pwr_hash($tok), date('Y-m-d H:i:s', time() + ABD_PWR_TTL), $ip]);
        if (random_int(1, 50) === 1) abd_q('DELETE FROM abd_password_resets WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)]);
        // fixed site URL (never the request Host header → no link poisoning); env override for local tests only
        $site = (($e = getenv('ABAD_SITE_URL')) !== false && $e !== '') ? rtrim($e, '/') . '/' : ABD_MAIL_SITE;
        $link = $site . 'forgot.html?t=' . $tok;
        $m = abd_pwr_mail((string)$u['email'], (string)($u['full_name_ar'] ?: $u['full_name_en']), $link);
        try { abd_mail_deliver('AUTH_MAIL_DRIVER', 'smtp', (string)$u['email'], (string)($u['full_name_ar'] ?: $u['full_name_en']), 'إعادة تعيين كلمة المرور — منصة آباد الرقمية', $m['html'], $m['text']); }
        catch (Throwable $e) { error_log('[abad-pwr] mail failed: ' . $e->getMessage()); }
      }
    }
    $el = microtime(true) - $t0; if ($el < 0.9) usleep((int)((0.9 - $el + random_int(0, 200) / 1000) * 1e6));   // even out timing
    abd_out(['ok' => true, 'ttl_min' => intdiv(ABD_PWR_TTL, 60)]);
  }

  if ($action === 'check' || $action === 'reset') {
    if (!abd_rate('pwr-try', $ip, 30, 900)) abd_err('rate_limited', 429);
    $r = abd_pwr_find(abd_str($b, 'token', 80));
    if (!$r) abd_err('invalid_link', 410);
    if ($action === 'check') abd_out(['ok' => true, 'email' => abd_mask_email((string)$r['email'])]);
    $pw = is_string($b['password'] ?? null) ? $b['password'] : '';
    if ($e = abd_pw_strength($pw, (string)$r['email'])) abd_err($e, 422);
    $pdo = abd_db(); $pdo->beginTransaction();
    try {
      if (abd_q('UPDATE abd_password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [$r['id']])->rowCount() !== 1) { $pdo->rollBack(); abd_err('invalid_link', 410); }
      abd_q('UPDATE abd_password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$r['user_id']]);
      abd_q('UPDATE abd_users SET password_hash = ?, session_ver = session_ver + 1, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?',
        [password_hash($pw, PASSWORD_DEFAULT), $r['user_id']]);
      $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    abd_rate_clear('login', $ip . '|' . strtolower((string)$r['email']));
    if ((int)($_SESSION['uid'] ?? 0) === (int)$r['user_id']) { $_SESSION = ['t' => time(), 'csrf' => bin2hex(random_bytes(32))]; session_regenerate_id(true); }
    abd_out(['ok' => true]);
  }
  abd_err('action', 400);
});
