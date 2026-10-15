-- Homepage redesign (Average Rate Stack component): lets an admin record a
-- corridor's YoY price growth as a short display label (e.g. "+9.1%"). No
-- values are seeded here — this is real business data and stays NULL (badge
-- hidden) until an admin fills it in via the admin panel.

ALTER TABLE corridors ADD COLUMN price_growth_label VARCHAR(16) NULL AFTER avg_price_band;
