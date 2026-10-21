<?php
// Lab auto-grading (تسليم). GET ?action=attempts&lab=… ; POST {action:"submit", seat, lab}.
// The grader (ubantu-server, read-only show/ping) is called with an HMAC-signed body:
//   abad-config/lab.php: 'GRADER_URL' => 'https://grader.abad-labs.com/grade', 'GRADER_HMAC_SECRET' => '<64 hex>'
declare(strict_types=1);
require_once __DIR__ . '/_lab_lib.php';
header('Cache-Control: no-store');
const ABD_GRADE_LABS = ['CCNA/01-Static-Routing.unl', 'CCNA/02-VLANs-Trunking.unl', 'CCNA/03-Inter-VLAN-Routing-ROAS.unl', 'CCNA/04-STP-RSTP.unl', 'CCNA/05-EtherChannel.unl', 'CCNA/06-OSPF-and-Floating-Static.unl', 'CCNA/07-DHCP-Telnet-SSH.unl', 'CCNA/08-NAT-PAT.unl', 'CCNA/10-Extended-ACL.unl', 'CCNA/11-IPv6-Addressing-and-Static.unl', 'CCNA/12-Port-Security.unl', 'CCNA/13-HSRP.unl'];

$u = abd_require_user();
$uid = (int)$u['id'];
$staff = abd_is_staff();
$m = $_SERVER['REQUEST_METHOD'];
$b = $m === 'POST' ? abd_body() : $_GET;
$action = (string)($b['action'] ?? '');
$lab = (string)($b['lab'] ?? '');
if (!in_array($lab, ABD_GRADE_LABS, true)) abd_err('bad_lab', 400);

if ($m === 'GET' && $action === 'attempts') {
  $rows = abd_q('SELECT id, seat, score, max_score, status, created_at FROM abd_lab_attempts WHERE user_id = ? AND lab = ? ORDER BY id DESC LIMIT 20', [$uid, $lab])->fetchAll();
  abd_out(['ok' => true, 'attempts' => $rows]);
}
if ($m !== 'POST' || $action !== 'submit') abd_err('bad_request', 400);
abd_require_csrf();
$seat = (int)($b['seat'] ?? 0);
if (!abd_lab_seat_enabled($seat)) abd_err('bad_seat', 400);
if (!abd_lab_can_open($uid, $staff, $seat)) abd_err('not_your_seat', 403);
// rate limit: 1 submit / 90 s and 20 / day per user
if (!abd_rate('lab_grade_burst', (string)$uid, 1, 90)) abd_err('rate_limited', 429);
if (!abd_rate('lab_grade_day', (string)$uid, 20, 86400)) abd_err('rate_limited_day', 429);

$url = (string)abd_lab_cfg('GRADER_URL', '');
$key = (string)abd_lab_cfg('GRADER_HMAC_SECRET', '');
if ($url === '' || strlen($key) < 32) abd_err('grader_unconfigured', 503);
$attempt = 1 + (int)abd_q('SELECT COUNT(*) FROM abd_lab_attempts WHERE user_id = ? AND lab = ? AND status = ?', [$uid, $lab, 'graded'])->fetchColumn();
$body = json_encode(['seat' => $seat, 'lab' => $lab, 'uid' => $uid, 'attempt' => $attempt, 'ts' => time(), 'nonce' => bin2hex(random_bytes(12))]);
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 150, CURLOPT_CONNECTTIMEOUT => 8,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Abad-Sig: ' . hash_hmac('sha256', $body, $key)]]);
$raw = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
$r = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($r) || $code !== 200 || empty($r['ok'])) {
  $err = is_array($r) ? (string)($r['error'] ?? 'grader') : 'grader_unreachable';
  abd_q('INSERT INTO abd_lab_attempts (user_id, seat, lab, status, results_json) VALUES (?,?,?,?,?)', [$uid, $seat, $lab, 'error', json_encode(['error' => $err, 'http' => $code])]);
  abd_rate_clear('lab_grade_burst', (string)$uid);   // a grader-side failure should not cost the trainee a submit
  abd_err($err, 502);
}
abd_q('INSERT INTO abd_lab_attempts (user_id, seat, lab, score, max_score, status, results_json) VALUES (?,?,?,?,?,?,?)',
  [$uid, $seat, $lab, (int)$r['score'], (int)$r['max'], 'graded', json_encode($r, JSON_UNESCAPED_UNICODE)]);
abd_out(['ok' => true, 'attempt' => $attempt, 'result' => $r]);
