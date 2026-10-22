# Staging plan — online section (Zoom lectures, roles, trainer dashboard, «دوراتي», trainer performance)

Status: **plan only — nothing has been created on the server.** Creating it needs explicit approval.

## ⚠️ Blocker to solve first: the DB config is shared per Cloudways application
`api/_db.php → abd_config_file()` takes everything before `/public_html` and reads
`private_html/abad-data/db.php` (or `abad-config/db.php`). A folder such as
`public_html/abad-digital-staging/` inside the **same** application would therefore use the **live database**.
Options (pick one):
1. **Recommended:** a separate Cloudways *application* (e.g. `abad-staging`) on the same server, with its own
   `public_html/abad-digital/` and its own `private_html/abad-data/db.php` pointing to its own MySQL database
   (Cloudways creates one DB per application). No code change; fully isolated; easy to delete.
2. Same application, folder `public_html/abad-digital-staging/`, plus a small code change: when the script path
   contains `/abad-digital-staging/`, read `private_html/abad-data-staging/db.php` and **refuse** to fall back to the
   live file. Needs a reviewed commit before upload.

## What is needed
- Approval to create the staging app/folder and a throwaway MySQL database (Cloudways panel).
- A staging DB config file (outside the web root): host/name/user/password of the **staging** DB only.
- Mail **off** in staging: no `mail.php`/Brevo config copied (or a sink address), so no real e-mails go out.
- `.htaccess` basic-auth or IP allow-list on the staging folder, plus `X-Robots-Tag: noindex` (already sent by admin pages).
- Upload the branch `feature/zoom-lectures` (commits 35cacd0 … latest) — not main, not live.
- Run `php api/bin/migrate.php` in staging only (applies 001–015 to the empty staging DB).
  Note: migration 015 was edited on this branch (zoom_staff, host_url, started_at/started_by); it has never run on live.
  Before merging to main, check the README note about migration number collisions (multi-tenant / online-payments).
- Seed fake data: `/tmp/zdb/seed-demo.php` (box) adapted to staging — 2 trainers + 1 extra, 5 online courses, sessions
  in the past/today/future with fake `zoom.us` links, ~40 fake trainees. No real trainee data.

## Test accounts (one per role, fake e-mails on a domain we control, passwords given privately)
| Role | Account | Lands on |
|---|---|---|
| admin | admin@staging… | admin/index.php (Users tab: assign roles; «محاضرات الزوم»; «أداء المدربين») |
| zoom_staff | zoom@staging… | admin/lectures.php «لوحة مسؤول الزوم» |
| trainer | trainer@staging… (assigned to 2–3 courses) | admin/trainer.php «لوحة المدرب» |
| trainer 2 | trainer2@staging… | to prove a trainer can't see/start others' sessions |
| staff | staff@staging… | admin/index.php (no Zoom page) |
| trainee | trainee@staging… (enrolled in 2–3 courses) | my-services.html → «دوراتي» → my-courses.html |
| trainee (empty) | new@staging… | my-courses.html empty state |

For the «ابدأ المحاضرة» test, create one session starting ~10 minutes after the test begins, with a real Zoom
test meeting link (host start link optional) entered by the zoom_staff account.

## Checklist for the user
1. zoom_staff: generate a schedule, paste join/host/recording links, import a CSV; titles are read-only; no start button.
2. trainer: hero countdown → button enables 15 min before → click opens Zoom, page switches to «العودة للمحاضرة»;
   after the lecture ends, write the title; no link inputs anywhere.
3. trainee: «دوراتي» card → course cards → «دخول الدورة» / «التسجيلات» (lectures.html filtered, breadcrumb back).
4. admin: «أداء المدربين» shows the delay of the session started in step 2; filters; CSV export.
5. Permission script (CLI, staging DB only): `php api/bin/test_lecture_perms.php https://…/staging/` —
   it refuses unless the DB name contains "perm"/"test" and the URL is 127.0.0.1, so on staging run it via an SSH
   tunnel or adjust the guard deliberately; it wipes users/courses/sessions of the DB it runs on.

## Teardown
Delete the staging application (or folder + staging DB + config file). Nothing on live is touched at any point.
