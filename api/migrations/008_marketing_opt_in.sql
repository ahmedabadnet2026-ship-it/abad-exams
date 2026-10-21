-- Marketing e-mail consent (Brevo). Default OFF: existing users never consented and are never synced.
ALTER TABLE abd_users ADD COLUMN marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER email_verified_at;
ALTER TABLE abd_users ADD COLUMN marketing_opt_in_at DATETIME NULL AFTER marketing_opt_in;
ALTER TABLE abd_users ADD COLUMN brevo_synced_at DATETIME NULL AFTER marketing_opt_in_at;
