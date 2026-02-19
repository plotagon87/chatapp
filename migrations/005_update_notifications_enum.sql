-- Migration 005: Add presentation type to notifications enum

USE lan_chat_db;

ALTER TABLE notifications 
    MODIFY notification_type ENUM('message','group_invite','announcement','system','presentation') NOT NULL;