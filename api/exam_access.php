<?php
// Exam entitlement API.
//   GET  ?action=mine        → {logged_in, staff, certs:[…], exams:{exam_id:true|false}}   (anyone; never 401)
// Staff (X-CSRF-Token):
//   GET  ?action=list        → manual/webhook grants
//   POST {action:"grant", email|user_id, cert_id, expires_at?, note?} | {action:"revoke", id}
declare(strict_types=1);
require_once __DIR__ . '/_exam_lib.php';

abd_api(function () {
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $b = $m === 'POST' ? abd_body() : [];
  $action = (string)($_GET['action'] ?? ($b['action'] ?? 'mine'));
  $u = abd_user();
  if ($m === 'GET' && $action === 'mine') {
    $certs = $u ? abd_exam_entitled_certs($u) : [];
    $ex = []; foreach (ABD_EXAMS as $id => $e) $ex[$id] = in_array($e['cert'], $certs, true);
    abd_out(['ok' => true, 'logged_in' => (bool)$u, 'staff' => $u && in_array($u['role'], ['admin', 'staff'], true), 'certs' => $certs, 'exams' => $ex, 'open_all' => (bool)ABAD_OPEN_ALL_FOR_LOGGED_IN]);
  }
  $me = abd_require_staff();
  if ($m === 'GET' && $action === 'list') {
    abd_out(['ok' => true, 'items' => abd_q('SELECT a.id, a.user_id, a.cert_id, a.source, a.source_ref, a.expires_at, a.revoked_at, a.note, a.created_at,
      u.email, u.full_name_ar, u.full_name_en FROM abd_exam_access a JOIN abd_users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 500')->fetchAll()]);
  }
  if ($m !== 'POST') abd_err('method', 405);
  abd_require_csrf();
  if ($action === 'grant') {
    $cert = abd_str($b, 'cert_id', 40);
    if (!isset(abd_exam_certs()[$cert])) abd_err('cert', 422);
    $uid = (int)($b['user_id'] ?? 0);
    if ($uid <= 0 && ($em = abd_email(abd_str($b, 'email')))) $uid = (int)abd_q('SELECT id FROM abd_users WHERE email = ?', [$em])->fetchColumn();
    if ($uid <= 0) abd_err('user', 404);
    $exp = abd_str($b, 'expires_at', 19); $exp = $exp === '' ? null : (($d = date_create($exp)) ? $d->format('Y-m-d H:i:s') : false);
    if ($exp === false) abd_err('expires_at', 422);
    abd_q("INSERT INTO abd_exam_access (user_id, cert_id, source, granted_by, note, expires_at) VALUES (?, ?, 'manual', ?, ?, ?)",
      [$uid, $cert, (int)$me['id'], abd_str($b, 'note', 255) ?: null, $exp]);
    abd_out(['ok' => true, 'id' => (int)abd_db()->lastInsertId()], 201);
  }
  if ($action === 'revoke') {
    abd_q('UPDATE abd_exam_access SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL', [(int)($b['id'] ?? 0)]);
    abd_out(['ok' => true]);
  }
  abd_err('action', 400);
});
