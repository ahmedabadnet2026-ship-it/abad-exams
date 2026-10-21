<?php
// EVE lab gate: seat assignment and signed seat tokens. Access rides on the platform session (single login) — no extra code.
// Secrets live OUTSIDE the web root: …/private_html/abad-data/lab.php (or abad-config/lab.php) returning an array, e.g.
//   <?php return ['LAB_HMAC_SECRET'=>'<64 hex>'];
// Environment variables with the same names override the file. Never commit real values.
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }
require_once __DIR__ . '/_db.php';

const ABD_LAB_SEATS = 20;          // total seats shown (eve1..eve20); only ENABLED seats can be assigned or opened

/* Seat pool. 1-12: first ESXi host (192.168.200.201-212). 13-20: second ESXi host (192.168.200.213-220), being built.
   ENABLE A SEAT: set 'enabled' => true below (code), OR without a code change add it to the private lab.php config:
     'EVE_SEATS_ENABLED' => [13, 14]      // extra seats switched on (adds to the ones enabled here)
     'EVE_SEATS_DISABLED' => [7]          // optional: take a seat out of service temporarily
   A disabled seat shows as «قيد التجهيز» on the lab screen, is not counted as available, cannot be assigned
   (admin, API, services grants) and cannot be opened — any existing assignment/entitlement to it is ignored. */
const ABD_LAB_SEAT_CFG = [
  1  => ['ip' => '192.168.200.201', 'esxi' => 1, 'enabled' => true],
  2  => ['ip' => '192.168.200.202', 'esxi' => 1, 'enabled' => true],
  3  => ['ip' => '192.168.200.203', 'esxi' => 1, 'enabled' => true],
  4  => ['ip' => '192.168.200.204', 'esxi' => 1, 'enabled' => true],
  5  => ['ip' => '192.168.200.205', 'esxi' => 1, 'enabled' => true],
  6  => ['ip' => '192.168.200.206', 'esxi' => 1, 'enabled' => true],
  7  => ['ip' => '192.168.200.207', 'esxi' => 1, 'enabled' => true],
  8  => ['ip' => '192.168.200.208', 'esxi' => 1, 'enabled' => true],
  9  => ['ip' => '192.168.200.209', 'esxi' => 1, 'enabled' => true],
  10 => ['ip' => '192.168.200.210', 'esxi' => 1, 'enabled' => true],
  11 => ['ip' => '192.168.200.211', 'esxi' => 1, 'enabled' => true],
  12 => ['ip' => '192.168.200.212', 'esxi' => 1, 'enabled' => true],
  13 => ['ip' => '192.168.200.213', 'esxi' => 2, 'enabled' => false],
  14 => ['ip' => '192.168.200.214', 'esxi' => 2, 'enabled' => false],
  15 => ['ip' => '192.168.200.215', 'esxi' => 2, 'enabled' => false],
  16 => ['ip' => '192.168.200.216', 'esxi' => 2, 'enabled' => false],
  17 => ['ip' => '192.168.200.217', 'esxi' => 2, 'enabled' => false],
  18 => ['ip' => '192.168.200.218', 'esxi' => 2, 'enabled' => false],
  19 => ['ip' => '192.168.200.219', 'esxi' => 2, 'enabled' => false],
  20 => ['ip' => '192.168.200.220', 'esxi' => 2, 'enabled' => false],
];
const ABD_LAB_TOKEN_TTL = 60;      // signed redirect token valid 60 s

