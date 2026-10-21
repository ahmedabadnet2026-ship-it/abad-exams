<?php
// Bulk enrollment import (CSV / XLSX): parse → analyse (preview) → commit (one transaction).
// Columns (any order, Arabic or English headers): course name, start date, end date, trainee email.
// Unregistered emails are reported only — the platform has no pending-invite mechanism, so no accounts are created.
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }
require_once __DIR__ . '/courses.php';
require_once __DIR__ . '/_xlsx.php';

const ABD_IMP_MAX_BYTES = 2 * 1024 * 1024;
const ABD_IMP_MAX_ROWS = 1000;
const ABD_IMP_HEADERS = [
  'course' => ['course', 'course name', 'course title', 'الدورة', 'اسم الدورة', 'الدوره', 'اسم الدوره', 'البرنامج', 'اسم البرنامج'],
  'start'  => ['start', 'start date', 'from', 'تاريخ البداية', 'تاريخ البدايه', 'البداية', 'بداية الدورة', 'تاريخ البدء', 'من'],
  'end'    => ['end', 'end date', 'to', 'تاريخ النهاية', 'تاريخ النهايه', 'النهاية', 'نهاية الدورة', 'تاريخ الانتهاء', 'إلى', 'الى'],
  'email'  => ['email', 'e-mail', 'trainee email', 'email address', 'البريد', 'البريد الإلكتروني', 'البريد الالكتروني', 'بريد المتدرب', 'الإيميل', 'الايميل'],
];

function abd_imp_norm(string $s): string {
  $s = mb_strtolower(trim(preg_replace('/[\x{200B}-\x{200F}\x{FEFF}\x{0640}]/u', '', $s) ?? ''));
  $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', '*' => '', ':' => '']);
  return preg_replace('/[\s_\-]+/u', ' ', $s) ?? $s;
}

/** Many date shapes → Y-m-d or null: 2026-10-22, 22/10/2026, 22-10-2026, 2026/10/22, 22.10.2026, Excel serial 46317, "22 Oct 2026", Arabic month names, Arabic-Indic digits. */
function abd_imp_date(string $v): ?string {
  $v = trim(abd_latin_digits($v)); if ($v === '') return null;
  $ok = fn(int $y, int $m, int $d) => ($y >= 2000 && $y <= 2100 && checkdate($m, $d, $y)) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : null;
  if (preg_match('/^\d{4,5}(\.\d+)?$/', $v) && (float)$v > 30000 && (float)$v < 80000) return gmdate('Y-m-d', (int)round(((int)$v - 25569) * 86400));
  if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})(?:[ T].*)?$/', $v, $m)) return $ok((int)$m[1], (int)$m[2], (int)$m[3]);
  if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})(?:\s.*)?$/', $v, $m)) return $ok((int)$m[3], (int)$m[2], (int)$m[1]);   // day first (Saudi usage)
  $ar = ['يناير' => 1, 'فبراير' => 2, 'مارس' => 3, 'ابريل' => 4, 'أبريل' => 4, 'مايو' => 5, 'يونيو' => 6, 'يوليو' => 7, 'اغسطس' => 8, 'أغسطس' => 8, 'سبتمبر' => 9, 'اكتوبر' => 10, 'أكتوبر' => 10, 'نوفمبر' => 11, 'ديسمبر' => 12];
  if (preg_match('/^(\d{1,2})\s+(\S+)\s+(\d{4})$/u', $v, $m) && isset($ar[$m[2]])) return $ok((int)$m[3], $ar[$m[2]], (int)$m[1]);
  if (preg_match('/[a-z]{3}/i', $v) && ($t = strtotime($v)) !== false) return $ok((int)date('Y', $t), (int)date('n', $t), (int)date('j', $t));
  return null;
}

/** File bytes → raw rows (first row = header). */
function abd_imp_read(string $bin, string $filename): array {
  if (strlen($bin) > ABD_IMP_MAX_BYTES) throw new RuntimeException('too_large');
  $isZip = str_starts_with($bin, "PK\x03\x04");
  if ($isZip) return abd_xlsx_rows($bin, ABD_IMP_MAX_ROWS + 1);
  if (preg_match('/\.xlsx?$/i', $filename)) throw new RuntimeException('bad_xlsx');
  $bin = preg_replace('/^\xEF\xBB\xBF/', '', $bin) ?? $bin;
  if (!mb_check_encoding($bin, 'UTF-8')) $bin = mb_convert_encoding($bin, 'UTF-8', 'Windows-1256');   // Excel "CSV" on Arabic Windows
  $first = strtok($bin, "\n") ?: '';
  $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
  $fh = fopen('php://memory', 'r+'); fwrite($fh, $bin); rewind($fh);
  $rows = [];
  while (($r = fgetcsv($fh, 0, $sep, '"', '')) !== false && count($rows) <= ABD_IMP_MAX_ROWS) $rows[] = array_map(fn($x) => trim((string)$x), $r);
  fclose($fh);
  return $rows;
}

