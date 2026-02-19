-- Database Verification Script
-- Run this to verify all migrations completed successfully
-- Shows all tables and their structure

USE lan_chat_db;

-- 1. List all tables in the database
SELECT '=== ALL TABLES IN DATABASE ===' AS Check_Type;
SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lan_chat_db' 
ORDER BY TABLE_NAME;

-- 2. Verify core User Management Tables exist
SELECT '=== CORE TABLES CHECK ===' AS Check_Type;
SELECT 
    'users' AS Table_Name,
    IF(COUNT(*) > 0, '✓ EXISTS', '✗ MISSING') AS Status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND TABLE_NAME = 'users'
UNION ALL
SELECT 
    'messages' AS Table_Name,
    IF(COUNT(*) > 0, '✓ EXISTS', '✗ MISSING') AS Status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND TABLE_NAME = 'messages'
UNION ALL
SELECT 
    'group_chats' AS Table_Name,
    IF(COUNT(*) > 0, '✓ EXISTS', '✗ MISSING') AS Status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND TABLE_NAME = 'group_chats'
UNION ALL
SELECT 
    'group_members' AS Table_Name,
    IF(COUNT(*) > 0, '✓ EXISTS', '✗ MISSING') AS Status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND TABLE_NAME = 'group_members'
UNION ALL
SELECT 
    'group_messages' AS Table_Name,
    IF(COUNT(*) > 0, '✓ EXISTS', '✗ MISSING') AS Status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND TABLE_NAME = 'group_messages'
UNION ALL
SELECT 
    'announcements' AS Table_Name,
    IF(COUNT(*) > 0, '✓ EXISTS', '✗ MISSING') AS Status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND TABLE_NAME = 'announcements'
UNION ALL
SELECT 
    'message_reactions' AS Table_Name,
    IF(COUNT(*) > 0, '✓ EXISTS', '✗ MISSING') AS Status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND TABLE_NAME = 'message_reactions'
UNION ALL
SELECT 
    'notifications' AS Table_Name,
    IF(COUNT(*) > 0, '✓ EXISTS', '✗ MISSING') AS Status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND TABLE_NAME = 'notifications'
UNION ALL
SELECT 
    'activity_log' AS Table_Name,
    IF(COUNT(*) > 0, '✓ EXISTS', '✗ MISSING') AS Status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND TABLE_NAME = 'activity_log';

-- 3. Verify Presentation Tables (Migration 004) exist
SELECT '=== PRESENTATION TABLES (Migration 004) ===' AS Check_Type;
SELECT 
    'presentations' AS Table_Name,
    IF(COUNT(*) > 0, '✓ EXISTS', '✗ MISSING') AS Status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND TABLE_NAME = 'presentations'
UNION ALL
SELECT 
    'presentation_files' AS Table_Name,
    IF(COUNT(*) > 0, '✓ EXISTS', '✗ MISSING') AS Status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND TABLE_NAME = 'presentation_files'
UNION ALL
SELECT 
    'presentation_viewers' AS Table_Name,
    IF(COUNT(*) > 0, '✓ EXISTS', '✗ MISSING') AS Status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND TABLE_NAME = 'presentation_viewers'
UNION ALL
SELECT 
    'presentation_announcements' AS Table_Name,
    IF(COUNT(*) > 0, '✓ EXISTS', '✗ MISSING') AS Status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND TABLE_NAME = 'presentation_announcements';

-- 4. Check notifications table has 'presentation' enum value (Migration 005)
SELECT '=== NOTIFICATIONS ENUM CHECK (Migration 005) ===' AS Check_Type;
SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'notification_type';

-- 5. Check presentation_viewers has group_id column (Migration 006)
SELECT '=== PRESENTATION_VIEWERS GROUP SUPPORT (Migration 006) ===' AS Check_Type;
SELECT 
    GROUP_CONCAT(COLUMN_NAME) AS Columns_Present
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND TABLE_NAME = 'presentation_viewers';

