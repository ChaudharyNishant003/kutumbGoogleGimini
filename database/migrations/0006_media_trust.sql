-- Phase 6: media & trust — galleries, virtual tours, team profiles, testimonials, RERA docs

CREATE TABLE IF NOT EXISTS property_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    caption VARCHAR(200) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    doc_type VARCHAR(60) NOT NULL,
    label VARCHAR(160) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    role_title VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(160) NULL,
    whatsapp VARCHAR(30) NULL,
    bio TEXT NULL,
    photo_path VARCHAR(255) NULL,
    specialty_corridor_id INT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    FOREIGN KEY (specialty_corridor_id) REFERENCES corridors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS testimonials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(120) NOT NULL,
    client_role VARCHAR(160) NULL,
    quote TEXT NOT NULL,
    rating TINYINT UNSIGNED NULL,
    photo_path VARCHAR(255) NULL,
    property_id INT NULL,
    featured_home TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE properties
  ADD COLUMN virtual_tour_url VARCHAR(500) NULL AFTER lng,
  ADD COLUMN rera_number VARCHAR(60) NULL AFTER virtual_tour_url,
  ADD COLUMN listing_agent_id INT NULL AFTER rera_number,
  ADD CONSTRAINT fk_properties_agent FOREIGN KEY (listing_agent_id) REFERENCES team_members(id) ON DELETE SET NULL;

-- Carry over the homepage's previous hardcoded placeholder testimonials so the
-- section doesn't go blank the moment index.php switches to a DB-driven query —
-- replace these with real reviews from the admin Testimonials panel when available.
INSERT INTO testimonials (client_name, client_role, quote, featured_home, sort_order) VALUES
('Verified Buyer', 'Sector 54, Golf Course Road', 'The team walked us through every RERA document before we signed anything. No pressure, just clear answers.', 1, 0),
('Verified Buyer', 'Sector 79, SPR', 'We compared three corridors before deciding — Kutumb''s sector guides made the tradeoffs obvious.', 1, 1),
('Verified Buyer', 'Sector 93, New Gurgaon', 'Fast WhatsApp responses and a genuinely curated shortlist instead of a hundred irrelevant listings.', 1, 2);
