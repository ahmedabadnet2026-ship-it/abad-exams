<?php
// Remote lab gate (ine-lab.html, net-lab.html). Login + CSRF; email code (same system as sign-up: 6 digits, 10 min, 5 tries,
// 60 s resend); a correct code opens a 30-min window for THIS session + lab, then the embed URLs of that lab only are returned (no-store).
//   GET  ?action=status&lab=ine          → {available, access, allowed, unlocked, sent_to?}
//   POST {action:"request", lab}          → emails a code → {sent_to (masked), ttl, cooldown}
//   POST {action:"verify", lab, code}     → {items:[{name,url}]}   (opens the window)
//   POST {action:"urls", lab}             → {items} while the window is open, else error "reverify"
declare(strict_types=1);
require_once __DIR__ . '/_remote_lab_lib.php';
require_once __DIR__ . '/_mail.php';

abd_api(function () {
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $u = abd_require_user();
  $uid = (int)$u['id'];
  $b = $m === 'POST' ? abd_body() : [];
  $action = (string)($_GET['action'] ?? ($b['action'] ?? 'status'));
  $lab = (string)($_GET['lab'] ?? ($b['lab'] ?? ''));
  if (!isset(ABD_RLABS[$lab])) abd_err('lab', 422);
  $cfg = abd_rlab_cfg($lab);
  header('Cache-Control: no-store, max-age=0'); header('Pragma: no-cache');

  if ($m === 'GET' && $action === 'status') {
    if (!$cfg) abd_out(['ok' => true, 'available' => false]);
    abd_out(['ok' => true, 'available' => true, 'access' => $cfg['access'], 'allowed' => abd_rlab_allowed($u, $lab, $cfg),
      'unlocked' => abd_rlab_window_ok($uid, $lab) && abd_rlab_allowed($u, $lab, $cfg), 'email' => abd_mask_email((string)$u['email'])]);
  }
  if ($m !== 'POST') abd_err('method', 405);
  abd_require_csrf();
  if (!$cfg) abd_err('unavailable', 503);
  if (!abd_rlab_allowed($u, $lab, $cfg)) abd_err($cfg['access'] === 'booked' ? 'not_booked' : 'not_entitled', 403,
    $cfg['access'] === 'booked' ? ['next' => abd_book_next($uid, $lab)] : []);
  $slot = ABD_RLABS[$lab][0];

  if ($action === 'request') {
    $last = abd_q("SELECT UNIX_TIMESTAMP(created_at) FROM abd_email_codes WHERE user_id = ? AND purpose = 'rlab' ORDER BY id DESC LIMIT 1", [$uid])->fetchColumn();
    if ($last !== false && ($wait = 60 - (time() - (int)$last)) > 0) abd_err('cooldown', 429, ['retry_after' => $wait]);
    if (!abd_rate('rlab-code-u', (string)$uid, 5, 900) || !abd_rate('rlab-code-ip', abd_ip_hash(), 15, 3600)) abd_err('rate_limited', 429);
    $to = (string)abd_q('SELECT email FROM abd_users WHERE id = ?', [$uid])->fetchColumn();
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) abd_err('no_email', 422);
    if (abd_mail_is_sender($to)) abd_err('email_is_sender', 409);
    $code = abd_code_issue($uid, 'rlab', $slot);
    try { if (!abd_rlab_code_mail($to, (string)($u['full_name_ar'] ?: $u['full_name_en']), ABD_RLABS[$lab][1], $code)) throw new RuntimeException('smtp'); }
    catch (Throwable $e) { error_log('[abad-rlab] mail failed'); abd_err('mail_failed', 502); }
    abd_out(['ok' => true, 'sent_to' => abd_mask_email($to), 'ttl' => ABD_CODE_TTL, 'tries' => ABD_CODE_TRIES, 'cooldown' => 60]);
  }
  if ($action === 'verify') {
    if (!abd_rate('rlab-verify-u', (string)$uid, 30, 900)) abd_err('rate_limited', 429);
    $r = abd_code_check($uid, 'rlab', abd_str($b, 'code', 12), $slot);
    if (!$r['ok']) abd_err($r['error'], $r['error'] === 'expired' ? 410 : 401, isset($r['left']) ? ['left' => $r['left']] : []);
    abd_session(); session_regenerate_id(true);
    $_SESSION['rlab_ok'][$lab] = ['u' => $uid, 'exp' => time() + ABD_RLAB_WINDOW];
    abd_out(['ok' => true, 'items' => $cfg['items'], 'until' => time() + ABD_RLAB_WINDOW, 'csrf' => abd_csrf()]);
  }
  if ($action === 'urls') {
    if (!abd_rlab_window_ok($uid, $lab)) abd_err('reverify', 401);
    abd_out(['ok' => true, 'items' => $cfg['items'], 'until' => (int)$_SESSION['rlab_ok'][$lab]['exp']]);
  }
  abd_err('action', 400);
});
