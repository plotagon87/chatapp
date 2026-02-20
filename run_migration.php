<?php
/**
 * Simple migration runner used during development
 * executes every SQL file inside migrations/ in alphanumeric order.
 *
 * Each script is expected to be idempotent (CREATE TABLE IF NOT EXISTS,
 * ALTER TABLE ... ADD COLUMN if not exists, etc.).  Running this repeatedly
 * on the same database is safe and will not re‑apply changes.
 *
 * To apply pending changes just invoke:
 *
 *     php run_migration.php
 *
 * or point your browser at this script.
 */

require_once __DIR__ . '/../includes/config.php';

echo "Looking for migration files...\n";
$migrations = glob(__DIR__ . '/migrations/*.sql');
sort($migrations, SORT_NATURAL);

if (empty($migrations)) {
    echo "No migration files found.\n";
    exit;
}

foreach ($migrations as $file) {
    echo "\n-- Applying " . basename($file) . " --\n";
    $sql = file_get_contents($file);
    try {
        $conn->exec($sql);
        echo "✔ ${file} executed.\n";
    } catch (PDOException $e) {
        // If a statement inside the migration failed because it already
        // exists, it's usually safe to ignore.  We don't try to parse the
        // error further; the migration script itself should use "IF NOT
        // EXISTS" clauses when appropriate.
        echo "✗ Error running ${file}: " . $e->getMessage() . "\n";
    }
}

echo "\nAll migrations processed.\n";
?>
