<?php
// ABAD Digital Platform — shared backend library (DB, sessions, CSRF, rate limiting, validation).
// Credentials live OUTSIDE the web root (…/private_html/abad-data/db.php or …/private_html/abad-config/db.php).
declare(strict_types=1);
date_default_timezone_set('Asia/Riyadh');

// TEMPORARY ACCESS SWITCH (single source of truth; the client reads it via auth.php?action=me → open_all).
// true  → every signed-in, email-verified user may open ALL exams (full, not just the free trial) and all course materials,
//         until bookings are linked to accounts. Booking/entitlement gating stays in code untouched.
// false → normal entitlement gating (booking / manual grant / ledger). Logged-out visitors get nothing either way.
// EVE lab seats are NOT affected: a seat is a physical per-trainee machine and stays seat-assigned.
if (!defined('ABAD_OPEN_ALL_FOR_LOGGED_IN')) define('ABAD_OPEN_ALL_FOR_LOGGED_IN', true);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }

/** App root directory on disk (the folder that contains api/ and admin/). */
function abd_app_dir(): string { return dirname(__DIR__); }

/** Locate the private config file: the dir that holds public_html, + /private_html/{abad-data,abad-config}/db.php */
function abd_config_file(): ?string {
  $pos = strpos(__DIR__, '/public_html');
  $root = $pos !== false ? substr(__DIR__, 0, $pos) : dirname(__DIR__, 2);
  $c = [];
  if (($e = getenv('ABAD_DB_CONFIG')) !== false && $e !== '') $c[] = $e;   // local testing only
  $c[] = $root . '/private_html/abad-data/db.php';
  $c[] = $root . '/private_html/abad-config/db.php';
  foreach ($c as $f) if (is_file($f) && is_readable($f)) return $f;
  return null;
}

function abd_db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  $f = abd_config_file();
  $cfg = $f ? require $f : null;
  if (!is_array($cfg) || empty($cfg['user'])) throw new RuntimeException('db config missing');
  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $cfg['host'] ?? 'localhost', $cfg['name'] ?? $cfg['user']);
  if (!empty($cfg['port'])) $dsn .= ';port=' . (int)$cfg['port'];
  $pdo = new PDO($dsn, (string)$cfg['user'], (string)($cfg['pass'] ?? ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_STRINGIFY_FETCHES => false,
  ]);
  $pdo->exec("SET time_zone = '+03:00'");
  return $pdo;
}

/** Prepared query helper. */
function abd_q(string $sql, array $params = []): PDOStatement {
  $st = abd_db()->prepare($sql);
  $st->execute($params);
  return $st;
}

// ---------------------------------------------------------------- request / response
function abd_is_https(): bool {
  return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
    || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
}

/** URL path of the app root, e.g. "/abad-digital/". */
function abd_base_path(): string {
  $s = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
  $d = rtrim(dirname($s), '/');
  if (preg_match('~/(api|admin)(/bin)?$~', $d)) $d = preg_replace('~/(api|admin)(/bin)?$~', '', $d);
  return ($d === '' ? '' : $d) . '/';
}

function abd_json_headers(): void {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, private');
  header('X-Content-Type-Options: nosniff');
  header('X-Robots-Tag: noindex');
  header('Referrer-Policy: same-origin');
}

function abd_out(array $d, int $code = 200): void {
  http_response_code($code);
  echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function abd_err(string $code, int $http = 400, array $extra = []): void { abd_out(['ok' => false, 'error' => $code] + $extra, $http); }

/** Decoded JSON body (or form POST fallback). */
function abd_body(): array {
  static $b = null;
  if ($b !== null) return $b;
  $ct = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
  if (str_contains($ct, 'application/json')) {
    $raw = file_get_contents('php://input', false, null, 0, 200000);
    $j = json_decode((string)$raw, true);
    $b = is_array($j) ? $j : [];
  } else $b = $_POST;
  return $b;
}
function abd_str(array $a, string $k, int $max = 190): string {
  $v = $a[$k] ?? '';
  if (!is_scalar($v)) return '';
  $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$v) ?? '');
  return mb_substr($v, 0, $max);
}

/** Wrap an API endpoint: JSON headers + uniform error handling (never leaks internals). */
function abd_api(callable $fn): void {
  abd_json_headers();
  try { $fn(); }
  catch (Throwable $e) {
    error_log('[abad-api] ' . get_class($e) . ': ' . $e->getMessage());
    abd_err($e instanceof RuntimeException && $e->getMessage() === 'db config missing' ? 'unavailable' : 'server', 500);
  }
}

