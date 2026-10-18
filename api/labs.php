<?php
declare(strict_types=1);
require __DIR__ . '/_labs_lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function out(array $d, int $code = 200): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function pub(array $data, bool $admin): array {
  $r = [];
  foreach (ABAD_LABS as $id => $meta) {
    $l = $data['labs'][$id];
    $r[$id] = ['visible' => (bool)$l['visible'], 'opens' => (int)$l['opens']];
    if ($admin) $r[$id] += ['last' => $l['last'], 'name' => $meta['ar'], 'name_en' => $meta['en'], 'href' => $meta['href']];
  }
  return ['labs' => $r];
}

$m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($m === 'GET') {
  $admin = isset($_COOKIE['wordpress_abadadm']) && abad_is_admin();
  out(pub(abad_labs_tx(), $admin));
}
if ($m !== 'POST') out(['error' => 'method'], 405);

$action = (string)($_POST['action'] ?? '');
$id = (string)($_POST['id'] ?? '');
if (!isset(ABAD_LABS[$id])) out(['error' => 'unknown lab'], 400);

if ($action === 'open') {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
  $key = hash('sha256', $ip . '|' . $id);
  $now = time(); $counted = false;
  $data = abad_labs_tx(function (array &$d) use ($key, $id, $now, &$counted) {
    foreach ($d['rl'] as $k => $t) if ($t < $now - 600) unset($d['rl'][$k]);
    if (!isset($d['rl'][$key])) {
      $d['rl'][$key] = $now; $d['labs'][$id]['opens']++; $d['labs'][$id]['last'] = $now; $counted = true;
    }
  });
  out(['ok' => true, 'counted' => $counted, 'opens' => $data['labs'][$id]['opens']]);
}

if (!abad_is_admin()) out(['error' => 'auth'], 401);
if (!hash_equals(abad_csrf(), (string)($_POST['csrf'] ?? ''))) out(['error' => 'csrf'], 403);
if ($action === 'toggle') {
  $data = abad_labs_tx(function (array &$d) use ($id) {
    $d['labs'][$id]['visible'] = isset($_POST['visible']) ? $_POST['visible'] === '1' : !$d['labs'][$id]['visible'];
  });
} elseif ($action === 'reset') {
  $data = abad_labs_tx(function (array &$d) use ($id) { $d['labs'][$id]['opens'] = 0; $d['labs'][$id]['last'] = null; });
} else out(['error' => 'action'], 400);
out(['ok' => true] + pub($data, true));
