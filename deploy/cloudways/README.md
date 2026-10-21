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
