-- "Pause AI" for a single lead: stops AI dialing until the given time without
-- marking the lead DNC/opted-out (dnc means the caller asked us to stop; this is
-- an admin's temporary hold). NULL = not paused.

ALTER TABLE enquiries
  ADD COLUMN ai_paused_until DATETIME NULL AFTER dnc_at;
