-- Forgot password: single-use reset links (only a SHA-256 of the token is stored) + per-user session version
-- (bumped on password reset → every existing session of that user stops being valid). Additive only.
ALTER TABLE abd_users ADD COLUMN session_ver INT UNSIGNED NOT NULL DEFAULT 0 AFTER password_hash;

CREATE TABLE IF NOT EXISTS abd_password_resets (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64) NOT NULL,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  ip_hash     CHAR(64) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_abd_pwr_token (token_hash),
  KEY ix_abd_pwr_user (user_id, used_at),
  KEY ix_abd_pwr_exp (expires_at),
  CONSTRAINT fk_abd_pwr_user FOREIGN KEY (user_id) REFERENCES abd_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