// ---------------------------------------------------------------- client IP (Cloudflare-aware)
function abd_ip_in(string $ip, string $cidr): bool {
  [$net, $bits] = explode('/', $cidr) + [1 => null];
  $ipb = @inet_pton($ip); $netb = @inet_pton($net);
  if ($ipb === false || $netb === false || strlen($ipb) !== strlen($netb)) return false;
  $bits = $bits === null ? strlen($ipb) * 8 : (int)$bits;
  $bytes = intdiv($bits, 8); $rem = $bits % 8;
  if (substr($ipb, 0, $bytes) !== substr($netb, 0, $bytes)) return false;
  if ($rem === 0) return true;
  $mask = (0xFF << (8 - $rem)) & 0xFF;
  return (ord($ipb[$bytes]) & $mask) === (ord($netb[$bytes]) & $mask);
}
function abd_client_ip(): string {
  $ra = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
  $cf = ['173.245.48.0/20','103.21.244.0/22','103.22.200.0/22','103.31.4.0/22','141.101.64.0/18','108.162.192.0/18',
    '190.93.240.0/20','188.114.96.0/20','197.234.240.0/22','198.41.128.0/17','162.158.0.0/15','104.16.0.0/13',
    '104.24.0.0/14','172.64.0.0/13','131.0.72.0/22','2400:cb00::/32','2606:4700::/32','2803:f800::/32',
    '2405:b500::/32','2405:8100::/32','2a06:98c0::/29','2c0f:f248::/32'];
  $trusted = in_array($ra, ['127.0.0.1', '::1'], true);
  foreach ($cf as $c) if (abd_ip_in($ra, $c)) { $trusted = true; break; }
  if ($trusted) {
    $h = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
    if ($h !== '' && filter_var($h, FILTER_VALIDATE_IP)) return $h;
  }
  return $ra;
}
function abd_ip_hash(): string { return hash('sha256', 'abd|' . abd_client_ip()); }

