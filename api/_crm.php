<?php
// ABAD CRM library: contacts/leads, pipeline stages, activities, follow-up tasks, reports.
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }
require_once __DIR__ . '/_db.php';

const ABD_CRM_STAGES = ['new' => 'عميل محتمل جديد', 'contacted' => 'تم التواصل', 'interested' => 'مهتم', 'registered' => 'مسجّل',
  'paid' => 'مدفوع', 'completed' => 'مكتمل', 'lost' => 'مفقود'];
const ABD_CRM_SOURCES = ['booking' => 'نموذج الحجز', 'website' => 'الموقع', 'whatsapp' => 'واتساب', 'walk-in' => 'زيارة المعهد',
  'phone' => 'اتصال هاتفي', 'referral' => 'توصية', 'other' => 'أخرى'];
const ABD_CRM_ACTS = ['call' => '📞 مكالمة', 'whatsapp' => '💬 واتساب', 'note' => '📝 ملاحظة', 'meeting' => '🤝 اجتماع', 'email' => '✉️ بريد',
  'stage' => '🔀 تغيير المرحلة', 'system' => '⚙️ النظام'];
const ABD_CRM_WON = ['paid', 'completed'];

function abd_crm_tags(string $s): string {
  $t = array_unique(array_filter(array_map(fn($x) => mb_substr(trim($x), 0, 40), preg_split('/[,،\n]+/u', $s) ?: [])));
  return mb_substr(implode(',', array_slice($t, 0, 20)), 0, 500);
}

/** Validate a contact payload. Returns [data, invalidFields]. */
function abd_crm_validate(array $b, bool $partial = false): array {
  $d = []; $bad = [];
  $has = fn($k) => !$partial || array_key_exists($k, $b);
  if ($has('full_name')) { $d['full_name'] = abd_str($b, 'full_name'); if ($d['full_name'] === '') $bad[] = 'full_name'; }
  if ($has('phone')) { $r = abd_str($b, 'phone', 32); $d['phone'] = $r === '' ? null : (abd_phone($r) ?? (preg_match('/^\+?[0-9 ]{6,20}$/', abd_latin_digits($r)) ? preg_replace('/\s/', '', abd_latin_digits($r)) : false)); if ($d['phone'] === false) $bad[] = 'phone'; }
  if ($has('email')) { $r = abd_str($b, 'email'); $d['email'] = $r === '' ? null : (abd_email($r) ?? false); if ($d['email'] === false) $bad[] = 'email'; }
  if ($has('source')) { $d['source'] = abd_str($b, 'source', 20); if (!isset(ABD_CRM_SOURCES[$d['source']])) $d['source'] = 'other'; }
  if ($has('interest')) { $d['interest'] = abd_str($b, 'interest', 255) ?: null; }
  if ($has('stage')) { $d['stage'] = abd_str($b, 'stage', 20); if (!$partial && $d['stage'] === '') $d['stage'] = 'new'; if (!isset(ABD_CRM_STAGES[$d['stage']])) $bad[] = 'stage'; }
  if ($has('owner_id')) { $o = (int)($b['owner_id'] ?? 0); $d['owner_id'] = $o > 0 ? $o : null;
    if ($o > 0 && !abd_q("SELECT 1 FROM abd_users WHERE id = ? AND role IN ('admin','staff')", [$o])->fetchColumn()) $bad[] = 'owner_id'; }
  if ($has('tags')) $d['tags'] = abd_crm_tags(abd_str($b, 'tags', 1000));
  if ($has('notes')) $d['notes'] = abd_str($b, 'notes', 4000) ?: null;
  if ($has('lost_reason')) $d['lost_reason'] = abd_str($b, 'lost_reason', 255) ?: null;
  if (!$partial && empty($d['phone']) && empty($d['email']) && !$bad) $bad[] = 'phone';
  return [$d, $bad];
}

