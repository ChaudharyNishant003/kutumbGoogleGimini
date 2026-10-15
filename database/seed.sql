-- Kutumb Realty seed data.
-- Property names, sectors and prices are taken from the live kutumbrealty.com
-- listing page. Category tags, BHK-configuration labels and descriptions are
-- reasonable editorial defaults — edit freely from the admin panel.

INSERT INTO corridors (name, slug, avg_price_band, description) VALUES
('Golf Course Road', 'golf-course-road', '₹22,000–35,000+/sq ft', 'Gurgaon''s most established luxury address — DLF Phase 5 and its neighbours, home to some of the city''s highest resale values.'),
('Golf Course Extension Road', 'golf-course-extension-road', '₹14,000–19,500/sq ft', 'The premium corridor just beyond GCR, with a dense run of large-format luxury launches from Godrej, Whiteland and others.'),
('Dwarka Expressway', 'dwarka-expressway', '₹8,000–15,000/sq ft', 'Gurgaon''s fastest-growing corridor (Sectors 102–113), driven by expressway connectivity and a wave of new-launch supply.'),
('Southern Peripheral Road', 'southern-peripheral-road', '₹12,350–17,900/sq ft', 'SPR (Sectors 68–79) blends mid-to-premium high-rise housing with strong social infrastructure.'),
('Sohna Road', 'sohna-road', '₹11,000–15,000/sq ft', 'A mature, well-connected corridor (Sectors 47–49, 63–69) mixing group housing with established builder floors.'),
('New Gurgaon', 'new-gurgaon', '₹4,500–8,000/sq ft', 'The peripheral sectors (80+) offering the most accessible entry price points in the city.'),
('MG Road / Old Gurgaon', 'mg-road-old-gurgaon', '₹8,000–14,000/sq ft', 'Gurgaon''s original settled neighbourhoods, with the metro at the doorstep and mature social infrastructure — an older housing stock and tighter parking than the newer corridors.'),
('Sector 79–95 Belt', 'sector-79-95-belt', '₹5,500–9,000/sq ft', 'Large integrated townships spanning Sectors 79 to 95, with real amenity sets inside the gate — everyday retail and services outside it are still filling in across several pockets.');

INSERT INTO properties (name, slug, corridor_id, sector, category, bhk_config, price_display, price_min, developer, description, featured) VALUES
('Godrej Astra', 'godrej-astra', (SELECT id FROM corridors WHERE slug='golf-course-road'), 'Sector 54', 'new-launch', 'Multiple configurations', '10 Cr+', 100000000, 'Godrej', 'An ultra-luxury Godrej address on Golf Course Road, part of Kutumb Realty''s curated Gurgaon portfolio.', 1),
('Godrej Vrikshya', 'godrej-vrikshya', (SELECT id FROM corridors WHERE slug='dwarka-expressway'), 'Sector 103', 'new-launch', 'Multiple configurations', '3.75 Cr+', 37500000, 'Godrej', 'A new-launch Godrej project on the fast-growing Dwarka Expressway corridor.', 1),
('M3M Golf Hills-2', 'm3m-golf-hills-2', (SELECT id FROM corridors WHERE slug='southern-peripheral-road'), 'Sector 79', 'under-construction', 'Multiple configurations', '1.27 Cr+', 12700000, 'M3M', 'An under-construction M3M project on SPR, offering a relatively accessible entry point in a premium corridor.', 0),
('Whiteland Aspen & Brissville', 'whiteland-aspen-brissville', (SELECT id FROM corridors WHERE slug='southern-peripheral-road'), 'Sector 76', 'new-launch', 'Multiple configurations', '3.7 Cr+', 37000000, 'Whiteland', 'A new-launch Whiteland development on SPR, part of Kutumb Realty''s curated portfolio.', 1),
('Ashiana Amarah Phase 4', 'ashiana-amarah-phase-4', (SELECT id FROM corridors WHERE slug='new-gurgaon'), 'Sector 93', 'under-construction', 'Multiple configurations', '2.04 Cr+', 20400000, 'Ashiana', 'An under-construction Ashiana project in New Gurgaon.', 0),
('Godrej Aristocrat', 'godrej-aristocrat', (SELECT id FROM corridors WHERE slug='sohna-road'), 'Sector 49', 'ready-to-move', 'Multiple configurations', '2.6 Cr', 26000000, 'Godrej', 'A ready-to-move Godrej address on Sohna Road.', 1),
('Smartworld One DXP', 'smartworld-one-dxp', (SELECT id FROM corridors WHERE slug='dwarka-expressway'), 'Dwarka Expressway', 'commercial', 'Commercial spaces', '70 Lakhs+', 7000000, 'Smartworld', 'A commercial development by Smartworld on Dwarka Expressway.', 0),
('Omaxe Street', 'omaxe-street', (SELECT id FROM corridors WHERE slug='new-gurgaon'), 'New Gurgaon', 'commercial', 'Retail spaces', '60 Lakhs+', 6000000, 'Omaxe', 'A high-street retail development by Omaxe.', 0),
('32 Milestone', '32-milestone', (SELECT id FROM corridors WHERE slug='sohna-road'), 'Sohna Road', 'commercial', 'Commercial spaces', '70 Lakhs+', 7000000, 'Milestone', 'A commercial project on Sohna Road.', 0);

INSERT INTO app_settings (id) VALUES (1);

-- Seeded admin account — password_hash below is a bcrypt hash, not reversible.
-- The plaintext temporary password was shared with the site owner directly
-- (not committed here) — change it via the admin panel after first login.
INSERT INTO admin_users (username, password_hash) VALUES
('admin', '$2y$12$mbUTbyntCHrOBsB8wR4bX.HvIwj6GanzrssBqOk69vS0q6VswWnIW');
