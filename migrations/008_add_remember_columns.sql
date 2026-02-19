-- Migration 008: Add remember token and expiry to user_sessions

USE lan_chat_db;

ALTER TABLE user_sessions
    ADD COLUMN remember_token VARCHAR(64) DEFAULT NULL,
    ADD COLUMN expires_at DATETIME DEFAULT NULL;