// ---------------------------------------------------------------- rate limiting (fixed window, DB-backed)
/** Count one hit; returns false when the limit for this window is exceeded. */
function abd_rate(string $bucket, string $key, int $max, int $window): bool {
  $k = hash('sha256', $bucket . '|' . $key);
  $now = time(); $ws = $now - ($now % $window);
  abd_q('INSERT INTO abd_rate_limits (k, hits, window_start) VALUES (?, 1, ?)
         ON DUPLICATE KEY UPDATE hits = IF(window_start = VALUES(window_start), hits + 1, 1), window_start = VALUES(window_start)', [$k, $ws]);
  $hits = (int)abd_q('SELECT hits FROM abd_rate_limits WHERE k = ?', [$k])->fetchColumn();
  if (random_int(1, 200) === 1) abd_q('DELETE FROM abd_rate_limits WHERE window_start < ?', [$now - 86400]);
  return $hits <= $max;
}
function abd_rate_clear(string $bucket, string $key): void {
  abd_q('DELETE FROM abd_rate_limits WHERE k = ?', [hash('sha256', $bucket . '|' . $key)]);
}

// ---------------------------------------------------------------- sessions, CSRF, auth
function abd_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  ini_set('session.use_strict_mode', '1');
  ini_set('session.use_only_cookies', '1');
  ini_set('session.gc_maxlifetime', '28800');
  // Cloudways Varnish strips request cookies on GET unless a cookie name contains "wordpress_" (WP bypass rule),
  // so the session cookie must carry that marker. It is scoped to the app path, so WordPress never receives it.
  session_name('wordpress_abadsid');
  session_set_cookie_params(['lifetime' => 0, 'path' => abd_base_path(), 'httponly' => true,
    'samesite' => 'Lax', 'secure' => abd_is_https()]);
  session_start();
  // idle timeout 8h
  if (isset($_SESSION['t']) && time() - (int)$_SESSION['t'] > 28800) { $_SESSION = []; session_regenerate_id(true); }
  $_SESSION['t'] = time();
}
function abd_csrf(): string { abd_session(); return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function abd_csrf_ok(?string $tok = null): bool {
  abd_session();
  $tok ??= (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? (abd_body()['csrf'] ?? ''));
  return !empty($_SESSION['csrf']) && is_string($tok) && hash_equals($_SESSION['csrf'], $tok);
}
function abd_require_csrf(): void { if (!abd_csrf_ok()) abd_err('csrf', 403); }

/** Current logged-in user row (fresh from DB) or null. */
function abd_user(): ?array {
  static $u = false;
  if ($u !== false) return $u;
  abd_session();
  $id = (int)($_SESSION['uid'] ?? 0);
  if ($id <= 0) return $u = null;
  $row = abd_q('SELECT id, full_name_ar, full_name_en, email, email_verified_at, phone, national_id, marketing_opt_in, role, is_active, created_at
                FROM abd_users WHERE id = ?', [$id])->fetch();
  // unverified accounts (migration 006) are never signed in; they sit in $_SESSION['pending_uid'] until the email code is entered
  if (!$row || !(int)$row['is_active'] || $row['email_verified_at'] === null) { unset($_SESSION['uid']); return $u = null; }
  return $u = $row;
}
function abd_is_admin(): bool { $u = abd_user(); return $u !== null && $u['role'] === 'admin'; }
/** Staff = admin or staff role (admin panel, bookings, CRM). Only admins manage users. */
function abd_is_staff(): bool { $u = abd_user(); return $u !== null && in_array($u['role'], ['admin', 'staff'], true); }
function abd_require_staff(): array { $u = abd_user(); if (!$u) abd_err('auth', 401); if (!in_array($u['role'], ['admin', 'staff'], true)) abd_err('forbidden', 403); return $u; }
function abd_require_user(): array { $u = abd_user(); if (!$u) abd_err('auth', 401); return $u; }
function abd_require_admin(): array { $u = abd_user(); if (!$u) abd_err('auth', 401); if ($u['role'] !== 'admin') abd_err('forbidden', 403); return $u; }

function abd_login_session(int $uid): void {
  abd_session();
  session_regenerate_id(true);
  $_SESSION['uid'] = $uid;
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
  abd_q('UPDATE abd_users SET last_login_at = NOW() WHERE id = ?', [$uid]);
}
function abd_logout(): void {
  abd_session();
  $_SESSION = [];
  $p = session_get_cookie_params();
  setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'httponly' => true, 'samesite' => 'Lax', 'secure' => $p['secure']]);
  session_destroy();
}
function abd_public_user(array $u): array {
  return ['id' => (int)$u['id'], 'full_name_ar' => $u['full_name_ar'], 'full_name_en' => $u['full_name_en'],
    'email' => $u['email'], 'phone' => $u['phone'], 'role' => $u['role'],
    'marketing_opt_in' => !empty($u['marketing_opt_in'])];
}
function abd_admin_exists(): bool {
  return (bool)abd_q("SELECT 1 FROM abd_users WHERE role = 'admin' AND is_active = 1 LIMIT 1")->fetchColumn();
}

// ---------------------------------------------------------------- validation helpers
function abd_latin_digits(string $s): string {
  return strtr($s, ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
}
/** Saudi mobile → +9665XXXXXXXX, else null. */
function abd_phone(string $m): ?string {
  $m = preg_replace('/[\s\-()]/', '', abd_latin_digits($m)) ?? '';
  return preg_match('/^(?:\+?966|00966|0)?(5\d{8})$/', $m, $r) ? '+966' . $r[1] : null;
}
/** Saudi national ID / Iqama (10 digits, starts 1/2, Luhn) → digits, else null. */
function abd_nid(string $s): ?string {
  $s = preg_replace('/\s/', '', abd_latin_digits($s)) ?? '';
  if (!preg_match('/^[12]\d{9}$/', $s)) return null;
  $sum = 0;
  for ($i = 0; $i < 10; $i++) { $d = (int)$s[$i]; if ($i % 2 === 0) { $d *= 2; $d = intdiv($d, 10) + $d % 10; } $sum += $d; }
  return $sum % 10 === 0 ? $s : null;
}
function abd_email(string $e): ?string {
  $e = mb_strtolower(trim($e));
  return (strlen($e) <= 190 && filter_var($e, FILTER_VALIDATE_EMAIL)) ? $e : null;
}
function abd_date(string $d): ?string {
  $dt = DateTime::createFromFormat('!Y-m-d', $d);
  return ($dt && $dt->format('Y-m-d') === $d) ? $d : null;
}
function abd_pw_ok(string $p): bool { $n = mb_strlen($p); return $n >= 8 && $n <= 200; }
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
