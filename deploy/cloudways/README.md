# Cloudways-only exam gate deployment
On Cloudways the public `<name>-exam.html` files are REPLACED by these stubs (redirect to `api/exam.php?id=<name>`),
and the real exam pages are copied to `~/applications/gabascwgon/private_html/abad-data/exams/` (outside the web root),
where only api/exam.php serves them to entitled trainees. The repo keeps the originals (source of truth for questions).

## Login gate (all pages)
`/.htaccess` rewrites every `*.html`, the folder root and `index.php` to `api/page.php`, which streams the page only to a signed-in,
email-verified user and otherwise 302s to `login.html?next=<page>`. Exempt: `login.html` (sign-in / register / email OTP),
`api/`, `admin/`, and static assets. `js/login-guard.js` is the client mirror. sw.js no longer caches any HTML.
Temporary switch: `ABAD_OPEN_ALL_FOR_LOGGED_IN` in `api/_db.php` (true = every signed-in user gets all exams + materials;
EVE lab seats unaffected). Flip to false to restore booking/entitlement gating.

## Migration numbering (2026-10-22)
- `013_password_resets.sql` (feature/forgot-password) is applied on live.
- `014_seat_bookings.sql` (feature/seat-booking-lock, renumbered from 013) is applied on live; additive, unused while `SEAT_BOOKING` is off.
- **TODO before merging:** `feature/multi-tenant` still uses **013** and `feature/online-payments` still uses **014** — both collide and must be renumbered (next free: 015, 016) before they are merged/deployed.
- `SEAT_BOOKING` is OFF on live: `api/seat_booking.php` and `admin/seat-bookings.php` return 404 and the admin link is hidden; `seat-booking.html` shows "not enabled" and is not linked anywhere.
