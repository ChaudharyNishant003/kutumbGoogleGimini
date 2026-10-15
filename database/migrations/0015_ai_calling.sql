-- AI Calling Agent — Phase 1 data layer (voice-calling lead agent).
-- See docs/ai-calling/schema-notes.md for why every FK here is plain INT
-- (not BIGINT UNSIGNED) and why this is a separate system from the existing
-- ai_rate_limits/ai_query_log/places/property_faqs/property_disclosures
-- tables (0014_ai_features.sql) — do not merge the two.

-- Extend existing tables (additive, nullable — nothing here can break
-- existing reads/writes against enquiries/follow_ups).
ALTER TABLE enquiries
  ADD COLUMN ai_budget_min_inr BIGINT UNSIGNED NULL,
  ADD COLUMN ai_budget_max_inr BIGINT UNSIGNED NULL,
  ADD COLUMN ai_location_pref VARCHAR(255) NULL,
  ADD COLUMN ai_bhk_pref VARCHAR(32) NULL,
  ADD COLUMN ai_timeline ENUM('immediate','0_3_months','3_6_months','6_12_months','12_plus','unknown') NULL,
  ADD COLUMN ai_intent ENUM('end_user','investor','both','unknown') NULL,
  ADD COLUMN ai_lead_score TINYINT UNSIGNED NULL,
  ADD COLUMN ai_language VARCHAR(8) NULL,
  ADD COLUMN ai_summary TEXT NULL,
  ADD COLUMN ai_last_call_at DATETIME NULL,
  ADD COLUMN ai_next_action_at DATETIME NULL,
  ADD COLUMN ai_fields_human_edited_at DATETIME NULL,
  ADD COLUMN phone_e164 VARCHAR(16) NULL,
  ADD COLUMN inquiry_at DATETIME NULL,
  ADD COLUMN consent_basis ENUM('inquiry','explicit','none') NOT NULL DEFAULT 'inquiry',
  ADD COLUMN dnc TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN dnc_at DATETIME NULL,
  ADD INDEX idx_enq_ai_next (ai_next_action_at),
  ADD INDEX idx_enq_phone_e164 (phone_e164),
  ADD INDEX idx_enq_dnc (dnc);

UPDATE enquiries SET inquiry_at = created_at WHERE inquiry_at IS NULL;
-- phone_e164 backfill is done in PHP (scripts/backfill-phone-e164.php) —
-- normalizing "98765 43210" / "09876543210" / "+91-9876543210" etc. into a
-- single canonical +91XXXXXXXXXX form is not a one-line SQL expression.

ALTER TABLE follow_ups
  ADD COLUMN source ENUM('manual','ai') NOT NULL DEFAULT 'manual',
  ADD COLUMN kind ENUM('callback','site_visit','reminder','human_task','ai_retry') NULL,
  ADD COLUMN ai_call_id INT NULL,
  ADD INDEX idx_fu_ai_call (ai_call_id);
-- Existing chk_followup_target CHECK (exactly one of enquiry_id/saved_search_id)
-- is untouched. AI-created rows always set enquiry_id, never saved_search_id.

