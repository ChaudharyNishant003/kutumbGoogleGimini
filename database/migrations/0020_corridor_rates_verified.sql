-- Date an advisor last reviewed a corridor's indicative price band; shown on the corridor page.
ALTER TABLE corridors
  ADD COLUMN rates_verified_on DATE NULL AFTER price_growth_label;
