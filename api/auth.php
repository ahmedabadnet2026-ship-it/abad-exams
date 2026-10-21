<?php
// Auth API: GET ?action=me | POST {action: register|login|logout|verify_email|resend_email|set_national_id}
// national_id is required at sign-up (10 digits, starts 1/2, Saudi checksum) and is printed on attendance certificates.
// Sign-up: register → 6-digit code emailed (10 min, hashed, 5 tries) → verify_email {code} activates the account and signs in.
// An unverified account is never signed in: login answers {error:"unverified", sent_to} and emails a fresh code.
declare(strict_types=1);
require_once __DIR__ . '/_db.php';
require_once __DIR__ . '/_crm.php';
require_once __DIR__ . '/_mail.php';

/** Email a fresh verification code to a pending account (rate limited). */
function abd_send_verify(array $row): array {
  $uid = (int)$row['id'];
  if (!abd_rate('verify-send-u', (string)$uid, 4, 3600) || !abd_rate('verify-send-ip', abd_ip_hash(), 12, 3600)) return ['ok' => false, 'error' => 'rate_limited'];
  $code = abd_code_issue($uid, 'verify');
  try { abd_verify_mail((string)$row['email'], (string)($row['full_name_ar'] ?: $row['full_name_en']), $code); }
  catch (Throwable $e) { error_log('[abad-auth] mail: ' . $e->getMessage()); return ['ok' => false, 'error' => 'mail_failed']; }
  return ['ok' => true];
}
function abd_pending_row(): ?array {
  $id = (int)($_SESSION['pending_uid'] ?? 0);
  if ($id <= 0) return null;
  $r = abd_q('SELECT id, email, full_name_ar, full_name_en, phone, national_id, email_verified_at, is_active FROM abd_users WHERE id = ?', [$id])->fetch();
  return ($r && (int)$r['is_active'] && $r['email_verified_at'] === null) ? $r : null;
}

