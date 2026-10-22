<?php
// Remote labs (shared consoles: Kali noVNC, router/PC web consoles) — addresses live ONLY in the private lab.php:
//   'REMOTE_LABS' => [
//     'ine' => ['access' => 'registered', 'items' => [['name' => 'Kali', 'url' => 'https://…/vnc.html?autoconnect=1&resize=scale']]],
//     'net' => ['access' => 'registered', 'items' => [['name' => 'R1', 'url' => 'https://…'], …]],
//   ]
// access: 'registered' (default) = any signed-in, verified trainee after the email code;
//         'booked'               = only the trainee holding a booking for the CURRENT slot (seat-booking.html; api/_booking_lib.php)
//         'entitled'             = only trainees with a live entitlement kind 'rlab', ref_id = lab id (admin «الخدمات» page or booking hook).
// Staff/admin always pass. Missing/empty config for a lab → 'unavailable' (page shows «المعمل غير متاح حاليًا»).
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }
require_once __DIR__ . '/_lab_lib.php';
require_once __DIR__ . '/_booking_lib.php';

/** Known remote labs (id → [code-slot, Arabic title]). The code slot is stored in abd_email_codes.seat to bind a code to one lab. */
const ABD_RLABS = ['ine' => [1, 'مختبر INE / eJPT (Kali)'], 'net' => [2, 'مختبر الشبكات']];
const ABD_RLAB_WINDOW = 1800;   // 30 min after a correct code, per session + lab

function abd_rlab_cfg(string $lab): ?array {
  if (!isset(ABD_RLABS[$lab])) return null;
  $all = abd_lab_cfg('REMOTE_LABS', null);
  $c = is_array($all) ? ($all[$lab] ?? null) : null;
  if (!is_array($c)) return null;
  $items = [];
  foreach ((array)($c['items'] ?? []) as $it) {
    $name = trim((string)($it['name'] ?? '')); $url = trim((string)($it['url'] ?? ''));
    if ($name !== '' && preg_match('#^https://[^\s"<>]+$#', $url)) $items[] = ['name' => mb_substr($name, 0, 40), 'url' => $url];
  }
  if (!$items) return null;
  $acc = (string)($c['access'] ?? 'registered');
  if (!in_array($acc, ['registered', 'entitled', 'booked'], true)) $acc = 'registered';
  if (abd_book_required($lab)) $acc = 'booked';   // SEAT_BOOKING on for this lab (or access => 'booked')
  return ['access' => $acc, 'items' => $items];
}
/** May this user use this lab at all (before the code)? */
function abd_rlab_allowed(array $u, string $lab, array $cfg): bool {
  if (in_array($u['role'], ['admin', 'staff'], true) || $cfg['access'] === 'registered') return true;
  if ($cfg['access'] === 'booked') return abd_rlab_booked_any($u, $lab);   // any seat of this lab booked for the current slot
  try { return (bool)abd_q("SELECT 1 FROM abd_entitlements WHERE user_id = ? AND kind = 'rlab' AND ref_id = ? AND revoked_at IS NULL LIMIT 1", [(int)$u['id'], $lab])->fetchColumn(); }
  catch (PDOException $e) { error_log('[abad-rlab] entitlement check: ' . $e->getMessage()); return false; }
}
function abd_rlab_window_ok(int $uid, string $lab): bool {
  abd_session(); $w = $_SESSION['rlab_ok'][$lab] ?? null;
  return is_array($w) && (int)($w['u'] ?? 0) === $uid && (int)($w['exp'] ?? 0) >= time();
}
/** Booking held now on any seat of this remote lab. */
function abd_rlab_booked_any(array $u, string $lab): bool {
  try { return abd_book_current((int)$u['id'], $lab) !== null; } catch (PDOException $e) { error_log('[abad-rlab] booking check: ' . $e->getMessage()); return false; }
}
