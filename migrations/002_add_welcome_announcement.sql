-- Add welcome announcement flag
ALTER TABLE announcements ADD COLUMN is_welcome BOOLEAN DEFAULT FALSE;

-- You can set existing announcements to welcome if needed (example):
-- UPDATE announcements SET is_welcome = TRUE WHERE announcement_id = 1;
