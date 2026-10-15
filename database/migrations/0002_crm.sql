-- Phase 2: admin/CRM depth — lead status, assignment, follow-ups

ALTER TABLE enquiries
  ADD COLUMN status ENUM('new','contacted','qualified','converted','lost') NOT NULL DEFAULT 'new' AFTER message,
  ADD COLUMN assigned_admin_id INT NULL AFTER status,
  ADD COLUMN source VARCHAR(40) NULL AFTER assigned_admin_id,
  ADD CONSTRAINT fk_enquiries_assigned FOREIGN KEY (assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS follow_ups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enquiry_id INT NOT NULL,
    assigned_admin_id INT NULL,
    due_at DATETIME NOT NULL,
    note VARCHAR(500) NULL,
    completed TINYINT(1) NOT NULL DEFAULT 0,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enquiry_id) REFERENCES enquiries(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
