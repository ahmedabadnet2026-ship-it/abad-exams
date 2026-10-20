<?php
// FUTURE (disabled): signed booking webhook from the institute's official website (abadnet.com.sa).
// Not active — always 404 until implemented and enabled. Design (see exam-gate docs):
//   POST JSON {event:"booking.confirmed"|"booking.cancelled", booking_id, cert_id, email, national_id?, phone?, expires_at?, ts}
//   Header X-Abad-Signature: sha256=<hex HMAC-SHA256(raw body, BOOKING_HOOK_SECRET)>; reject if |now-ts| > 300 s.
//   Idempotent: abd_exam_access(source='webhook', source_ref=booking_id) UNIQUE → upsert; 'cancelled' sets revoked_at.
//   Trainee match order: email → national_id → phone (+9665…); unmatched events are stored for staff review, never auto-create users.
declare(strict_types=1);
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'not found';
