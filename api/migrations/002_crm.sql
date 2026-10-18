-- ABAD CRM: contacts/leads, pipeline, activities, follow-up tasks. Idempotent.
-- Adds a 'staff' role (CRM/admin-panel access without user management).
ALTER TABLE abd_users MODIFY role ENUM('trainee','staff','admin') NOT NULL DEFAULT 'trainee';

CREATE TABLE IF NOT EXISTS abd_crm_contacts (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name        VARCHAR(190) NOT NULL,
  phone            VARCHAR(32)  NULL,
  email            VARCHAR(190) NULL,
  source           ENUM('booking','website','whatsapp','walk-in','phone','referral','other') NOT NULL DEFAULT 'other',
  interest         VARCHAR(255) NULL,
  stage            ENUM('new','contacted','interested','registered','paid','completed','lost') NOT NULL DEFAULT 'new',
  owner_id         INT UNSIGNED NULL,
  user_id          INT UNSIGNED NULL,
  booking_id       INT UNSIGNED NULL,
  tags             VARCHAR(500) NOT NULL DEFAULT '',
  notes            TEXT NULL,
  lost_reason      VARCHAR(255) NULL,
  stage_changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  won_at           DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_abd_crm_c_stage (stage, updated_at),
  KEY ix_abd_crm_c_source (source),
  KEY ix_abd_crm_c_phone (phone),
  KEY ix_abd_crm_c_email (email),
  KEY ix_abd_crm_c_owner (owner_id),
  KEY ix_abd_crm_c_user (user_id),
  KEY ix_abd_crm_c_created (created_at),
  CONSTRAINT fk_abd_crm_c_owner   FOREIGN KEY (owner_id)   REFERENCES abd_users (id)    ON DELETE SET NULL,
  CONSTRAINT fk_abd_crm_c_user    FOREIGN KEY (user_id)    REFERENCES abd_users (id)    ON DELETE SET NULL,
  CONSTRAINT fk_abd_crm_c_booking FOREIGN KEY (booking_id) REFERENCES abd_bookings (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS abd_crm_activities (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contact_id  INT UNSIGNED NOT NULL,
  type        ENUM('call','whatsapp','note','meeting','email','stage','system') NOT NULL DEFAULT 'note',
  body        TEXT NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_abd_crm_a_contact (contact_id, created_at),
  CONSTRAINT fk_abd_crm_a_contact FOREIGN KEY (contact_id) REFERENCES abd_crm_contacts (id) ON DELETE CASCADE,
  CONSTRAINT fk_abd_crm_a_user    FOREIGN KEY (created_by) REFERENCES abd_users (id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS abd_crm_tasks (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contact_id   INT UNSIGNED NULL,
  title        VARCHAR(255) NOT NULL,
  due_at       DATETIME NOT NULL,
  assigned_to  INT UNSIGNED NULL,
  done_at      DATETIME NULL,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_abd_crm_t_due (done_at, due_at),
  KEY ix_abd_crm_t_contact (contact_id),
  KEY ix_abd_crm_t_assigned (assigned_to, done_at, due_at),
  CONSTRAINT fk_abd_crm_t_contact  FOREIGN KEY (contact_id)  REFERENCES abd_crm_contacts (id) ON DELETE CASCADE,
  CONSTRAINT fk_abd_crm_t_assigned FOREIGN KEY (assigned_to) REFERENCES abd_users (id)        ON DELETE SET NULL,
  CONSTRAINT fk_abd_crm_t_creator  FOREIGN KEY (created_by)  REFERENCES abd_users (id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
