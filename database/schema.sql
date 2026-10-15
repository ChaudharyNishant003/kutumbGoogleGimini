-- Kutumb Realty database schema
-- Tables are ordered so a fresh install can run this file top-to-bottom with all
-- foreign keys resolvable (each table only references tables already created above it).

CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('owner','admin','agent') NOT NULL DEFAULT 'agent',
    full_name VARCHAR(120) NULL,
    email VARCHAR(160) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS corridors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    avg_price_band VARCHAR(80) NOT NULL,
    tier ENUM('luxury','premium','standard') NOT NULL DEFAULT 'standard',
    price_growth_label VARCHAR(16) NULL,
    rates_verified_on DATE NULL,
    description TEXT NOT NULL,
    lat DECIMAL(10,7) NULL,
    lng DECIMAL(10,7) NULL,
    hero_image_path VARCHAR(255) NULL,
    long_description TEXT NULL,
    view_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL
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
    deleted_at DATETIME NULL DEFAULT NULL,
    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    FOREIGN KEY (specialty_corridor_id) REFERENCES corridors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS properties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    corridor_id INT NULL,
    sector VARCHAR(80) NOT NULL,
    category ENUM('new-launch','under-construction','ready-to-move','commercial') NOT NULL,
    tier ENUM('luxury','premium','standard') NOT NULL DEFAULT 'standard',
    bhk_config VARCHAR(60) NOT NULL,
    possession_text VARCHAR(60) NULL,
    info_verified_on DATE NULL,
    land_area_acres DECIMAL(7,2) NULL,
    tower_count SMALLINT UNSIGNED NULL,
    unit_count SMALLINT UNSIGNED NULL,
    floors_text VARCHAR(40) NULL,
    rate_text VARCHAR(80) NULL,
    payment_plan_note VARCHAR(255) NULL,
    is_eoi TINYINT(1) NOT NULL DEFAULT 0,
    price_display VARCHAR(60) NOT NULL,
    price_min DECIMAL(14,2) NOT NULL DEFAULT 0,
    developer VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    image_path VARCHAR(255) NULL,
    lat DECIMAL(10,7) NULL,
    lng DECIMAL(10,7) NULL,
    location_source ENUM('manual','auto') NULL,
    virtual_tour_url VARCHAR(500) NULL,
    rera_number VARCHAR(60) NULL,
    listing_agent_id INT NULL,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    view_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,
    FOREIGN KEY (corridor_id) REFERENCES corridors(id) ON DELETE SET NULL,
    FOREIGN KEY (listing_agent_id) REFERENCES team_members(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Structured project details (see migrations/0019_project_details.sql).
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

CREATE TABLE IF NOT EXISTS corridor_faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    corridor_id INT NOT NULL,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (corridor_id) REFERENCES corridors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    excerpt VARCHAR(400) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    cover_image_path VARCHAR(255) NULL,
    post_type ENUM('article','market-report','guide','case-study') NOT NULL DEFAULT 'article',
    corridor_id INT NULL,
    author_id INT NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    meta_description VARCHAR(320) NULL,
    view_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,
    FOREIGN KEY (corridor_id) REFERENCES corridors(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES admin_users(id) ON DELETE SET NULL
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
    deleted_at DATETIME NULL DEFAULT NULL,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
    id TINYINT PRIMARY KEY DEFAULT 1,
    openai_api_key VARCHAR(255) NULL,
    ai_enabled TINYINT(1) NOT NULL DEFAULT 1,
    ai_limit_10min INT NOT NULL DEFAULT 20,
    ai_limit_day INT NOT NULL DEFAULT 100,
    ai_global_daily_cap INT NOT NULL DEFAULT 1500,
    auto_assign_leads TINYINT(1) NOT NULL DEFAULT 1,
    last_assigned_admin_id INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(160) NULL,
    message TEXT NULL,
    status ENUM('new','contacted','qualified','converted','lost') NOT NULL DEFAULT 'new',
    assigned_admin_id INT NULL,
    source VARCHAR(40) NULL,
    property_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_searches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(160) NULL,
    corridor_id INT NULL,
    category VARCHAR(40) NULL,
    budget_min DECIMAL(14,2) NULL,
    budget_max DECIMAL(14,2) NULL,
    timeline ENUM('now','6m','12m','research') NULL,
    bhk_config VARCHAR(60) NULL,
    notes TEXT NULL,
    source VARCHAR(40) NULL,
    status ENUM('new','contacted','matched','closed') NOT NULL DEFAULT 'new',
    assigned_admin_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (corridor_id) REFERENCES corridors(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate-limiting log for public forms (contact / saved search / quick enquiry).
-- Decoupled from enquiries/saved_searches on purpose — those tables are shown
-- directly in the admin Leads/Saved Searches lists, so bookkeeping rows for
-- every attempt (including rejected ones) live here instead. No cron exists,
-- so old rows are purged opportunistically from rate_limit_check() itself.
CREATE TABLE IF NOT EXISTS form_submission_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    endpoint VARCHAR(60) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_endpoint_time (ip_address, endpoint, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS follow_ups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enquiry_id INT NULL,
    saved_search_id INT NULL,
    assigned_admin_id INT NULL,
    due_at DATETIME NOT NULL,
    note VARCHAR(500) NULL,
    completed TINYINT(1) NOT NULL DEFAULT 0,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enquiry_id) REFERENCES enquiries(id) ON DELETE CASCADE,
    FOREIGN KEY (saved_search_id) REFERENCES saved_searches(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    CONSTRAINT chk_followup_target CHECK ((enquiry_id IS NOT NULL) <> (saved_search_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI features (see migrations/0014_ai_features.sql).
CREATE TABLE IF NOT EXISTS places (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    category ENUM('school','hospital','metro','airport','mall','office') NOT NULL,
    lat DECIMAL(10,7) NOT NULL,
    lng DECIMAL(10,7) NOT NULL,
    source VARCHAR(160) NOT NULL,
    osm_ref VARCHAR(40) NULL,
    verified_on DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,
    INDEX idx_places_category (category, deleted_at),
    UNIQUE KEY uq_places_osm_ref (osm_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FULLTEXT INDEX ft_property_faqs (question, answer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_disclosures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    text TEXT NOT NULL,
    reviewed_by_counsel_on DATE NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_rate_limits (
    subject_hash CHAR(64) NOT NULL,
    endpoint VARCHAR(40) NOT NULL,
    window_kind ENUM('10min','day') NOT NULL,
    window_start DATETIME NOT NULL,
    count INT NOT NULL DEFAULT 0,
    PRIMARY KEY (subject_hash, endpoint, window_kind, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_query_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_id INT NULL,
    visit_no INT NULL,
    endpoint VARCHAR(40) NOT NULL,
    property_id INT NULL,
    input VARCHAR(500) NOT NULL,
    output TEXT NULL,
    status ENUM('answered','fallback','refused','limited','error','gated') NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_log_status (status, created_at),
    INDEX idx_ai_log_endpoint (endpoint, created_at),
    INDEX idx_ai_log_visitor (visitor_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Website visitors who use the chat / AI features (see migration 0022).
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

-- Indexes on columns that are actively filtered/sorted on but otherwise have
-- no index beyond primary/foreign keys (see migrations/0010_perf_indexes.sql).
CREATE INDEX idx_properties_category ON properties (category);
CREATE INDEX idx_properties_featured ON properties (featured);
CREATE INDEX idx_properties_deleted_at ON properties (deleted_at);
CREATE INDEX idx_properties_price_min ON properties (price_min);
CREATE INDEX idx_blog_posts_status ON blog_posts (status);
CREATE INDEX idx_blog_posts_published_at ON blog_posts (published_at);
CREATE INDEX idx_enquiries_status ON enquiries (status);
CREATE INDEX idx_saved_searches_status ON saved_searches (status);
