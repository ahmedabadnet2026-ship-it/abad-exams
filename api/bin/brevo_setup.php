<?php
// CLI only: php api/bin/brevo_setup.php  — checks the key, finds/creates the list "متدربين منصة آباد" and the NAME_AR attribute.
// Prints the list id to put into private_html/abad-config/brevo.php as LIST_ID. Never prints the key. Sends no email.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/_brevo.php';
const LIST_NAME = 'متدربين منصة آباد';
[$c, $a] = abd_brevo_req('GET', '/account');
echo 'account: HTTP ' . $c . ($c === 200 ? ' plan=' . json_encode(array_map(fn($p) => ['type' => $p['type'] ?? null, 'credits' => $p['credits'] ?? null], (array)($a['plan'] ?? [])), JSON_UNESCAPED_UNICODE) : ' ' . ($a['code'] ?? '') . ' ' . ($a['message'] ?? '')) . "\n";
[$c, $l] = abd_brevo_req('GET', '/contacts/lists?limit=50');
if ($c !== 200) { echo "lists: HTTP $c " . ($l['message'] ?? '') . "\n"; exit(1); }
$id = 0; foreach ((array)($l['lists'] ?? []) as $x) if (($x['name'] ?? '') === LIST_NAME) $id = (int)$x['id'];
if (!$id) {
  [$c, $f] = abd_brevo_req('GET', '/contacts/folders?limit=50');
  $folder = (int)(($f['folders'][0]['id'] ?? 0));
  if (!$folder) { [$c, $nf] = abd_brevo_req('POST', '/contacts/folders', ['name' => 'ABAD Digital']); $folder = (int)($nf['id'] ?? 0); }
  [$c, $n] = abd_brevo_req('POST', '/contacts/lists', ['name' => LIST_NAME, 'folderId' => $folder]);
  $id = (int)($n['id'] ?? 0); echo "list created: HTTP $c\n";
}
echo "LIST_ID = $id\n";
[$c, $at] = abd_brevo_req('GET', '/contacts/attributes');
$have = []; foreach ((array)($at['attributes'] ?? []) as $x) $have[strtoupper((string)$x['name'])] = true;
foreach (['FIRSTNAME', 'LASTNAME', 'NAME_AR'] as $name) if (empty($have[$name])) { [$c] = abd_brevo_req('POST', '/contacts/attributes/normal/' . $name, ['type' => 'text']); echo "attribute $name created: HTTP $c\n"; }
echo "SMS attribute: " . (empty($have['SMS']) ? 'missing (Brevo default; phone will be skipped)' : 'ok') . "\n";
$n = (int)abd_q('SELECT COUNT(*) FROM abd_users WHERE marketing_opt_in = 0')->fetchColumn();
echo "users without marketing consent (never synced): $n\n";
