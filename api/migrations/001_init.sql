-- ABAD Digital Platform — initial schema (MySQL 8 / MariaDB 10.6+). Idempotent.
-- All tables use the abd_ prefix, InnoDB, utf8mb4.

CREATE TABLE IF NOT EXISTS abd_users (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name_ar   VARCHAR(190) NOT NULL DEFAULT '',
  full_name_en   VARCHAR(190) NOT NULL DEFAULT '',
  email          VARCHAR(190) NOT NULL,
  phone          VARCHAR(32)  NULL,
  national_id    VARCHAR(16)  NULL,
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('trainee','admin') NOT NULL DEFAULT 'trainee',
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at  DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_abd_users_email (email),
  KEY ix_abd_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS abd_courses (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug           VARCHAR(80)  NOT NULL,
  title_ar       VARCHAR(190) NOT NULL,
  title_en       VARCHAR(190) NOT NULL DEFAULT '',
  description_ar TEXT NULL,
  description_en TEXT NULL,
  delivery       ENUM('onsite','online') NOT NULL DEFAULT 'onsite',
  start_date     DATE NULL,
  end_date       DATE NULL,
  link_url       VARCHAR(255) NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_abd_courses_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS abd_enrollments (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED NOT NULL,
  course_id      INT UNSIGNED NOT NULL,
  status         ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_abd_enr (user_id, course_id),
  KEY ix_abd_enr_course (course_id),
  CONSTRAINT fk_abd_enr_user   FOREIGN KEY (user_id)   REFERENCES abd_users (id)   ON DELETE CASCADE,
  CONSTRAINT fk_abd_enr_course FOREIGN KEY (course_id) REFERENCES abd_courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS abd_bookings (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ref            VARCHAR(24)  NOT NULL,
  user_id        INT UNSIGNED NULL,
  vendor         VARCHAR(190) NOT NULL,
  cert           VARCHAR(255) NOT NULL,
  exam_type      VARCHAR(120) NULL,
  name_en        VARCHAR(190) NOT NULL,
  national_id    VARCHAR(16)  NOT NULL,
  phone          VARCHAR(32)  NOT NULL,
  email          VARCHAR(190) NOT NULL,
  preferred_date DATE NULL,
  preferred_time VARCHAR(40)  NULL,
  payment_method VARCHAR(40)  NULL,
  notes          TEXT NULL,
  invoice_path   VARCHAR(255) NULL,
  status         ENUM('new','contacted','booked','cancelled') NOT NULL DEFAULT 'new',
  admin_note     TEXT NULL,
  lang           CHAR(2) NULL,
  ip_hash        CHAR(64) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_abd_bookings_ref (ref),
  KEY ix_abd_bookings_status (status, created_at),
  KEY ix_abd_bookings_email (email),
  KEY ix_abd_bookings_user (user_id),
  CONSTRAINT fk_abd_bookings_user FOREIGN KEY (user_id) REFERENCES abd_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS abd_exam_results (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          INT UNSIGNED NULL,
  exam_id          VARCHAR(80)  NOT NULL,
  score            INT UNSIGNED NOT NULL,
  total            INT UNSIGNED NOT NULL,
  percent          DECIMAL(5,2) NOT NULL,
  domain_breakdown JSON NULL,
  duration_sec     INT UNSIGNED NULL,
  page             VARCHAR(120) NULL,
  ip_hash          CHAR(64) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_abd_res_user (user_id, created_at),
  KEY ix_abd_res_exam (exam_id, created_at),
  CONSTRAINT fk_abd_res_user FOREIGN KEY (user_id) REFERENCES abd_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed-window rate limiting (login, register, bookings, results).
CREATE TABLE IF NOT EXISTS abd_rate_limits (
  k            CHAR(64) NOT NULL,
  hits         INT UNSIGNED NOT NULL DEFAULT 0,
  window_start INT UNSIGNED NOT NULL,
  PRIMARY KEY (k),
  KEY ix_abd_rl_ws (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
