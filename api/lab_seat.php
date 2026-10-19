<?php
// EVE lab seat gate API.
//   GET  ?action=mine                       logged-in user → {seat|null, staff}
//   POST {action:"request", seat}           email a 6-digit code (assigned seat only; staff: any seat)
//   POST {action:"verify",  seat, code}     → {url: https://eveN.abad-labs.com/?t=<signed 60s token>}
// Staff (X-CSRF-Token):
//   GET  ?action=list                       all assignments
//   POST {action:"assign", seat, user_id|email, valid_from?, valid_to?, note?} / {action:"unassign", user_id}
declare(strict_types=1);
require_once __DIR__ . '/_lab_lib.php';

abd_api(function () {
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $u = abd_require_user();
  $uid = (int)$u['id'];
  $staff = in_array($u['role'], ['admin', 'staff'], true);
  $b = $m === 'POST' ? abd_body() : [];
  $action = (string)($_GET['action'] ?? ($b['action'] ?? 'mine'));

  if ($m === 'GET' && $action === 'mine') abd_out(['ok' => true, 'seat' => abd_lab_seat_of($uid), 'staff' => $staff, 'seats' => ABD_LAB_SEATS]);
  if ($m === 'GET' && $action === 'list') {
    abd_require_staff();
    abd_out(['ok' => true, 'items' => abd_q('SELECT s.seat, s.user_id, s.valid_from, s.valid_to, s.note, u.full_name_ar, u.full_name_en, u.email
      FROM abd_lab_seats s JOIN abd_users u ON u.id = s.user_id ORDER BY s.seat, s.valid_from')->fetchAll()]);
  }
  if ($m !== 'POST') abd_err('method', 405);
  abd_require_csrf();
  $seat = (int)($b['seat'] ?? 0);
  $ip = abd_ip_hash();

  if ($action === 'request') {
    if ($seat < 1 || $seat > ABD_LAB_SEATS) abd_err('seat', 422);
    if (!$staff && abd_lab_seat_of($uid) !== $seat) abd_err('not_your_seat', 403);
    if (!abd_rate('lab-otp-u', (string)$uid, 3, 600) || !abd_rate('lab-otp-ip', $ip, 10, 3600)) abd_err('rate_limited', 429);
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    abd_q('UPDATE abd_lab_otps SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$uid]);   // only newest code is valid
    abd_q('INSERT INTO abd_lab_otps (user_id, seat, code_hash, expires_at, ip_hash) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?)',
      [$uid, $seat, hash_hmac('sha256', $code, (string)abd_lab_cfg('LAB_HMAC_SECRET', 'x') . '|otp|' . $uid), ABD_LAB_OTP_TTL, $ip]);
    if (random_int(1, 50) === 1) abd_q('DELETE FROM abd_lab_otps WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
    try { abd_lab_otp_mail((string)$u['email'], (string)($u['full_name_ar'] ?: $u['full_name_en']), $seat, $code); }
    catch (Throwable $e) { error_log('[abad-lab] mail: ' . $e->getMessage()); abd_err('mail_failed', 502); }
    abd_out(['ok' => true, 'sent_to' => abd_mask_email((string)$u['email']), 'ttl' => ABD_LAB_OTP_TTL]);
  }

  if ($action === 'verify') {
    $code = preg_replace('/\D/', '', abd_latin_digits(abd_str($b, 'code', 12))) ?? '';
    if ($seat < 1 || $seat > ABD_LAB_SEATS || strlen($code) !== 6) abd_err('invalid', 422);
    if (!$staff && abd_lab_seat_of($uid) !== $seat) abd_err('not_your_seat', 403);
    if (!abd_rate('lab-verify', (string)$uid, 15, 900)) abd_err('rate_limited', 429);
    $o = abd_q('SELECT id, code_hash, attempts, expires_at < NOW() AS expired FROM abd_lab_otps
                WHERE user_id = ? AND seat = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1', [$uid, $seat])->fetch();
    if (!$o || (int)$o['expired'] || (int)$o['attempts'] >= ABD_LAB_OTP_TRIES) abd_err('expired', 410);
    $want = hash_hmac('sha256', $code, (string)abd_lab_cfg('LAB_HMAC_SECRET', 'x') . '|otp|' . $uid);
    if (!hash_equals($o['code_hash'], $want)) {
      abd_q('UPDATE abd_lab_otps SET attempts = attempts + 1 WHERE id = ?', [$o['id']]);
      abd_err('wrong_code', 401, ['left' => max(0, ABD_LAB_OTP_TRIES - (int)$o['attempts'] - 1)]);
    }
    $n = abd_q('UPDATE abd_lab_otps SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [$o['id']])->rowCount();
    if ($n !== 1) abd_err('expired', 410);
    abd_out(['ok' => true, 'url' => abd_lab_seat_url($seat) . '?t=' . abd_lab_token($seat, $uid, $staff)]);
  }

  if ($action === 'assign' || $action === 'unassign') {
    $me = abd_require_staff();
    $target = (int)($b['user_id'] ?? 0);
    if ($target <= 0 && ($em = abd_email(abd_str($b, 'email')))) $target = (int)abd_q('SELECT id FROM abd_users WHERE email = ?', [$em])->fetchColumn();
    if ($target <= 0) abd_err('user', 404);
    if ($action === 'unassign') { abd_q('DELETE FROM abd_lab_seats WHERE user_id = ?', [$target]); abd_out(['ok' => true]); }
    if ($seat < 1 || $seat > ABD_LAB_SEATS) abd_err('seat', 422);
    $vf = abd_str($b, 'valid_from', 19); $vt = abd_str($b, 'valid_to', 19);
    $dt = fn(string $s) => $s === '' ? null : (($d = date_create($s)) ? $d->format('Y-m-d H:i:s') : false);
    $vf = $dt($vf); $vt = $dt($vt);
    if ($vf === false || $vt === false || ($vf && $vt && $vt < $vf)) abd_err('dates', 422);
    abd_q('INSERT INTO abd_lab_seats (seat, user_id, valid_from, valid_to, note, created_by) VALUES (?, ?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE seat = VALUES(seat), valid_from = VALUES(valid_from), valid_to = VALUES(valid_to), note = VALUES(note), created_by = VALUES(created_by)',
      [$seat, $target, $vf, $vt, abd_str($b, 'note', 255) ?: null, (int)$me['id']]);
    abd_out(['ok' => true]);
  }
  abd_err('action', 400);
});
