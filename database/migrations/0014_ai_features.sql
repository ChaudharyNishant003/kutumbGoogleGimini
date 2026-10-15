-- AI features (Home Finder, Neighbourhood, Project Assistant, rate limiting).
-- Additive only: new columns are nullable/defaulted and new tables are independent,
-- so code from before this migration keeps working against the migrated DB.

ALTER TABLE properties
  ADD COLUMN possession_text VARCHAR(60) NULL AFTER bhk_config,
  ADD COLUMN info_verified_on DATE NULL AFTER possession_text;

ALTER TABLE app_settings
  ADD COLUMN ai_enabled TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN ai_limit_10min INT NOT NULL DEFAULT 20,
  ADD COLUMN ai_limit_day INT NOT NULL DEFAULT 100,
  ADD COLUMN ai_global_daily_cap INT NOT NULL DEFAULT 1500;

-- Admin-maintained location dataset; distances are computed (Haversine), never generated.
CREATE TABLE IF NOT EXISTS places (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    category ENUM('school','hospital','metro','airport','mall','office') NOT NULL,
    lat DECIMAL(10,7) NOT NULL,
    lng DECIMAL(10,7) NOT NULL,
    source VARCHAR(160) NOT NULL,
    verified_on DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,
    INDEX idx_places_category (category, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Curated per-listing Q&A the Project Assistant quotes verbatim.
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

-- Regulatory text; the assistant only repeats rows with a counsel review date.
CREATE TABLE IF NOT EXISTS property_disclosures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    text TEXT NOT NULL,
    reviewed_by_counsel_on DATE NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed-window counters for AI endpoints (per hashed IP and per session).
CREATE TABLE IF NOT EXISTS ai_rate_limits (
    subject_hash CHAR(64) NOT NULL,
    endpoint VARCHAR(40) NOT NULL,
    window_kind ENUM('10min','day') NOT NULL,
    window_start DATETIME NOT NULL,
    count INT NOT NULL DEFAULT 0,
    PRIMARY KEY (subject_hash, endpoint, window_kind, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Review log: how data gaps in admin content get found.
CREATE TABLE IF NOT EXISTS ai_query_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(40) NOT NULL,
    property_id INT NULL,
    input VARCHAR(500) NOT NULL,
    output TEXT NULL,
    status ENUM('answered','fallback','refused','limited','error') NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_log_status (status, created_at),
    INDEX idx_ai_log_endpoint (endpoint, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
