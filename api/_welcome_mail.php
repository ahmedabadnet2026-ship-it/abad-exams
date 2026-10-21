<?php
// One-time welcome email after sign-up verification. Sent through the same SMTP mailer as the OTP code mail
// (abd_smtp_send + branded layout in _mail_tpl.php). Best effort only: never throws, never blocks registration.
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }

/** Returns ['subject','html','text'] for the welcome email. */
function abd_welcome_mail_build(string $name): array {
  $first = preg_split('/\s+/u', trim($name))[0] ?? '';
  $m = abd_mail_render([
    'title' => 'أهلًا بك في منصة آباد الرقمية',
    'preheader' => 'تم تفعيل حسابك بنجاح — ابدأ رحلتك التدريبية الآن',
    'name' => $first,
    'intro_html' => 'يسعدنا انضمامك إلى منصة آباد الرقمية التابعة لمعهد شبكة آباد للتدريب. تم تفعيل حسابك بنجاح، ويمكنك الآن البدء بما يلي:'
      . "<br>\n• " . '<b>الاختبارات التجريبية</b>: تدرّب على نماذج اختبارات الشهادات المهنية.'
      . "<br>\n• " . '<b>المعامل العملية</b>: طبّق ما تعلمته في بيئات محاكاة عملية.'
      . "<br>\n• " . '<b>خدماتي وشهاداتي</b>: تابع خدماتك واطّلع على شهاداتك.',
    'cta_text' => 'الدخول إلى المنصة',
    'cta_url' => ABD_MAIL_SITE,
    'note_html' => 'لأي استفسار، يسعد فريق الدعم بخدمتك عبر واتساب. نتمنى لك تجربة تدريبية مثمرة.',
    'security' => false,
  ]);
  return ['subject' => 'مرحبًا بك في منصة آباد الرقمية — معهد شبكة آباد للتدريب', 'html' => $m['html'], 'text' => $m['text']];
}

function abd_welcome_mail(string $to, string $name): bool {
  try { $m = abd_welcome_mail_build($name); return abd_smtp_send($to, $m['subject'], $m['html'], $m['text']); }
  catch (Throwable $e) { error_log('abd welcome mail failed: ' . get_class($e)); return false; }
}
