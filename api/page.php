<?php
// Login gate for every public page of the platform (wired by /.htaccess: *.html, the folder root and index.php are rewritten here).
// Signed-in (email-verified) user → the page is streamed with no-store headers. Signed out → 302 to login.html?next=<page>.
// Exempt pages (login.html) and static assets are never rewritten to this script. Entitlement gating (api/exam.php, materials,
// lab seats, exam-lock.js trial lock) is unchanged and still applies inside the pages.
declare(strict_types=1);
require_once __DIR__ . '/_db.php';

const ABD_GATE_EXEMPT = ['login.html'];   // sign-in / register / email-OTP verify all live in login.html (no password-reset page exists yet)

$f = (string)($_GET['f'] ?? 'index.html');
if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.html$/', $f)) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); exit('not found'); }
$path = abd_app_dir() . '/' . $f;
header('Cache-Control: no-store, private, max-age=0');
header('Vary: Cookie');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
if (!is_file($path)) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); exit('not found'); }

$exempt = in_array(strtolower($f), ABD_GATE_EXEMPT, true);
if (!$exempt) {
  try { $u = abd_user(); }
  catch (Throwable $ex) { error_log('[abad-gate] ' . $ex->getMessage()); http_response_code(503); header('Content-Type: text/plain; charset=utf-8'); exit('service unavailable'); }
  if (!$u) {
    $q = $_GET; unset($q['f']);
    $next = $f === 'index.html' ? 'index.html' : $f;
    if ($q) $next .= '?' . http_build_query($q);
    header('Location: ' . abd_base_path() . 'login.html?next=' . rawurlencode($next), true, 302);
    exit;
  }
}
header('Content-Type: text/html; charset=utf-8');
readfile($path);
