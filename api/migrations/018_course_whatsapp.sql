-- «جروب واتساب الدورة» (admin/lectures.php, admin + zoom_staff): per-course WhatsApp group invite link and welcome message template,
-- and per-enrollment tracking of the wa.me invite click so staff don't send twice. Trainees see the link as «انضم لجروب الدورة».
ALTER TABLE abd_courses
  ADD COLUMN wa_group_url VARCHAR(255) NULL AFTER trainer_id,
  ADD COLUMN wa_welcome_tpl TEXT NULL AFTER wa_group_url;   -- NULL = default template (ABD_WA_TPL in api/_lectures_lib.php)

ALTER TABLE abd_enrollments
  ADD COLUMN wa_invite_sent_at DATETIME NULL,
  ADD COLUMN wa_invite_sent_by INT UNSIGNED NULL;
