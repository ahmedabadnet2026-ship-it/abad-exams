<?php
// Seat booking API (seat-booking.html, admin/seat-bookings.php). Times are Asia/Riyadh.
//   GET  ?action=config                       → {enabled:{eve,ine,net}, labs:[{id,title,seats}], hours, slot_minutes, days_ahead, max_per_day, early_minutes}
//   GET  ?action=grid&lab=eve&date=YYYY-MM-DD → {slots:[{start,end,seats:[{seat,state:free|mine|taken|past, id?}]}]}  (no other trainees' names)
//   GET  ?action=mine                         → {items:[upcoming bookings], current:{eve:[seats],ine:[…],net:[…]}}
//   POST {action:"book", lab, seat, start}    (X-CSRF-Token) → {id}
//   POST {action:"cancel", id}                own future booking
// Staff: GET ?action=admin&lab&date → with names; POST {action:"admin_book", lab, seat, start, email, note?} / {action:"admin_cancel", id}
declare(strict_types=1);
require_once __DIR__ . '/_booking_lib.php';

abd_api(function () {
  if (!abd_book_any_on()) abd_err('not_found', 404);   // SEAT_BOOKING off: endpoint not usable at all (staff included)
  $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $u = abd_require_user();
  $uid = (int)$u['id'];
  $staff = in_array($u['role'], ['admin', 'staff'], true);
  $b = $m === 'POST' ? abd_body() : [];
  $action = (string)($_GET['action'] ?? ($b['action'] ?? 'config'));
  $lab = (string)($_GET['lab'] ?? ($b['lab'] ?? 'eve'));
  header('Cache-Control: no-store, max-age=0');
  $c = abd_book_cfg();
  $labs = []; foreach (ABD_BOOK_RESOURCES as $id => $t) if (abd_book_offered($id)) $labs[] = ['id' => $id, 'title' => $t, 'seats' => abd_book_seats($id)];

  if ($m === 'GET' && $action === 'config') {
    abd_out(['ok' => true, 'enabled' => (bool)$labs, 'labs' => $labs, 'hours' => $c['hours'], 'slot_minutes' => $c['slot_minutes'], 'days_ahead' => $c['days_ahead'],
      'max_per_day' => $c['max_per_day'], 'early_minutes' => $c['early_minutes'], 'staff' => $staff, 'now' => date('Y-m-d H:i:s')]);
  }
  if ($m === 'GET' && $action === 'mine') {
    $items = abd_q('SELECT id, resource AS lab, seat, slot_start, slot_end FROM abd_seat_bookings WHERE user_id = ? AND live = 1 AND slot_end > NOW() ORDER BY slot_start LIMIT 50', [$uid])->fetchAll();
    $cur = []; foreach (array_keys(ABD_BOOK_RESOURCES) as $id) $cur[$id] = abd_book_current_seats($uid, $id);
    abd_out(['ok' => true, 'items' => $items, 'current' => $cur]);
  }
  if (!isset(ABD_BOOK_RESOURCES[$lab])) abd_err('lab', 422);
  if (!abd_book_offered($lab) && !$staff) abd_err('booking_off', 409);

  if ($m === 'GET' && ($action === 'grid' || $action === 'admin')) {
    if ($action === 'admin') abd_require_staff();
    $day = abd_date((string)($_GET['date'] ?? '')) ?? date('Y-m-d');
    $names = $action === 'admin';
    $by = abd_book_day($lab, $day, $names); $now = time(); $out = [];
    foreach (abd_book_day_slots($day) as $s) {
      $row = ['start' => $s['start'], 'end' => $s['end'], 'seats' => []];
      $past = strtotime($s['end']) <= $now;
      foreach (abd_book_seats($lab) as $n) {
        $r = $by[$n][$s['start']] ?? null;
        $cell = ['seat' => $n, 'state' => $r ? ((int)$r['user_id'] === $uid ? 'mine' : 'taken') : ($past ? 'past' : 'free')];
        if ($r && ($names || (int)$r['user_id'] === $uid)) $cell['id'] = (int)$r['id'];
        if ($r && $names) $cell += ['name' => $r['full_name_ar'] ?: $r['full_name_en'], 'email' => $r['email'], 'note' => $r['note']];
        $row['seats'][] = $cell;
      }
      $out[] = $row;
    }
    abd_out(['ok' => true, 'lab' => $lab, 'date' => $day, 'slots' => $out]);
  }
  if ($m !== 'POST') abd_err('method', 405);
  abd_require_csrf();
  if ($action === 'book') {
    if (!abd_rate('book-u', (string)$uid, 30, 3600)) abd_err('rate_limited', 429);
    if (!abd_book_may_book($u, $lab)) abd_err('not_entitled', 403);
    [$ok, $e, $id] = abd_book_create($uid, $lab, (int)($b['seat'] ?? 0), abd_str($b, 'start', 19), $uid, false);
    if (!$ok) abd_err($e, $e === 'taken' ? 409 : 422);
    abd_out(['ok' => true, 'id' => $id], 201);
  }
  if ($action === 'cancel') {
    if (!abd_book_cancel((int)($b['id'] ?? 0), $uid, $uid)) abd_err('not_found', 404);
    abd_out(['ok' => true]);
  }
  abd_require_staff();
  if ($action === 'admin_book') {
    $em = abd_email(abd_str($b, 'email'));
    $tid = $em ? (int)abd_q('SELECT id FROM abd_users WHERE email = ? AND is_active = 1', [$em])->fetchColumn() : 0;
    if ($tid <= 0) abd_err('user', 404);
    [$ok, $e, $id] = abd_book_create($tid, $lab, (int)($b['seat'] ?? 0), abd_str($b, 'start', 19), $uid, true, abd_str($b, 'note', 255));
    if (!$ok) abd_err($e, $e === 'taken' ? 409 : 422);
    abd_out(['ok' => true, 'id' => $id], 201);
  }
  if ($action === 'admin_cancel') {
    if (!abd_book_cancel((int)($b['id'] ?? 0), $uid, null)) abd_err('not_found', 404);
    abd_out(['ok' => true]);
  }
  abd_err('action', 400);
});
