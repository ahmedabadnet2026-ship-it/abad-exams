<?php
// Auth API: GET ?action=me | POST {action: register|login|logout}
declare(strict_types=1);
require_once __DIR__ . '/_db.php';
require_once __DIR__ . '/_crm.php';

abd_api(function () {
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $action = (string)($_GET['action'] ?? (abd_body()['action'] ?? 'me'));

  if ($m === 'GET') {
    if ($action !== 'me') abd_err('action', 400);
    $u = abd_user();
    abd_out(['ok' => true, 'api' => 1, 'csrf' => abd_csrf(), 'user' => $u ? abd_public_user($u) : null]);
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
    $row = $email !== '' ? abd_q('SELECT id, password_hash, is_active FROM abd_users WHERE email = ?', [$email])->fetch() : false;
    // constant-ish work even when the user does not exist
    $hash = $row['password_hash'] ?? '$2y$12$iK2U.0RuJYuxaZSAM5XOueqPKj0TjxVfd1FTFeZjnVcMTuhotxISq';
    $ok = password_verify($pw, $hash) && $row && (int)$row['is_active'];
    if (!$ok) { usleep(300000); abd_err('invalid_credentials', 401); }
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT))
      abd_q('UPDATE abd_users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $row['id']]);
    abd_rate_clear('login', $ip . '|' . $email);
    abd_login_session((int)$row['id']);
    $u = abd_q('SELECT * FROM abd_users WHERE id = ?', [$row['id']])->fetch();
    abd_out(['ok' => true, 'user' => abd_public_user($u), 'csrf' => abd_csrf()]);
  }

  if ($action === 'register') {
    if (!abd_rate('register', $ip, 5, 3600)) abd_err('rate_limited', 429);
    $nameAr = abd_str($b, 'full_name_ar'); $nameEn = abd_str($b, 'full_name_en');
    $email = abd_email(abd_str($b, 'email'));
    $phoneRaw = abd_str($b, 'phone', 32); $phone = $phoneRaw === '' ? null : abd_phone($phoneRaw);
    $nidRaw = abd_str($b, 'national_id', 16); $nid = $nidRaw === '' ? null : abd_nid($nidRaw);
    $pw = is_string($b['password'] ?? null) ? $b['password'] : '';
    $fields = [];
    if ($nameAr === '' && $nameEn === '') $fields[] = 'full_name';
    if ($nameEn !== '' && !preg_match("/^[A-Za-z][A-Za-z' \\-]*$/", $nameEn)) $fields[] = 'full_name_en';
    if (!$email) $fields[] = 'email';
    if ($phoneRaw !== '' && !$phone) $fields[] = 'phone';
    if ($nidRaw !== '' && !$nid) $fields[] = 'national_id';
    if (!abd_pw_ok($pw)) $fields[] = 'password';
    if ($fields) abd_err('invalid', 422, ['fields' => $fields]);
    try {
      abd_q("INSERT INTO abd_users (full_name_ar, full_name_en, email, phone, national_id, password_hash, role)
             VALUES (?, ?, ?, ?, ?, ?, 'trainee')", [$nameAr, $nameEn, $email, $phone, $nid, password_hash($pw, PASSWORD_DEFAULT)]);
    } catch (PDOException $e) {
      if (($e->errorInfo[1] ?? 0) === 1062) abd_err('email_taken', 409);
      throw $e;
    }
    $id = (int)abd_db()->lastInsertId();
    abd_crm_from_register($id, $nameAr !== '' ? $nameAr : $nameEn, $email, $phone);   // link CRM lead -> user
    abd_login_session($id);
    $u = abd_q('SELECT * FROM abd_users WHERE id = ?', [$id])->fetch();
    abd_out(['ok' => true, 'user' => abd_public_user($u), 'csrf' => abd_csrf()], 201);
  }
  abd_err('action', 400);
});
