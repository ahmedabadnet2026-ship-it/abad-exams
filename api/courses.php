<?php
// Courses API
//   GET            my courses (logged-in trainee)
//   GET ?all=1     admin: all courses + enrollment counts;  GET ?enrollments=<course_id>  admin: enrolled users
//   POST {action}  admin (X-CSRF-Token): create | update | delete | enroll | unenroll | enrollment_status
//   PATCH = update, DELETE = delete (admin, JSON body with id)
declare(strict_types=1);
require_once __DIR__ . '/_db.php';
require_once __DIR__ . '/_crm.php';

/** Validate a course payload. Returns [data, invalidFields]. */
function abd_course_validate(array $b): array {
  $d = [
    'slug' => strtolower(abd_str($b, 'slug', 80)),
    'title_ar' => abd_str($b, 'title_ar'),
    'title_en' => abd_str($b, 'title_en'),
    'description_ar' => abd_str($b, 'description_ar', 4000),
    'description_en' => abd_str($b, 'description_en', 4000),
    'delivery' => abd_str($b, 'delivery', 10) === 'online' ? 'online' : 'onsite',
    'start_date' => abd_str($b, 'start_date', 10),
    'end_date' => abd_str($b, 'end_date', 10),
    'link_url' => abd_str($b, 'link_url', 255),
    'is_active' => !isset($b['is_active']) || in_array((string)$b['is_active'], ['1', 'true', 'on'], true) ? 1 : 0,
  ];
  $bad = [];
  if ($d['slug'] === '') $d['slug'] = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($d['title_en'])) ?? '', '-');
  if ($d['slug'] === '') $d['slug'] = 'c-' . date('ymd') . '-' . bin2hex(random_bytes(2));
  if (!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $d['slug'])) $bad[] = 'slug';
  if ($d['title_ar'] === '') $bad[] = 'title_ar';
  foreach (['start_date', 'end_date'] as $k) {
    if ($d[$k] === '') $d[$k] = null; elseif (!abd_date($d[$k])) $bad[] = $k;
  }
  if ($d['start_date'] && $d['end_date'] && $d['end_date'] < $d['start_date']) $bad[] = 'end_date';
  if ($d['link_url'] === '') $d['link_url'] = null;
  elseif (!preg_match('~^(https://|[a-z0-9][a-z0-9._/-]*\.html(\?[^\s"<>]*)?(#[^\s"<>]*)?$)~i', $d['link_url'])) $bad[] = 'link_url';
  foreach (['title_en', 'description_ar', 'description_en'] as $k) if ($d[$k] === '') $d[$k] = $k === 'title_en' ? '' : null;
  return [$d, $bad];
}

