<?php
// Course-end exam-booking email (حجز الاختبار): template + pluggable transport + daily selection.
// Transport is chosen in …/private_html/abad-config/mail.php (never hard-coded here):
//   'EXAM_MAIL_DRIVER'   => 'log' | 'brevo' | 'smtp'   (default 'log' = write to log only, sends nothing)
//   'BREVO_API_KEY'      => 'xkeysib-…'                (Brevo transactional API key — only used when driver = brevo)
//   'EXAM_MAIL_FROM'     => 'noreply@abad-labs.com'    (must be a sender verified in Brevo; falls back to SMTP_FROM)
//   'EXAM_MAIL_FROM_NAME'=> 'معهد شبكة آباد للتدريب'
//   'EXAM_MAIL_BCC'      => ''                          (optional copy for the institute)
// Env vars with the same names override (local testing). The API key is never logged or printed.
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }
require_once __DIR__ . '/_mail.php';

const ABD_EXAM_MAIL_MAX_TRIES = 5;

function abd_exam_booking_url(): string { return ABD_MAIL_SITE . 'booking.html?utm_source=course_end_mail'; }

/** Arabic date like «22 أكتوبر 2026». */
function abd_ar_date(?string $ymd): string {
  if (!$ymd || !($d = DateTime::createFromFormat('!Y-m-d', $ymd))) return '—';
  $m = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
  return (int)$d->format('j') . ' ' . $m[(int)$d->format('n') - 1] . ' ' . $d->format('Y');
}

/** Render the exam-booking email. $r: name, course_ar, course_en, start_date, end_date. Returns [subject, html, text]. */
function abd_exam_mail_render(array $r): array {
  $e = 'abd_mail_e';
  $course = (string)($r['course_ar'] ?? '');
  $m = abd_mail_render([
    'title' => 'حان وقت حجز اختبارك 🎓',
    'preheader' => 'أتممت ' . $course . ' — احجز موعد اختبارك الآن عبر منصة آباد الرقمية',
    'name' => (string)($r['name'] ?? ''),
    'intro_html' => 'نهنئك على إتمام <b>' . $e($course) . '</b> في ' . ABD_MAIL_BRAND . ' بتاريخ ' . abd_ar_date($r['end_date'] ?? null) . '. '
      . 'الخطوة التالية هي <b>حجز اختبار الشهادة</b> وأنت في أفضل جاهزية، ويتولّى فريق المعهد ترتيب الحجز معك.',
    'rows' => array_filter([
      'الدورة' => trim(($r['course_en'] ?? '') !== '' ? (string)$r['course_en'] : $course),
      'بداية الدورة' => (string)($r['start_date'] ?? ''),
      'نهاية الدورة' => (string)($r['end_date'] ?? ''),
    ], fn($v) => $v !== '' && $v !== '—'),
    'cta_text' => 'احجز اختبارك الآن', 'cta_url' => abd_exam_booking_url(),
    'note_html' => 'ننصحك بحلّ الاختبارات التجريبية في المنصة قبل موعد الاختبار. لأي استفسار عن الحجز أو المواعيد تواصل معنا عبر واتساب.',
    'security' => false,
  ]);
  return ['subject' => 'احجز اختبارك — ' . $course, 'html' => $m['html'], 'text' => $m['text']];
}

function abd_exam_mail_driver(): string {
  $d = strtolower((string)abd_mail_cfg('EXAM_MAIL_DRIVER', 'log'));
  return in_array($d, ['log', 'brevo', 'smtp'], true) ? $d : 'log';
}

/** Send through Brevo's transactional API (POST /v3/smtp/email). Throws on failure. */
function abd_brevo_tx_send(string $to, string $toName, string $subject, string $html, string $text): string {
  $key = (string)abd_mail_cfg('BREVO_API_KEY', '');
  $from = (string)abd_mail_cfg('EXAM_MAIL_FROM', (string)abd_mail_cfg('SMTP_FROM', ''));
  if ($key === '' || str_contains($key, 'PUT_') || !filter_var($from, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('brevo not configured');
  $body = ['sender' => ['email' => $from, 'name' => (string)abd_mail_cfg('EXAM_MAIL_FROM_NAME', ABD_MAIL_BRAND)],
    'to' => [['email' => $to] + ($toName !== '' ? ['name' => $toName] : [])], 'subject' => $subject, 'htmlContent' => $html, 'textContent' => $text,
    'tags' => ['exam-booking']];
  if (($bcc = (string)abd_mail_cfg('EXAM_MAIL_BCC', '')) !== '' && filter_var($bcc, FILTER_VALIDATE_EMAIL)) $body['bcc'] = [['email' => $bcc]];
  $base = rtrim((string)abd_mail_cfg('BREVO_BASE', 'https://api.brevo.com/v3'), '/');
  $ch = curl_init($base . '/smtp/email');
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['api-key: ' . $key, 'accept: application/json', 'content-type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE)]);
  $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  if ($raw === false) throw new RuntimeException('brevo transport error');
  $j = json_decode((string)$raw, true);
  if ($code < 200 || $code >= 300) throw new RuntimeException('brevo http ' . $code . (is_array($j) && isset($j['code']) ? ' ' . $j['code'] : ''));
  return (string)($j['messageId'] ?? 'ok');
}

/**
 * Deliver any platform email through a configurable driver. $driverKey names the mail.php setting
 * (e.g. 'AUTH_MAIL_DRIVER'); values 'smtp' | 'brevo' | 'log'. Returns provider id, or 'logged' for the log driver.
 * Log driver: writes only subject + masked recipient to the PHP error log. For LOCAL tests only, the env var
 * ABAD_MAIL_DUMP_DIR (never read from config) additionally saves the rendered HTML there.
 */
function abd_mail_deliver(string $driverKey, string $default, string $to, string $name, string $subject, string $html, string $text): string {
  $d = strtolower((string)abd_mail_cfg($driverKey, $default));
  if (!in_array($d, ['log', 'brevo', 'smtp'], true)) $d = 'log';
  if ($d === 'brevo') return abd_brevo_tx_send($to, $name, $subject, $html, $text);
  if ($d === 'smtp') { abd_smtp_send($to, $subject, $html, $text); return 'smtp'; }
  error_log('[abad-mail] log driver: «' . $subject . '» → ' . abd_mask_email($to));
  $dir = getenv('ABAD_MAIL_DUMP_DIR');
  if ($dir !== false && $dir !== '' && is_dir($dir)) file_put_contents(rtrim($dir, '/') . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.html', $html);
  return 'logged';
}

/** Enrollments due today (effective end date = enrollment end or course end), active, verified recipient, not yet sent. */
function abd_exam_mail_due(string $today): array {
  return abd_q("SELECT e.id, e.user_id, e.course_id, e.exam_mail_attempts, u.email, u.full_name_ar, u.full_name_en,
      c.title_ar, c.title_en, COALESCE(e.start_date, c.start_date) AS start_date, COALESCE(e.end_date, c.end_date) AS end_date
    FROM abd_enrollments e JOIN abd_users u ON u.id = e.user_id JOIN abd_courses c ON c.id = e.course_id
    WHERE e.exam_mail_sent_at IS NULL AND e.status <> 'cancelled' AND u.is_active = 1 AND u.email_verified_at IS NOT NULL
      AND COALESCE(e.end_date, c.end_date) = ? AND e.exam_mail_attempts < ?
    ORDER BY e.id", [$today, ABD_EXAM_MAIL_MAX_TRIES])->fetchAll();
}