function abd_crm_log(int $cid, string $type, string $body, ?int $by): void {
  if (!isset(ABD_CRM_ACTS[$type])) $type = 'note';
  abd_q('INSERT INTO abd_crm_activities (contact_id, type, body, created_by) VALUES (?,?,?,?)', [$cid, $type, mb_substr($body, 0, 4000), $by]);
}

function abd_crm_create(array $d, ?int $by): int {
  $d += ['stage' => 'new', 'source' => 'other', 'tags' => '', 'interest' => null, 'owner_id' => null, 'notes' => null, 'lost_reason' => null, 'phone' => null, 'email' => null];
  abd_q('INSERT INTO abd_crm_contacts (full_name, phone, email, source, interest, stage, owner_id, tags, notes, lost_reason, won_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)', [$d['full_name'], $d['phone'], $d['email'], $d['source'], $d['interest'], $d['stage'], $d['owner_id'],
         $d['tags'], $d['notes'], $d['lost_reason'], in_array($d['stage'], ABD_CRM_WON, true) ? date('Y-m-d H:i:s') : null]);
  $id = (int)abd_db()->lastInsertId();
  abd_crm_log($id, 'system', 'تم إنشاء جهة الاتصال (المصدر: ' . (ABD_CRM_SOURCES[$d['source']] ?? $d['source']) . ')', $by);
  return $id;
}

/** Partial update; logs stage changes. Returns false when not found. */
function abd_crm_update(int $id, array $d, ?int $by): bool {
  $cur = abd_q('SELECT stage FROM abd_crm_contacts WHERE id = ?', [$id])->fetch();
  if (!$cur) return false;
  if (!$d) return true;
  $set = []; $p = [];
  foreach ($d as $k => $v) { $set[] = "`$k` = ?"; $p[] = $v; }
  if (isset($d['stage']) && $d['stage'] !== $cur['stage']) {
    $set[] = 'stage_changed_at = NOW()';
    if (in_array($d['stage'], ABD_CRM_WON, true)) $set[] = 'won_at = COALESCE(won_at, NOW())';
  }
  $p[] = $id;
  abd_q('UPDATE abd_crm_contacts SET ' . implode(', ', $set) . ' WHERE id = ?', $p);
  if (isset($d['stage']) && $d['stage'] !== $cur['stage'])
    abd_crm_log($id, 'stage', (ABD_CRM_STAGES[$cur['stage']] ?? $cur['stage']) . ' ← ' . ABD_CRM_STAGES[$d['stage']], $by);
  return true;
}

/** Find an existing contact by phone or email. */
function abd_crm_find(?string $phone, ?string $email): ?int {
  if (!$phone && !$email) return null;
  $id = abd_q('SELECT id FROM abd_crm_contacts WHERE (phone = ? AND phone IS NOT NULL) OR (email = ? AND email IS NOT NULL) ORDER BY id LIMIT 1',
    [$phone ?? '', $email ?? ''])->fetchColumn();
  return $id ? (int)$id : null;
}

