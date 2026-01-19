<?php
/** 
 * Cron Job untuk mengirim notifikasi WhatsApp
 * File: /admin/modules/wa_notification/cron.php
 * 
 * Jalankan cron job:
 * 0 8 * * * /usr/bin/php /path/to/slims/admin/modules/wa_notification/cron.php >> /path/to/slims/admin/modules/wa_notification/cron.log 2>&1
 */
define('INDEX_AUTH', true);
define('DB_ACCESS', true);

// Pastikan dijalankan dari CLI
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Load SLiMS config
require __DIR__ . '/../../../sysconfig.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';

// Load notification service
require __DIR__ . '/lib/NotificationService.class.php';

// Mulai proses
echo "[" . date('Y-m-d H:i:s') . "] Starting WhatsApp notification process...\n";

try {
    $service = new NotificationService($dbs);
    $result = $service->processNotifications();

    // Output result
    echo "Total Processed: " . $result['total_processed'] . "\n";
    echo "Success: " . $result['success'] . "\n";
    echo "Failed: " . $result['failed'] . "\n";
    echo "Skipped: " . $result['skipped'] . "\n";

    if (!empty($result['errors'])) {
        echo "\nErrors:\n";
        foreach ($result['errors'] as $error) {
            echo "- " . $error . "\n";
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Process completed successfully.\n";
    exit(0);
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}