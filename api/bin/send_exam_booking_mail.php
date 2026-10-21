<?php
// CLI only. Daily sender for the course-end exam-booking email (حجز الاختبار).
//   php api/bin/send_exam_booking_mail.php            DRY RUN (default): lists who would get the email, sends nothing, writes nothing to the DB
//   php api/bin/send_exam_booking_mail.php --send     really send (driver from abad-config/mail.php EXAM_MAIL_DRIVER; 'log' never sends)
//   --date=YYYY-MM-DD   treat that day as "today" (default: today in Asia/Riyadh)
//   --preview=FILE      also write the first rendered email HTML to FILE
// Idempotent: a row is claimed atomically (exam_mail_sent_at set before sending, cleared again on failure),
// so overlapping runs or reruns never double-send. Log: …/private_html/abad-config/../abad-logs/exam-mail.log (fallback: stderr).
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/_exam_mail.php';

$opt = getopt('', ['send', 'date:', 'preview:']);
$send = isset($opt['send']);
$today = (string)($opt['date'] ?? (new DateTime('now', new DateTimeZone('Asia/Riyadh')))->format('Y-m-d'));
if (!abd_date($today)) { fwrite(STDERR, "bad --date\n"); exit(2); }
$driver = abd_exam_mail_driver();

function xlog(string $m): void {
  static $f = null;
  if ($f === null) {
    $f = false;
    $pos = strpos(__DIR__, '/public_html');
    if ($pos !== false) { $d = substr(__DIR__, 0, $pos) . '/private_html/abad-logs'; if ((is_dir($d) || @mkdir($d, 0750, true)) && is_writable($d)) $f = $d . '/exam-mail.log'; }
  }
  $line = '[' . date('Y-m-d H:i:s') . ' +03] ' . $m . "\n";
  echo $line;
  if ($f) @file_put_contents($f, $line, FILE_APPEND | LOCK_EX);
}

$lock = fopen(sys_get_temp_dir() . '/abad-exam-mail.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { xlog('another run in progress — exit'); exit(0); }

$rows = abd_exam_mail_due($today);
xlog(sprintf('start date=%s mode=%s driver=%s due=%d', $today, $send ? 'SEND' : 'DRY-RUN', $driver, count($rows)));
$ok = $fail = 0;
foreach ($rows as $i => $r) {
  $name = (string)($r['full_name_ar'] ?: $r['full_name_en']);
  $m = abd_exam_mail_render(['name' => $name, 'course_ar' => $r['title_ar'], 'course_en' => $r['title_en'], 'start_date' => $r['start_date'], 'end_date' => $r['end_date']]);
  if ($i === 0 && !empty($opt['preview'])) file_put_contents((string)$opt['preview'], $m['html']);
  $who = 'enr#' . $r['id'] . ' ' . abd_mask_email((string)$r['email']);
  if (!$send || $driver === 'log') { xlog(($send ? 'LOG-DRIVER (not sent) ' : 'would send ') . $who . ' «' . $m['subject'] . '»'); continue; }
  // claim the row: only one run can flip NULL → NOW()
  if (abd_q('UPDATE abd_enrollments SET exam_mail_sent_at = NOW(), exam_mail_attempts = exam_mail_attempts + 1 WHERE id = ? AND exam_mail_sent_at IS NULL', [$r['id']])->rowCount() !== 1) {
    xlog('skip (already claimed) ' . $who); continue;
  }
  try {
    $id = $driver === 'brevo' ? abd_brevo_tx_send((string)$r['email'], $name, $m['subject'], $m['html'], $m['text'])
      : (abd_smtp_send((string)$r['email'], $m['subject'], $m['html'], $m['text']) ? 'smtp' : '');
    abd_q('UPDATE abd_enrollments SET exam_mail_last_error = NULL WHERE id = ?', [$r['id']]);
    xlog('sent ' . $who . ' id=' . $id); $ok++;
  } catch (Throwable $e) {
    abd_q('UPDATE abd_enrollments SET exam_mail_sent_at = NULL, exam_mail_last_error = ? WHERE id = ?', [mb_substr($e->getMessage(), 0, 190), $r['id']]);
    xlog('FAIL ' . $who . ' ' . $e->getMessage()); $fail++;
  }
}
xlog("done sent=$ok failed=$fail");
exit($fail ? 1 : 0);
