<?php
// EVE lab seat gate API.
//   GET  ?action=mine                       logged-in user → {seat|null, staff}
//   POST {action:"open", seat}              (X-CSRF-Token) assigned seat only (staff: any) → {url: https://eveN.abad-labs.com/?t=<signed 60s token>}
//   No second sign-in: the platform session (wordpress_abadsid) is the credential. The old email-OTP request/verify actions were removed.
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

  if ($action === 'open') {   // single login: the platform session is the only credential
    if ($seat < 1 || $seat > ABD_LAB_SEATS) abd_err('seat', 422);
    if (!$staff && abd_lab_seat_of($uid) !== $seat) abd_err('not_your_seat', 403);
    if (!abd_rate('lab-open-u', (string)$uid, 30, 600)) abd_err('rate_limited', 429);
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
