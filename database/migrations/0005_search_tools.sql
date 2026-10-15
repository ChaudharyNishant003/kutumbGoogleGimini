-- Phase 5: saved searches & alerts + map-based search

CREATE TABLE IF NOT EXISTS saved_searches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(160) NULL,
    corridor_id INT NULL,
    category VARCHAR(40) NULL,
    budget_min DECIMAL(14,2) NULL,
    budget_max DECIMAL(14,2) NULL,
    bhk_config VARCHAR(60) NULL,
    notes TEXT NULL,
    status ENUM('new','contacted','matched','closed') NOT NULL DEFAULT 'new',
    assigned_admin_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (corridor_id) REFERENCES corridors(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE follow_ups
  MODIFY COLUMN enquiry_id INT NULL,
  ADD COLUMN saved_search_id INT NULL AFTER enquiry_id,
  ADD CONSTRAINT fk_followups_savedsearch FOREIGN KEY (saved_search_id) REFERENCES saved_searches(id) ON DELETE CASCADE,
  ADD CONSTRAINT chk_followup_target CHECK ((enquiry_id IS NOT NULL) <> (saved_search_id IS NOT NULL));
