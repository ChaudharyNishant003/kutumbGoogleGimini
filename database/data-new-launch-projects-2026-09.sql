-- One-off data load (not a schema migration): restores the "Godrej Astra"
-- seed row that had drifted out of the local dev DB (earlier QA test run
-- left it deleted), then adds the 14 new-launch projects the site owner
-- shared on 2026-09-16. Local/test DB only — not applied to production.

INSERT INTO properties (name, slug, corridor_id, sector, category, bhk_config, price_display, price_min, developer, description, featured)
SELECT 'Godrej Astra', 'godrej-astra', (SELECT id FROM corridors WHERE slug='golf-course-road'), 'Sector 54', 'new-launch', 'Multiple configurations', '10 Cr+', 100000000, 'Godrej', 'An ultra-luxury Godrej address on Golf Course Road, part of Kutumb Realty''s curated Gurgaon portfolio.', 1
WHERE NOT EXISTS (SELECT 1 FROM properties WHERE slug = 'godrej-astra');

INSERT INTO properties (name, slug, corridor_id, sector, category, bhk_config, price_display, price_min, developer, description, featured) VALUES
('ATS Homecraft Grandstand', 'ats-homecraft-grandstand', (SELECT id FROM corridors WHERE slug='dwarka-expressway'), 'Sector 99A', 'new-launch', '3.5 & 4.5 BHK (~2,125–3,000 sq ft)', '3 Cr+', 30000000, 'ATS Homecraft', 'A premium ATS Homecraft development on Dwarka Expressway, offering large-format 3.5 & 4.5 BHK residences.', 0),
('Ganga Realty', 'ganga-realty', (SELECT id FROM corridors WHERE slug='new-gurgaon'), 'Sector 90', 'new-launch', 'Multiple configurations', '2.50 Cr+', 25000000, 'Ganga Realty', 'A premium Ganga Realty residence in New Gurgaon with strong connectivity.', 0),
('County', 'county', (SELECT id FROM corridors WHERE slug='new-gurgaon'), 'Sector 88A', 'new-launch', 'Multiple configurations', 'Price on Request', 0, 'County', 'A premium residential development in New Gurgaon at a strategic location — price on request.', 0),
('Godrej Verano', 'godrej-verano', (SELECT id FROM corridors WHERE slug='golf-course-extension-road'), 'Sector 63A', 'new-launch', 'Multiple configurations', '5.19 Cr+', 51900000, 'Godrej', 'A large-format luxury Godrej address on Golf Course Extension Road.', 0),
('Sobha', 'sobha-sector-63a', (SELECT id FROM corridors WHERE slug='golf-course-extension-road'), 'Sector 63A', 'new-launch', '3 & 4 BHK', '6 Cr+', 60000000, 'Sobha', 'A luxury Sobha development on the premium Golf Course Extension Road corridor.', 0),
('Elevate by Hines', 'elevate-by-hines', (SELECT id FROM corridors WHERE slug='golf-course-extension-road'), 'Sector 63A', 'new-launch', 'Multiple configurations', '7 Cr+', 70000000, 'Hines', 'An ultra-luxury, internationally-positioned Hines development on Golf Course Extension Road.', 0),
('Max Estates', 'max-estates-sector-59', (SELECT id FROM corridors WHERE slug='golf-course-road'), 'Sector 59', 'new-launch', 'Multiple configurations', '16.50 Cr+', 165000000, 'Max Estates', 'An ultra-luxury Max Estates address on Golf Course Road with limited inventory.', 0),
('Krisumi', 'krisumi', (SELECT id FROM corridors WHERE slug='new-gurgaon'), 'Sector 36', 'new-launch', 'Multiple configurations', '6 Cr+', 60000000, 'Krisumi', 'A premium Japanese-inspired Krisumi development at a strategic New Gurgaon location.', 0),
('Max Estates', 'max-estates-sector-36', (SELECT id FROM corridors WHERE slug='new-gurgaon'), 'Sector 36', 'new-launch', 'Multiple configurations', '6 Cr+', 60000000, 'Max Estates', 'A premium/luxury Max Estates development in New Gurgaon.', 0),
('Emaar India', 'emaar-india', (SELECT id FROM corridors WHERE slug='new-gurgaon'), 'Sector 86', 'new-launch', '3 & 4 BHK (~1,751–3,081 sq ft)', '3 Cr+', 30000000, 'Emaar India', 'A premium Emaar India development in New Gurgaon with excellent connectivity.', 0),
('L&T', 'l-t-sector-86', (SELECT id FROM corridors WHERE slug='new-gurgaon'), 'Sector 86', 'new-launch', 'Multiple configurations', '4 Cr+', 40000000, 'L&T', 'A premium luxury development from established developer L&T in New Gurgaon.', 0),
('Signature Global Sarvam', 'signature-global-sarvam', (SELECT id FROM corridors WHERE slug='dwarka-expressway'), 'Sector 37D', 'new-launch', 'Multiple configurations (~1,815–2,495 sq ft)', '3 Cr+', 30000000, 'Signature Global', 'A new-age luxury Signature Global development on Dwarka Expressway.', 0),
('Indiabulls', 'indiabulls', (SELECT id FROM corridors WHERE slug='dwarka-expressway'), 'Sector 104', 'new-launch', 'Multiple configurations', '2.50 Cr+', 25000000, 'Indiabulls', 'A premium Indiabulls development at a prime Dwarka Expressway location.', 0),
('ATS HomeKraft Sanctuary', 'ats-homekraft-sanctuary', (SELECT id FROM corridors WHERE slug='dwarka-expressway'), 'Sector 105', 'new-launch', 'Multiple configurations', '2.50 Cr+', 25000000, 'ATS HomeKraft', 'A premium ATS HomeKraft development on Dwarka Expressway.', 0);
