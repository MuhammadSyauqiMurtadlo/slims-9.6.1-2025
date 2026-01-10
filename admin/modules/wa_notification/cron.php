<?php
/** 
 * Cron Job untuk mengirim notifikasi WhatsApp
 * File: /admin/modules/wa_notification/cron.php
 * 
 * Jalankan cron job:
 * ! 0 8 * * * /usr/bin/php /path/to/slims/admin/modules/wa_notification/cron.php
 */

// Pastikan dijalankan dari CLI
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

// Load SLiMS config
require '../../../sysconfig.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';

// Load notification service
require 'lib/NotificationService.class.php';

// Mulai proses
echo "[" . date('Y-m-d H:i:s') . "] Starting WhatsApp notification process...\n";

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

echo "[" . date('Y-m-d H:i:s') . "] Process completed.\n";