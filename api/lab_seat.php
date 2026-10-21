<?php
// EVE lab seat gate API.
//   GET  ?action=mine                       logged-in user → {seat|null, my_seats, staff, seats (total), available (enabled), seat_list}
//   GET  ?action=seats                      seat pool for the lab screen → {seats, available, seat_list:[{seat,host,enabled,status:ready|prep}]}
//   POST {action:"request", seat}           (X-CSRF-Token) emails a fresh 6-digit code + the seat's EVE-NG user/password → {sent_to (masked), ttl}
//   POST {action:"open"|"verify", seat, code} assigned seat only (staff: any) → {url: https://eveN.abad-labs.com/?t=<signed 60s token>, eve_user}
//   Single login: the platform session (wordpress_abadsid) is the only login; the emailed code is a per-open confirmation step.
//   The EVE password is only ever sent by email, never returned to the page or logged.
// Staff (X-CSRF-Token):
//   GET  ?action=list                       all assignments
//   POST {action:"assign", seat, user_id|email, valid_from?, valid_to?, note?} / {action:"unassign", user_id}
declare(strict_types=1);
require_once __DIR__ . '/_lab_lib.php';
require_once __DIR__ . '/_mail.php';

abd_api(function () {
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $u = abd_require_user();
  $uid = (int)$u['id'];
  $staff = in_array($u['role'], ['admin', 'staff'], true);
  $b = $m === 'POST' ? abd_body() : [];
  $action = (string)($_GET['action'] ?? ($b['action'] ?? 'mine'));

  if ($m === 'GET' && $action === 'mine') { $mine = abd_lab_seats_of($uid); abd_out(['ok' => true, 'seat' => $mine[0] ?? null, 'my_seats' => $mine, 'staff' => $staff, 'seats' => ABD_LAB_SEATS,
    'available' => count(abd_lab_enabled_seats()), 'seat_list' => abd_lab_seat_list()]); }
  if ($m === 'GET' && $action === 'seats') { abd_out(['ok' => true, 'seats' => ABD_LAB_SEATS, 'available' => count(abd_lab_enabled_seats()), 'seat_list' => abd_lab_seat_list()]); }
  if ($m === 'GET' && $action === 'list') {
    abd_require_staff();
    abd_out(['ok' => true, 'items' => abd_q('SELECT s.seat, s.user_id, s.valid_from, s.valid_to, s.note, u.full_name_ar, u.full_name_en, u.email
      FROM abd_lab_seats s JOIN abd_users u ON u.id = s.user_id ORDER BY s.seat, s.valid_from')->fetchAll()]);
  }
  if ($m !== 'POST') abd_err('method', 405);
  abd_require_csrf();
  $seat = (int)($b['seat'] ?? 0);

  if ($action === 'request') {
    if (!abd_lab_seat_valid($seat)) abd_err('seat', 422);
    if (!abd_lab_seat_enabled($seat)) abd_err('seat_prep', 409);   // «قيد التجهيز»: not assignable/openable yet
    // the CLICKED seat is the one used (code, EVE creds, signed link) — never swapped for another assigned seat
    if (!abd_lab_can_open($uid, $staff, $seat)) abd_err('not_your_seat', 403, ['my_seats' => abd_lab_seats_of($uid)]);
    if (!abd_rate('lab-code-u', (string)$uid, 5, 900) || !abd_rate('lab-code-ip', abd_ip_hash(), 15, 3600)) abd_err('rate_limited', 429);
    // recipient = the session user's registered email, re-read from abd_users (no fallback, never the SMTP sender)
    $to = (string)abd_q('SELECT email FROM abd_users WHERE id = ?', [$uid])->fetchColumn();
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) abd_err('no_email', 422);
    if (abd_mail_is_sender($to)) abd_err('email_is_sender', 409);
    $code = abd_code_issue($uid, 'lab', $seat);
    try { abd_lab_code_mail($to, (string)($u['full_name_ar'] ?: $u['full_name_en']), $seat, $code, abd_eve_seat_cred($seat)); }
    catch (Throwable $e) { error_log('[abad-lab] mail failed'); abd_err('mail_failed', 502); }
    abd_out(['ok' => true, 'seat' => $seat, 'sent_to' => abd_mask_email($to), 'ttl' => ABD_CODE_TTL]);
  }

  if ($action === 'open' || $action === 'verify') {
    if (!abd_lab_seat_valid($seat)) abd_err('seat', 422);
    if (!abd_lab_seat_enabled($seat)) abd_err('seat_prep', 409);   // «قيد التجهيز»: not assignable/openable yet
    // the CLICKED seat is the one used (code, EVE creds, signed link) — never swapped for another assigned seat
    if (!abd_lab_can_open($uid, $staff, $seat)) abd_err('not_your_seat', 403, ['my_seats' => abd_lab_seats_of($uid)]);
    if (!abd_rate('lab-open-u', (string)$uid, 30, 900)) abd_err('rate_limited', 429);
    if (abd_str($b, 'code', 12) === '') abd_err('code_required', 428);
    $url = abd_lab_seat_url($seat) . '?t=' . abd_lab_token($seat, $uid, $staff);   // before consuming the code (config errors must not burn it)
    $r = abd_code_check($uid, 'lab', abd_str($b, 'code', 12), $seat);
    if (!$r['ok']) abd_err($r['error'], $r['error'] === 'expired' ? 410 : 401, isset($r['left']) ? ['left' => $r['left']] : []);
    $cred = abd_eve_seat_cred($seat);
    abd_out(['ok' => true, 'seat' => $seat, 'url' => $url, 'eve_user' => $cred['user'] ?? null]);
  }

  if ($action === 'assign' || $action === 'unassign') {
    $me = abd_require_staff();
    $target = (int)($b['user_id'] ?? 0);
    if ($target <= 0 && ($em = abd_email(abd_str($b, 'email')))) $target = (int)abd_q('SELECT id FROM abd_users WHERE email = ?', [$em])->fetchColumn();
    if ($target <= 0) abd_err('user', 404);
    if ($action === 'unassign') { abd_q('DELETE FROM abd_lab_seats WHERE user_id = ?', [$target]); abd_out(['ok' => true]); }
    if (!abd_lab_seat_valid($seat)) abd_err('seat', 422);
    if (!abd_lab_seat_enabled($seat)) abd_err('seat_prep', 409);   // «قيد التجهيز»: not assignable/openable yet
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
