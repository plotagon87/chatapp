<?php
// CLI script to purge expired/old user sessions. Run via PHP CLI or cron.
require_once __DIR__ . '/includes/config.php';

$days = $argv[1] ?? 30;
$deleted = purgeOldSessions($days);
echo "Purged $deleted old/expired sessions (max_days=$days)\n";
