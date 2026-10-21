<?php
// Exam entitlement layer. An exam (page) belongs to a certification id (the certSections id used by booking.html).
// A logged-in user may open an exam when ANY entitlement source says so:
//   (a) booking : abd_bookings row with status 'booked' for that cert, linked to the user (user_id, or same email if unlinked)
//   (b) manual  : abd_exam_access grant by staff (optional expiry)
//   (c) webhook : abd_exam_access rows written by api/hooks/booking.php (future official-site feed; disabled)
// Staff/admin always pass. Question pages live OUTSIDE the web root: …/private_html/abad-data/exams/<file>.
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }
require_once __DIR__ . '/_db.php';

/** exam id => [cert id, vendor id, file, title, legacy booking-text LIKE patterns] */
const ABD_EXAMS = [
  'aplus-core1'    => ['cert' => 'aplus',    'vendor' => 'comptia',    'file' => 'aplus-core1-exam.html',    'title' => 'CompTIA A+ Core 1',        'like' => ['CompTIA A+%']],
  'aplus-core2'    => ['cert' => 'aplus',    'vendor' => 'comptia',    'file' => 'aplus-core2-exam.html',    'title' => 'CompTIA A+ Core 2',        'like' => ['CompTIA A+%']],
  'network-plus'   => ['cert' => 'network',  'vendor' => 'comptia',    'file' => 'network-plus-exam.html',   'title' => 'CompTIA Network+',         'like' => ['CompTIA Network+%']],
  'security-plus'  => ['cert' => 'security', 'vendor' => 'comptia',    'file' => 'security-plus-exam.html',  'title' => 'CompTIA Security+',        'like' => ['CompTIA Security+%']],
  'linux-plus'     => ['cert' => 'linux',    'vendor' => 'comptia',    'file' => 'linux-plus-exam.html',     'title' => 'CompTIA Linux+',           'like' => ['CompTIA Linux+%']],
  'pmp'            => ['cert' => 'pmp',      'vendor' => 'pmi',        'file' => 'pmp-exam.html',            'title' => 'PMP',                      'like' => ['Project Management Professional%', '%PMP%']],
  'itil5'          => ['cert' => 'itil5',    'vendor' => 'peoplecert', 'file' => 'itil5-exam.html',          'title' => 'ITIL Foundation (V5)',     'like' => ['ITIL Foundation%']],
  'ccna'           => ['cert' => 'ccna',     'vendor' => 'cisco',      'file' => 'ccna-exam.html',           'title' => 'Cisco CCNA',               'like' => ['%CCNA%']],
  'cobit'          => ['cert' => 'cobit',    'vendor' => 'isaca',      'file' => 'cobit-exam.html',          'title' => 'ISACA COBIT 2019 Foundation', 'like' => ['%COBIT%']],
  'mos-word'       => ['cert' => 'mos-word',  'vendor' => 'microsoft',  'file' => 'mos-word-exam.html',       'title' => 'MOS Word (MO-100)',       'like' => ['%MO-100%', '%Word%MOS%', 'MOS%Word%']],
  'mos-excel'      => ['cert' => 'mos-excel', 'vendor' => 'microsoft',  'file' => 'mos-excel-exam.html',      'title' => 'MOS Excel (MO-200)',      'like' => ['%MO-200%', '%Excel%MOS%', 'MOS%Excel%']],
  'mos-ppt'        => ['cert' => 'mos-ppt',   'vendor' => 'microsoft',  'file' => 'mos-ppt-exam.html',        'title' => 'MOS PowerPoint (MO-300)', 'like' => ['%MO-300%', '%PowerPoint%MOS%', 'MOS%PowerPoint%']],
  'ceh'            => ['cert' => 'ceh',      'vendor' => 'eccouncil',  'file' => 'ceh-exam.html',            'title' => 'EC-Council CEH v13',       'like' => ['%CEH%']],
  'cysa'           => ['cert' => 'cysa',     'vendor' => 'comptia',    'file' => 'cysa-exam.html',           'title' => 'CompTIA CySA+',            'like' => ['%CySA%']],
  'ejpt-trial'     => ['cert' => 'ejpt',     'vendor' => 'ine',        'file' => 'ejpt-trial-exam.html',     'title' => 'INE eJPT',                 'like' => ['%eJPT%']],
];

function abd_exam_dir(): string {
  $pos = strpos(__DIR__, '/public_html');
  $root = $pos !== false ? substr(__DIR__, 0, $pos) : dirname(__DIR__, 2);
  if (($e = getenv('ABAD_EXAM_DIR')) !== false && $e !== '') return $e;
  // abad-data/ (app user) first, else abad-config/ (SSH master user; same fallback as db.php / lab.php)
  foreach (['abad-data', 'abad-config'] as $d) if (is_dir($f = $root . '/private_html/' . $d . '/exams')) return $f;
  return $root . '/private_html/abad-data/exams';
}
function abd_exam_certs(): array { $c = []; foreach (ABD_EXAMS as $e) $c[$e['cert']] = $e; return $c; }

/** Set of cert ids the user may open (staff: all). */
function abd_exam_entitled_certs(array $u): array {
  if (in_array($u['role'], ['admin', 'staff'], true)) return array_keys(abd_exam_certs());
  $uid = (int)$u['id']; $email = mb_strtolower((string)$u['email']);
  $ok = [];
  // (b)+(c) explicit grants
  foreach (abd_q('SELECT DISTINCT cert_id FROM abd_exam_access WHERE user_id = ? AND revoked_at IS NULL
                  AND (expires_at IS NULL OR expires_at >= NOW())', [$uid])->fetchAll(PDO::FETCH_COLUMN) as $c) $ok[$c] = true;
  // (d) unified services ledger (migration 005); tolerated if not migrated yet
  try { foreach (abd_q("SELECT DISTINCT ref_id FROM abd_entitlements WHERE user_id = ? AND kind = 'exam' AND revoked_at IS NULL", [$uid])->fetchAll(PDO::FETCH_COLUMN) as $c) $ok[$c] = true; }
  catch (PDOException $e) { if (($e->errorInfo[1] ?? 0) !== 1146) throw $e; }
  // (a) booked bookings: by stable cert_id, else legacy text match on the cert label
  $rows = abd_q("SELECT cert_id, cert FROM abd_bookings WHERE status = 'booked'
                 AND (user_id = ? OR (user_id IS NULL AND email = ?))", [$uid, $email])->fetchAll();
  foreach ($rows as $r) if ($c = abd_exam_cert_of_booking($r['cert_id'] ?? null, (string)$r['cert'])) $ok[$c] = true;
  return array_keys($ok);
}
/** Map a booking to a cert id: stable cert_id first, else case-insensitive match of the legacy label (e.g. "CompTIA Network+ — N10-009"). */
function abd_exam_cert_of_booking(?string $certId, string $label): ?string {
  $certs = abd_exam_certs();
  if ($certId !== null && $certId !== '') return isset($certs[$certId]) ? $certId : null;
  foreach (ABD_EXAMS as $e) foreach ($e['like'] as $p) if (fnmatch(str_replace('%', '*', $p), trim($label), FNM_CASEFOLD)) return $e['cert'];
  return null;
}
function abd_exam_allowed(array $u, string $examId): bool {
  return isset(ABD_EXAMS[$examId]) && in_array(ABD_EXAMS[$examId]['cert'], abd_exam_entitled_certs($u), true);
}