function abd_courses_all(): array {
  return abd_q('SELECT c.*, (SELECT COUNT(*) FROM abd_enrollments e WHERE e.course_id = c.id) AS enrolled
                FROM abd_courses c ORDER BY c.is_active DESC, COALESCE(c.start_date, \'9999-12-31\'), c.id DESC')->fetchAll();
}
function abd_course_enrollments(int $cid): array {
  return abd_q('SELECT e.id, e.user_id, e.status, e.created_at, u.full_name_ar, u.full_name_en, u.email, u.phone
                FROM abd_enrollments e JOIN abd_users u ON u.id = e.user_id WHERE e.course_id = ? ORDER BY e.created_at DESC', [$cid])->fetchAll();
}
function abd_my_courses(int $uid): array {
  return abd_q('SELECT c.id, c.slug, c.title_ar, c.title_en, c.description_ar, c.description_en, c.delivery,
                COALESCE(e.start_date, c.start_date) AS start_date, COALESCE(e.end_date, c.end_date) AS end_date,
                c.link_url, e.status AS enrollment_status, e.created_at AS enrolled_at
                FROM abd_enrollments e JOIN abd_courses c ON c.id = e.course_id
                WHERE e.user_id = ? AND c.is_active = 1 AND e.status <> \'cancelled\'
                ORDER BY COALESCE(c.start_date, \'9999-12-31\'), c.id', [$uid])->fetchAll();
}

/** Optional per-enrollment dates from a payload (empty = inherit course). Returns [start|null, end|null, invalidFields]. */
function abd_enr_dates(array $b): array {
  $bad = []; $o = [];
  foreach (['start_date', 'end_date'] as $k) { $v = abd_str($b, $k, 10); $o[$k] = $v === '' ? null : abd_date($v); if ($v !== '' && !$o[$k]) $bad[] = $k; }
  if ($o['start_date'] && $o['end_date'] && $o['end_date'] < $o['start_date']) $bad[] = 'end_date';
  return [$o['start_date'], $o['end_date'], $bad];
}

/** Admin actions shared by the API and admin/index.php. Returns [ok, errorCodeOrPayload]. */
function abd_course_action(string $action, array $b): array {
  $id = (int)($b['id'] ?? $b['course_id'] ?? 0);
  switch ($action) {
    case 'create':
    case 'update':
      [$d, $bad] = abd_course_validate($b);
      if ($bad) return [false, 'invalid', $bad];
      try {
        if ($action === 'create') {
          abd_q('INSERT INTO abd_courses (slug, title_ar, title_en, description_ar, description_en, delivery, start_date, end_date, link_url, is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?)', array_values($d));
          return [true, ['id' => (int)abd_db()->lastInsertId()]];
        }
        if (!abd_q('SELECT 1 FROM abd_courses WHERE id = ?', [$id])->fetchColumn()) return [false, 'not_found'];
        abd_q('UPDATE abd_courses SET slug=?, title_ar=?, title_en=?, description_ar=?, description_en=?, delivery=?, start_date=?, end_date=?,
               link_url=?, is_active=? WHERE id = ?', array_merge(array_values($d), [$id]));
        return [true, ['id' => $id]];
      } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1062) return [false, 'slug_taken', ['slug']];
        throw $e;
      }
    case 'delete':
      $n = abd_q('DELETE FROM abd_courses WHERE id = ?', [$id])->rowCount();
      return $n ? [true, ['id' => $id]] : [false, 'not_found'];
    case 'enroll':
      if (!abd_q('SELECT 1 FROM abd_courses WHERE id = ?', [$id])->fetchColumn()) return [false, 'not_found'];
      $uid = (int)($b['user_id'] ?? 0);
      if ($uid <= 0 && ($em = abd_email(abd_str($b, 'email')))) $uid = (int)abd_q('SELECT id FROM abd_users WHERE email = ?', [$em])->fetchColumn();
      if ($uid <= 0 || !abd_q('SELECT 1 FROM abd_users WHERE id = ?', [$uid])->fetchColumn()) return [false, 'user_not_found'];
      [$sd, $ed, $bad] = abd_enr_dates($b);
      if ($bad) return [false, 'invalid', $bad];
      abd_q("INSERT INTO abd_enrollments (user_id, course_id, status, start_date, end_date) VALUES (?, ?, 'active', ?, ?)
             ON DUPLICATE KEY UPDATE status = 'active', start_date = COALESCE(VALUES(start_date), start_date), end_date = COALESCE(VALUES(end_date), end_date)",
        [$uid, $id, $sd, $ed]);
      abd_crm_on_enroll($uid, $id);
      return [true, ['course_id' => $id, 'user_id' => $uid]];
    case 'unenroll':
      $n = abd_q('DELETE FROM abd_enrollments WHERE course_id = ? AND user_id = ?', [$id, (int)($b['user_id'] ?? 0)])->rowCount();
      return $n ? [true, ['course_id' => $id]] : [false, 'not_found'];
    case 'enrollment_status':
      $s = abd_str($b, 'status', 20);
      if (!in_array($s, ['active', 'completed', 'cancelled'], true)) return [false, 'invalid', ['status']];
      abd_q('UPDATE abd_enrollments SET status = ? WHERE course_id = ? AND user_id = ?', [$s, $id, (int)($b['user_id'] ?? 0)]);
      return [true, ['course_id' => $id, 'status' => $s]];
  }
  return [false, 'action'];
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') !== 'courses.php') return;

abd_api(function () {
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  if ($m === 'GET') {
    $u = abd_require_user();
    if (!empty($_GET['all'])) { abd_require_staff(); abd_out(['ok' => true, 'items' => abd_courses_all()]); }
    if (!empty($_GET['enrollments'])) { abd_require_staff(); abd_out(['ok' => true, 'items' => abd_course_enrollments((int)$_GET['enrollments'])]); }
    abd_out(['ok' => true, 'items' => abd_my_courses((int)$u['id'])]);
  }
  abd_require_staff();
  abd_require_csrf();
  $b = abd_body();
  $action = $m === 'PATCH' ? 'update' : ($m === 'DELETE' ? 'delete' : ($m === 'POST' ? abd_str($b, 'action', 30) : ''));
  if ($action === '') abd_err('method', 405);
  $r = abd_course_action($action, $b);
  if ($r[0]) abd_out(['ok' => true] + $r[1], $action === 'create' ? 201 : 200);
  $code = $r[1];
  abd_err($code, $code === 'not_found' || $code === 'user_not_found' ? 404 : ($code === 'slug_taken' ? 409 : ($code === 'action' ? 400 : 422)),
    isset($r[2]) ? ['fields' => $r[2]] : []);
});
