<?php
// Exam results API
//   POST  {exam_id, score, total, domains?, duration_sec?, page?}  — from exam pages when finished.
//         Linked to the logged-in user when a valid X-CSRF-Token is sent; otherwise stored anonymously.
//   GET   own results (logged in).  Admin: ?all=1&exam_id=&user_id=&page=
declare(strict_types=1);
require_once __DIR__ . '/_db.php';

function abd_results_list(array $f, int $page = 1, int $per = 50): array {
  $w = []; $p = [];
  if (($v = trim((string)($f['exam_id'] ?? ''))) !== '') { $w[] = 'r.exam_id = ?'; $p[] = $v; }
  if (($v = (int)($f['user_id'] ?? 0)) > 0) { $w[] = 'r.user_id = ?'; $p[] = $v; }
  if (($f['who'] ?? '') === 'users') $w[] = 'r.user_id IS NOT NULL';
  if (($f['who'] ?? '') === 'anon') $w[] = 'r.user_id IS NULL';
  $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
  $total = (int)abd_q("SELECT COUNT(*) FROM abd_exam_results r $where", $p)->fetchColumn();
  $page = max(1, $page); $off = ($page - 1) * $per;
  $rows = abd_q("SELECT r.id, r.user_id, r.exam_id, r.score, r.total, r.percent, r.domain_breakdown, r.duration_sec, r.page, r.created_at,
                 u.full_name_ar, u.full_name_en, u.email
                 FROM abd_exam_results r LEFT JOIN abd_users u ON u.id = r.user_id $where
                 ORDER BY r.created_at DESC, r.id DESC LIMIT $per OFFSET $off", $p)->fetchAll();
  foreach ($rows as &$r) $r['domain_breakdown'] = $r['domain_breakdown'] !== null ? json_decode((string)$r['domain_breakdown'], true) : null;
  return ['total' => $total, 'page' => $page, 'per_page' => $per, 'items' => $rows];
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') !== 'results.php') return;

abd_api(function () {
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  if ($m === 'POST') {
    if (!abd_rate('results', abd_ip_hash(), 60, 3600)) abd_err('rate_limited', 429);
    $b = abd_body();
    $exam = abd_str($b, 'exam_id', 80);
    $score = filter_var($b['score'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10000]]);
    $total = filter_var($b['total'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,79}$/', $exam) || $score === false || $total === false || $score > $total)
      abd_err('invalid', 422);
    $dom = null;
    if (is_array($b['domains'] ?? null)) {
      $dom = [];
      foreach (array_slice($b['domains'], 0, 40, true) as $k => $v) {
        $k = mb_substr(trim((string)$k), 0, 120);
        if ($k === '' || !is_array($v)) continue;
        $ok = (int)($v['ok'] ?? 0); $n = (int)($v['n'] ?? 0);
        if ($n < 1 || $n > 10000 || $ok < 0 || $ok > $n) continue;
        $dom[$k] = ['ok' => $ok, 'n' => $n];
      }
      if (!$dom) $dom = null;
    }
    $dur = filter_var($b['duration_sec'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 86400]]);
    $page = abd_str($b, 'page', 120);
    $u = abd_csrf_ok() ? abd_user() : null;
    abd_q('INSERT INTO abd_exam_results (user_id, exam_id, score, total, percent, domain_breakdown, duration_sec, page, ip_hash)
           VALUES (?,?,?,?,?,?,?,?,?)',
      [$u ? (int)$u['id'] : null, $exam, $score, $total, round($score / $total * 100, 2),
       $dom ? json_encode($dom, JSON_UNESCAPED_UNICODE) : null, $dur === false ? null : $dur, $page ?: null, abd_ip_hash()]);
    abd_out(['ok' => true, 'id' => (int)abd_db()->lastInsertId(), 'linked' => (bool)$u], 201);
  }
  if ($m !== 'GET') abd_err('method', 405);
  $u = abd_require_user();
  if (!empty($_GET['all'])) {
    abd_require_staff();
    abd_out(['ok' => true] + abd_results_list($_GET, (int)($_GET['page'] ?? 1)));
  }
  $rows = abd_q('SELECT id, exam_id, score, total, percent, domain_breakdown, duration_sec, created_at
                 FROM abd_exam_results WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 200', [(int)$u['id']])->fetchAll();
  foreach ($rows as &$r) $r['domain_breakdown'] = $r['domain_breakdown'] !== null ? json_decode((string)$r['domain_breakdown'], true) : null;
  abd_out(['ok' => true, 'items' => $rows]);
});
