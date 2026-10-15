-- Phase 7: analytics dashboard + view counters

ALTER TABLE properties ADD COLUMN view_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER featured;
ALTER TABLE corridors ADD COLUMN view_count INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE blog_posts ADD COLUMN view_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER meta_description;
