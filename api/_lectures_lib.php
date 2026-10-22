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

/** Recording link: https on zoom.us (+subdomains), YouTube or Google Drive. Returns ['url','kind','embed'] or null. */
function abd_rec_url(string $u): ?array {
  $u = trim($u);
  if ($u === '' || strlen($u) > 500 || preg_match('/[\s"<>\\\\]/', $u)) return null;
  $p = parse_url($u);
  if (!$p || strtolower($p['scheme'] ?? '') !== 'https' || isset($p['user']) || isset($p['pass']) || isset($p['port'])) return null;
  $host = strtolower(rtrim((string)($p['host'] ?? ''), '.')); $path = (string)($p['path'] ?? ''); parse_str((string)($p['query'] ?? ''), $q);
  if ($host === 'zoom.us' || preg_match('/^([a-z0-9-]+\.)+zoom\.us$/', $host)) return ['url' => $u, 'kind' => 'zoom', 'embed' => null];
  $id = null;
  if ($host === 'youtu.be' && preg_match('~^/([A-Za-z0-9_-]{11})$~', $path, $m)) $id = $m[1];
  elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
    if ($path === '/watch' && is_string($q['v'] ?? null) && preg_match('/^[A-Za-z0-9_-]{11}$/', $q['v'])) $id = $q['v'];
    elseif (preg_match('~^/(embed|live|shorts|v)/([A-Za-z0-9_-]{11})/?$~', $path, $m)) $id = $m[2];
  }
  if ($id) return ['url' => $u, 'kind' => 'youtube', 'embed' => 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0&modestbranding=1'];
  if ($host === 'drive.google.com') {
    $fid = preg_match('~^/file/d/([A-Za-z0-9_-]{10,})(/|$)~', $path, $m) ? $m[1] : ($path === '/open' && is_string($q['id'] ?? null) && preg_match('/^[A-Za-z0-9_-]{10,}$/', $q['id']) ? $q['id'] : null);
    if ($fid) return ['url' => $u, 'kind' => 'drive', 'embed' => 'https://drive.google.com/file/d/' . $fid . '/preview'];
  }
  return null;
}