/** Map header row → column indexes. Returns [map, missing]. */
function abd_imp_map(array $hdr): array {
  $map = [];
  foreach ($hdr as $i => $h) { $n = abd_imp_norm((string)$h);
    foreach (ABD_IMP_HEADERS as $k => $al) if (!isset($map[$k]) && in_array($n, array_map('abd_imp_norm', $al), true)) { $map[$k] = $i; break; } }
  return [$map, array_values(array_diff(array_keys(ABD_IMP_HEADERS), array_keys($map)))];
}

/** Analyse rows against the DB. Each item: line, course, start, end, email, course_id|null, course_new, user_id|null, user_name, status, errors[], notes[]. */
function abd_imp_analyse(array $rows): array {
  if (!$rows) return ['ok' => false, 'error' => 'empty'];
  [$map, $missing] = abd_imp_map(array_shift($rows));
  if ($missing) return ['ok' => false, 'error' => 'headers', 'missing' => $missing];
  if (count($rows) > ABD_IMP_MAX_ROWS) return ['ok' => false, 'error' => 'too_many'];
  $courses = []; foreach (abd_q('SELECT id, title_ar, title_en, start_date, end_date FROM abd_courses')->fetchAll() as $c) {
    foreach (['title_ar', 'title_en'] as $k) if (($c[$k] ?? '') !== '') $courses[abd_imp_norm($c[$k])] ??= $c; }
  $newCourses = []; $seen = []; $items = [];
  foreach ($rows as $i => $r) {
    $g = fn(string $k) => trim((string)($r[$map[$k]] ?? ''));
    $it = ['line' => $i + 2, 'course' => mb_substr($g('course'), 0, 190), 'start_raw' => $g('start'), 'end_raw' => $g('end'), 'email_raw' => $g('email'),
      'start' => null, 'end' => null, 'email' => null, 'course_id' => null, 'course_new' => false, 'user_id' => null, 'user_name' => '', 'errors' => [], 'notes' => [], 'status' => 'ok'];
    if ($it['course'] === '' && $it['email_raw'] === '' && $it['start_raw'] === '' && $it['end_raw'] === '') continue;   // blank line
    if ($it['course'] === '') $it['errors'][] = 'اسم الدورة فارغ';
    $it['start'] = abd_imp_date($it['start_raw']); $it['end'] = abd_imp_date($it['end_raw']);
    if ($it['start_raw'] === '') $it['errors'][] = 'تاريخ البداية فارغ'; elseif (!$it['start']) $it['errors'][] = 'تاريخ البداية غير مفهوم';
    if ($it['end_raw'] === '') $it['errors'][] = 'تاريخ النهاية فارغ'; elseif (!$it['end']) $it['errors'][] = 'تاريخ النهاية غير مفهوم';
    if ($it['start'] && $it['end'] && $it['end'] < $it['start']) $it['errors'][] = 'النهاية قبل البداية';
    $it['email'] = abd_email(abd_latin_digits($it['email_raw']));
    if (!$it['email']) $it['errors'][] = $it['email_raw'] === '' ? 'البريد فارغ' : 'البريد غير صحيح';
    if ($it['course'] !== '') {
      $key = abd_imp_norm($it['course']);
      if (isset($courses[$key])) $it['course_id'] = (int)$courses[$key]['id'];
      else $it['course_new'] = true;
    }
    if ($it['email']) {
      $u = abd_q('SELECT id, full_name_ar, full_name_en, is_active FROM abd_users WHERE email = ?', [$it['email']])->fetch();
      if (!$u) { $it['status'] = 'no_user'; }
      elseif (!(int)$u['is_active']) { $it['status'] = 'no_user'; $it['notes'][] = 'الحساب معطّل'; }
      else { $it['user_id'] = (int)$u['id']; $it['user_name'] = (string)($u['full_name_ar'] ?: $u['full_name_en']); }
    }
    if ($it['email'] && $it['course'] !== '') {
      $dk = $it['email'] . '|' . abd_imp_norm($it['course']);
      if (isset($seen[$dk])) { $it['status'] = 'dup'; $it['notes'][] = 'مكرر في الملف (السطر ' . $seen[$dk] . ')'; } else $seen[$dk] = $it['line'];
    }
    if ($it['errors']) $it['status'] = 'error';
    if ($it['status'] === 'ok' && $it['course_id'] && $it['user_id']) {
      $ex = abd_q('SELECT start_date, end_date FROM abd_enrollments WHERE user_id = ? AND course_id = ?', [$it['user_id'], $it['course_id']])->fetch();
      if ($ex) { $it['status'] = 'update'; $it['notes'][] = 'مسجّل مسبقًا — ستُحدَّث التواريخ' . (($ex['end_date'] ?? null) !== $it['end'] ? ' ويُعاد جدولة البريد' : ''); }
    }
    if ($it['course_new'] && in_array($it['status'], ['ok', 'update'], true)) $newCourses[abd_imp_norm($it['course'])] ??= ['start' => $it['start'], 'end' => $it['end']];
    $items[] = $it;
  }
  $count = fn(string $s) => count(array_filter($items, fn($x) => $x['status'] === $s));
  return ['ok' => true, 'items' => $items, 'new_courses' => $newCourses, 'summary' => ['total' => count($items), 'ok' => $count('ok'), 'update' => $count('update'),
    'no_user' => $count('no_user'), 'error' => $count('error'), 'dup' => $count('dup'),
    'new_courses' => count(array_unique(array_map(fn($x) => abd_imp_norm($x['course']), array_filter($items, fn($x) => $x['course_new'] && in_array($x['status'], ['ok', 'update'], true))))) ]];
}

