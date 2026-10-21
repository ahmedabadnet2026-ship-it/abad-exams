<?php
// Shared mailer + one-time email codes (sign-up verification, lab seat confirmation).
// Config: …/private_html/abad-config/mail.php (fallback abad-data/mail.php) returning
//   ['SMTP_HOST'=>'smtp.gmail.com','SMTP_PORT'=>587,'SMTP_SECURE'=>'starttls','SMTP_USER'=>'…@gmail.com','SMTP_PASS'=>'app password',
//    'SMTP_FROM'=>'…@gmail.com','SMTP_FROM_NAME'=>'معهد شبكة آباد للتدريب','CODE_SECRET'=>'<random hex>']
// Environment variables with the same names override (local testing). SMTP_PASS spaces are stripped (Gmail App Password format).
// EVE seat credentials: …/private_html/abad-config/eve-seats.php (fallback abad-data) → [seat => ['user'=>…, 'pass'=>…]]. Never logged.
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }
require_once __DIR__ . '/_db.php';
require_once __DIR__ . '/_mail_tpl.php';

const ABD_CODE_TTL = 600;     // 10 minutes
const ABD_CODE_TRIES = 5;     // wrong attempts per code

function abd_private_file(string $name): ?string {
  $pos = strpos(__DIR__, '/public_html');
  $root = $pos !== false ? substr(__DIR__, 0, $pos) : dirname(__DIR__, 2);
  foreach (['abad-config', 'abad-data'] as $d) { $f = $root . '/private_html/' . $d . '/' . $name; if (is_file($f) && is_readable($f)) return $f; }
  return null;
}
function abd_mail_cfg(string $k, $def = null) {
  static $cfg = null;
  if ($cfg === null) { $cfg = []; if ($f = abd_private_file('mail.php')) { $c = require $f; if (is_array($c)) $cfg = $c; } }
  $e = getenv($k);
  $v = ($e !== false && $e !== '') ? $e : ($cfg[$k] ?? $def);
  if ($k === 'SMTP_PASS' && is_string($v)) $v = preg_replace('/\s+/', '', $v);
  return $v;
}
/** EVE-NG credentials for a seat, or null. */
function abd_eve_seat_cred(int $seat): ?array {
  static $all = null;
  if ($all === null) { $all = []; if ($f = abd_private_file('eve-seats.php')) { $c = require $f; if (is_array($c)) $all = $c; } }
  $c = $all[$seat] ?? null;
  return (is_array($c) && isset($c['user'], $c['pass'])) ? ['user' => (string)$c['user'], 'pass' => (string)$c['pass']] : null;
}
function abd_mask_email(string $e): string {
  [$u, $d] = explode('@', $e, 2) + [1 => ''];
  return mb_substr($u, 0, 2) . str_repeat('•', max(1, mb_strlen($u) - 2)) . '@' . $d;
}

