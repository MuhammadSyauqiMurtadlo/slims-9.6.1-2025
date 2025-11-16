<?php

// Prevent direct browser access
if (php_sapi_name() !== 'cli') {
  die("This script can only be run from command line!");
}

date_default_timezone_set('Asia/Jakarta');

// Bootstrap SLiMS
define('INDEX_AUTH', 1);
define('SENAYAN_BASE_DIR', __DIR__);

require_once __DIR__ . '/sysconfig.inc.php';

use SLiMS\Wablas\WablasHelper;

echo "\n========================================\n";
echo "WABLAS QUEUE PROCESSOR\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

WablasHelper::log("=== Queue Processor Started ===", 'INFO');

try {
  // Process up to 50 messages per run
  $results = WablasHelper::processQueue(50);

  if ($results) {
    echo "Queue Results:\n";
    echo "  - Processed: {$results['processed']}\n";
    echo "  - Sent: {$results['sent']}\n";
    echo "  - Failed: {$results['failed']}\n\n";

    WablasHelper::log(
      "Queue: {$results['processed']} processed, {$results['sent']} sent, {$results['failed']} failed",
      'INFO'
    );
  } else {
    echo "No messages in queue or processing error.\n";
  }
} catch (Exception $e) {
  echo "\n✗ ERROR: " . $e->getMessage() . "\n";
  WablasHelper::log("ERROR: " . $e->getMessage(), 'ERROR');
  exit(1);
}

echo "\nFinished at: " . date('Y-m-d H:i:s') . "\n";
WablasHelper::log("=== Queue Processor Finished ===", 'INFO');
exit(0);