/** Apply an analysis in ONE transaction: create missing courses (dates from their first row), upsert enrollments with dates. */
function abd_imp_commit(array $a): array {
  $pdo = abd_db(); $made = []; $enr = 0; $upd = 0;
  $pdo->beginTransaction();
  try {
    foreach ($a['items'] as $it) {
      if (!in_array($it['status'], ['ok', 'update'], true)) continue;
      $cid = $it['course_id'];
      if (!$cid) {
        $key = abd_imp_norm($it['course']);
        if (!isset($made[$key])) {
          $cd = $a['new_courses'][$key] ?? ['start' => $it['start'], 'end' => $it['end']];
          $isAr = (bool)preg_match('/\p{Arabic}/u', $it['course']);
          $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($it['course'])) ?? '', '-');
          $slug = substr(($slug !== '' ? $slug : 'course') . '-' . str_replace('-', '', (string)$cd['start']) . '-' . bin2hex(random_bytes(2)), 0, 80);
          abd_q('INSERT INTO abd_courses (slug, title_ar, title_en, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, 1)',
            [$slug, $it['course'], $isAr ? '' : $it['course'], $cd['start'], $cd['end']]);
          $made[$key] = (int)$pdo->lastInsertId();
        }
        $cid = $made[$key];
      }
      $old = abd_q('SELECT id, end_date FROM abd_enrollments WHERE user_id = ? AND course_id = ? FOR UPDATE', [$it['user_id'], $cid])->fetch();
      if ($old) {
        $rearm = ($old['end_date'] ?? null) !== $it['end'];
        abd_q("UPDATE abd_enrollments SET status = IF(status = 'cancelled', 'active', status), start_date = ?, end_date = ?"
          . ($rearm ? ', exam_mail_sent_at = NULL, exam_mail_attempts = 0, exam_mail_last_error = NULL' : '') . ' WHERE id = ?', [$it['start'], $it['end'], $old['id']]);
        $upd++;
      } else {
        abd_q("INSERT INTO abd_enrollments (user_id, course_id, status, start_date, end_date) VALUES (?, ?, 'active', ?, ?)", [$it['user_id'], $cid, $it['start'], $it['end']]);
        $enr++;
      }
    }
    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
  foreach ($a['items'] as $it) if ($it['status'] === 'ok' && $it['user_id']) { try { abd_crm_on_enroll((int)$it['user_id'], (int)($it['course_id'] ?: $made[abd_imp_norm($it['course'])] ?? 0)); } catch (Throwable $e) { error_log('[abad-import] crm ' . $e->getMessage()); } }
  return ['courses' => count($made), 'enrolled' => $enr, 'updated' => $upd];
}
