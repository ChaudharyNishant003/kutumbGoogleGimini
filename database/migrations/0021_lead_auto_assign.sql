-- Round-robin assignment of new website leads to active agents (Settings → "Auto-assign new leads").
-- last_assigned_admin_id remembers whose turn was last, so the next lead goes to the next agent.
ALTER TABLE app_settings
  ADD COLUMN auto_assign_leads TINYINT(1) NOT NULL DEFAULT 1 AFTER ai_global_daily_cap,
  ADD COLUMN last_assigned_admin_id INT NULL AFTER auto_assign_leads;
