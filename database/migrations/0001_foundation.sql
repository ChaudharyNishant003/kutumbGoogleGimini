-- Phase 1: admin roles/permissions + map lat/lng scaffolding

ALTER TABLE admin_users
  ADD COLUMN role ENUM('owner','admin','agent') NOT NULL DEFAULT 'agent' AFTER password_hash,
  ADD COLUMN full_name VARCHAR(120) NULL AFTER role,
  ADD COLUMN email VARCHAR(160) NULL AFTER full_name,
  ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER email;

UPDATE admin_users SET role = 'owner' WHERE id = 1;

ALTER TABLE properties
  ADD COLUMN lat DECIMAL(10,7) NULL AFTER image_path,
  ADD COLUMN lng DECIMAL(10,7) NULL AFTER lat;

ALTER TABLE corridors
  ADD COLUMN lat DECIMAL(10,7) NULL AFTER description,
  ADD COLUMN lng DECIMAL(10,7) NULL AFTER lat;