-- New tables for this feature. All InnoDB / utf8mb4_unicode_ci, all IDs
-- plain INT AUTO_INCREMENT, all FKs plain INT matching the parent columns
-- above (never BIGINT UNSIGNED — this codebase doesn't use that anywhere).

CREATE TABLE IF NOT EXISTS ai_settings (
  k VARCHAR(64) NOT NULL PRIMARY KEY,
  v MEDIUMTEXT NULL,                 -- base64 libsodium secretbox ciphertext when is_secret=1
  is_secret TINYINT(1) NOT NULL DEFAULT 0,
  updated_by INT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_aisettings_admin FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_agent_versions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  version INT NOT NULL,
  system_prompt MEDIUMTEXT NOT NULL,
  script_json JSON NOT NULL,
  decision_config_json JSON NOT NULL,
  llm_config_json JSON NOT NULL,
  tts_config_json JSON NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_agent_ver (name, version),
  CONSTRAINT fk_agentver_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_campaigns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  purpose ENUM('new_lead_qualification','follow_up','site_visit_reminder','reactivation','inbound') NOT NULL,
  agent_version_id INT NOT NULL,
  status ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'draft',
  max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
  retry_backoff_json JSON NOT NULL,
  window_start TIME NOT NULL DEFAULT '10:00:00',
  window_end TIME NOT NULL DEFAULT '19:30:00',
  days_mask TINYINT UNSIGNED NOT NULL DEFAULT 63,
  daily_call_cap INT UNSIGNED NOT NULL DEFAULT 200,
  max_concurrency TINYINT UNSIGNED NOT NULL DEFAULT 5,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_camp_agent FOREIGN KEY (agent_version_id) REFERENCES ai_agent_versions(id),
  CONSTRAINT fk_camp_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_calls (
  id INT AUTO_INCREMENT PRIMARY KEY,
  enquiry_id INT NOT NULL,
  campaign_id INT NULL,
  agent_version_id INT NOT NULL,
  direction ENUM('outbound','inbound') NOT NULL,
  purpose VARCHAR(40) NOT NULL,
  state ENUM('queued','scheduled','dialing','in_progress','post_processing','completed',
             'retry_wait','failed','canceled','blocked_compliance') NOT NULL DEFAULT 'queued',
  attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
  next_attempt_at DATETIME NULL,
  consent_basis_snapshot ENUM('inquiry','explicit','inbound') NOT NULL,
  disposition_code VARCHAR(40) NULL,
  summary TEXT NULL,
  lead_status_before VARCHAR(20) NULL,
  lead_status_after VARCHAR(20) NULL,
  transferred_to_admin_id INT NULL,
  started_at DATETIME NULL,
  ended_at DATETIME NULL,
  talk_seconds INT UNSIGNED NULL,
  cost_paise INT UNSIGNED NOT NULL DEFAULT 0,
  dedupe_key VARCHAR(80) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_call_dedupe (dedupe_key),
  KEY idx_calls_enq (enquiry_id, created_at),
  KEY idx_calls_state (state, next_attempt_at),
  CONSTRAINT fk_calls_enq FOREIGN KEY (enquiry_id) REFERENCES enquiries(id),
  CONSTRAINT fk_calls_camp FOREIGN KEY (campaign_id) REFERENCES ai_campaigns(id),
  CONSTRAINT fk_calls_agent FOREIGN KEY (agent_version_id) REFERENCES ai_agent_versions(id),
  CONSTRAINT fk_calls_transferred FOREIGN KEY (transferred_to_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE follow_ups
  ADD CONSTRAINT fk_followups_aicall FOREIGN KEY (ai_call_id) REFERENCES ai_calls(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS ai_call_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  call_id INT NOT NULL,
  attempt_no TINYINT UNSIGNED NOT NULL,
  provider VARCHAR(20) NOT NULL,
  provider_call_uuid VARCHAR(80) NULL,
  from_number VARCHAR(20) NULL,
  to_number VARCHAR(20) NULL,
  state ENUM('dialing','ringing','answered','completed','transferred','no_answer','busy',
             'failed','voicemail','canceled') NOT NULL DEFAULT 'dialing',
  amd_result VARCHAR(20) NULL,
  hangup_cause VARCHAR(60) NULL,
  recording_ref VARCHAR(255) NULL,
  recording_deleted_at DATETIME NULL,
  answered_at DATETIME NULL,
  ended_at DATETIME NULL,
  billed_seconds INT UNSIGNED NULL,
  cost_telephony_paise INT UNSIGNED NOT NULL DEFAULT 0,
  cost_stt_paise INT UNSIGNED NOT NULL DEFAULT 0,
  cost_tts_paise INT UNSIGNED NOT NULL DEFAULT 0,
  cost_llm_paise INT UNSIGNED NOT NULL DEFAULT 0,
  cost_jev_paise INT UNSIGNED NOT NULL DEFAULT 0,
  latency_p50_ms INT UNSIGNED NULL,
  latency_p95_ms INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_attempt_uuid (provider, provider_call_uuid),
  UNIQUE KEY uq_attempt_no (call_id, attempt_no),
  CONSTRAINT fk_att_call FOREIGN KEY (call_id) REFERENCES ai_calls(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_transcript_segments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT NOT NULL,
  seq INT UNSIGNED NOT NULL,
  speaker ENUM('agent','caller','system') NOT NULL,
  lang VARCHAR(8) NULL,
  text TEXT NOT NULL,
  text_en TEXT NULL,
  start_ms INT UNSIGNED NULL,
  end_ms INT UNSIGNED NULL,
  stt_confidence DECIMAL(4,3) NULL,
  interrupted TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_seg (attempt_id, seq),
  CONSTRAINT fk_seg_att FOREIGN KEY (attempt_id) REFERENCES ai_call_attempts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_extracted_facts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  call_id INT NOT NULL,
  enquiry_id INT NOT NULL,
  slot ENUM('budget','location','bhk','timeline','intent','callback_time','site_visit_time',
            'property_interest','name_confirmed','other') NOT NULL,
  value_json JSON NOT NULL,
  value_display VARCHAR(255) NOT NULL,
  confidence DECIMAL(4,3) NOT NULL,
  source ENUM('jev','llm','regex','human') NOT NULL,
  source_segment_id INT NULL,
  span_start SMALLINT UNSIGNED NULL,
  span_end SMALLINT UNSIGNED NULL,
  confirmed_by_caller TINYINT(1) NOT NULL DEFAULT 0,
  applied_to_lead TINYINT(1) NOT NULL DEFAULT 0,
  superseded_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_facts_enq (enquiry_id, slot),
  CONSTRAINT fk_facts_call FOREIGN KEY (call_id) REFERENCES ai_calls(id),
  CONSTRAINT fk_facts_seg FOREIGN KEY (source_segment_id) REFERENCES ai_transcript_segments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_dispositions (
  code VARCHAR(40) NOT NULL PRIMARY KEY,
  label VARCHAR(120) NOT NULL,
  category ENUM('not_connected','connected','outcome','compliance') NOT NULL,
  is_terminal TINYINT(1) NOT NULL DEFAULT 0,
  retry_allowed TINYINT(1) NOT NULL DEFAULT 1,
  suggested_lead_status VARCHAR(20) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ai_dispositions (code, label, category, is_terminal, retry_allowed, suggested_lead_status) VALUES
 ('no_answer','No answer','not_connected',0,1,NULL),
 ('busy','Busy','not_connected',0,1,NULL),
 ('voicemail','Voicemail/IVR','not_connected',0,1,NULL),
 ('failed','Network/provider failure','not_connected',0,1,NULL),
 ('callback_requested','Callback requested','connected',0,1,'contacted'),
 ('qualified','Qualified','outcome',1,0,'qualified'),
 ('partially_qualified','Partially qualified','outcome',0,1,'contacted'),
 ('site_visit_booked','Site visit booked','outcome',1,0,'qualified'),
 ('transferred','Transferred to human','outcome',1,0,'contacted'),
 ('not_interested','Not interested','outcome',1,0,'lost'),
 ('wrong_number','Wrong number','compliance',1,0,'lost'),
 ('opt_out','Opt-out / do not call','compliance',1,0,NULL),
 ('language_barrier','Language not supported','connected',1,0,NULL),
 ('abusive','Abusive caller','compliance',1,0,NULL)
ON DUPLICATE KEY UPDATE label = VALUES(label);

CREATE TABLE IF NOT EXISTS ai_consent_evidence (
  id INT AUTO_INCREMENT PRIMARY KEY,
  enquiry_id INT NULL,
  phone_e164 VARCHAR(16) NOT NULL,
  type ENUM('inquiry_form','explicit_registered','verbal_on_call','recording_notice',
            'ai_disclosure','opt_out','revocation','voice_clone_owner') NOT NULL,
  evidence_json JSON NOT NULL,
  call_attempt_id INT NULL,
  segment_id INT NULL,
  valid_from DATETIME NOT NULL,
  valid_until DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_consent_phone (phone_e164, type),
  CONSTRAINT fk_consent_enq FOREIGN KEY (enquiry_id) REFERENCES enquiries(id) ON DELETE SET NULL,
  CONSTRAINT fk_consent_att FOREIGN KEY (call_attempt_id) REFERENCES ai_call_attempts(id) ON DELETE SET NULL,
  CONSTRAINT fk_consent_seg FOREIGN KEY (segment_id) REFERENCES ai_transcript_segments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_suppression (
  phone_e164 VARCHAR(16) NOT NULL PRIMARY KEY,
  reason ENUM('opt_out','wrong_number','abusive','manual','dnd_registry') NOT NULL,
  source VARCHAR(40) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_decision_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  call_id INT NULL,
  attempt_id INT NULL,
  turn_seq INT UNSIGNED NULL,
  decision_key VARCHAR(40) NOT NULL,
  engine ENUM('jev','llm','rule') NOT NULL,
  model_snapshot VARCHAR(80) NULL,
  question_type ENUM('choice','noul','score','rule','llm_json') NOT NULL,
  answer_label VARCHAR(60) NULL,
  answer_value DECIMAL(6,4) NULL,
  confidence DECIMAL(5,4) NULL,
  probabilities_json JSON NULL,
  threshold_used DECIMAL(5,4) NULL,
  action_taken VARCHAR(60) NOT NULL,
  mode ENUM('live','shadow') NOT NULL DEFAULT 'shadow',
  fallback_reason VARCHAR(60) NULL,
  latency_ms INT UNSIGNED NULL,
  input_tokens INT UNSIGNED NULL,
  cost_usd_micros INT UNSIGNED NULL,
  state_excerpt TEXT NULL,
  human_label VARCHAR(60) NULL,
  labeled_by INT NULL,
  labeled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_dec_key (decision_key, created_at),
  KEY idx_dec_call (call_id),
  CONSTRAINT fk_dec_call FOREIGN KEY (call_id) REFERENCES ai_calls(id),
  CONSTRAINT fk_dec_att FOREIGN KEY (attempt_id) REFERENCES ai_call_attempts(id),
  CONSTRAINT fk_dec_admin FOREIGN KEY (labeled_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_tool_invocations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  call_id INT NOT NULL,
  attempt_id INT NULL,
  tool_name VARCHAR(40) NOT NULL,
  args_json JSON NOT NULL,
  requested_by ENUM('llm','router','system') NOT NULL,
  gate_result ENUM('allowed','blocked','needs_human','not_gated') NOT NULL,
  gate_decision_id INT NULL,
  api_status SMALLINT UNSIGNED NULL,
  result_json JSON NULL,
  idempotency_key VARCHAR(120) NOT NULL,
  approved_by INT NULL,
  latency_ms INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tool_idem (idempotency_key),
  KEY idx_tool_call (call_id),
  CONSTRAINT fk_tool_call FOREIGN KEY (call_id) REFERENCES ai_calls(id),
  CONSTRAINT fk_tool_att FOREIGN KEY (attempt_id) REFERENCES ai_call_attempts(id),
  CONSTRAINT fk_tool_dec FOREIGN KEY (gate_decision_id) REFERENCES ai_decision_log(id),
  CONSTRAINT fk_tool_admin FOREIGN KEY (approved_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_audit_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('admin','ai_runtime','worker','system') NOT NULL,
  actor_id INT NULL,
  event VARCHAR(60) NOT NULL,
  entity_type VARCHAR(40) NOT NULL,
  entity_id INT NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('dial','post_call','reminder','follow_up_scan','retention_purge','eligibility_scan') NOT NULL,
  payload_json JSON NOT NULL,
  run_at DATETIME NOT NULL,
  status ENUM('pending','running','done','failed','dead') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_by VARCHAR(64) NULL,
  locked_at DATETIME NULL,
  last_error TEXT NULL,
  dedupe_key VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_job_dedupe (dedupe_key),
  KEY idx_jobs_due (status, run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
