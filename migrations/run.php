<?php
// Simple migration runner - run from browser or CLI
// Usage (CLI): php run.php
// It will apply all .sql files in this directory that haven't been applied yet.

require_once __DIR__ . '/../includes/config.php';

try {
    // ensure migrations table exists
    $conn->exec("CREATE TABLE IF NOT EXISTS migrations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        migration VARCHAR(255) UNIQUE NOT NULL,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $files = glob(__DIR__ . '/*.sql');
    sort($files, SORT_NATURAL);

    $appliedStmt = $conn->prepare('SELECT COUNT(*) FROM migrations WHERE migration = ?');
    $insertStmt = $conn->prepare('INSERT INTO migrations (migration) VALUES (?)');

    foreach ($files as $file) {
        $name = basename($file);

        // check if applied
        $appliedStmt->execute([$name]);
        if ($appliedStmt->fetchColumn() > 0) {
            echo "Skipping already applied: $name\n";
            continue;
        }

        echo "Applying migration: $name\n";
        $sql = file_get_contents($file);
        if ($sql === false) {
            echo "Failed to read $file\n";
            continue;
        }

        try {
            // Execute the migration SQL (may contain multiple statements)
            $conn->exec($sql);
            $insertStmt->execute([$name]);
            echo "Applied: $name\n";
        } catch (PDOException $e) {
            echo "Error applying $name: " . $e->getMessage() . "\n";
            // stop on failure
            exit(1);
        }
    }

    echo "Migrations complete.\n";
} catch (PDOException $e) {
    echo 'Migration runner error: ' . $e->getMessage() . "\n";
    exit(1);
}
