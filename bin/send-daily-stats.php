#!/usr/bin/env php
<?php
/**
 * Daily Stats Email Cron Job
 * 
 * Sends a daily summary email to all active users with their current stats.
 * 
 * Usage:
 *   php bin/send-daily-stats.php
 * 
 * Recommended cron schedule (daily at 8:00 AM):
 *   0 8 * * * cd /path/to/porra-mundial-2026 && php bin/send-daily-stats.php >> storage/logs/cron.log 2>&1
 */

declare(strict_types=1);

// Bootstrap the application
require __DIR__ . '/../app/bootstrap.php';

use App\Core\Application;
use App\Core\DailyStatsEmail;

$app = Application::boot();

echo "[" . gmdate('Y-m-d H:i:s') . "] Starting daily stats email job...\n";

// Check if mail is configured
if (!$app->mail()->isConfigured()) {
    echo "[ERROR] Mail is not configured. Please configure SMTP settings first.\n";
    exit(1);
}

// Send emails
$emailService = new DailyStatsEmail($app);

try {
    $result = $emailService->sendToAllUsers();
    
    echo "[SUCCESS] Sent {$result['sent']} emails successfully.\n";
    
    if ($result['failed'] > 0) {
        echo "[WARNING] {$result['failed']} emails failed to send.\n";
        exit(1);
    }
    
    echo "[" . gmdate('Y-m-d H:i:s') . "] Daily stats email job completed.\n";
    exit(0);
    
} catch (\Throwable $e) {
    echo "[ERROR] Failed to send daily stats emails: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
