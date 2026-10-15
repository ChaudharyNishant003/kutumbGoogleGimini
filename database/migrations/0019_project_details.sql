-- Structured project details for the property page: key facts, unit plans,
-- payment plan, specifications and multiple RERA registrations, plus an
-- EOI ("Coming Soon") flag for projects that are taking expressions of
-- interest before their RERA registration / price list is out.
-- Additive only: new nullable/defaulted columns and new independent tables,
-- so code from before this migration keeps working against the migrated DB.
-- SET NAMES: the amenity names below include non-ASCII text (Café), which the mysql
-- client would otherwise mangle when it defaults to latin1.
SET NAMES utf8mb4;

ALTER TABLE properties
  ADD COLUMN land_area_acres DECIMAL(7,2) NULL AFTER info_verified_on,
  ADD COLUMN tower_count SMALLINT UNSIGNED NULL AFTER land_area_acres,
  ADD COLUMN unit_count SMALLINT UNSIGNED NULL AFTER tower_count,
  ADD COLUMN floors_text VARCHAR(40) NULL AFTER unit_count,
  ADD COLUMN rate_text VARCHAR(80) NULL AFTER floors_text,
  ADD COLUMN payment_plan_note VARCHAR(255) NULL AFTER rate_text,
  ADD COLUMN is_eoi TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_plan_note;

-- One row per unit type. Approx price on the page = saleable_sqft × rate_per_sqft
-- (shown only when both are set and the project isn't in EOI mode).
CREATE TABLE IF NOT EXISTS property_unit_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    group_label VARCHAR(80) NULL,
    unit_label VARCHAR(120) NOT NULL,
    saleable_sqft INT UNSIGNED NULL,
    carpet_sqft DECIMAL(8,2) NULL,
    balcony_sqft DECIMAL(8,2) NULL,
    rate_per_sqft INT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    INDEX idx_unit_plans_property (property_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_payment_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    plan_name VARCHAR(80) NOT NULL,
    milestone VARCHAR(200) NOT NULL,
    amount_text VARCHAR(60) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    INDEX idx_payment_plans_property (property_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_specifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    group_label VARCHAR(80) NULL,
    category VARCHAR(60) NOT NULL,
    detail VARCHAR(500) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    INDEX idx_specifications_property (property_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A project can carry several RERA registrations (one per phase). The older
-- single properties.rera_number column stays as-is; the page shows these rows
-- when a property has any, otherwise falls back to that column.
CREATE TABLE IF NOT EXISTS property_rera_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    phase_label VARCHAR(80) NULL,
    rera_number VARCHAR(80) NOT NULL,
    registered_on DATE NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    INDEX idx_rera_property (property_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Amenities that appear in the new projects' brochures but weren't in the master list.
INSERT IGNORE INTO amenities (name, sort_order) VALUES
('Tennis Court', 12),
('Pickleball Court', 13),
('Padel Court', 14),
('Badminton Court', 15),
('Squash Court', 16),
('Basketball Court', 17),
('Cricket Pitch', 18),
('Multipurpose Court', 19),
('Golf Putting Green / Simulator', 20),
('Skating Rink', 21),
('Yoga & Meditation', 22),
('Spa / Sauna & Steam', 23),
('Amphitheatre', 24),
('Mini Theatre', 25),
('Library', 26),
('Café / Restaurant', 27),
('Business Centre', 28),
('Pet Park', 29),
('Senior Citizen Area', 30);
