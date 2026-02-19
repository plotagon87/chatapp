-- Migration 006: Add group support to presentations
-- Allows presenters to invite groups instead of just individual users
-- This migration adds group_id column and dual-access support to presentation_viewers

USE lan_chat_db;

-- Step 1: Make user_id nullable since either user_id or group_id should be set
ALTER TABLE presentation_viewers 
    MODIFY user_id INT DEFAULT NULL;

-- Step 2: Add group_id column for group-based access
ALTER TABLE presentation_viewers 
    ADD COLUMN group_id INT DEFAULT NULL AFTER user_id;

-- Step 3: Add foreign key for group reference
ALTER TABLE presentation_viewers 
    ADD CONSTRAINT fk_group_id 
    FOREIGN KEY (group_id) REFERENCES group_chats(group_id) ON DELETE CASCADE;

-- Step 4: Create unique key for group viewers (presentation + group combo must be unique)
ALTER TABLE presentation_viewers 
    ADD CONSTRAINT unique_group_viewer 
    UNIQUE KEY (presentation_id, group_id);

-- Step 5: Recreate unique constraint for user viewers only (drop and recreate)
ALTER TABLE presentation_viewers 
    DROP INDEX unique_viewer;

ALTER TABLE presentation_viewers 
    ADD CONSTRAINT unique_user_viewer 
    UNIQUE KEY (presentation_id, user_id);

-- Step 6: Add check constraint (require exactly one of user_id or group_id)
-- Note: MySQL 8.0.16+ supports CHECK constraints
-- For older MySQL versions, this constraint may need to be enforced in application code
ALTER TABLE presentation_viewers 
    ADD CONSTRAINT check_viewer_type 
    CHECK ((user_id IS NOT NULL AND group_id IS NULL) OR (user_id IS NULL AND group_id IS NOT NULL));
