<?php
// Trainee services (خدماتي): exams, course materials, EVE lab seats.
// Single ledger abd_entitlements (migration 005) + the earlier per-feature tables, read side by side:
//   exam     ← abd_entitlements(kind=exam) ∪ abd_exam_access ∪ booked abd_bookings   (abd_exam_entitled_certs)
//   material ← abd_entitlements(kind=material)
//   lab      ← abd_lab_seats ∪ abd_entitlements(kind=lab, ref_id=seat)               (abd_lab_seat_of)
// Material files live OUTSIDE the web root: …/private_html/abad-data/materials/<file>.
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }
require_once __DIR__ . '/_exam_lib.php';
require_once __DIR__ . '/_lab_lib.php';

/** material id => [title, file (in materials dir), mime] */
const ABD_MATERIALS = [
  'network-camp'  => ['title' => 'معسكر الشبكات — الماتريال الكاملة', 'file' => 'network-camp.pdf',  'mime' => 'application/pdf'],
  'ccna'          => ['title' => 'Cisco CCNA 200-301 — Course Material', 'file' => 'ccna.pdf',          'mime' => 'application/pdf'],
  'network-plus'  => ['title' => 'CompTIA Network+ — Course Material',   'file' => 'network-plus.pdf',  'mime' => 'application/pdf'],
  'security-plus' => ['title' => 'CompTIA Security+ — Course Material',  'file' => 'security-plus.pdf', 'mime' => 'application/pdf'],
  'linux-plus'    => ['title' => 'CompTIA Linux+ — Course Material',     'file' => 'linux-plus.pdf',    'mime' => 'application/pdf'],
  'ccnp'          => ['title' => 'Cisco CCNP — Course Material',         'file' => 'ccnp.pdf',          'mime' => 'application/pdf'],
  'mcsa'          => ['title' => 'Microsoft MCSA — Course Material',     'file' => 'mcsa.pdf',          'mime' => 'application/pdf'],
  'nse4'          => ['title' => 'Fortinet NSE4 — Course Material',      'file' => 'nse4.pdf',          'mime' => 'application/pdf'],
  'rhcsa'         => ['title' => 'Red Hat RHCSA — Course Material',      'file' => 'rhcsa.pdf',         'mime' => 'application/pdf'],
];
const ABD_ENT_KINDS = ['exam' => 'اختبار', 'material' => 'ماتريال', 'lab' => 'لاب (مقعد EVE)'];

function abd_material_dir(): string {
  $pos = strpos(__DIR__, '/public_html');
  $root = $pos !== false ? substr(__DIR__, 0, $pos) : dirname(__DIR__, 2);
  return (($e = getenv('ABAD_MATERIAL_DIR')) !== false && $e !== '') ? $e : $root . '/private_html/abad-data/materials';
}

/** Valid ref ids per kind (for admin grant validation). */
function abd_ent_refs(string $kind): array {
  if ($kind === 'exam') { $o = []; foreach (abd_exam_certs() as $id => $c) $o[$id] = $c['title']; return $o; }
  if ($kind === 'material') { $o = []; foreach (ABD_MATERIALS as $id => $m) $o[$id] = $m['title']; return $o; }
  if ($kind === 'lab') { $o = []; for ($i = 1; $i <= ABD_LAB_SEATS; $i++) $o[(string)$i] = 'EVE ' . $i; return $o; }
  return [];
}

function abd_material_ids_of(int $uid): array {
  try { return abd_q("SELECT DISTINCT ref_id FROM abd_entitlements WHERE user_id = ? AND kind = 'material' AND revoked_at IS NULL", [$uid])->fetchAll(PDO::FETCH_COLUMN); }
  catch (PDOException $e) { if (($e->errorInfo[1] ?? 0) !== 1146) throw $e; return []; }
}
function abd_material_allowed(array $u, string $id): bool {
  if (!isset(ABD_MATERIALS[$id])) return false;
  if (in_array($u['role'], ['admin', 'staff'], true)) return true;
  return in_array($id, abd_material_ids_of((int)$u['id']), true);
}

/** Open services of a user, ready for the "خدماتي" page. */
function abd_services_of(array $u): array {
  $certs = abd_exam_entitled_certs($u);
  $exams = [];
  foreach (ABD_EXAMS as $id => $e) if (in_array($e['cert'], $certs, true))
    $exams[] = ['id' => $id, 'title' => $e['title'], 'vendor' => $e['vendor'], 'url' => 'api/exam.php?id=' . rawurlencode($id)];
  $mats = [];
  $staff = in_array($u['role'], ['admin', 'staff'], true);
  $mid = $staff ? array_keys(ABD_MATERIALS) : abd_material_ids_of((int)$u['id']);
  foreach (ABD_MATERIALS as $id => $m) if (in_array($id, $mid, true))
    $mats[] = ['id' => $id, 'title' => $m['title'], 'url' => 'api/material.php?id=' . rawurlencode($id)];
  $labs = [];
  if (($s = abd_lab_seat_of((int)$u['id'])) !== null)
    $labs[] = ['id' => 'eve-' . $s, 'seat' => $s, 'title' => 'معمل EVE — المقعد ' . $s, 'url' => 'eve-classroom.html?seat=' . $s];
  return ['exams' => $exams, 'materials' => $mats, 'labs' => $labs];
}
