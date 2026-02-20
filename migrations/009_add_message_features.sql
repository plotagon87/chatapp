-- Migration 009: Support for message edits, replies, deletions, pins, and group delivery/read status

USE lan_chat_db;

-- Add columns to messages (one-to-one)
ALTER TABLE messages
    ADD COLUMN edited_at DATETIME NULL AFTER created_at,
    ADD COLUMN reply_to INT NULL AFTER message_type,
    ADD COLUMN is_deleted BOOLEAN DEFAULT FALSE AFTER is_read,
    ADD COLUMN deleted_at DATETIME NULL AFTER is_deleted,
    ADD COLUMN deleted_by INT NULL AFTER deleted_at,
    ADD COLUMN edited_count INT DEFAULT 0 AFTER deleted_by,
    -- the INDEX keyword is not itself a column; some MariaDB/MySQL versions
    -- reject "ADD COLUMN INDEX" therefore we use the proper syntax below.
    ADD INDEX idx_reply_to (reply_to),
    ADD FOREIGN KEY (reply_to) REFERENCES messages(message_id) ON DELETE SET NULL;

-- Add columns to group_messages (mirror fields)
ALTER TABLE group_messages
    ADD COLUMN edited_at DATETIME NULL AFTER created_at,
    ADD COLUMN reply_to INT NULL AFTER message_type,
    ADD COLUMN is_deleted BOOLEAN DEFAULT FALSE AFTER message_type,
    ADD COLUMN deleted_at DATETIME NULL AFTER is_deleted,
    ADD COLUMN deleted_by INT NULL AFTER deleted_at,
    ADD COLUMN edited_count INT DEFAULT 0 AFTER deleted_by,
    -- same index syntax fix here
    ADD INDEX idx_reply_to (reply_to),
    ADD FOREIGN KEY (reply_to) REFERENCES group_messages(message_id) ON DELETE SET NULL;

-- Table to record edit history for personal messages
CREATE TABLE IF NOT EXISTS message_edits (
    edit_id INT PRIMARY KEY AUTO_INCREMENT,
    message_id INT NOT NULL,
    old_text TEXT,
    new_text TEXT,
    edited_by INT NOT NULL,
    edited_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE,
    FOREIGN KEY (edited_by) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Table to record edit history for group messages
CREATE TABLE IF NOT EXISTS group_message_edits (
    edit_id INT PRIMARY KEY AUTO_INCREMENT,
    group_message_id INT NOT NULL,
    old_text TEXT,
    new_text TEXT,
    edited_by INT NOT NULL,
    edited_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_message_id) REFERENCES group_messages(message_id) ON DELETE CASCADE,
    FOREIGN KEY (edited_by) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Pinned messages we allow users to pin individual messages
CREATE TABLE IF NOT EXISTS pinned_messages (
    pin_id INT PRIMARY KEY AUTO_INCREMENT,
    message_id INT NULL,
    group_message_id INT NULL,
    pinned_by INT NOT NULL,
    pinned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE,
    FOREIGN KEY (group_message_id) REFERENCES group_messages(message_id) ON DELETE CASCADE,
    FOREIGN KEY (pinned_by) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_pin (message_id, group_message_id, pinned_by)
);

-- Status table for delivery/read tracking in group chats
CREATE TABLE IF NOT EXISTS group_message_status (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_message_id INT NOT NULL,
    user_id INT NOT NULL,
    is_delivered BOOLEAN DEFAULT FALSE,
    delivered_at DATETIME NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME NULL,
    FOREIGN KEY (group_message_id) REFERENCES group_messages(message_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_status (group_message_id, user_id)
);

-- Clean up existing indexes if needed (no-op)

-- NOTE: Running this migration on production may take some time if tables are large.
--       Back up your database before applying.
