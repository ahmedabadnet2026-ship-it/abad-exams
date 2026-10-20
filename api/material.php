<?php
// Gated course material: GET api/material.php?id=<material id>
// Entitled (abd_entitlements kind=material, or staff) → streams the file from …/private_html/abad-data/materials/.
declare(strict_types=1);
require_once __DIR__ . '/_services_lib.php';

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
$id = (string)($_GET['id'] ?? '');
$fail = function (int $code, string $msg): void { http_response_code($code); header('Content-Type: text/plain; charset=utf-8'); exit($msg); };
if (!isset(ABD_MATERIALS[$id])) $fail(404, 'not found');
try { $u = abd_user(); $ok = $u && abd_material_allowed($u, $id); }
catch (Throwable $e) { error_log('[abad-material] ' . $e->getMessage()); $fail(503, 'unavailable'); }
if (!$u) { header('Location: ' . abd_base_path() . 'my-services.html', true, 303); exit; }
if (!$ok) $fail(403, 'هذه المادة غير مفتوحة لحسابك. تواصل مع معهد آباد.');
$m = ABD_MATERIALS[$id];
$f = abd_material_dir() . '/' . basename($m['file']);
if (!is_file($f)) { error_log('[abad-material] missing ' . $f); $fail(503, 'material unavailable'); }
header('Content-Type: ' . $m['mime']);
header('Content-Length: ' . filesize($f));
header('Content-Disposition: inline; filename="' . basename($m['file']) . '"');
readfile($f);
