-- Phase 4 (UI/UX build): real per-property amenities, replacing the hardcoded
-- six shown on every property page regardless of what that project actually has.

CREATE TABLE IF NOT EXISTS amenities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_amenities (
    property_id INT NOT NULL,
    amenity_id INT NOT NULL,
    PRIMARY KEY (property_id, amenity_id),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO amenities (name, sort_order) VALUES
('Clubhouse', 0),
('Landscaped Gardens', 1),
('24x7 Security', 2),
('Power Backup', 3),
('Covered Parking', 4),
('Swimming Pool', 5),
('Kids'' Play Area', 6),
('Gymnasium', 7),
('Jogging Track', 8),
('Indoor Games Room', 9),
('Rainwater Harvesting', 10),
('EV Charging Points', 11);
