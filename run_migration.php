<?php
require_once __DIR__ . '/../includes/config.php';

echo "Running migration: Add is_welcome field to announcements...\n";

try {
    $sql = "ALTER TABLE announcements ADD COLUMN is_welcome BOOLEAN DEFAULT FALSE;";
    $conn->exec($sql);
    echo "✓ Migration completed successfully!\n";
    echo "The 'is_welcome' field has been added to the announcements table.\n";
    echo "\nTo mark an announcement as a welcome message:\n";
    echo "UPDATE announcements SET is_welcome = TRUE WHERE announcement_id = [ID];\n";
} catch (PDOException $e) {
    // Check if column already exists
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "✓ Column 'is_welcome' already exists in announcements table.\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}
?>