-- 6. Verify user exists in users table
SELECT '=== ADMIN USER CHECK ===' AS Check_Type;
SELECT 
    COUNT(*) AS Admin_User_Count,
    IF(COUNT(*) > 0, '✓ PRESENT', '✗ MISSING') AS Status
FROM lan_chat_db.users 
WHERE username = 'admin';

-- 7. Detailed table structures for key tables
SELECT '=== PRESENTATION_VIEWERS COLUMNS ===' AS Check_Type;
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_KEY,
    EXTRA
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND TABLE_NAME = 'presentation_viewers'
ORDER BY ORDINAL_POSITION;

-- 8. Check all foreign keys
SELECT '=== FOREIGN KEY CONSTRAINTS ===' AS Check_Type;
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- 9. Check unique constraints
SELECT '=== UNIQUE CONSTRAINTS ===' AS Check_Type;
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY ORDINAL_POSITION) AS Columns
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'lan_chat_db' AND CONSTRAINT_NAME != 'PRIMARY'
GROUP BY CONSTRAINT_NAME, TABLE_NAME
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- 10. Summary: Count rows in each table
SELECT '=== TABLE ROW COUNTS ===' AS Check_Type;
SELECT 'users' AS Table_Name, COUNT(*) AS Row_Count FROM lan_chat_db.users
UNION ALL
SELECT 'messages' AS Table_Name, COUNT(*) AS Row_Count FROM lan_chat_db.messages
UNION ALL
SELECT 'group_chats' AS Table_Name, COUNT(*) AS Row_Count FROM lan_chat_db.group_chats
UNION ALL
SELECT 'group_members' AS Table_Name, COUNT(*) AS Row_Count FROM lan_chat_db.group_members
UNION ALL
SELECT 'group_messages' AS Table_Name, COUNT(*) AS Row_Count FROM lan_chat_db.group_messages
UNION ALL
SELECT 'announcements' AS Table_Name, COUNT(*) AS Row_Count FROM lan_chat_db.announcements
UNION ALL
SELECT 'message_reactions' AS Table_Name, COUNT(*) AS Row_Count FROM lan_chat_db.message_reactions
UNION ALL
SELECT 'notifications' AS Table_Name, COUNT(*) AS Row_Count FROM lan_chat_db.notifications
UNION ALL
SELECT 'activity_log' AS Table_Name, COUNT(*) AS Row_Count FROM lan_chat_db.activity_log
UNION ALL
SELECT 'presentations' AS Table_Name, COUNT(*) AS Row_Count FROM lan_chat_db.presentations
UNION ALL
SELECT 'presentation_files' AS Table_Name, COUNT(*) AS Row_Count FROM lan_chat_db.presentation_files
UNION ALL
SELECT 'presentation_viewers' AS Table_Name, COUNT(*) AS Row_Count FROM lan_chat_db.presentation_viewers
UNION ALL
SELECT 'presentation_announcements' AS Table_Name, COUNT(*) AS Row_Count FROM lan_chat_db.presentation_announcements
ORDER BY Table_Name;

-- 11. Final Verification Summary
SELECT '=== FINAL MIGRATION STATUS ===' AS Check_Type;
SELECT 'All Migrations' AS Migration, 'Status Summary' AS Description
UNION ALL
SELECT '', '✓ Migrations 001-003: Core tables ready'
UNION ALL
SELECT '', IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='lan_chat_db' AND TABLE_NAME='presentations') > 0,
    '✓ Migration 004 (Presentations)',
    '✗ Migration 004 (Presentations) - MISSING'
)
UNION ALL
SELECT '', IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='lan_chat_db' AND TABLE_NAME='notifications' AND COLUMN_TYPE LIKE '%presentation%') > 0,
    '✓ Migration 005 (Notifications Enum)',
    '✗ Migration 005 (Notifications Enum) - MISSING'
)
UNION ALL
SELECT '', IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='lan_chat_db' AND TABLE_NAME='presentation_viewers' AND COLUMN_NAME='group_id') > 0,
    '✓ Migration 006 (Group Support)',
    '✗ Migration 006 (Group Support) - MISSING'
);