function abd_lab_cfg(string $k, $def = null) {
  static $cfg = null;
  if ($cfg === null) {
    $cfg = [];
    $pos = strpos(__DIR__, '/public_html');
    $root = $pos !== false ? substr(__DIR__, 0, $pos) : dirname(__DIR__, 2);
    foreach ([$root . '/private_html/abad-data/lab.php', $root . '/private_html/abad-config/lab.php'] as $f)
      if (is_file($f) && is_readable($f)) { $c = require $f; if (is_array($c)) { $cfg = $c; break; } }
  }
  $e = getenv($k);
  if ($e !== false && $e !== '') return $e;
  return $cfg[$k] ?? $def;
}
function abd_lab_seat_valid(int $seat): bool { return $seat >= 1 && $seat <= ABD_LAB_SEATS && isset(ABD_LAB_SEAT_CFG[$seat]); }
/** Is this seat switched on (built and in service)? Code flag, overridden by private config EVE_SEATS_ENABLED / EVE_SEATS_DISABLED. */
function abd_lab_seat_enabled(int $seat): bool {
  if (!abd_lab_seat_valid($seat)) return false;
  $list = function (string $k): array { $v = abd_lab_cfg($k, []); if (is_string($v)) $v = preg_split('/[\s,]+/', $v, -1, PREG_SPLIT_NO_EMPTY);
    return is_array($v) ? array_map('intval', $v) : []; };
  if (in_array($seat, $list('EVE_SEATS_DISABLED'), true)) return false;
  return !empty(ABD_LAB_SEAT_CFG[$seat]['enabled']) || in_array($seat, $list('EVE_SEATS_ENABLED'), true);
}
/** Enabled seat numbers, ascending. */
function abd_lab_enabled_seats(): array { return array_values(array_filter(range(1, ABD_LAB_SEATS), 'abd_lab_seat_enabled')); }
/** Public seat list for the lab screen: [{seat, host, enabled, status: ready|prep}] — no IPs to the browser. */
function abd_lab_seat_list(): array {
  $o = []; for ($i = 1; $i <= ABD_LAB_SEATS; $i++) { $on = abd_lab_seat_enabled($i);
    $o[] = ['seat' => $i, 'host' => 'eve' . $i . '.abad-labs.com', 'enabled' => $on, 'status' => $on ? 'ready' : 'prep']; }
  return $o;
}
function abd_lab_seat_url(int $seat): string { return 'https://eve' . $seat . '.abad-labs.com/'; }

/** Current valid seat assignment for a user, or null. */
function abd_lab_seat_of(int $uid): ?int {
  $s = abd_q('SELECT seat FROM abd_lab_seats WHERE user_id = ?
              AND (valid_from IS NULL OR valid_from <= NOW()) AND (valid_to IS NULL OR valid_to >= NOW())', [$uid])->fetchColumn();
  if ($s !== false && abd_lab_seat_enabled((int)$s)) return (int)$s;
  // unified services ledger (migration 005): kind 'lab', ref_id = seat number
  try { $s = abd_q("SELECT ref_id FROM abd_entitlements WHERE user_id = ? AND kind = 'lab' AND revoked_at IS NULL ORDER BY id DESC LIMIT 1", [$uid])->fetchColumn(); }
  catch (PDOException $e) { if (($e->errorInfo[1] ?? 0) !== 1146) throw $e; $s = false; }
  return ($s === false || !abd_lab_seat_enabled((int)$s)) ? null : (int)$s;
}

/** All seats the user is currently entitled to (abd_lab_seats ∪ abd_entitlements kind=lab), ascending. */
function abd_lab_seats_of(int $uid): array {
  $s = array_map('intval', abd_q('SELECT seat FROM abd_lab_seats WHERE user_id = ?
              AND (valid_from IS NULL OR valid_from <= NOW()) AND (valid_to IS NULL OR valid_to >= NOW())', [$uid])->fetchAll(PDO::FETCH_COLUMN));
  try { $s = array_merge($s, array_map('intval', abd_q("SELECT ref_id FROM abd_entitlements WHERE user_id = ? AND kind = 'lab' AND revoked_at IS NULL", [$uid])->fetchAll(PDO::FETCH_COLUMN))); }
  catch (PDOException $e) { if (($e->errorInfo[1] ?? 0) !== 1146) throw $e; }
  $s = array_values(array_unique(array_filter($s, fn($n) => abd_lab_seat_enabled($n))));
  sort($s);
  return $s;
}
/** May this user open this exact seat? Staff/admin: any seat; trainee: only seats they are entitled to. */
function abd_lab_can_open(int $uid, bool $staff, int $seat): bool {
  return abd_lab_seat_enabled($seat) && ($staff || in_array($seat, abd_lab_seats_of($uid), true));
}

// ---------------------------------------------------------------- signed tokens (shared secret with the gate on ubantu-server)
function abd_b64u(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function abd_lab_token(int $seat, int $uid, bool $staff): string {
  $key = (string)abd_lab_cfg('LAB_HMAC_SECRET', '');
  if (strlen($key) < 32) throw new RuntimeException('lab secret missing');
  $p = abd_b64u(json_encode(['v' => 1, 's' => $seat, 'u' => $uid, 'r' => $staff ? 'staff' : 'trainee',
    'e' => time() + ABD_LAB_TOKEN_TTL, 'n' => bin2hex(random_bytes(12))], JSON_UNESCAPED_SLASHES));
  return $p . '.' . abd_b64u(hash_hmac('sha256', $p, $key, true));
}
