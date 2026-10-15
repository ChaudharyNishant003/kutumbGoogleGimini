-- Adds a buying-timeline field and a source tag to saved_searches, so the
-- new "Why an Advisor" consultation form (which asks "when are you buying?"
-- and isn't the same entry point as the general Saved Searches page) can be
-- told apart from regular saved searches in the admin panel.

ALTER TABLE saved_searches
  ADD COLUMN timeline ENUM('now','6m','12m','research') NULL AFTER budget_max,
  ADD COLUMN source VARCHAR(40) NULL AFTER notes;
