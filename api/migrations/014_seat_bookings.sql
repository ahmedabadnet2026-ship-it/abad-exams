-- Seat booking per time slot (EVE seats + remote labs). Additive only; nothing reads it unless SEAT_BOOKING is enabled in the private lab.php.
-- live = 1 while booked, NULL once cancelled → UNIQUE(resource, seat, slot_start, live) allows one active booking per seat+slot
-- while keeping any number of cancelled rows for history.
CREATE TABLE IF NOT EXISTS abd_seat_bookings (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  resource     VARCHAR(16) NOT NULL,
  seat         TINYINT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  slot_start   DATETIME NOT NULL,
  slot_end     DATETIME NOT NULL,
  live         TINYINT UNSIGNED NULL DEFAULT 1,
  note         VARCHAR(255) NULL,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_at DATETIME NULL,
  cancelled_by INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_abd_seat_bookings_slot (resource, seat, slot_start, live),
  KEY ix_abd_seat_bookings_user (user_id, slot_start),
  KEY ix_abd_seat_bookings_day (resource, slot_start),
  CONSTRAINT fk_abd_seat_bookings_user FOREIGN KEY (user_id) REFERENCES abd_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