abd_api(function () {
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $action = (string)($_GET['action'] ?? (abd_body()['action'] ?? 'me'));

  if ($m === 'GET') {
    if ($action !== 'me') abd_err('action', 400);
    $u = abd_user();
    $p = $u ? null : abd_pending_row();
    abd_out(['ok' => true, 'api' => 1, 'csrf' => abd_csrf(), 'user' => $u ? abd_public_user($u) : null, 'open_all' => (bool)ABAD_OPEN_ALL_FOR_LOGGED_IN]
      + ($p ? ['pending' => ['sent_to' => abd_mask_email((string)$p['email']),
         // prefill for "edit details" — only ever the caller's own still-unverified sign-up (session-bound pending_uid)
         'edit' => ['full_name_ar' => (string)$p['full_name_ar'], 'full_name_en' => (string)$p['full_name_en'], 'email' => (string)$p['email'],
                    'phone' => (string)($p['phone'] ?? ''), 'national_id' => (string)($p['national_id'] ?? '')]]] : []));
  }
  if ($m !== 'POST') abd_err('method', 405);
  abd_require_csrf();
  $b = abd_body();
  $ip = abd_ip_hash();

  if ($action === 'logout') { abd_logout(); abd_out(['ok' => true]); }

  if ($action === 'login') {
    $email = abd_email(abd_str($b, 'email')) ?? '';
    $pw = is_string($b['password'] ?? null) ? $b['password'] : '';
    if (!abd_rate('login-ip', $ip, 30, 900) || !abd_rate('login', $ip . '|' . $email, 6, 900)) abd_err('rate_limited', 429);
    $row = $email !== '' ? abd_q('SELECT id, email, full_name_ar, full_name_en, email_verified_at, password_hash, is_active FROM abd_users WHERE email = ?', [$email])->fetch() : false;
    // constant-ish work even when the user does not exist
    $hash = $row['password_hash'] ?? '$2y$12$iK2U.0RuJYuxaZSAM5XOueqPKj0TjxVfd1FTFeZjnVcMTuhotxISq';
    $ok = password_verify($pw, $hash) && $row && (int)$row['is_active'];
    if (!$ok) { usleep(300000); abd_err('invalid_credentials', 401); }
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT))
      abd_q('UPDATE abd_users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $row['id']]);
    abd_rate_clear('login', $ip . '|' . $email);
    if ($row['email_verified_at'] === null) {   // correct password but email not confirmed yet → (re)send code, no session
      abd_session(); session_regenerate_id(true); $_SESSION['pending_uid'] = (int)$row['id'];
      $r = abd_send_verify($row);
      abd_err('unverified', 403, ['sent_to' => abd_mask_email((string)$row['email']), 'mail' => $r['ok'] ? 'sent' : $r['error'], 'csrf' => abd_csrf()]);
    }
    abd_login_session((int)$row['id']);
    $u = abd_q('SELECT * FROM abd_users WHERE id = ?', [$row['id']])->fetch();
    abd_out(['ok' => true, 'user' => abd_public_user($u), 'csrf' => abd_csrf()]);
  }

  if ($action === 'register') {
    if (!abd_rate('register', $ip, 5, 3600)) abd_err('rate_limited', 429);
    $nameAr = abd_str($b, 'full_name_ar'); $nameEn = abd_str($b, 'full_name_en');
    $email = abd_email(abd_str($b, 'email'));
    $phoneRaw = abd_str($b, 'phone', 32); $phone = $phoneRaw === '' ? null : abd_phone($phoneRaw);
    $nidRaw = abd_str($b, 'national_id', 16); $nid = abd_nid($nidRaw);   // REQUIRED (printed on certificates)
    $pw = is_string($b['password'] ?? null) ? $b['password'] : '';
    $fields = [];
    if ($nameAr === '' && $nameEn === '') $fields[] = 'full_name';
    if ($nameEn !== '' && !preg_match("/^[A-Za-z][A-Za-z' \\-]*$/", $nameEn)) $fields[] = 'full_name_en';
    if (!$email) $fields[] = 'email';
    if ($phoneRaw !== '' && !$phone) $fields[] = 'phone';
    if (!$nid) $fields[] = abd_nid_shape($nidRaw) ? 'national_id_check' : 'national_id';   // shape ok but checksum fails → distinct message
    if (!abd_pw_ok($pw)) $fields[] = 'password';
    if ($fields) abd_err('invalid', 422, ['fields' => $fields]);
    // Edit details: this session already holds an UNVERIFIED sign-up (pending_uid) → correct that row in place instead of
    // creating a second one. Verified accounts are never touched (abd_pending_row() and the UPDATE both require
    // email_verified_at IS NULL). The old code is invalidated and a fresh one goes to the (possibly corrected) email.
    if ($p = abd_pending_row()) {
      $pid = (int)$p['id'];
      if (abd_q('SELECT id FROM abd_users WHERE email = ? AND id <> ?', [$email, $pid])->fetch()) abd_err('email_taken', 409);
      try {
        $st = abd_q('UPDATE abd_users SET full_name_ar = ?, full_name_en = ?, email = ?, phone = ?, national_id = ?, password_hash = ?
                     WHERE id = ? AND email_verified_at IS NULL AND is_active = 1',
          [$nameAr, $nameEn, $email, $phone, $nid, password_hash($pw, PASSWORD_DEFAULT), $pid]);
      } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1062) abd_err('email_taken', 409);
        throw $e;
      }
      if ($st->rowCount() !== 1) abd_err('no_pending', 409);
      abd_q("UPDATE abd_email_codes SET used_at = NOW() WHERE user_id = ? AND purpose = 'verify' AND used_at IS NULL", [$pid]);
      if (mb_strtolower((string)$p['email']) !== $email) abd_crm_from_register($pid, $nameAr !== '' ? $nameAr : $nameEn, $email, $phone);
      session_regenerate_id(true);
      $r = abd_send_verify(['id' => $pid, 'email' => $email, 'full_name_ar' => $nameAr, 'full_name_en' => $nameEn]);
      abd_out(['ok' => true, 'verify' => true, 'edited' => true, 'sent_to' => abd_mask_email($email), 'mail' => $r['ok'] ? 'sent' : $r['error'], 'ttl' => ABD_CODE_TTL, 'csrf' => abd_csrf()]);
    }
    try {
      abd_q("INSERT INTO abd_users (full_name_ar, full_name_en, email, phone, national_id, password_hash, role)
             VALUES (?, ?, ?, ?, ?, ?, 'trainee')", [$nameAr, $nameEn, $email, $phone, $nid, password_hash($pw, PASSWORD_DEFAULT)]);
    } catch (PDOException $e) {
      if (($e->errorInfo[1] ?? 0) === 1062) abd_err('email_taken', 409);
      throw $e;
    }
    $id = (int)abd_db()->lastInsertId();
    abd_crm_from_register($id, $nameAr !== '' ? $nameAr : $nameEn, $email, $phone);   // link CRM lead -> user
    abd_session(); session_regenerate_id(true); $_SESSION['pending_uid'] = $id;   // not signed in until the email code is entered
    $r = abd_send_verify(['id' => $id, 'email' => $email, 'full_name_ar' => $nameAr, 'full_name_en' => $nameEn]);
    abd_out(['ok' => true, 'verify' => true, 'sent_to' => abd_mask_email($email), 'mail' => $r['ok'] ? 'sent' : $r['error'], 'ttl' => ABD_CODE_TTL, 'csrf' => abd_csrf()], 201);
  }

  // Existing accounts created before the ID was required: a signed-in user adds it once (never overwrites a stored ID —
  // corrections go through staff in the admin panel, since the number is printed on issued certificates).
  if ($action === 'set_national_id') {
    $u = abd_require_user();
    if (!abd_rate('set-nid', (string)$u['id'], 10, 3600)) abd_err('rate_limited', 429);
    $raw = abd_str($b, 'national_id', 16); $nid = abd_nid($raw);
    if (!$nid) abd_err('invalid', 422, ['fields' => [abd_nid_shape($raw) ? 'national_id_check' : 'national_id']]);
    if ((string)($u['national_id'] ?? '') !== '') abd_err('national_id_set', 409);
    abd_q("UPDATE abd_users SET national_id = ? WHERE id = ? AND (national_id IS NULL OR national_id = '')", [$nid, $u['id']]);
    $row = abd_q('SELECT * FROM abd_users WHERE id = ?', [$u['id']])->fetch();
    abd_out(['ok' => true, 'user' => abd_public_user($row)]);
  }

  if ($action === 'verify_email') {
    $p = abd_pending_row();
    if (!$p) abd_err('no_pending', 409);
    if (!abd_rate('verify-try', (string)$p['id'], 15, 900)) abd_err('rate_limited', 429);
    $r = abd_code_check((int)$p['id'], 'verify', abd_str($b, 'code', 12));
    if (!$r['ok']) abd_err($r['error'], $r['error'] === 'expired' ? 410 : 401, isset($r['left']) ? ['left' => $r['left']] : []);
    abd_q('UPDATE abd_users SET email_verified_at = NOW() WHERE id = ? AND email_verified_at IS NULL', [$p['id']]);
    unset($_SESSION['pending_uid']);
    abd_login_session((int)$p['id']);
    $u = abd_q('SELECT * FROM abd_users WHERE id = ?', [$p['id']])->fetch();
    abd_out(['ok' => true, 'user' => abd_public_user($u), 'csrf' => abd_csrf()]);
  }

  if ($action === 'resend_email') {
    $p = abd_pending_row();
    if (!$p) abd_err('no_pending', 409);
    $r = abd_send_verify($p);
    if (!$r['ok']) abd_err($r['error'], $r['error'] === 'rate_limited' ? 429 : 502);
    abd_out(['ok' => true, 'sent_to' => abd_mask_email((string)$p['email']), 'ttl' => ABD_CODE_TTL]);
  }
  abd_err('action', 400);
});
