-- Auto-graded lab submissions (تسليم). One row per submit; results_json holds per-check expected/found/hint.
CREATE TABLE IF NOT EXISTS abd_lab_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  seat         TINYINT UNSIGNED NOT NULL,
  lab          VARCHAR(160) NOT NULL,
  score        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_score    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status       ENUM('graded','error') NOT NULL DEFAULT 'graded',
  results_json MEDIUMTEXT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_abd_lab_attempts_user (user_id, lab, created_at),
  CONSTRAINT fk_abd_lab_attempts_user FOREIGN KEY (user_id) REFERENCES abd_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