// ---------------------------------------------------------------- minimal SMTP client (STARTTLS 587 / implicit TLS 465)
function abd_smtp_send(string $to, string $subject, string $html, string $text): bool {
  $host = (string)abd_mail_cfg('SMTP_HOST', 'smtp.gmail.com'); $port = (int)abd_mail_cfg('SMTP_PORT', 587);
  $user = (string)abd_mail_cfg('SMTP_USER', ''); $pass = (string)abd_mail_cfg('SMTP_PASS', '');
  $from = (string)abd_mail_cfg('SMTP_FROM', $user); $fromName = (string)abd_mail_cfg('SMTP_FROM_NAME', 'معهد شبكة آباد للتدريب');
  $sec = strtolower((string)abd_mail_cfg('SMTP_SECURE', $port === 465 ? 'ssl' : 'starttls'));
  if ($sec === 'none' && !in_array($host, ['127.0.0.1', 'localhost'], true)) $sec = 'starttls';   // plaintext only for a local test sink
  if ($host === '' || $user === '' || $pass === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('smtp config missing');
  if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to)) throw new RuntimeException('smtp bad recipient');
  $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
  $fp = @stream_socket_client(($sec === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
  if (!$fp) throw new RuntimeException('smtp connect failed');
  stream_set_timeout($fp, 15);
  $read = function () use ($fp): array { $all = ''; while (($l = fgets($fp, 2048)) !== false) { $all .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return [(int)substr($all, 0, 3), $all]; };
  $cmd = function (string $c, array $okCodes) use ($fp, $read): string { if ($c !== '') fwrite($fp, $c . "\r\n"); [$code, $r] = $read();
    if (!in_array($code, $okCodes, true)) throw new RuntimeException('smtp error ' . $code); return $r; };
  try {
    $cmd('', [220]);
    $ehlo = 'EHLO abad-labs.com';
    $caps = $cmd($ehlo, [250]);
    if ($sec === 'starttls') {
      if (stripos($caps, 'STARTTLS') === false) throw new RuntimeException('smtp no starttls');
      $cmd('STARTTLS', [220]);
      if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('smtp tls failed');
      $cmd($ehlo, [250]);
    }
    $cmd('AUTH LOGIN', [334]); $cmd(base64_encode($user), [334]); $cmd(base64_encode($pass), [235]);
    $cmd('MAIL FROM:<' . $from . '>', [250]); $cmd('RCPT TO:<' . $to . '>', [250, 251]); $cmd('DATA', [354]);
    $b = 'b' . bin2hex(random_bytes(8));
    $enc = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $msg = 'Date: ' . date(DATE_RFC2822) . "\r\nFrom: " . $enc($fromName) . " <$from>\r\nTo: <$to>\r\nSubject: " . $enc($subject)
      . "\r\nMessage-ID: <" . bin2hex(random_bytes(10)) . '@abad-labs.com>' . "\r\nMIME-Version: 1.0\r\nAuto-Submitted: auto-generated\r\n"
      . "Content-Type: multipart/alternative; boundary=\"$b\"\r\n\r\n"
      . "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
      . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
      . "--$b--\r\n";
    fwrite($fp, $msg . ".\r\n"); $cmd('', [250]);
    $cmd('QUIT', [221, 250]);
    return true;
  } finally { fclose($fp); }
}

/** Branded Arabic email with a big code box; $rows = extra [label => value] lines (shown LTR). Layout lives in _mail_tpl.php. */
function abd_code_mail(string $name, string $title, string $pre, string $intro, string $code, array $rows = []): array {
  return abd_mail_render(['title' => $title, 'preheader' => $pre, 'name' => $name, 'intro_html' => $intro, 'code' => $code,
    'expiry_min' => intdiv(ABD_CODE_TTL, 60), 'rows' => $rows]);
}
function abd_verify_mail(string $to, string $name, string $code): bool {
  $m = abd_code_mail($name, 'تأكيد البريد الإلكتروني', 'كود التفعيل: ' . $code . ' — صالح ' . intdiv(ABD_CODE_TTL, 60) . ' دقائق',
    'شكرًا لتسجيلك في منصة آباد الرقمية. أدخل الكود التالي لتفعيل حسابك:', $code);
  return abd_smtp_send($to, 'رمز تفعيل حسابك في منصة آباد: ' . $code, $m['html'], $m['text']);
}
/** True if $to is the platform's own sending mailbox (SMTP_FROM / SMTP_USER, ignoring +tags and Gmail dots). */
function abd_mail_is_sender(string $to): bool {
  $n = function (string $a): string { $a = strtolower(trim($a)); if (!str_contains($a, '@')) return $a; [$l, $d] = explode('@', $a, 2);
    $l = explode('+', $l, 2)[0]; if ($d === 'gmail.com' || $d === 'googlemail.com') { $l = str_replace('.', '', $l); $d = 'gmail.com'; } return $l . '@' . $d; };
  $t = $n($to);
  foreach (['SMTP_FROM', 'SMTP_USER'] as $k) { $v = (string)abd_mail_cfg($k, ''); if ($v !== '' && $n($v) === $t) return true; }
  return false;
}
/** Lab seat OTP — same branded code mail as sign-up. Code only: the EVE username/password are NOT emailed any more;
 *  they are shown on the lab page only after this code is verified (api/lab_seat.php action=open). */
function abd_lab_code_mail(string $to, string $name, int $seat, string $code): bool {
  $m = abd_code_mail($name, 'رمز دخول مقعد المعمل', 'رمز دخول مقعد ' . $seat . ': ' . $code . ' — صالح ' . intdiv(ABD_CODE_TTL, 60) . ' دقائق',
    'أدخل الرمز التالي في صفحة المعمل لعرض بيانات دخول <b>مقعد ' . $seat . '</b>:', $code, ['المقعد' => 'مقعد ' . $seat . ' · eve' . $seat . '.abad-labs.com']);
  return abd_smtp_send($to, 'رمز فتح مقعد المعمل ' . $seat . ': ' . $code, $m['html'], $m['text']);
}

/** Remote lab (Kali / network consoles) OTP — same branded code mail; no addresses or credentials in the email. */
function abd_rlab_code_mail(string $to, string $name, string $labTitle, string $code): bool {
  $m = abd_code_mail($name, 'رمز دخول المعمل', 'رمز دخول ' . $labTitle . ': ' . $code . ' — صالح ' . intdiv(ABD_CODE_TTL, 60) . ' دقائق',
    'أدخل الرمز التالي في صفحة المعمل لفتح <b>' . htmlspecialchars($labTitle, ENT_QUOTES, 'UTF-8') . '</b>:', $code);
  return abd_smtp_send($to, 'رمز دخول المعمل: ' . $code, $m['html'], $m['text']);
}

// ---------------------------------------------------------------- one-time codes (hashed, 10 min, attempt-limited)
function abd_code_secret(): string {
  $s = (string)abd_mail_cfg('CODE_SECRET', '');
  if ($s === '' && ($f = abd_private_file('lab.php'))) { $c = require $f; $s = (string)($c['LAB_HMAC_SECRET'] ?? ''); }
  if ($s === '' && ($f = abd_config_file())) $s = hash_file('sha256', $f);
  return $s;
}
function abd_code_hash(string $code, int $uid, string $purpose): string { return hash_hmac('sha256', $code, abd_code_secret() . '|' . $purpose . '|' . $uid); }

/** Create a new code (older unused ones of the same purpose are voided). Returns the plain code. */
function abd_code_issue(int $uid, string $purpose, ?int $seat = null): string {
  $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  abd_q('UPDATE abd_email_codes SET used_at = NOW() WHERE user_id = ? AND purpose = ? AND used_at IS NULL', [$uid, $purpose]);
  abd_q('INSERT INTO abd_email_codes (user_id, purpose, seat, code_hash, expires_at, ip_hash) VALUES (?, ?, ?, ?, ?, ?)',
    [$uid, $purpose, $seat, abd_code_hash($code, $uid, $purpose), date('Y-m-d H:i:s', time() + ABD_CODE_TTL), abd_ip_hash()]);
  if (random_int(1, 50) === 1) abd_q('DELETE FROM abd_email_codes WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)]);
  return $code;
}
/** Check a code. Returns ['ok'=>true] or ['ok'=>false,'error'=>'expired'|'wrong_code','left'=>n]. Consumes on success. */
function abd_code_check(int $uid, string $purpose, string $code, ?int $seat = null): array {
  $code = preg_replace('/\D/', '', abd_latin_digits($code)) ?? '';
  if (strlen($code) !== 6) return ['ok' => false, 'error' => 'wrong_code', 'left' => null];
  $o = abd_q('SELECT id, seat, code_hash, attempts, expires_at FROM abd_email_codes WHERE user_id = ? AND purpose = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1', [$uid, $purpose])->fetch();
  if (!$o || strtotime((string)$o['expires_at']) < time() || (int)$o['attempts'] >= ABD_CODE_TRIES || ($seat !== null && (int)$o['seat'] !== $seat))
    return ['ok' => false, 'error' => 'expired'];
  if (!hash_equals((string)$o['code_hash'], abd_code_hash($code, $uid, $purpose))) {
    abd_q('UPDATE abd_email_codes SET attempts = attempts + 1 WHERE id = ?', [$o['id']]);
    return ['ok' => false, 'error' => 'wrong_code', 'left' => max(0, ABD_CODE_TRIES - (int)$o['attempts'] - 1)];
  }
  if (abd_q('UPDATE abd_email_codes SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [$o['id']])->rowCount() !== 1) return ['ok' => false, 'error' => 'expired'];
  return ['ok' => true];
}
