-- Optimization pass (Batch 1.3): indexes on columns that are actively
-- filtered/sorted on today but have no index beyond primary/foreign keys.
-- Irrelevant at current row counts — this is pure insurance against admin
-- list pages and public filters getting slow once data grows.

CREATE INDEX idx_properties_category ON properties (category);
CREATE INDEX idx_properties_featured ON properties (featured);
CREATE INDEX idx_properties_deleted_at ON properties (deleted_at);
CREATE INDEX idx_properties_price_min ON properties (price_min);

CREATE INDEX idx_blog_posts_status ON blog_posts (status);
CREATE INDEX idx_blog_posts_published_at ON blog_posts (published_at);

CREATE INDEX idx_enquiries_status ON enquiries (status);
CREATE INDEX idx_saved_searches_status ON saved_searches (status);
