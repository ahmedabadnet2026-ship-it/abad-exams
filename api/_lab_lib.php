<?php
// EVE lab gate: seat assignment and signed seat tokens. Access rides on the platform session (single login) — no extra code.
// Secrets live OUTSIDE the web root: …/private_html/abad-data/lab.php (or abad-config/lab.php) returning an array, e.g.
//   <?php return ['LAB_HMAC_SECRET'=>'<64 hex>'];
// Environment variables with the same names override the file. Never commit real values.
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }
require_once __DIR__ . '/_db.php';

const ABD_LAB_SEATS = 12;
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
function abd_lab_seat_url(int $seat): string { return 'https://eve' . $seat . '.abad-labs.com/'; }

/** Current valid seat assignment for a user, or null. */
function abd_lab_seat_of(int $uid): ?int {
  $s = abd_q('SELECT seat FROM abd_lab_seats WHERE user_id = ?
              AND (valid_from IS NULL OR valid_from <= NOW()) AND (valid_to IS NULL OR valid_to >= NOW())', [$uid])->fetchColumn();
  if ($s !== false) return (int)$s;
  // unified services ledger (migration 005): kind 'lab', ref_id = seat number
  try { $s = abd_q("SELECT ref_id FROM abd_entitlements WHERE user_id = ? AND kind = 'lab' AND revoked_at IS NULL ORDER BY id DESC LIMIT 1", [$uid])->fetchColumn(); }
  catch (PDOException $e) { if (($e->errorInfo[1] ?? 0) !== 1146) throw $e; $s = false; }
  return ($s === false || (int)$s < 1 || (int)$s > ABD_LAB_SEATS) ? null : (int)$s;
}

/** All seats the user is currently entitled to (abd_lab_seats ∪ abd_entitlements kind=lab), ascending. */
function abd_lab_seats_of(int $uid): array {
  $s = array_map('intval', abd_q('SELECT seat FROM abd_lab_seats WHERE user_id = ?
              AND (valid_from IS NULL OR valid_from <= NOW()) AND (valid_to IS NULL OR valid_to >= NOW())', [$uid])->fetchAll(PDO::FETCH_COLUMN));
  try { $s = array_merge($s, array_map('intval', abd_q("SELECT ref_id FROM abd_entitlements WHERE user_id = ? AND kind = 'lab' AND revoked_at IS NULL", [$uid])->fetchAll(PDO::FETCH_COLUMN))); }
  catch (PDOException $e) { if (($e->errorInfo[1] ?? 0) !== 1146) throw $e; }
  $s = array_values(array_unique(array_filter($s, fn($n) => $n >= 1 && $n <= ABD_LAB_SEATS)));
  sort($s);
  return $s;
}
/** May this user open this exact seat? Staff/admin: any seat; trainee: only seats they are entitled to. */
function abd_lab_can_open(int $uid, bool $staff, int $seat): bool {
  return $seat >= 1 && $seat <= ABD_LAB_SEATS && ($staff || in_array($seat, abd_lab_seats_of($uid), true));
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
