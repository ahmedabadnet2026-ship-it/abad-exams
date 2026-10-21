-- EVE lab pool grows from 12 to 20 seats (eve13..eve20 on the second ESXi host). Whether a seat can be assigned/opened is
-- decided in code (api/_lab_lib.php ABD_LAB_SEAT_CFG 'enabled' / private EVE_SEATS_ENABLED), not here; this only widens the range.
-- (Numbered 009 so it does not collide with 008_marketing_opt_in.sql on feature/brevo-marketing.)
ALTER TABLE abd_lab_seats DROP CONSTRAINT ck_abd_lab_seats_seat;
ALTER TABLE abd_lab_seats ADD CONSTRAINT ck_abd_lab_seats_seat CHECK (seat BETWEEN 1 AND 20);
