<?php
// Shared storage helpers for lab control. Data lives OUTSIDE the web root.
declare(strict_types=1);
date_default_timezone_set('Asia/Riyadh');

const ABAD_LABS = [
  'linux-real' => ['ar' => 'مختبر لينكس الحقيقي', 'en' => 'Linux Real Lab', 'href' => 'linux-real-lab.html'],
  'rhcsa'      => ['ar' => 'مختبر RHCSA', 'en' => 'RHCSA Lab', 'href' => 'rhcsa-lab.html'],
  'ccna'       => ['ar' => 'مختبر CCNA', 'en' => 'CCNA Lab', 'href' => 'ccna-lab.html'],
  'ine'        => ['ar' => 'مختبر INE / eJPT (Kali)', 'en' => 'INE / eJPT Lab (Kali)', 'href' => 'ine-lab.html'],
  'net'        => ['ar' => 'مختبر الشبكات', 'en' => 'Network Lab', 'href' => 'net-lab.html'],
  'eve'        => ['ar' => 'حجز مختبر EVE-NG', 'en' => 'EVE-NG Lab Booking', 'href' => 'eve-lab-booking.html'],
];

function abad_data_dir(): string {
  static $dir = null;
  if ($dir !== null) return $dir;
  $__pos = strpos(__DIR__, '/public_html');
  $__root = $__pos !== false ? substr(__DIR__, 0, $__pos) : dirname(__DIR__, 2);
  $cands = [$__root . '/private_html/abad-data', rtrim(sys_get_temp_dir(), '/') . '/abad-data'];
  foreach ($cands as $d) {
    if (!is_dir($d)) @mkdir($d, 0700, true);
    if (is_dir($d) && is_writable($d)) {
      if (!file_exists($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "Require all denied\n");
      return $dir = $d;
    }
  }
  http_response_code(500); exit('storage unavailable');
}

/** Run $fn(array &$data) under an exclusive lock; returns $data. */
function abad_labs_tx(?callable $fn = null): array {
  $f = abad_data_dir() . '/labs.json';
  $h = fopen($f, 'c+');
  if (!$h) { http_response_code(500); exit('storage error'); }
  flock($h, $fn ? LOCK_EX : LOCK_SH);
  $raw = stream_get_contents($h);
  $data = json_decode($raw ?: '{}', true);
  if (!is_array($data)) $data = [];
  $data += ['labs' => [], 'rl' => []];
  foreach (ABAD_LABS as $id => $_) {
    $data['labs'][$id] = ($data['labs'][$id] ?? []) + ['visible' => true, 'opens' => 0, 'last' => null];
  }
  if ($fn) {
    $fn($data);
    ftruncate($h, 0); rewind($h);
    fwrite($h, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($h);
  }
  flock($h, LOCK_UN); fclose($h);
  return $data;
}

function abad_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  session_name('abadadm');
  session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
  session_start();
}
function abad_is_admin(): bool { abad_session(); return !empty($_SESSION['abad_admin']); }
function abad_csrf(): string { abad_session(); return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function abad_pw_file(): string { return abad_data_dir() . '/admin.json'; }
