-- Account toggle for marketing e-mails: a preference change that could not reach Brevo is queued here and retried by
-- api/bin/brevo_sync.php (both directions). Numbered 010: 009 is used by feature/eve-seats-20 (009_lab_seats_20.sql).
ALTER TABLE abd_users ADD COLUMN brevo_pending TINYINT(1) NOT NULL DEFAULT 0 AFTER brevo_synced_at;
