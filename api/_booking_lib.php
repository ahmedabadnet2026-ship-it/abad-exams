<?php
// Seat booking per time slot — EVE seats (eve) and remote labs (ine, net). OFF by default: with no SEAT_BOOKING block in the
// private lab.php (or 'enabled' => false) nothing changes — the gates behave exactly as before (assignment / registered / entitled).
// Private lab.php:
//   'SEAT_BOOKING' => [
//     'enabled'     => true,                     // master switch (default false = current open behaviour)
//     'resources'   => ['eve', 'ine', 'net'],    // which labs REQUIRE a booking for the code / credentials (default: all three)
//     'hours'       => [16, 17, 18, 19, 20, 21], // slot start hours, Asia/Riyadh (default = eve-lab-booking.html: 4–10 pm)
//     'slot_minutes'=> 60,                       // slot length
//     'days_ahead'  => 7,                        // how far ahead trainees may book
//     'max_per_day' => 2,                        // trainee limit per day per lab
//     'early_minutes' => 10,                     // the code may be requested this long before the slot starts
//     'seats'       => ['ine' => 1, 'net' => 1], // bookable "seats" of a remote lab (shared console = 1)
//     'who'         => 'registered',             // who may book: 'registered' | 'entitled' (EVE: lab seat/entitlement; remote: rlab entitlement)
//   ],
// A remote lab can also opt in alone with REMOTE_LABS[lab]['access'] = 'booked'.
// Staff/admin always pass and can book/cancel for anyone (admin/seat-bookings.php).
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }
require_once __DIR__ . '/_lab_lib.php';

const ABD_BOOK_RESOURCES = ['eve' => 'مقاعد معمل EVE', 'ine' => 'مختبر INE / eJPT (Kali)', 'net' => 'مختبر الشبكات'];

