<?php
// Branded, email-safe HTML layout for all platform emails (table-based, inline CSS, RTL, Gmail/Outlook/iOS Mail).
// Presentation only — no sending logic here. Used by _mail.php.
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }

const ABD_MAIL_BRAND   = 'معهد شبكة آباد للتدريب';
const ABD_MAIL_PLATFORM = 'منصة آباد الرقمية';
const ABD_MAIL_SITE    = 'https://abad-labs.com/abad-digital/';
const ABD_MAIL_LOGO    = 'https://abad-labs.com/abad-digital/assets/abad-logo.png';
const ABD_MAIL_WA      = '966561393960';   // same support number the site uses (window.ABAD_SUPPORT.wa)

function abd_mail_e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function abd_mail_wa_display(): string { return '+' . substr(ABD_MAIL_WA, 0, 3) . ' ' . substr(ABD_MAIL_WA, 3, 2) . ' ' . substr(ABD_MAIL_WA, 5, 3) . ' ' . substr(ABD_MAIL_WA, 8); }

/**
 * Render a branded email.
 * $o keys: title, preheader, name, intro_html (trusted HTML), code (optional), expiry_min (int, optional),
 *          rows ([label => value], optional, shown LTR), cta_text/cta_url (optional), note_html (optional), security (bool, default true when code)
 * Returns ['html' => ..., 'text' => ...].
 */
