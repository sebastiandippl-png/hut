-- Migration 007: Add user approval workflow

ALTER TABLE users ADD COLUMN is_approved TINYINT(1) NOT NULL DEFAULT 0;

-- Mark all existing users as approved (backward compatibility)
UPDATE users SET is_approved = 1;

-- But if they're admins, they should stay approved
-- (no update needed since they're already set to 1)
