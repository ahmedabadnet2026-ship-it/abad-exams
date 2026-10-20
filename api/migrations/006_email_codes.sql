-- Email codes: account verification on sign-up + per-open lab seat confirmation.
-- Existing accounts (current admin and users) are treated as verified.
ALTER TABLE abd_users ADD COLUMN email_verified_at DATETIME NULL AFTER email;
UPDATE abd_users SET email_verified_at = COALESCE(created_at, NOW()) WHERE email_verified_at IS NULL;

CREATE TABLE IF NOT EXISTS abd_email_codes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  purpose     ENUM('verify','lab') NOT NULL,
  seat        TINYINT UNSIGNED NULL,
  code_hash   CHAR(64) NOT NULL,
  expires_at  DATETIME NOT NULL,
  attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  used_at     DATETIME NULL,
  ip_hash     CHAR(64) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_abd_email_codes_user (user_id, purpose, used_at),
  KEY ix_abd_email_codes_exp (expires_at),
  CONSTRAINT fk_abd_email_codes_user FOREIGN KEY (user_id) REFERENCES abd_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
