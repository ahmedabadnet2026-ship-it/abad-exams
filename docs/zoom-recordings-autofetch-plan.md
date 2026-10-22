# Plan (not built): auto-fetch Zoom cloud recordings

Status: **plan only** — nothing here is implemented. Today recordings are pasted manually in
`admin/lectures.php` (or by the course trainer in `admin/trainer.php`).

## Prerequisites
- **Cloud recording needs a paid Zoom plan** (Pro or higher) on every host account that runs lectures.
  Local recordings (free plan) never reach Zoom's cloud and cannot be fetched.
- Zoom account admin creates a **Server-to-Server OAuth app** (Zoom App Marketplace → Develop → Build app → Server-to-Server OAuth).
  Scopes: `cloud_recording:read:list_recording_files:admin` (or `recording:read:admin`), `meeting:read:admin`.
- Store `account_id`, `client_id`, `client_secret` and the webhook **secret token** only in the private config
  (`private_html/abad-config/…`), never in the repo or web root.

## Matching a recording to a session (by meeting ID)
- Each session's join URL already contains the meeting ID: `https://…zoom.us/j/<meetingId>?pwd=…`.
  Add a stored, indexed `abd_course_sessions.zoom_meeting_id` (parsed on save) — or fall back to the trainer's
  `abd_users.zoom_meeting_id` (personal / recurring meeting) from migration 015.
- A recurring meeting re-uses one ID, so match on **meeting ID + recording `start_time` within the session window**
  (e.g. start between `starts_at − 30 min` and `ends_at`), Asia/Riyadh → UTC conversion done explicitly.

## Flow
1. `api/zoom_webhook.php` (public POST endpoint):
   - Answer Zoom's `endpoint.url_validation` challenge (HMAC-SHA256 of `plainToken` with the secret token).
   - Verify every event: `x-zm-signature` = `v0=` HMAC-SHA256(`v0:{x-zm-request-timestamp}:{raw body}`), reject if timestamp is > 5 min old.
   - On `recording.completed`: read `payload.object.id` (meeting ID), `start_time`, `share_url`, `password`/`recording_play_passcode`.
   - Find the session (rule above). If found **and `rec_url` is empty**, set `rec_url = share_url`, `rec_passcode = passcode`.
     Never overwrite a link staff entered. Log unmatched events to a small `abd_zoom_events` table for staff review.
   - Respond 200 fast; do no slow work inline.
2. Fallback cron (`api/bin/zoom_sync.php`, hourly): get a token via
   `POST https://zoom.us/oauth/token?grant_type=account_credentials&account_id=…` (Basic client_id:client_secret),
   then `GET /v2/users/{zoom_email}/recordings?from=…&to=…` for each trainer and fill any ended session still missing `rec_url`.
3. Admin page shows the source ("تلقائي من Zoom") and keeps the «بانتظار التسجيل» highlight for anything unmatched.

## Notes / risks
- `share_url` respects the Zoom account's sharing settings (require sign-in / passcode / expiry); set "viewable by anyone with link + passcode".
- Zoom may need minutes to hours after the meeting to process; webhook handles that, cron catches misses.
- Recordings auto-deleted by Zoom retention policy will 404 — `rec_until` can mirror that date.
- Zoom pages cannot be embedded (X-Frame-Options), so Zoom recordings keep opening in a new tab.