function abd_mail_render(array $o): array {
  $F = "font-family:Cairo,Tahoma,Arial,sans-serif;";
  $title = (string)($o['title'] ?? ''); $pre = (string)($o['preheader'] ?? $title);
  $name = trim((string)($o['name'] ?? '')); $name = $name !== '' ? $name : 'متدربنا العزيز';
  $code = (string)($o['code'] ?? ''); $exp = isset($o['expiry_min']) ? (int)$o['expiry_min'] : 0;
  $rows = (array)($o['rows'] ?? []); $sec = (bool)($o['security'] ?? ($code !== ''));
  $e = 'abd_mail_e';

  $b = '';
  $b .= '<p style="margin:0 0 12px;' . $F . 'font-size:16px;line-height:1.7;color:#10293b">مرحبًا ' . $e($name) . '،</p>';
  $b .= '<p style="margin:0 0 20px;' . $F . 'font-size:15px;line-height:1.9;color:#33475b">' . (string)($o['intro_html'] ?? '') . '</p>';
  if ($code !== '') {
    $b .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" style="padding:0 0 8px">'
      . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" bgcolor="#fff8e1" style="background:#fff8e1;border:2px dashed #F5BD2D;border-radius:14px;padding:16px 26px">'
      . '<div style="font-family:Consolas,Menlo,\'Courier New\',monospace;font-size:38px;line-height:44px;font-weight:700;letter-spacing:12px;color:#10293b;direction:ltr;text-align:center;-webkit-user-select:all;user-select:all">' . $e($code) . '</div>'
      . '</td></tr></table></td></tr>';
    if ($exp > 0) $b .= '<tr><td align="center" style="padding:4px 0 20px;' . $F . 'font-size:14px;color:#3f86b3;font-weight:700">⏱ صالح لمدة ' . $exp . ' دقائق ويُستخدم مرة واحدة فقط</td></tr>';
    $b .= '</table>';
  }
  if ($rows) {
    $b .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#eef4f9" style="background:#eef4f9;border-right:4px solid #3f86b3;border-radius:10px;margin:0 0 20px">';
    foreach ($rows as $k => $v) $b .= '<tr><td style="padding:10px 16px;' . $F . 'font-size:13px;color:#5d7183;width:38%">' . $e((string)$k) . '</td>'
      . '<td style="padding:10px 16px;font-family:Consolas,Menlo,monospace;font-size:15px;font-weight:700;color:#10293b;direction:ltr;text-align:left">' . $e((string)$v) . '</td></tr>';
    $b .= '</table>';
  }
  if (!empty($o['cta_url'])) {
    $b .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 22px"><tr><td align="center" bgcolor="#3f86b3" style="background:#3f86b3;border-radius:10px">'
      . '<a href="' . $e((string)$o['cta_url']) . '" style="display:inline-block;padding:13px 30px;' . $F . 'font-size:15px;font-weight:700;color:#ffffff;text-decoration:none">' . $e((string)($o['cta_text'] ?? 'افتح المنصة')) . '</a></td></tr></table>';
  }
  if (!empty($o['note_html'])) $b .= '<p style="margin:0 0 18px;' . $F . 'font-size:14px;line-height:1.8;color:#33475b">' . $o['note_html'] . '</p>';
  if ($sec) {
    $b .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#fdf3f2" style="background:#fdf3f2;border-radius:10px"><tr><td style="padding:12px 16px;' . $F . 'font-size:13px;line-height:1.8;color:#8a2b22">'
      . '🔒 <b>تنبيه أمني:</b> لا تشارك الكود مع أحد — فريق آباد لن يطلبه منك أبدًا.<br>إذا لم تطلب هذا فتجاهل الرسالة.</td></tr></table>';
  }

  $wa = 'https://wa.me/' . ABD_MAIL_WA;
  $html = '<!DOCTYPE html><html lang="ar" dir="rtl" xmlns="http://www.w3.org/1999/xhtml"><head><meta charset="UTF-8">'
    . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="x-apple-disable-message-reformatting">'
    . '<meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark"><title>' . $e($title) . '</title>'
    . '<style>@media (max-width:600px){.abd-c{width:100%!important;border-radius:0!important}.abd-p{padding:22px 18px!important}}'
    . '@media (prefers-color-scheme:dark){.abd-bg{background:#0b1b27!important}.abd-c,.abd-p{background:#13283a!important}.abd-p p,.abd-p td{color:#e6eef5!important}}</style></head>'
    . '<body style="margin:0;padding:0;background:#eef3f7;-webkit-text-size-adjust:100%">'
    . '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#eef3f7">' . $e($pre) . str_repeat('&#8204;&nbsp;', 60) . '</div>'
    . '<table role="presentation" class="abd-bg" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#eef3f7" style="background:#eef3f7"><tr><td align="center" style="padding:24px 10px">'
    . '<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->'
    . '<table role="presentation" class="abd-c" width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:600px;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;direction:rtl;text-align:right">'
    // header: logo on white (logo has dark text), then navy title band with gold rule
    . '<tr><td align="center" bgcolor="#ffffff" style="background:#ffffff;padding:22px 24px 16px"><a href="' . ABD_MAIL_SITE . '"><img src="' . ABD_MAIL_LOGO . '" width="150" alt="' . ABD_MAIL_BRAND . '" style="display:block;width:150px;max-width:150px;height:auto;border:0"></a></td></tr>'
    . '<tr><td bgcolor="#10293b" style="background:#10293b;padding:18px 28px;border-bottom:4px solid #F5BD2D">'
    . '<div style="' . $F . 'font-size:13px;color:#9cc7e3;line-height:1.6">' . ABD_MAIL_PLATFORM . '</div>'
    . '<div style="' . $F . 'font-size:22px;font-weight:700;color:#ffffff;line-height:1.5">' . $e($title) . '</div></td></tr>'
    . '<tr><td class="abd-p" style="padding:28px 28px 24px;background:#ffffff">' . $b . '</td></tr>'
    . '<tr><td bgcolor="#10293b" style="background:#10293b;padding:18px 28px;' . $F . 'font-size:12px;line-height:1.9;color:#c7d6e2;text-align:center">'
    . '<b style="color:#F5BD2D;font-size:14px">' . ABD_MAIL_BRAND . '</b> — الرياض<br>'
    . '<a href="' . ABD_MAIL_SITE . '" style="color:#9cc7e3;text-decoration:underline">abad-labs.com/abad-digital</a> &nbsp;|&nbsp; '
    . 'واتساب: <a href="' . $wa . '" style="color:#9cc7e3;text-decoration:underline"><span dir="ltr">' . abd_mail_wa_display() . '</span></a><br>'
    . '<span style="color:#8aa3b6">رسالة آلية — يُرجى عدم الرد عليها.</span></td></tr>'
    . '</table><!--[if mso]></td></tr></table><![endif]--></td></tr></table></body></html>';

  // plain-text alternative
  $t = ABD_MAIL_BRAND . ' — ' . ABD_MAIL_PLATFORM . "\n" . $title . "\n\nمرحبًا {$name}،\n"
    . trim(html_entity_decode(strip_tags((string)($o['intro_html'] ?? '')), ENT_QUOTES, 'UTF-8')) . "\n";
  if ($code !== '') $t .= "\nالكود: $code\n" . ($exp > 0 ? "صالح لمدة $exp دقائق ويُستخدم مرة واحدة فقط.\n" : '');
  foreach ($rows as $k => $v) $t .= "$k: $v\n";
  if (!empty($o['cta_url'])) $t .= "\n" . ($o['cta_text'] ?? 'افتح المنصة') . ': ' . $o['cta_url'] . "\n";
  if (!empty($o['note_html'])) $t .= "\n" . trim(html_entity_decode(strip_tags((string)$o['note_html']), ENT_QUOTES, 'UTF-8')) . "\n";
  if ($sec) $t .= "\nتنبيه أمني: لا تشارك الكود مع أحد. إذا لم تطلب هذا فتجاهل الرسالة.\n";
  $t .= "\n--\n" . ABD_MAIL_BRAND . "\n" . ABD_MAIL_SITE . "\nواتساب: " . abd_mail_wa_display() . "\n";
  return ['html' => $html, 'text' => $t];
}

/** Welcome email content (template only; the platform does not currently send a welcome email). */
function abd_welcome_mail_render(string $name): array {
  return abd_mail_render(['title' => 'أهلًا بك في منصة آباد الرقمية', 'preheader' => 'تم تفعيل حسابك — ابدأ رحلتك التدريبية الآن', 'name' => $name,
    'intro_html' => 'تم تفعيل حسابك بنجاح في <b>منصة آباد الرقمية</b>. يمكنك الآن الوصول إلى محاكيات الاختبارات والمعامل العملية وحجز مقاعد التدريب.',
    'cta_text' => 'ادخل إلى المنصة', 'cta_url' => ABD_MAIL_SITE, 'security' => false,
    'note_html' => 'لأي استفسار تواصل معنا عبر واتساب، ويسعدنا خدمتك.']);
}
