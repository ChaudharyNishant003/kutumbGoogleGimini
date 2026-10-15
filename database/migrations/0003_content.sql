-- Phase 3: content & SEO — blog/insights hub, market reports, corridor guides

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    excerpt VARCHAR(400) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    cover_image_path VARCHAR(255) NULL,
    post_type ENUM('article','market-report','guide') NOT NULL DEFAULT 'article',
    corridor_id INT NULL,
    author_id INT NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    meta_description VARCHAR(320) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (corridor_id) REFERENCES corridors(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS corridor_faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    corridor_id INT NOT NULL,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (corridor_id) REFERENCES corridors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE corridors
  ADD COLUMN hero_image_path VARCHAR(255) NULL AFTER lng,
  ADD COLUMN long_description TEXT NULL AFTER hero_image_path;
