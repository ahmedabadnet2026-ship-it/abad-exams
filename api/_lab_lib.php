<?php
// EVE lab gate: seat assignment, email OTP, SMTP mailer and signed seat tokens.
// Secrets live OUTSIDE the web root: …/private_html/abad-data/lab.php (or abad-config/lab.php) returning an array, e.g.
//   <?php return ['SMTP_HOST'=>'…','SMTP_PORT'=>587,'SMTP_USER'=>'…','SMTP_PASS'=>'…','LAB_HMAC_SECRET'=>'<64 hex>'];
// Environment variables with the same names override the file. Never commit real values.
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }
require_once __DIR__ . '/_db.php';

const ABD_LAB_SEATS = 12;
const ABD_LAB_OTP_TTL = 300;       // code valid 5 min
const ABD_LAB_OTP_TRIES = 5;       // wrong attempts per code
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
function abd_mask_email(string $e): string {
  [$u, $d] = explode('@', $e, 2) + [1 => ''];
  return mb_substr($u, 0, 2) . str_repeat('•', max(1, mb_strlen($u) - 2)) . '@' . $d;
}

/** Current valid seat assignment for a user, or null. */
function abd_lab_seat_of(int $uid): ?int {
  $s = abd_q('SELECT seat FROM abd_lab_seats WHERE user_id = ?
              AND (valid_from IS NULL OR valid_from <= NOW()) AND (valid_to IS NULL OR valid_to >= NOW())', [$uid])->fetchColumn();
  return $s === false ? null : (int)$s;
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

// ---------------------------------------------------------------- minimal SMTP client (STARTTLS on 587, implicit TLS on 465)
function abd_smtp_send(string $to, string $subject, string $html, string $text): bool {
  $host = (string)abd_lab_cfg('SMTP_HOST', ''); $port = (int)abd_lab_cfg('SMTP_PORT', 587);
  $user = (string)abd_lab_cfg('SMTP_USER', ''); $pass = (string)abd_lab_cfg('SMTP_PASS', '');
  $from = (string)abd_lab_cfg('SMTP_FROM', $user); $fromName = (string)abd_lab_cfg('SMTP_FROM_NAME', 'Abad Digital');
  if ($host === '' || $user === '' || $pass === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('smtp config missing');
  $tls = $port === 465;
  $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
  $fp = @stream_socket_client(($tls ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
  if (!$fp) throw new RuntimeException('smtp connect failed');
  stream_set_timeout($fp, 15);
  $read = function () use ($fp): array { $all = ''; while (($l = fgets($fp, 2048)) !== false) { $all .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return [(int)substr($all, 0, 3), $all]; };
  $cmd = function (string $c, array $okCodes) use ($fp, $read): string { if ($c !== '') fwrite($fp, $c . "\r\n"); [$code, $r] = $read();
    if (!in_array($code, $okCodes, true)) throw new RuntimeException('smtp error ' . $code); return $r; };
  try {
    $cmd('', [220]);
    $ehlo = 'EHLO abad-labs.com';
    $caps = $cmd($ehlo, [250]);
    if (!$tls) {
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

function abd_lab_otp_mail(string $to, string $name, int $seat, string $code): bool {
  $n = h($name !== '' ? $name : 'متدربنا العزيز');
  $html = '<!doctype html><html lang="ar" dir="rtl"><body style="margin:0;background:#eef3f7;font-family:Cairo,Tahoma,Arial,sans-serif;color:#10293b">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3f7;padding:24px 0"><tr><td align="center">'
    . '<table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%;background:#fff;border-radius:16px;overflow:hidden;direction:rtl;text-align:right">'
    . '<tr><td style="background:#10293b;padding:18px 24px;border-bottom:4px solid #F5BD2D;color:#fff"><b style="font-size:18px">منصة آباد الرقمية</b><br><span style="font-size:13px;color:#9cc7e3">معهد شبكة آباد للتدريب — المعمل</span></td></tr>'
    . '<tr><td style="padding:24px"><p style="margin:0 0 10px;font-size:15px">مرحبًا ' . $n . '،</p>'
    . '<p style="margin:0 0 16px;font-size:15px;line-height:1.8">رمز الدخول إلى <b>مقعد ' . $seat . '</b> في معمل EVE-NG:</p>'
    . '<div style="text-align:center;margin:0 0 16px"><span style="display:inline-block;direction:ltr;letter-spacing:10px;font-size:32px;font-weight:800;color:#10293b;background:#fff8e1;border:2px solid #F5BD2D;border-radius:12px;padding:10px 18px 10px 28px">' . h($code) . '</span></div>'
    . '<p style="margin:0 0 6px;font-size:13px;color:#3f86b3">ينتهي الرمز خلال 5 دقائق ويُستخدم مرة واحدة.</p>'
    . '<p style="margin:0;font-size:13px;color:#5d7183">إذا لم تطلب هذا الرمز فتجاهل الرسالة، ولا تشاركه مع أي شخص.</p></td></tr>'
    . '<tr><td style="background:#f6f9fb;padding:12px 24px;font-size:12px;color:#5d7183">© معهد شبكة آباد للتدريب</td></tr></table></td></tr></table></body></html>';
  $text = "منصة آباد الرقمية — المعمل\nرمز الدخول إلى مقعد $seat: $code\nينتهي خلال 5 دقائق ويُستخدم مرة واحدة. لا تشاركه مع أحد.";
  return abd_smtp_send($to, 'رمز دخول المعمل: ' . $code, $html, $text);
}