/** Called after every booking insert: create the lead or attach the booking to the existing one. Never throws. */
function abd_crm_from_booking(int $bookingId, array $d, string $ref, ?int $userId): void {
  try {
    $interest = trim($d['vendor'] . ' — ' . $d['cert'], ' —');
    $cid = abd_crm_find($d['phone'], $d['email']);
    if ($cid === null) {
      $cid = abd_crm_create(['full_name' => $d['name_en'], 'phone' => $d['phone'], 'email' => $d['email'], 'source' => 'booking',
        'interest' => mb_substr($interest, 0, 255), 'tags' => 'حجز اختبار'], null);
      abd_q('UPDATE abd_crm_contacts SET booking_id = ?, user_id = COALESCE(user_id, ?) WHERE id = ?', [$bookingId, $userId, $cid]);
    } else {
      abd_q("UPDATE abd_crm_contacts SET booking_id = ?, interest = ?, user_id = COALESCE(user_id, ?),
             stage = IF(stage = 'lost', 'new', stage) WHERE id = ?", [$bookingId, mb_substr($interest, 0, 255), $userId, $cid]);
    }
    abd_crm_log($cid, 'system', "طلب حجز جديد $ref: $interest — التاريخ المفضّل {$d['preferred_date']}", null);
  } catch (Throwable $e) { error_log('[abad-crm] booking->lead: ' . $e->getMessage()); }
}

/** Called after registration: link matching leads to the new user (or create a website lead). Never throws. */
function abd_crm_from_register(int $userId, string $name, ?string $email, ?string $phone): void {
  try {
    $ids = abd_q('SELECT id FROM abd_crm_contacts WHERE user_id IS NULL AND ((email = ? AND email IS NOT NULL) OR (phone = ? AND phone IS NOT NULL))',
      [$email ?? '', $phone ?? ''])->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) {
      $cid = abd_crm_create(['full_name' => $name !== '' ? $name : (string)$email, 'phone' => $phone, 'email' => $email, 'source' => 'website', 'tags' => 'حساب منصة'], null);
      $ids = [$cid];
    }
    foreach ($ids as $cid) {
      abd_q('UPDATE abd_crm_contacts SET user_id = ? WHERE id = ?', [$userId, $cid]);
      abd_crm_log((int)$cid, 'system', 'أنشأ حساباً على المنصة (مستخدم #' . $userId . ')', null);
    }
  } catch (Throwable $e) { error_log('[abad-crm] register->lead: ' . $e->getMessage()); }
}

