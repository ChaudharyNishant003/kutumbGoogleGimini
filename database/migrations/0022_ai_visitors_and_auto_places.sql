-- One row per website visitor who uses an AI feature (chatbot, project assistant, home finder,
-- neighbourhood). Identified by a first-party cookie (kv_vid, stored here only as a hash);
-- merged with a lead once the visitor leaves a phone number in the chat.
CREATE TABLE IF NOT EXISTS ai_visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_key CHAR(64) NOT NULL,
    first_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    visit_count INT NOT NULL DEFAULT 1,
    question_count INT NOT NULL DEFAULT 0,
    name VARCHAR(120) NULL,
    phone VARCHAR(30) NULL,
    phone_e164 VARCHAR(20) NULL,
    enquiry_id INT NULL,
    saved_search_id INT NULL,
    req_step TINYINT NOT NULL DEFAULT 0,
    req_budget VARCHAR(10) NULL,
    req_bhk VARCHAR(10) NULL,
    req_corridor_id INT NULL,
    req_timeline VARCHAR(10) NULL,
    UNIQUE KEY uq_ai_visitor_key (visitor_key),
    KEY idx_ai_visitor_phone (phone_e164),
    KEY idx_ai_visitor_enquiry (enquiry_id),
    KEY idx_ai_visitor_last_seen (last_seen_at),
    CONSTRAINT fk_ai_visitor_enquiry FOREIGN KEY (enquiry_id) REFERENCES enquiries(id) ON DELETE SET NULL,
    CONSTRAINT fk_ai_visitor_saved_search FOREIGN KEY (saved_search_id) REFERENCES saved_searches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Each logged question belongs to a visitor and to one of their visits (a new visit starts after 30 idle minutes).
-- 'gated' = the visitor was asked for a phone number instead of getting an answer.
ALTER TABLE ai_query_log
  ADD COLUMN visitor_id INT NULL AFTER id,
  ADD COLUMN visit_no INT NULL AFTER visitor_id,
  ADD KEY idx_ai_log_visitor (visitor_id, created_at),
  MODIFY status ENUM('answered','fallback','refused','limited','error','gated') NOT NULL;

-- Nearby places filled in automatically from OpenStreetMap: osm_ref de-duplicates refreshes;
-- manual entries (osm_ref NULL) are never touched by an automatic refresh.
ALTER TABLE places
  ADD COLUMN osm_ref VARCHAR(40) NULL AFTER source,
  ADD UNIQUE KEY uq_places_osm_ref (osm_ref);

-- Where a project's map position came from: typed/pasted by an admin, or looked up from its name (approximate).
ALTER TABLE properties
  ADD COLUMN location_source ENUM('manual','auto') NULL AFTER lng;
UPDATE properties SET location_source = 'manual' WHERE lat IS NOT NULL AND lng IS NOT NULL;
