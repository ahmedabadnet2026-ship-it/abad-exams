-- Optional trainer note per lecture («ملاحظة», max 190 chars), written in admin/trainer.php (admin can edit it in admin/lectures.php,
-- zoom_staff read-only). Displayed merged with the title as «<title> — <note>» (note alone when there is no title).
ALTER TABLE abd_course_sessions
  ADD COLUMN title_note VARCHAR(190) NULL AFTER title;
