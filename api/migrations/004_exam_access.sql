-- Exam entitlement: an exam opens only for trainees with a booked exam (abd_bookings) or an explicit grant.
-- Grants are pluggable by source: 'manual' (staff), 'webhook' (future official-site booking feed), 'booking' (optional snapshot).
CREATE TABLE IF NOT EXISTS abd_exam_access (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  cert_id     VARCHAR(40)  NOT NULL,
  source      ENUM('manual','booking','webhook') NOT NULL DEFAULT 'manual',
  source_ref  VARCHAR(120) NULL,
  granted_by  INT UNSIGNED NULL,
  note        VARCHAR(255) NULL,
  expires_at  DATETIME NULL,
  revoked_at  DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_abd_exam_access_src (source, source_ref),
  KEY ix_abd_exam_access_user (user_id, cert_id),
  CONSTRAINT fk_abd_exam_access_user FOREIGN KEY (user_id) REFERENCES abd_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stable matching key for bookings (booking.html sends the certSections id, e.g. 'network'); NULL for older rows.
ALTER TABLE abd_bookings ADD COLUMN cert_id VARCHAR(40) NULL AFTER cert;
ALTER TABLE abd_bookings ADD KEY ix_abd_bookings_cert (cert_id, status);
