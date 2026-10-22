<?php
// CLI only. Role/permission test for Zoom lectures against a THROWAWAY local DB.
//   ABAD_DB_CONFIG=/tmp/zdb/perm-db.php php api/bin/test_lecture_perms.php http://127.0.0.1:8098/abad-digital/
// The web server under test must use the same ABAD_DB_CONFIG. Refuses to run unless the DB name contains "perm" or "test".
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/_db.php';
$base = rtrim($argv[1] ?? '', '/') . '/';
$cfg = require abd_config_file();
if (!preg_match('/perm|test/i', (string)$cfg['name']) || !preg_match('~^http://127\.0\.0\.1:\d+/~', $base)) { fwrite(STDERR, "refusing: not a throwaway DB / local URL\n"); exit(2); }

// ---- seed
$pw = 'Perm-test-' . bin2hex(random_bytes(4));
$h = password_hash($pw, PASSWORD_DEFAULT);
foreach (['abd_course_sessions', 'abd_enrollments', 'abd_courses', 'abd_users'] as $t) abd_q("DELETE FROM $t");
abd_q('DELETE FROM abd_rate_limits'); 
$U = [];
foreach (['admin', 'zoom_staff', 'trainer', 'trainer2' => 'trainer', 'staff', 'trainee'] as $k => $r) { $k = is_int($k) ? $r : $k;
  abd_q("INSERT INTO abd_users (full_name_ar, email, email_verified_at, password_hash, role) VALUES (?, ?, NOW(), ?, ?)", ["مستخدم $k", "$k@perm.test", $h, $r]);
  $U[$k] = (int)abd_db()->lastInsertId(); }
abd_q("INSERT INTO abd_courses (slug, title_ar, trainer_id) VALUES ('c1', 'دورة 1', ?), ('c2', 'دورة 2', ?)", [$U['trainer'], $U['trainer2']]);
[$c1, $c2] = array_map('intval', abd_q('SELECT id FROM abd_courses ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
abd_q("INSERT INTO abd_enrollments (user_id, course_id, status) VALUES (?, ?, 'active')", [$U['trainee'], $c1]);
$mk = function (int $c) { abd_q("INSERT INTO abd_course_sessions (course_id, session_no, title, starts_at, ends_at, zoom_url) VALUES (?, 1, 'T0', NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY + INTERVAL 2 HOUR, 'https://zoom.us/j/100')", [$c]); return (int)abd_db()->lastInsertId(); };

// ---- http
function req(string $jar, string $url, ?array $post = null, array $hdr = [], bool $json = false): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $hdr]);
  if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $json ? json_encode($post) : http_build_query($post)); }
  $r = (string)curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  preg_match('/^Location:\s*(\S+)/mi', substr($r, 0, $hs), $m);
  return ['code' => $code, 'loc' => $m[1] ?? '', 'body' => substr($r, $hs)];
}
function login(string $base, string $who): array {
  $jar = tempnam(sys_get_temp_dir(), 'pj');
  $me = json_decode(req($jar, $base . 'api/auth.php?action=me')['body'], true);
  global $pw;
  $r = json_decode(req($jar, $base . 'api/auth.php', ['action' => 'login', 'email' => "$who@perm.test", 'password' => $pw], ['Content-Type: application/json', 'X-CSRF-Token: ' . $me['csrf']], true)['body'], true);
  if (empty($r['ok'])) { fwrite(STDERR, "login failed for $who\n"); exit(1); }
  return [$jar, (string)$r['csrf']];
}
$pass = 0; $fail = 0; $M = [];
function chk(string $act, string $role, bool $expect, bool $got): void { global $pass, $fail, $M;
  $M[$act][$role] = ($got ? 'YES' : 'no') . ($got === $expect ? '' : ' ✗FAIL'); $got === $expect ? $pass++ : $fail++; }
