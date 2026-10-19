-- EVE lab seats: seat assignment per trainee + one-time email codes (OTP) gating eveN.abad-labs.com
CREATE TABLE IF NOT EXISTS abd_lab_seats (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  seat        TINYINT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  valid_from  DATETIME NULL,
  valid_to    DATETIME NULL,
  note        VARCHAR(255) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_abd_lab_seats_user (user_id),
  KEY ix_abd_lab_seats_seat (seat, valid_to),
  CONSTRAINT ck_abd_lab_seats_seat CHECK (seat BETWEEN 1 AND 12),
  CONSTRAINT fk_abd_lab_seats_user FOREIGN KEY (user_id) REFERENCES abd_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS abd_lab_otps (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  seat        TINYINT UNSIGNED NOT NULL,
  code_hash   CHAR(64) NOT NULL,
  expires_at  DATETIME NOT NULL,
  attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  used_at     DATETIME NULL,
  ip_hash     CHAR(64) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_abd_lab_otps_user (user_id, seat, created_at),
  KEY ix_abd_lab_otps_exp (expires_at),
  CONSTRAINT fk_abd_lab_otps_user FOREIGN KEY (user_id) REFERENCES abd_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