/** Zoom links, passcodes, recording links and schedules: zoom_staff and admin only. */
function abd_lec_can_links(?array $u): bool { return $u !== null && in_array($u['role'], ['admin', 'zoom_staff'], true); }
/** Lecture titles: admin, or the trainer assigned to the course. zoom_staff: read-only. */
function abd_lec_can_title(?array $u, int $courseId): bool {
  if (!$u) return false; if ($u['role'] === 'admin') return true;
  return $u['role'] === 'trainer' && (bool)abd_q('SELECT 1 FROM abd_courses WHERE id = ? AND trainer_id = ?', [$courseId, (int)$u['id']])->fetchColumn();
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
  $staff = in_array($u['role'], ['admin', 'staff', 'zoom_staff'], true);
  $now = time();
  $courses = $u['role'] === 'trainer'
    ? abd_q('SELECT c.id, c.title_ar, c.title_en, c.start_date, c.end_date, (SELECT t.full_name_ar FROM abd_users t WHERE t.id = c.trainer_id) trainer_name FROM abd_courses c WHERE c.trainer_id = ? ORDER BY c.id', [(int)$u['id']])->fetchAll()
    : ($staff
    ? abd_q("SELECT c.id, c.title_ar, c.title_en, c.start_date, c.end_date, (SELECT t.full_name_ar FROM abd_users t WHERE t.id = c.trainer_id) trainer_name FROM abd_courses c WHERE EXISTS (SELECT 1 FROM abd_course_sessions s WHERE s.course_id = c.id) ORDER BY c.id")->fetchAll()
    : abd_q("SELECT c.id, c.title_ar, c.title_en, c.start_date, c.end_date, (SELECT t.full_name_ar FROM abd_users t WHERE t.id = c.trainer_id) trainer_name FROM abd_enrollments e JOIN abd_courses c ON c.id = e.course_id
             WHERE e.user_id = ? AND e.status IN ('active','completed') ORDER BY c.id", [(int)$u['id']])->fetchAll());
  if ($u['role'] === 'trainer') $staff = true;   // a trainer sees links/recordings of their own courses only (query above)
  foreach ($courses as &$c) {
    $rows = abd_q('SELECT id, session_no, title, starts_at, ends_at, zoom_url, zoom_passcode, rec_url, rec_passcode, rec_until FROM abd_course_sessions WHERE course_id = ? ORDER BY starts_at, id', [(int)$c['id']])->fetchAll();
    $c['sessions'] = array_map(function ($s) use ($now, $staff) {
      $st = strtotime((string)$s['starts_at']); $en = strtotime((string)$s['ends_at']);
      $open = $now >= $st - ABD_LEC_EARLY_MIN * 60 && $now < $en;
      $show = $staff || $open;
      // recording: only after the session ended, and until rec_until (inclusive). Staff see it whenever it exists.
      $r = $s['rec_url'] ? abd_rec_url((string)$s['rec_url']) : null;
      $expired = $s['rec_until'] && date('Y-m-d', $now) > $s['rec_until'];
      $rec = null; $rs = $now < $en ? 'pending' : ($r ? ($expired ? 'expired' : 'available') : 'soon');
      if ($r && ($staff || $rs === 'available')) $rec = ['kind' => $r['kind'], 'url' => $r['kind'] === 'zoom' ? $r['url'] : null, 'embed' => $r['embed'],
        'passcode' => $s['rec_passcode'], 'until' => $s['rec_until']];
      return ['id' => (int)$s['id'], 'rec_status' => $rs, 'rec' => $rec, 'no' => (int)$s['session_no'], 'title' => $s['title'],
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

/** First $n dates from $from (inclusive) whose weekday is in $days, in day order (max 200, within ~2 years). */
function abd_lec_dates_n(string $from, array $days, int $n): array {
  $out = []; $d = new DateTimeImmutable($from); $n = max(1, min(200, $n));
  for ($i = 0; $i < 800 && count($out) < $n; $i++, $d = $d->modify('+1 day')) if (in_array((int)$d->format('w'), $days, true)) $out[] = $d->format('Y-m-d');
  return $out;
}

/** A course is "held" once any session was started by the trainer or has already begun. Held courses can no longer be edited/deleted from the create card. */
function abd_lec_held(int $cid): int {
  return (int)abd_q('SELECT COUNT(*) FROM abd_course_sessions WHERE course_id = ? AND (started_at IS NOT NULL OR starts_at <= NOW())', [$cid])->fetchColumn();
}

/**
 * Validate the «إنشاء دورة أونلاين» form. Returns [data, null] or [null, error message].
 * data: title, trainer_id, from, to, days, st, et, n (requested count or 0).
 */
function abd_lec_course_input(array $p): array {
  $title = abd_str($p, 'title', 190); $tid = (int)($p['trainer_id'] ?? 0);
  $from = abd_str($p, 'from', 10); $st = abd_lec_time(abd_str($p, 'st', 5)); $dur = (int)($p['dur'] ?? 0);
  $days = array_values(array_unique(array_filter(array_map('intval', (array)($p['days'] ?? [])), fn($d) => $d >= 0 && $d <= 6))); sort($days);
  $by = ($p['by'] ?? 'count') === 'end' ? 'end' : 'count'; $n = (int)($p['count'] ?? 0); $to = abd_str($p, 'to', 10);
  if (mb_strlen($title) < 3) return [null, 'اكتب اسم الدورة (3 أحرف على الأقل).'];
  if (!$tid || !abd_q("SELECT 1 FROM abd_users WHERE id = ? AND role = 'trainer' AND is_active = 1", [$tid])->fetchColumn()) return [null, 'اختر مدرّب الدورة.'];
  if (!abd_date($from)) return [null, 'تاريخ البداية غير صحيح.'];
  if (!$days) return [null, 'اختر يومًا واحدًا على الأقل من أيام الأسبوع.'];
  if (!$st) return [null, 'وقت البداية غير صحيح.'];
  if ($dur < 30 || $dur > 480) return [null, 'مدة المحاضرة بين 30 و480 دقيقة.'];
  $endMin = (int)substr($st, 0, 2) * 60 + (int)substr($st, 3, 2) + $dur;
  if ($endMin >= 24 * 60) return [null, 'يجب أن تنتهي المحاضرة قبل منتصف الليل.'];
  $et = sprintf('%02d:%02d', intdiv($endMin, 60), $endMin % 60);
  if ($by === 'count') {
    if ($n < 1 || $n > 200) return [null, 'عدد المحاضرات بين 1 و200.'];
    $dates = abd_lec_dates_n($from, $days, $n);
  } else {
    if (!abd_date($to) || $to < $from) return [null, 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية.'];
    if ((strtotime($to) - strtotime($from)) > 400 * 86400) return [null, 'المدة طويلة جدًا (الحد سنة واحدة).'];
    $dates = abd_lec_dates($from, $to, $days); $n = 0;
  }
  if (!$dates) return [null, 'لا توجد أيام مطابقة بين التاريخين.'];
  if ($dates[0] . " $st:00" <= date('Y-m-d H:i:s')) return [null, 'أول محاضرة يجب أن تكون في المستقبل.'];
  return [['title' => $title, 'trainer_id' => $tid, 'from' => $dates[0], 'to' => end($dates), 'days' => $days, 'st' => $st, 'et' => $et, 'n' => count($dates)], null];
}

// ── «مواضيع الدورة» (abd_course_topics, migration 016) ──────────────────────────────────────────────
const ABD_TOPICS_MAX = 200;

/** Ordered topics of a course: [['id'=>, 'title'=>], …]. Returns [] if the table does not exist yet (migration not run). */
function abd_topics(int $cid): array {
  try { return abd_q('SELECT id, title FROM abd_course_topics WHERE course_id = ? ORDER BY sort_order, id', [$cid])->fetchAll(); }
  catch (Throwable $e) { return []; }
}

/** Clean one pasted line: strip control chars, leading bullets/numbering ("1.", "١)", "-", "•"), collapse spaces, max 190. */
function abd_topic_clean(string $s): string {
  $s = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $s) ?? '';
  $s = preg_replace('/^\s*(?:[-–—•*·▪●]+|[0-9٠-٩]{1,3}\s*[\.\)\-:،])\s*/u', '', $s) ?? '';
  return mb_substr(trim(preg_replace('/\s+/u', ' ', $s) ?? ''), 0, 190);
}

/** Replace or append topics from a pasted list (one per line). Duplicates (also of existing ones on append) are skipped. Returns count added. */
function abd_topics_paste(int $cid, string $text, bool $replace): int {
  $have = $replace ? [] : array_map(fn($t) => mb_strtolower($t['title']), abd_topics($cid));
  $new = [];
  foreach (preg_split('/\R/u', mb_substr($text, 0, 40000)) ?: [] as $line) {
    $t = abd_topic_clean($line); $k = mb_strtolower($t);
    if ($t === '' || in_array($k, $have, true)) continue;
    $have[] = $k; $new[] = $t;
  }
  $pdo = abd_db(); $pdo->beginTransaction();
  try {
    if ($replace) abd_q('DELETE FROM abd_course_topics WHERE course_id = ?', [$cid]);
    $base = (int)abd_q('SELECT COALESCE(MAX(sort_order), 0) FROM abd_course_topics WHERE course_id = ?', [$cid])->fetchColumn();
    $room = ABD_TOPICS_MAX - (int)abd_q('SELECT COUNT(*) FROM abd_course_topics WHERE course_id = ?', [$cid])->fetchColumn();
    $new = array_slice($new, 0, max(0, $room));
    foreach ($new as $i => $t) abd_q('INSERT INTO abd_course_topics (course_id, sort_order, title) VALUES (?, ?, ?)', [$cid, $base + $i + 1, $t]);
    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
  abd_topics_renumber($cid);
  return count($new);
}

/** Rewrite sort_order as 1..n, optionally in the given id order (ids not in the course are ignored, missing ones keep their relative order). */
function abd_topics_renumber(int $cid, ?array $order = null): void {
  $ids = array_map(fn($t) => (int)$t['id'], abd_topics($cid));
  if ($order !== null) { $order = array_values(array_intersect(array_unique(array_map('intval', $order)), $ids)); $ids = array_merge($order, array_values(array_diff($ids, $order))); }
  foreach ($ids as $i => $id) abd_q('UPDATE abd_course_topics SET sort_order = ? WHERE id = ? AND course_id = ?', [$i + 1, $id, $cid]);
}

/** Move one topic up (-1) or down (+1). */
function abd_topic_move(int $cid, int $id, int $dir): void {
  $ids = array_map(fn($t) => (int)$t['id'], abd_topics($cid)); $i = array_search($id, $ids, true);
  if ($i === false || !isset($ids[$i + $dir])) return;
  [$ids[$i], $ids[$i + $dir]] = [$ids[$i + $dir], $ids[$i]];
  abd_topics_renumber($cid, $ids);
}
