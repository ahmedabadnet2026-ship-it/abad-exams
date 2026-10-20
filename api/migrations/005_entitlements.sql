-- Unified trainee services ledger (exam | material | lab).
-- Compatibility: abd_exam_access and abd_lab_seats stay untouched and keep working. They are read as
-- additional sources by api/_services_lib.php; new grants of every kind are written here.
--   exam     : ref_id = cert id (e.g. 'network')      → also honoured by the exam gate (api/exam.php)
--   material : ref_id = material id (ABD_MATERIALS)    → served by api/material.php
--   lab      : ref_id = EVE seat number '1'..'12'      → also honoured by lab_seat.php (abd_lab_seat_of)
-- An entitlement is live while revoked_at IS NULL.
CREATE TABLE IF NOT EXISTS abd_entitlements (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  kind        ENUM('exam','material','lab') NOT NULL,
  ref_id      VARCHAR(60)  NOT NULL,
  source      ENUM('manual','booking','website') NOT NULL DEFAULT 'manual',
  source_ref  VARCHAR(120) NULL,
  granted_by  INT UNSIGNED NULL,
  note        VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at  DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_abd_ent_user (user_id, kind, revoked_at),
  KEY ix_abd_ent_ref (kind, ref_id),
  UNIQUE KEY uq_abd_ent_src (source, source_ref),
  CONSTRAINT fk_abd_ent_user FOREIGN KEY (user_id) REFERENCES abd_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
