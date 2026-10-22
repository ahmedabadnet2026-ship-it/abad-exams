-- Zoom lecture links: numbered sessions of a course (times are Asia/Riyadh wall-clock, DB session time_zone +03:00).
-- rec_url (Zoom / Google Drive / YouTube) is shown after the session ends, until rec_until (inclusive, NULL = no expiry).
-- zoom_url and rec_url are served only through api/lectures.php (enrolled trainees inside the join window, or staff).
CREATE TABLE IF NOT EXISTS abd_course_sessions (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_id     INT UNSIGNED NOT NULL,
  session_no    SMALLINT UNSIGNED NOT NULL,
  title         VARCHAR(190) NULL,
  starts_at     DATETIME NOT NULL,
  ends_at       DATETIME NOT NULL,
  zoom_url      VARCHAR(500) NULL,
  zoom_passcode VARCHAR(64) NULL,
  rec_url       VARCHAR(500) NULL,
  rec_passcode  VARCHAR(64) NULL,
  rec_until     DATE NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_abd_cs_course (course_id, starts_at),
  CONSTRAINT fk_abd_cs_course FOREIGN KEY (course_id) REFERENCES abd_courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles: 'trainer' (assigned per course; sets lecture titles only, never links) and 'zoom_staff' (manages Zoom join links, passcodes,
-- recording links and schedules for all courses; titles are read-only for them). Admins assign both.
ALTER TABLE abd_users
  MODIFY role ENUM('trainee','staff','admin','trainer','zoom_staff') NOT NULL DEFAULT 'trainee',
  ADD COLUMN zoom_email VARCHAR(190) NULL AFTER role,
  ADD COLUMN zoom_meeting_id VARCHAR(32) NULL AFTER zoom_email;

ALTER TABLE abd_courses
  ADD COLUMN trainer_id INT UNSIGNED NULL AFTER link_url,
  ADD KEY ix_abd_courses_trainer (trainer_id),
  ADD CONSTRAINT fk_abd_courses_trainer FOREIGN KEY (trainer_id) REFERENCES abd_users (id) ON DELETE SET NULL;

ALTER TABLE abd_course_sessions
  ADD COLUMN title_by INT UNSIGNED NULL AFTER title,
  ADD COLUMN title_at DATETIME NULL AFTER title_by;
