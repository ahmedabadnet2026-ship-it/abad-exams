-- Remote labs (ine-lab / net-lab) behind an email code, and per-lab entitlements for when a lab is switched to access = 'entitled'.
-- Numbered 011: 009 = lab seats (this branch), 010 = brevo_pending (feature/brevo-marketing).
ALTER TABLE abd_email_codes MODIFY purpose ENUM('verify','lab','rlab') NOT NULL;
ALTER TABLE abd_entitlements MODIFY kind ENUM('exam','material','lab','rlab') NOT NULL;
