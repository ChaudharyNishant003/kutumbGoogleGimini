-- Phase 4 admin review: an admin accepts/edits/rejects facts the AI
-- extracted, and approves/rejects tool calls the gate held for a human.

ALTER TABLE ai_extracted_facts
  ADD COLUMN review_status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending' AFTER applied_to_lead,
  ADD COLUMN reviewed_by INT NULL AFTER review_status,
  ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by;

ALTER TABLE ai_tool_invocations
  ADD COLUMN review_decision ENUM('approved','rejected') NULL AFTER approved_by,
  ADD COLUMN reviewed_at DATETIME NULL AFTER review_decision;
