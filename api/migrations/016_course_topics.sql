-- «مواضيع الدورة»: ordered topic list per course, managed by admins in admin/lectures.php (zoom_staff: read-only).
-- The trainer picks a lecture title from this list in admin/trainer.php (or writes another one); the chosen text is still
-- stored in abd_course_sessions.title, so deleting/renaming a topic never changes titles already saved.
CREATE TABLE IF NOT EXISTS abd_course_topics (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_id   INT UNSIGNED NOT NULL,
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  title       VARCHAR(190) NOT NULL,
  PRIMARY KEY (id),
  KEY ix_abd_ct_course (course_id, sort_order),
  CONSTRAINT fk_abd_ct_course FOREIGN KEY (course_id) REFERENCES abd_courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