$exp = [ // action => roles allowed
  'admin login lands on' => null,
  'open Zoom staff dashboard (lectures.php)' => ['admin', 'zoom_staff'],
  'edit join link + passcode' => ['admin', 'zoom_staff'],
  'edit recording link' => ['admin', 'zoom_staff'],
  'generate / edit schedule' => ['admin', 'zoom_staff'],
  'import links (Excel/CSV)' => ['admin', 'zoom_staff'],
  'edit title via lectures.php' => ['admin'],
  'edit title of own course (trainer.php)' => ['trainer'],
  'edit title of other trainer\'s course' => [],
  'add recording link via trainer.php' => [],
  'host start button on staff page' => [],
  'assign trainer to course' => ['admin'],
  'change user role (Users tab)' => ['admin'],
  'reach other admin pages (CRM, seats, services, exam access)' => ['admin', 'staff'],
  'API: join URLs of courses not enrolled in' => ['admin', 'zoom_staff', 'staff'],
];
$roles = ['admin', 'zoom_staff', 'trainer', 'staff', 'trainee'];
foreach ($roles as $role) {
  [$jar, $csrf] = login($base, $role);
  abd_q('DELETE FROM abd_course_sessions');
  $s1 = $mk($c1); $s2 = $mk($c2);
  $row = fn(int $id) => abd_q('SELECT * FROM abd_course_sessions WHERE id = ?', [$id])->fetch();
  // landing after admin login form
  $j2 = tempnam(sys_get_temp_dir(), 'pj'); $pg = req($j2, $base . 'admin/index.php');
  preg_match('/name="csrf" value="([^"]+)"/', $pg['body'], $m);
  abd_q('DELETE FROM abd_rate_limits');
  $r = req($j2, $base . 'admin/index.php', ['do' => 'login', 'csrf' => $m[1] ?? '', 'email' => "$role@perm.test", 'password' => $pw]);
  $M['admin login lands on'][$role] = $r['code'] === 303 ? $r['loc'] : 'refused';
  $pg = req($jar, $base . 'admin/lectures.php?course=' . $c1);
  $canPage = $pg['code'] === 200 && str_contains($pg['body'], 'لوحة <span>مسؤول الزوم');
  chk('open Zoom staff dashboard (lectures.php)', $role, in_array($role, $exp['open Zoom staff dashboard (lectures.php)'], true), $canPage);
  chk('host start button on staff page', $role, false, $canPage && str_contains($pg['body'], 'ابدأ المحاضرة'));
  $d = substr((string)$row($s1)['starts_at'], 0, 10);
  req($jar, $base . 'admin/lectures.php', ['csrf' => $csrf, 'do' => 'save_all', 'course' => $c1, 'date' => [$s1 => $d], 'st' => [$s1 => '10:00'], 'et' => [$s1 => '12:00'],
    'url' => [$s1 => 'https://zoom.us/j/999'], 'pass' => [$s1 => 'P9'], 'title' => [$s1 => 'HACK'], 'rec' => [$s1 => 'https://zoom.us/rec/share/x'], 'recpass' => [$s1 => ''], 'recuntil' => [$s1 => '']]);
  $a = $row($s1);
  chk('edit join link + passcode', $role, in_array($role, $exp['edit join link + passcode'], true), $a['zoom_url'] === 'https://zoom.us/j/999' && $a['zoom_passcode'] === 'P9');
  chk('edit recording link', $role, in_array($role, $exp['edit recording link'], true), $a['rec_url'] === 'https://zoom.us/rec/share/x');
  chk('edit title via lectures.php', $role, in_array($role, $exp['edit title via lectures.php'], true), $a['title'] === 'HACK');
  $before = (int)abd_q('SELECT COUNT(*) FROM abd_course_sessions WHERE course_id = ?', [$c1])->fetchColumn();
  req($jar, $base . 'admin/lectures.php', ['csrf' => $csrf, 'do' => 'generate', 'course' => $c1, 'from' => '2030-01-06', 'to' => '2030-01-12', 'days' => [0, 4], 'st' => '19:00', 'et' => '21:00', 'mode' => 'add']);
  $after = (int)abd_q('SELECT COUNT(*) FROM abd_course_sessions WHERE course_id = ?', [$c1])->fetchColumn();
  chk('generate / edit schedule', $role, in_array($role, $exp['generate / edit schedule'], true), $after > $before);
  abd_q('UPDATE abd_course_sessions SET zoom_url = NULL WHERE id = ?', [$s1]);
  $csv = tempnam(sys_get_temp_dir(), 'pc') . '.csv'; file_put_contents($csv, "رقم المحاضرة,رابط الزوم\n1,https://zoom.us/j/777\n");
  $ch = curl_init($base . 'admin/lectures.php'); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar, CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => ['csrf' => $csrf, 'do' => 'import_upload', 'course' => (string)$c1, 'file' => new CURLFile($csv, 'text/csv', 'l.csv')]]); curl_exec($ch);
  $pv = req($jar, $base . 'admin/lectures.php?course=' . $c1 . '&step=preview'); preg_match('/name="nonce" value="([^"]+)"/', $pv['body'], $nm);
  req($jar, $base . 'admin/lectures.php', ['csrf' => $csrf, 'do' => 'import_confirm', 'course' => $c1, 'nonce' => $nm[1] ?? 'x']);
  chk('import links (Excel/CSV)', $role, in_array($role, $exp['import links (Excel/CSV)'], true), abd_q('SELECT COUNT(*) FROM abd_course_sessions WHERE course_id = ? AND zoom_url = ?', [$c1, 'https://zoom.us/j/777'])->fetchColumn() > 0);
  // trainer page
  abd_q('UPDATE abd_course_sessions SET rec_url = NULL WHERE id IN (?, ?)', [$s1, $s2]);
  req($jar, $base . 'admin/trainer.php', ['csrf' => $csrf, 'do' => 'save', 'course' => $c1, 'title' => [$s1 => 'TR-OWN'], 'rec' => [$s1 => 'https://youtu.be/AAAAAAAAAAA']]);
  req($jar, $base . 'admin/trainer.php', ['csrf' => $csrf, 'do' => 'save', 'course' => $c2, 'title' => [$s2 => 'TR-OTHER']]);
  chk('edit title of own course (trainer.php)', $role, in_array($role, $exp['edit title of own course (trainer.php)'], true), $row($s1)['title'] === 'TR-OWN');
  chk('edit title of other trainer\'s course', $role, false, $row($s2)['title'] === 'TR-OTHER');
  chk('add recording link via trainer.php', $role, false, $row($s1)['rec_url'] !== null);
  req($jar, $base . 'admin/lectures.php', ['csrf' => $csrf, 'do' => 'set_trainer', 'course' => $c2, 'trainer_id' => $U['trainer']]);
  chk('assign trainer to course', $role, in_array($role, $exp['assign trainer to course'], true), (int)abd_q('SELECT trainer_id FROM abd_courses WHERE id = ?', [$c2])->fetchColumn() === $U['trainer']);
  abd_q('UPDATE abd_courses SET trainer_id = ? WHERE id = ?', [$U['trainer2'], $c2]);
  req($jar, $base . 'admin/index.php', ['csrf' => $csrf, 'do' => 'user_role', 'id' => $U['trainee'], 'role' => 'zoom_staff', 'ret' => 'users']);
  chk('change user role (Users tab)', $role, in_array($role, $exp['change user role (Users tab)'], true), abd_q('SELECT role FROM abd_users WHERE id = ?', [$U['trainee']])->fetchColumn() === 'zoom_staff');
  abd_q("UPDATE abd_users SET role = 'trainee' WHERE id = ?", [$U['trainee']]);
  $reach = 0; foreach (['admin/index.php?tab=crm', 'admin/seats.php', 'admin/services.php', 'admin/exam-access.php'] as $p) { $x = req($jar, $base . $p); $reach += $x['code'] === 200 && !str_contains($x['body'], 'name="password"') ? 1 : 0; }
  chk('reach other admin pages (CRM, seats, services, exam access)', $role, in_array($role, $exp['reach other admin pages (CRM, seats, services, exam access)'], true), $reach > 0);
  $api = json_decode(req($jar, $base . 'api/lectures.php')['body'], true);
  $other = false; foreach ($api['courses'] ?? [] as $c) if ((int)$c['id'] === $c2) foreach ($c['sessions'] as $s) if ($s['join_url']) $other = true;
  chk('API: join URLs of courses not enrolled in', $role, in_array($role, $exp['API: join URLs of courses not enrolled in'], true), $other);
}
// ---- matrix
$w = 52; printf("%-{$w}s", 'action'); foreach ($roles as $r) printf("| %-14s", $r); echo "\n", str_repeat('-', $w + 16 * count($roles)), "\n";
foreach ($M as $act => $row) { echo str_pad($act, $w + strlen($act) - mb_strlen($act)); foreach ($roles as $r) printf("| %-14s", $row[$r] ?? ''); echo "\n"; }
echo "\n$pass checks passed, $fail failed\n"; exit($fail ? 1 : 0);
