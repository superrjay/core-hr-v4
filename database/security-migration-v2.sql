-- Core HR security migration v2.
-- MariaDB 10.11. Safe to run more than once. Does not delete or rewrite existing rows.
-- Import after database/profreehost-import.sql on the live database,
-- or after database/schema.sql plus the phase seeds on a new database.
-- email and session_version already exist on the live import; IF NOT EXISTS skips them.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS session_version INT UNSIGNED NOT NULL DEFAULT 1;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS is_locked TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS locked_at DATETIME NULL;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS lock_reason VARCHAR(120) NULL;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS last_failed_login_at DATETIME NULL;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS unlocked_by INT UNSIGNED NULL;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS unlocked_at DATETIME NULL;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0;

CREATE INDEX IF NOT EXISTS idx_users_email ON users (email);

ALTER TABLE notifications
  ADD COLUMN IF NOT EXISTS event_key VARCHAR(60) NULL;

ALTER TABLE notifications
  ADD COLUMN IF NOT EXISTS email_sent_at DATETIME NULL;

ALTER TABLE notifications
  ADD COLUMN IF NOT EXISTS email_error VARCHAR(255) NULL;

-- Seed accounts use the well-known README password.
-- Re-running this does not flag an account again after password_changed_at is set.
UPDATE users
SET must_change_password = 1
WHERE username IN ('admin', 'hr.manager', 'employee.benjie', 'employee.celeste')
  AND password_changed_at IS NULL;

-- The seed admin has no employee row, so users.email stays empty until you set it:
-- UPDATE users SET email = 'your-admin@example.com' WHERE username = 'admin';
