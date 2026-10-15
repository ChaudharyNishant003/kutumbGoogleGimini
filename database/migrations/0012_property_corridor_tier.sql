-- Adds an admin-editable tier classification to properties and corridors,
-- so listings/corridors can be marked Luxury / Premium / Standard from the
-- admin panel. Admin-internal only for now — no public-site display yet
-- (see WEBSITE-DESIGN-BRIEF.md for the agreed future public design).

ALTER TABLE properties
  ADD COLUMN tier ENUM('luxury','premium','standard') NOT NULL DEFAULT 'standard' AFTER category;

ALTER TABLE corridors
  ADD COLUMN tier ENUM('luxury','premium','standard') NOT NULL DEFAULT 'standard' AFTER avg_price_band;