/** Contact list with filters: q, stage, source, owner_id, tag. */
function abd_crm_list(array $f, int $page = 1, int $per = 50): array {
  $w = []; $p = [];
  if (($q = trim((string)($f['q'] ?? ''))) !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $w[] = '(c.full_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.interest LIKE ? OR c.tags LIKE ?)'; array_push($p, $like, $like, $like, $like, $like);
  }
  if (isset(ABD_CRM_STAGES[$f['stage'] ?? ''])) { $w[] = 'c.stage = ?'; $p[] = $f['stage']; }
  if (isset(ABD_CRM_SOURCES[$f['source'] ?? ''])) { $w[] = 'c.source = ?'; $p[] = $f['source']; }
  if (($o = (string)($f['owner_id'] ?? '')) !== '') { if ($o === 'none') $w[] = 'c.owner_id IS NULL'; else { $w[] = 'c.owner_id = ?'; $p[] = (int)$o; } }
  if (($t = trim((string)($f['tag'] ?? ''))) !== '') { $w[] = 'FIND_IN_SET(?, c.tags) > 0'; $p[] = $t; }
  $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
  $total = (int)abd_q("SELECT COUNT(*) FROM abd_crm_contacts c $where", $p)->fetchColumn();
  $page = max(1, $page); $per = max(1, min(500, $per)); $off = ($page - 1) * $per;
  $rows = abd_q("SELECT c.*, o.full_name_ar AS owner_name, o.email AS owner_email,
      (SELECT MIN(t.due_at) FROM abd_crm_tasks t WHERE t.contact_id = c.id AND t.done_at IS NULL) AS next_due,
      (SELECT MAX(a.created_at) FROM abd_crm_activities a WHERE a.contact_id = c.id) AS last_activity
      FROM abd_crm_contacts c LEFT JOIN abd_users o ON o.id = c.owner_id $where
      ORDER BY c.updated_at DESC, c.id DESC LIMIT $per OFFSET $off", $p)->fetchAll();
  return ['total' => $total, 'page' => $page, 'per_page' => $per, 'items' => $rows];
}

function abd_crm_get(int $id): ?array {
  $c = abd_q('SELECT c.*, o.full_name_ar AS owner_name, u.email AS user_email, u.full_name_ar AS user_name, b.ref AS booking_ref
              FROM abd_crm_contacts c LEFT JOIN abd_users o ON o.id = c.owner_id LEFT JOIN abd_users u ON u.id = c.user_id
              LEFT JOIN abd_bookings b ON b.id = c.booking_id WHERE c.id = ?', [$id])->fetch();
  if (!$c) return null;
  $c['activities'] = abd_q('SELECT a.id, a.type, a.body, a.created_at, u.full_name_ar AS by_name FROM abd_crm_activities a
                            LEFT JOIN abd_users u ON u.id = a.created_by WHERE a.contact_id = ? ORDER BY a.created_at DESC, a.id DESC LIMIT 300', [$id])->fetchAll();
  $c['tasks'] = abd_q('SELECT t.*, u.full_name_ar AS assignee FROM abd_crm_tasks t LEFT JOIN abd_users u ON u.id = t.assigned_to
                       WHERE t.contact_id = ? ORDER BY t.done_at IS NOT NULL, t.due_at', [$id])->fetchAll();
  $c['bookings'] = abd_q('SELECT id, ref, vendor, cert, preferred_date, status, created_at FROM abd_bookings
                          WHERE (phone = ? AND ? <> \'\') OR (email = ? AND ? <> \'\') ORDER BY created_at DESC LIMIT 50',
                          [$c['phone'] ?? '', $c['phone'] ?? '', $c['email'] ?? '', $c['email'] ?? ''])->fetchAll();
  return $c;
}

function abd_crm_staff(): array {
  return abd_q("SELECT id, full_name_ar, full_name_en, email, role FROM abd_users WHERE role IN ('admin','staff') AND is_active = 1 ORDER BY full_name_ar, email")->fetchAll();
}

function abd_crm_task_add(?int $cid, string $title, string $due, ?int $assigned, ?int $by): array {
  $title = mb_substr(trim($title), 0, 255);
  $due = str_replace('T', ' ', trim($due));
  $dt = DateTime::createFromFormat('Y-m-d H:i', substr($due, 0, 16)) ?: DateTime::createFromFormat('!Y-m-d', substr($due, 0, 10));
  if ($title === '') return [false, 'title'];
  if (!$dt) return [false, 'due_at'];
  if ($cid !== null && !abd_q('SELECT 1 FROM abd_crm_contacts WHERE id = ?', [$cid])->fetchColumn()) return [false, 'not_found'];
  if ($assigned !== null && !abd_q("SELECT 1 FROM abd_users WHERE id = ? AND role IN ('admin','staff')", [$assigned])->fetchColumn()) return [false, 'assigned_to'];
  abd_q('INSERT INTO abd_crm_tasks (contact_id, title, due_at, assigned_to, created_by) VALUES (?,?,?,?,?)', [$cid, $title, $dt->format('Y-m-d H:i:00'), $assigned, $by]);
  $tid = (int)abd_db()->lastInsertId();
  if ($cid) abd_crm_log($cid, 'system', 'مهمة متابعة: ' . $title . ' — ' . $dt->format('Y-m-d H:i'), $by);
  return [true, $tid];
}
function abd_crm_task_done(int $tid, bool $done, ?int $by): bool {
  $t = abd_q('SELECT contact_id, title FROM abd_crm_tasks WHERE id = ?', [$tid])->fetch();
  if (!$t) return false;
  abd_q('UPDATE abd_crm_tasks SET done_at = ' . ($done ? 'NOW()' : 'NULL') . ' WHERE id = ?', [$tid]);
  if ($done && $t['contact_id']) abd_crm_log((int)$t['contact_id'], 'system', '✔ أُنجزت المهمة: ' . $t['title'], $by);
  return true;
}

/** Open tasks: overdue / today / upcoming (optionally only those assigned to $uid). */
function abd_crm_tasks_open(?int $uid = null, int $limit = 100): array {
  $w = 't.done_at IS NULL'; $p = [];
  if ($uid) { $w .= ' AND (t.assigned_to = ? OR t.assigned_to IS NULL)'; $p[] = $uid; }
  return abd_q("SELECT t.*, c.full_name AS contact_name, c.phone AS contact_phone, c.stage AS contact_stage, u.full_name_ar AS assignee,
      (t.due_at < NOW()) AS overdue, (DATE(t.due_at) = CURDATE()) AS today
      FROM abd_crm_tasks t LEFT JOIN abd_crm_contacts c ON c.id = t.contact_id LEFT JOIN abd_users u ON u.id = t.assigned_to
      WHERE $w ORDER BY t.due_at LIMIT " . (int)$limit, $p)->fetchAll();
}

/** Reports: per stage, per source (with conversion), monthly (last 12 months), per owner. */
function abd_crm_reports(): array {
  $won = "'" . implode("','", ABD_CRM_WON) . "'";
  $stage = abd_q('SELECT stage, COUNT(*) n FROM abd_crm_contacts GROUP BY stage')->fetchAll(PDO::FETCH_KEY_PAIR);
  $source = abd_q("SELECT source, COUNT(*) n, SUM(stage IN ($won)) won, SUM(stage = 'lost') lost FROM abd_crm_contacts GROUP BY source ORDER BY n DESC")->fetchAll();
  $monthly = abd_q("SELECT m.ym, COALESCE(n.c,0) leads, COALESCE(w.c,0) won FROM (
        SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL k MONTH), '%Y-%m') ym FROM (
          SELECT 0 k UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL
          SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11) ks) m
      LEFT JOIN (SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COUNT(*) c FROM abd_crm_contacts GROUP BY ym) n ON n.ym = m.ym
      LEFT JOIN (SELECT DATE_FORMAT(won_at, '%Y-%m') ym, COUNT(*) c FROM abd_crm_contacts WHERE won_at IS NOT NULL GROUP BY ym) w ON w.ym = m.ym
      ORDER BY m.ym")->fetchAll();
  $owner = abd_q("SELECT COALESCE(u.full_name_ar, u.email, '— بدون مسؤول —') owner, COUNT(*) n, SUM(c.stage IN ($won)) won
      FROM abd_crm_contacts c LEFT JOIN abd_users u ON u.id = c.owner_id GROUP BY c.owner_id, owner ORDER BY n DESC")->fetchAll();
  $total = array_sum(array_map('intval', $stage));
  $wonN = 0; foreach (ABD_CRM_WON as $s) $wonN += (int)($stage[$s] ?? 0);
  $closed = $wonN + (int)($stage['lost'] ?? 0);
  return ['total' => $total, 'won' => $wonN, 'lost' => (int)($stage['lost'] ?? 0),
    'conversion' => $total ? round($wonN / $total * 100, 1) : 0.0, 'win_rate' => $closed ? round($wonN / $closed * 100, 1) : 0.0,
    'by_stage' => $stage, 'by_source' => $source, 'monthly' => $monthly, 'by_owner' => $owner];
}

/** Course enrollment → move the user's open leads to "registered" (never backwards). Never throws. */
function abd_crm_on_enroll(int $userId, int $courseId): void {
  try {
    $title = (string)abd_q('SELECT title_ar FROM abd_courses WHERE id = ?', [$courseId])->fetchColumn();
    foreach (abd_q("SELECT id, stage FROM abd_crm_contacts WHERE user_id = ?", [$userId])->fetchAll() as $c) {
      if (in_array($c['stage'], ['new', 'contacted', 'interested', 'lost'], true)) abd_crm_update((int)$c['id'], ['stage' => 'registered'], null);
      abd_crm_log((int)$c['id'], 'system', 'تم التسجيل في الدورة: ' . $title, null);
    }
  } catch (Throwable $e) { error_log('[abad-crm] enroll: ' . $e->getMessage()); }
}
