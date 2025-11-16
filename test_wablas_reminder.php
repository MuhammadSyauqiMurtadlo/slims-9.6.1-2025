<?php

/**
 * Wablas Testing Script
 * 
 * Script untuk testing lengkap dengan simulasi loan
 * 
 * Usage: php test_wablas_reminder.php [options]
 * 
 * Options:
 *   --info          Show testing mode info
 *   --create-loan   Create test loan with testing duration
 *   --check-loans   Check loans ready for reminder
 *   --override      Override existing loan due dates
 *   --send          Send reminder immediately
 *   --reset         Reset reminder flags
 */

// Prevent browser access
if (php_sapi_name() !== 'cli') {
  die("This script can only be run from command line!");
}

date_default_timezone_set('Asia/Jakarta');

define('INDEX_AUTH', 1);
define('SENAYAN_BASE_DIR', __DIR__);
require_once __DIR__ . '/sysconfig.inc.php';

use SLiMS\DB;
use SLiMS\Wablas\WablasHelper;
use SLiMS\Wablas\WablasTestingHelper;

// Parse arguments
$options = [
  'info' => in_array('--info', $argv),
  'create' => in_array('--create-loan', $argv),
  'check' => in_array('--check-loans', $argv),
  'override' => in_array('--override', $argv),
  'send' => in_array('--send', $argv),
  'reset' => in_array('--reset', $argv),
];

// If no options, show help
if (!array_filter($options)) {
  echo "\n";
  echo "╔════════════════════════════════════════════════════════╗\n";
  echo "║        WABLAS REMINDER TESTING SCRIPT                  ║\n";
  echo "╚════════════════════════════════════════════════════════╝\n\n";
  echo "Usage: php test_wablas_reminder.php [options]\n\n";
  echo "Options:\n";
  echo "  --info          Show testing mode configuration\n";
  echo "  --create-loan   Create test loan with short duration\n";
  echo "  --check-loans   Check loans ready for reminder\n";
  echo "  --override      Override existing active loans to testing duration\n";
  echo "  --send          Send reminders now\n";
  echo "  --reset         Reset reminder sent flags\n\n";
  echo "Examples:\n";
  echo "  php test_wablas_reminder.php --info\n";
  echo "  php test_wablas_reminder.php --override --send\n";
  echo "  php test_wablas_reminder.php --check-loans\n\n";
  exit(0);
}

$db = DB::getInstance();

echo "\n";
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║        WABLAS REMINDER TESTING                         ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

// Option: Show Info
if ($options['info']) {
  echo WablasTestingHelper::displayTestingInfo();

  $config = WablasHelper::getConfig();
  echo "Additional Config:\n";
  echo "  Server: {$config['wablas_host']}\n";
  echo "  Token: " . substr($config['token'], 0, 10) . "...\n\n";
}

// Option: Check Loans
if ($options['check']) {
  echo "Checking loans ready for reminder...\n";
  echo "───────────────────────────────────────────────\n\n";

  $loans = WablasTestingHelper::getLoansForReminder();

  if (empty($loans)) {
    echo "✓ No loans found ready for reminder.\n\n";
  } else {
    echo "Found " . count($loans) . " loan(s):\n\n";

    foreach ($loans as $idx => $loan) {
      echo ($idx + 1) . ". Member: {$loan['member_name']} ({$loan['member_id']})\n";
      echo "   Phone: {$loan['member_phone']}\n";
      echo "   Book: {$loan['title']}\n";
      echo "   Code: {$loan['item_code']}\n";
      echo "   Loan Date: {$loan['loan_date']}\n";
      echo "   Due Date: {$loan['due_date']}\n";

      if (isset($loan['hours_remaining'])) {
        echo "   ⏰ Hours Remaining: " . round($loan['hours_remaining'], 2) . "\n";
      }

      echo "\n";
    }
  }
}

// Option: Override Existing Loans
if ($options['override']) {
  echo "Overriding existing active loans...\n";
  echo "───────────────────────────────────────────────\n\n";

  $testConfig = WablasTestingHelper::getTestingConfig();

  if (!$testConfig['enabled']) {
    echo "✗ Testing mode is not enabled!\n";
    echo "  Please enable testing mode in Wablas configuration first.\n\n";
    exit(1);
  }

  echo "⚠️  WARNING: This will change due dates of ALL active loans!\n";
  echo "Duration: {$testConfig['loan_duration']} hours from loan date\n";
  echo "Reminder: {$testConfig['reminder_before']} hours before due\n\n";

  echo "Continue? (yes/no): ";
  $handle = fopen("php://stdin", "r");
  $line = trim(fgets($handle));
  fclose($handle);

  if (strtolower($line) !== 'yes') {
    echo "Cancelled.\n\n";
    exit(0);
  }

  $results = WablasTestingHelper::bulkOverrideDueDates();

  echo "\nResults:\n";
  echo "  Success: {$results['success']}\n";
  echo "  Failed: {$results['failed']}\n";

  if (isset($results['error'])) {
    echo "  Error: {$results['error']}\n";
  }

  echo "\n✓ Override completed!\n\n";

  // Show sample
  if ($results['success'] > 0) {
    echo "Sample updated loans:\n";
    $stmt = $db->query("
            SELECT loan_id, item_code, member_id, loan_date, due_date,
                   TIMESTAMPDIFF(HOUR, NOW(), due_date) as hours_remaining
            FROM loan 
            WHERE is_lent = 1 AND is_return = 0
            ORDER BY due_date ASC
            LIMIT 3
        ");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($samples as $sample) {
      echo "  • Loan #{$sample['loan_id']} - Due: {$sample['due_date']} ";
      echo "({$sample['hours_remaining']}h remaining)\n";
    }
    echo "\n";
  }
}

// Option: Reset Reminder Flags
if ($options['reset']) {
  echo "Resetting reminder sent flags...\n";
  echo "───────────────────────────────────────────────\n\n";

  $reset = $db->query("
        UPDATE loan 
        SET reminder_h1_sent = 0, 
            reminder_h1_sent_at = NULL
        WHERE is_lent = 1 
        AND is_return = 0
    ");

  $count = $reset->rowCount();
  echo "✓ Reset {$count} loan(s)\n\n";
}

// Option: Send Reminders
if ($options['send']) {
  echo "Sending reminders now...\n";
  echo "───────────────────────────────────────────────\n\n";

  // Include the reminder script
  echo "Running reminder script...\n\n";
  include __DIR__ . '/cron_reminder_h1.php';
}

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║        Testing completed at " . date('H:i:s') . "                   ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";
