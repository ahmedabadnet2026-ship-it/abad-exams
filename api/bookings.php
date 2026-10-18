<?php
// Bookings API
//   POST  (public)  create a booking from booking.html
//   GET   (admin)   list: ?status=&q=&from=&to=&page=
//   PATCH (admin)   {id, status, admin_note?}  (header X-CSRF-Token)
declare(strict_types=1);
require_once __DIR__ . '/_db.php';
require_once __DIR__ . '/_crm.php';

const ABD_BOOKING_STATUSES = ['new', 'contacted', 'booked', 'cancelled'];

function abd_booking_ref(): string {
  return 'AB-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

/** Validate & normalise a booking payload. Returns [data, invalidFields]. */
function abd_booking_validate(array $b): array {
  $d = [
    'name_en' => preg_replace('/\s+/', ' ', abd_str($b, 'name')) ?? '',
    'phone' => abd_phone(abd_str($b, 'mobile', 32)),
    'national_id' => abd_nid(abd_str($b, 'nid', 16)),
    'email' => abd_email(abd_str($b, 'email')),
    'vendor' => abd_str($b, 'vendor'),
    'cert' => abd_str($b, 'cert', 255),
    'exam_type' => abd_str($b, 'etype', 120),
    'preferred_date' => abd_date(abd_str($b, 'date', 10)),
    'preferred_time' => abd_str($b, 'time', 40),
    'payment_method' => abd_str($b, 'payment_method', 40),
    'notes' => abd_str($b, 'notes', 2000),
    'lang' => abd_str($b, 'lang', 2) === 'en' ? 'en' : 'ar',
  ];
  $bad = [];
  if (!preg_match("/^[A-Za-z][A-Za-z' \\-]*$/", $d['name_en']) || count(preg_split('/\s+/', $d['name_en'])) < 4) $bad[] = 'name';
  foreach (['phone' => 'mobile', 'national_id' => 'nid', 'email' => 'email'] as $k => $f) if (!$d[$k]) $bad[] = $f;
  if ($d['vendor'] === '') $bad[] = 'vendor';
  if ($d['cert'] === '') $bad[] = 'cert';
  if (!$d['preferred_date'] || $d['preferred_date'] < date('Y-m-d', time() - 86400) || $d['preferred_date'] > date('Y-m-d', time() + 86400 * 730)) $bad[] = 'date';
  return [$d, $bad];
}

/** Admin list query with filters (shared with admin/index.php). */
function abd_bookings_list(array $f, int $page = 1, int $per = 50): array {
  $w = []; $p = [];
  if (in_array($f['status'] ?? '', ABD_BOOKING_STATUSES, true)) { $w[] = 'status = ?'; $p[] = $f['status']; }
  if (($q = trim((string)($f['q'] ?? ''))) !== '') {
    $w[] = '(name_en LIKE ? OR email LIKE ? OR phone LIKE ? OR national_id LIKE ? OR ref LIKE ? OR cert LIKE ? OR vendor LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($p, $like, $like, $like, $like, $like, $like, $like);
  }
  if ($v = abd_date((string)($f['from'] ?? ''))) { $w[] = 'created_at >= ?'; $p[] = $v . ' 00:00:00'; }
  if ($v = abd_date((string)($f['to'] ?? ''))) { $w[] = 'created_at <= ?'; $p[] = $v . ' 23:59:59'; }
  if (($v = trim((string)($f['vendor'] ?? ''))) !== '') { $w[] = 'vendor = ?'; $p[] = $v; }
  $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
  $total = (int)abd_q("SELECT COUNT(*) FROM abd_bookings $where", $p)->fetchColumn();
  $page = max(1, $page); $off = ($page - 1) * $per;
  $rows = abd_q("SELECT id, ref, user_id, vendor, cert, exam_type, name_en, national_id, phone, email, preferred_date, preferred_time,
                 payment_method, notes, invoice_path, status, admin_note, lang, created_at, updated_at
                 FROM abd_bookings $where ORDER BY created_at DESC, id DESC LIMIT $per OFFSET $off", $p)->fetchAll();
  return ['total' => $total, 'page' => $page, 'per_page' => $per, 'items' => $rows];
}

function abd_booking_set_status(int $id, string $status, ?string $note): bool {
  if (!in_array($status, ABD_BOOKING_STATUSES, true)) return false;
  if ($note === null) $st = abd_q('UPDATE abd_bookings SET status = ? WHERE id = ?', [$status, $id]);
  else $st = abd_q('UPDATE abd_bookings SET status = ?, admin_note = ? WHERE id = ?', [$status, $note, $id]);
  return (bool)abd_q('SELECT 1 FROM abd_bookings WHERE id = ?', [$id])->fetchColumn();
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') !== 'bookings.php') return; // included as a library

abd_api(function () {
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  if ($m === 'POST') {
    $b = abd_body();
    if (abd_str($b, 'website') !== '') abd_out(['ok' => true, 'ref' => abd_booking_ref()], 201); // honeypot
    if (!abd_rate('booking', abd_ip_hash(), 10, 3600)) abd_err('rate_limited', 429);
    [$d, $bad] = abd_booking_validate($b);
    if ($bad) abd_err('invalid', 422, ['fields' => $bad]);
    $u = abd_csrf_ok() ? abd_user() : null;
    $ref = abd_booking_ref();
    for ($i = 0; $i < 3; $i++) {
      try {
        abd_q('INSERT INTO abd_bookings (ref, user_id, vendor, cert, exam_type, name_en, national_id, phone, email, preferred_date,
               preferred_time, payment_method, notes, lang, ip_hash, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'new\')',
          [$ref, $u ? (int)$u['id'] : null, $d['vendor'], $d['cert'], $d['exam_type'] ?: null, $d['name_en'], $d['national_id'], $d['phone'],
           $d['email'], $d['preferred_date'], $d['preferred_time'] ?: null, $d['payment_method'] ?: null, $d['notes'] ?: null, $d['lang'], abd_ip_hash()]);
        $bid = (int)abd_db()->lastInsertId();
        abd_crm_from_booking($bid, $d, $ref, $u ? (int)$u['id'] : null);   // auto-create / update CRM lead
        abd_out(['ok' => true, 'ref' => $ref, 'id' => $bid], 201);
      } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) !== 1062) throw $e;
        $ref = abd_booking_ref();
      }
    }
    abd_err('server', 500);
  }
  abd_require_staff();
  if ($m === 'GET') {
    abd_out(['ok' => true] + abd_bookings_list($_GET, (int)($_GET['page'] ?? 1)));
  }
  if ($m === 'PATCH') {
    abd_require_csrf();
    $b = abd_body();
    $id = (int)($b['id'] ?? 0);
    $status = abd_str($b, 'status', 20);
    if (!in_array($status, ABD_BOOKING_STATUSES, true)) abd_err('invalid', 422, ['fields' => ['status']]);
    $note = array_key_exists('admin_note', $b) ? abd_str($b, 'admin_note', 2000) : null;
    if (!abd_booking_set_status($id, $status, $note)) abd_err('not_found', 404);
    abd_out(['ok' => true, 'id' => $id, 'status' => $status]);
  }
  abd_err('method', 405);
});
