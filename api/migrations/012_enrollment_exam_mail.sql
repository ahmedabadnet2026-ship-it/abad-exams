-- Per-trainee course dates on enrollments + exam-booking email tracking (sent on the enrollment's end date, Asia/Riyadh).
-- start_date / end_date NULL = inherit the course's dates. exam_mail_sent_at NULL = not sent yet (sender is idempotent on it).
ALTER TABLE abd_enrollments
  ADD COLUMN start_date DATE NULL AFTER status,
  ADD COLUMN end_date DATE NULL AFTER start_date,
  ADD COLUMN exam_mail_sent_at DATETIME NULL AFTER end_date,
  ADD COLUMN exam_mail_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER exam_mail_sent_at,
  ADD COLUMN exam_mail_last_error VARCHAR(190) NULL AFTER exam_mail_attempts,
  ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD KEY ix_abd_enr_end (end_date, exam_mail_sent_at);