function abd_book_cfg(): array {
  $c = abd_lab_cfg('SEAT_BOOKING', null); $c = is_array($c) ? $c : [];
  $hours = array_values(array_unique(array_filter(array_map('intval', (array)($c['hours'] ?? [16, 17, 18, 19, 20, 21])), fn($h) => $h >= 0 && $h <= 23)));
  sort($hours);
  $res = array_values(array_intersect(array_keys(ABD_BOOK_RESOURCES), (array)($c['resources'] ?? array_keys(ABD_BOOK_RESOURCES))));
  return [
    'enabled' => !empty($c['enabled']), 'resources' => $res, 'hours' => $hours ?: [16],
    'slot_minutes' => max(15, min(240, (int)($c['slot_minutes'] ?? 60))), 'days_ahead' => max(1, min(60, (int)($c['days_ahead'] ?? 7))),
    'max_per_day' => max(1, min(12, (int)($c['max_per_day'] ?? 2))), 'early_minutes' => max(0, min(60, (int)($c['early_minutes'] ?? 10))),
    'seats' => ['ine' => max(1, min(20, (int)(($c['seats'] ?? [])['ine'] ?? 1))), 'net' => max(1, min(20, (int)(($c['seats'] ?? [])['net'] ?? 1)))],
    'who' => (($c['who'] ?? 'registered') === 'entitled') ? 'entitled' : 'registered',
  ];
}
/** Does opening this lab need a booking for the current slot? (false = current behaviour) */
function abd_book_required(string $res): bool {
  if (!isset(ABD_BOOK_RESOURCES[$res])) return false;
  $c = abd_book_cfg();
  if ($c['enabled'] && in_array($res, $c['resources'], true)) return true;
  if ($res !== 'eve') { $rl = abd_lab_cfg('REMOTE_LABS', null); return is_array($rl) && (($rl[$res]['access'] ?? '') === 'booked'); }
  return false;
}
/** Is booking offered at all for this lab (page + API)? */
function abd_book_offered(string $res): bool { return abd_book_required($res); }
/** Is booking switched on for ANY lab? false = booking page/API/admin view are 404 and not linked. */
function abd_book_any_on(): bool { foreach (array_keys(ABD_BOOK_RESOURCES) as $r) if (abd_book_required($r)) return true; return false; }
/** Bookable seat numbers of a lab. */
function abd_book_seats(string $res): array {
  if ($res === 'eve') return abd_lab_enabled_seats();
  return isset(ABD_BOOK_RESOURCES[$res]) ? range(1, abd_book_cfg()['seats'][$res]) : [];
}
/** Slot starts (Y-m-d H:i:s, Riyadh) for one day. */
function abd_book_day_slots(string $day): array {
  $c = abd_book_cfg(); $o = [];
  foreach ($c['hours'] as $h) { $s = sprintf('%s %02d:00:00', $day, $h); $o[] = ['start' => $s, 'end' => date('Y-m-d H:i:s', strtotime($s) + 60 * $c['slot_minutes'])]; }
  return $o;
}
function abd_book_is_slot(string $start): bool {
  if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:00:00$/', $start)) return false;
  foreach (abd_book_day_slots(substr($start, 0, 10)) as $s) if ($s['start'] === $start) return true;
  return false;
}
/** Active booking of this user for this lab (+ seat) that covers NOW (from early_minutes before start until the end). */
function abd_book_current(int $uid, string $res, ?int $seat = null): ?array {
  $early = abd_book_cfg()['early_minutes'];
  $sql = 'SELECT id, resource, seat, slot_start, slot_end FROM abd_seat_bookings WHERE user_id = ? AND resource = ? AND live = 1
          AND slot_start <= DATE_ADD(NOW(), INTERVAL ? MINUTE) AND slot_end > NOW()';
  $p = [$uid, $res, $early];
  if ($seat !== null) { $sql .= ' AND seat = ?'; $p[] = $seat; }
  $r = abd_q($sql . ' ORDER BY slot_start LIMIT 1', $p)->fetch();
  return $r ?: null;
}
/** Seats of this lab the user has booked right now. */
function abd_book_current_seats(int $uid, string $res): array {
  $early = abd_book_cfg()['early_minutes'];
  return array_map('intval', abd_q('SELECT seat FROM abd_seat_bookings WHERE user_id = ? AND resource = ? AND live = 1
    AND slot_start <= DATE_ADD(NOW(), INTERVAL ? MINUTE) AND slot_end > NOW() ORDER BY seat', [$uid, $res, $early])->fetchAll(PDO::FETCH_COLUMN));
}
/** Next upcoming booking (for messages). */
function abd_book_next(int $uid, string $res): ?array {
  $r = abd_q('SELECT id, seat, slot_start, slot_end FROM abd_seat_bookings WHERE user_id = ? AND resource = ? AND live = 1 AND slot_end > NOW()
              ORDER BY slot_start LIMIT 1', [$uid, $res])->fetch();
  return $r ?: null;
}
/** Gate: may this user get the code / credentials for this lab+seat now? Staff always; booking not required → true (old behaviour). */
function abd_book_gate_ok(array $u, string $res, int $seat): bool {
  if (in_array($u['role'], ['admin', 'staff'], true) || !abd_book_required($res)) return true;
  try { return abd_book_current((int)$u['id'], $res, $seat) !== null; }
  catch (PDOException $e) { error_log('[abad-book] gate: ' . $e->getMessage()); return false; }   // table missing while required → closed
}
/** May this trainee book this lab at all? */
function abd_book_may_book(array $u, string $res): bool {
  if (in_array($u['role'], ['admin', 'staff'], true) || abd_book_cfg()['who'] === 'registered') return true;
  $uid = (int)$u['id'];
  if ($res === 'eve') return (bool)abd_lab_seats_of($uid);
  return (bool)abd_q("SELECT 1 FROM abd_entitlements WHERE user_id = ? AND kind = 'rlab' AND ref_id = ? AND revoked_at IS NULL LIMIT 1", [$uid, $res])->fetchColumn();
}
/** Create a booking. Returns [ok, error|null, id|null]. $by = acting user id (staff booking for someone else). */
function abd_book_create(int $uid, string $res, int $seat, string $start, int $by, bool $staffAct, string $note = ''): array {
  $c = abd_book_cfg();
  if (!isset(ABD_BOOK_RESOURCES[$res])) return [false, 'lab', null];
  if (!in_array($seat, abd_book_seats($res), true)) return [false, 'seat', null];
  if (!abd_book_is_slot($start)) return [false, 'slot', null];
  $t = strtotime($start); $end = date('Y-m-d H:i:s', $t + 60 * $c['slot_minutes']);
  if (strtotime($end) <= time()) return [false, 'past', null];
  if (!$staffAct) {
    if ($t > strtotime('today') + 86400 * ($c['days_ahead'] + 1)) return [false, 'too_far', null];
    $n = (int)abd_q('SELECT COUNT(*) FROM abd_seat_bookings WHERE user_id = ? AND resource = ? AND live = 1 AND DATE(slot_start) = ?', [$uid, $res, substr($start, 0, 10)])->fetchColumn();
    if ($n >= $c['max_per_day']) return [false, 'day_limit', null];
  }
  // one seat per trainee per slot in the same lab
  if (abd_q('SELECT 1 FROM abd_seat_bookings WHERE user_id = ? AND resource = ? AND slot_start = ? AND live = 1', [$uid, $res, $start])->fetchColumn()) return [false, 'have_slot', null];
  try {
    abd_q('INSERT INTO abd_seat_bookings (resource, seat, user_id, slot_start, slot_end, live, note, created_by) VALUES (?,?,?,?,?,1,?,?)',
      [$res, $seat, $uid, $start, $end, $note !== '' ? mb_substr($note, 0, 255) : null, $by]);
  } catch (PDOException $e) { if (($e->errorInfo[1] ?? 0) === 1062) return [false, 'taken', null]; throw $e; }
  return [true, null, (int)abd_db()->lastInsertId()];
}
function abd_book_cancel(int $id, int $by, ?int $ownerOnly): bool {
  $sql = 'UPDATE abd_seat_bookings SET live = NULL, cancelled_at = NOW(), cancelled_by = ? WHERE id = ? AND live = 1';
  $p = [$by, $id];
  if ($ownerOnly !== null) { $sql .= ' AND user_id = ? AND slot_end > NOW()'; $p[] = $ownerOnly; }
  return abd_q($sql, $p)->rowCount() > 0;
}
/** Bookings of one lab for a day: [seat][slot_start] => row (with names only when $names). */
function abd_book_day(string $res, string $day, bool $names): array {
  $rows = abd_q('SELECT b.id, b.seat, b.user_id, b.slot_start, b.slot_end, b.note, b.created_at' . ($names ? ', u.full_name_ar, u.full_name_en, u.email' : '') . '
    FROM abd_seat_bookings b' . ($names ? ' JOIN abd_users u ON u.id = b.user_id' : '') . '
    WHERE b.resource = ? AND b.live = 1 AND b.slot_start >= ? AND b.slot_start < DATE_ADD(?, INTERVAL 1 DAY) ORDER BY b.seat, b.slot_start', [$res, $day . ' 00:00:00', $day . ' 00:00:00'])->fetchAll();
  $o = []; foreach ($rows as $r) $o[(int)$r['seat']][$r['slot_start']] = $r;
  return $o;
}
