-- Zoom lecture links: numbered sessions of a course (times are Asia/Riyadh wall-clock, DB session time_zone +03:00).
-- zoom_url is served only through api/lectures.php (enrolled trainees inside the join window, or staff).
CREATE TABLE IF NOT EXISTS abd_course_sessions (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_id     INT UNSIGNED NOT NULL,
  session_no    SMALLINT UNSIGNED NOT NULL,
  title         VARCHAR(190) NULL,
  starts_at     DATETIME NOT NULL,
  ends_at       DATETIME NOT NULL,
  zoom_url      VARCHAR(500) NULL,
  zoom_passcode VARCHAR(64) NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_abd_cs_course (course_id, starts_at),
  CONSTRAINT fk_abd_cs_course FOREIGN KEY (course_id) REFERENCES abd_courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
