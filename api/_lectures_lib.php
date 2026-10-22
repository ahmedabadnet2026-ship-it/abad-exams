<?php
// Zoom lecture links — shared helpers (admin/lectures.php + api/lectures.php).
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }
require_once __DIR__ . '/_db.php';

const ABD_LEC_EARLY_MIN = 15;   // join button opens this many minutes before start

/** Only https URLs on zoom.us or a subdomain of it (e.g. us06web.zoom.us). Returns normalized URL or null. */
function abd_zoom_url(string $u): ?string {
  $u = trim($u);
  if ($u === '' || strlen($u) > 500 || preg_match('/[\s"<>\\\\]/', $u)) return null;
  $p = parse_url($u);
  if (!$p || strtolower($p['scheme'] ?? '') !== 'https' || isset($p['user']) || isset($p['pass']) || isset($p['port'])) return null;
  $host = strtolower(rtrim((string)($p['host'] ?? ''), '.'));
  if ($host !== 'zoom.us' && !preg_match('/^([a-z0-9-]+\.)+zoom\.us$/', $host)) return null;
  return $u;
}

function abd_lec_time(string $t): ?string { return preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $t) ? $t : null; }

/** Renumber a course's sessions 1..n by start time. */
function abd_lec_renumber(int $cid): void {
  $ids = abd_q('SELECT id FROM abd_course_sessions WHERE course_id = ? ORDER BY starts_at, id', [$cid])->fetchAll(PDO::FETCH_COLUMN);
  foreach ($ids as $i => $id) abd_q('UPDATE abd_course_sessions SET session_no = ? WHERE id = ? AND session_no <> ?', [$i + 1, $id, $i + 1]);
}

/** Dates (Y-m-d) from $from..$to whose weekday (0=Sun..6=Sat) is in $days. Max 200. */
function abd_lec_dates(string $from, string $to, array $days): array {
  $out = []; $d = new DateTimeImmutable($from); $end = new DateTimeImmutable($to);
  while ($d <= $end && count($out) < 200) { if (in_array((int)$d->format('w'), $days, true)) $out[] = $d->format('Y-m-d'); $d = $d->modify('+1 day'); }
  return $out;
}

/** Courses the user is enrolled in (active/completed) or all courses for staff, with sessions. Zoom URL only inside the join window (staff: always). */
function abd_lec_for_user(array $u): array {
  $staff = in_array($u['role'], ['admin', 'staff'], true);
  $now = time();
  $courses = $staff
    ? abd_q("SELECT c.id, c.title_ar, c.title_en FROM abd_courses c WHERE EXISTS (SELECT 1 FROM abd_course_sessions s WHERE s.course_id = c.id) ORDER BY c.id")->fetchAll()
    : abd_q("SELECT c.id, c.title_ar, c.title_en FROM abd_enrollments e JOIN abd_courses c ON c.id = e.course_id
             WHERE e.user_id = ? AND e.status IN ('active','completed') ORDER BY c.id", [(int)$u['id']])->fetchAll();
  foreach ($courses as &$c) {
    $rows = abd_q('SELECT id, session_no, title, starts_at, ends_at, zoom_url, zoom_passcode FROM abd_course_sessions WHERE course_id = ? ORDER BY starts_at, id', [(int)$c['id']])->fetchAll();
    $c['sessions'] = array_map(function ($s) use ($now, $staff) {
      $st = strtotime((string)$s['starts_at']); $en = strtotime((string)$s['ends_at']);
      $open = $now >= $st - ABD_LEC_EARLY_MIN * 60 && $now < $en;
      $show = $staff || $open;
      return ['id' => (int)$s['id'], 'no' => (int)$s['session_no'], 'title' => $s['title'],
        'start' => $st * 1000, 'end' => $en * 1000, 'has_link' => (bool)$s['zoom_url'],
        'join_url' => $show ? $s['zoom_url'] : null, 'passcode' => $show ? $s['zoom_passcode'] : null];
    }, $rows);
    $c['total'] = count($rows);
  }
  unset($c);
  return array_values(array_filter($courses, fn($c) => $c['total'] > 0));
}

/** Generate sessions for a course. $replace deletes existing sessions first, otherwise adds only dates not already present. Returns count added. */
function abd_lec_generate(int $cid, string $from, string $to, array $days, string $st, string $et, ?int $by, bool $replace): int {
  $pdo = abd_db(); $pdo->beginTransaction();
  try {
    if ($replace) abd_q('DELETE FROM abd_course_sessions WHERE course_id = ?', [$cid]);
    $have = array_flip(abd_q('SELECT DATE(starts_at) FROM abd_course_sessions WHERE course_id = ?', [$cid])->fetchAll(PDO::FETCH_COLUMN));
    $n = 0;
    foreach (abd_lec_dates($from, $to, $days) as $d) {
      if (isset($have[$d])) continue;
      abd_q('INSERT INTO abd_course_sessions (course_id, session_no, starts_at, ends_at, created_by) VALUES (?, 0, ?, ?, ?)', [$cid, "$d $st:00", "$d $et:00", $by]);
      $n++;
    }
    abd_lec_renumber($cid);
    $pdo->commit();
    return $n;
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}
