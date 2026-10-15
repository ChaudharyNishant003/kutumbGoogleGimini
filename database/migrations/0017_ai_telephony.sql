-- Phase 3 (telephony + worker) schema fixes.

-- record_opt_out() cancels a lead's pending jobs with status='canceled', but
-- the enum had no such value. Under STRICT_TRANS_TABLES that UPDATE errors
-- and rolls back the whole opt-out the moment the worker has a pending job
-- for the lead — the one action the spec says must never fail.
ALTER TABLE ai_jobs
  MODIFY COLUMN status ENUM('pending','running','done','failed','dead','canceled') NOT NULL DEFAULT 'pending';

-- The retention job deletes transcript segments older than 180 days; a
-- RESTRICT key from extracted facts would block that purge forever. Facts
-- keep their value, only the pointer to the purged segment is cleared.
ALTER TABLE ai_extracted_facts DROP FOREIGN KEY fk_facts_seg;

ALTER TABLE ai_extracted_facts
  ADD CONSTRAINT fk_facts_seg FOREIGN KEY (source_segment_id)
  REFERENCES ai_transcript_segments(id) ON DELETE SET NULL;
