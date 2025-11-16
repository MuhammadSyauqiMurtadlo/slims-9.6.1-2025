<?php


// Prevent direct browser access
if (php_sapi_name() !== 'cli') {
  die("This script can only be run from command line!");
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Bootstrap SLiMS
define('INDEX_AUTH', 1);
define('SENAYAN_BASE_DIR', __DIR__);

// Load autoloader
require_once __DIR__ . '/sysconfig.inc.php';

use SLiMS\DB;
use SLiMS\Wablas\WablasHelper;
use SLiMS\Wablas\WablasTestingHelper;

// Start script
echo "\n========================================\n";
echo "WABLAS H-1 REMINDER SCRIPT\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// Display testing mode info
echo WablasTestingHelper::displayTestingInfo();

WablasHelper::log("=== Cron H-1 Reminder Started ===", 'INFO');

try {
  $db = DB::getInstance();

  // Get config
  $config = WablasHelper::getConfig();
  $testConfig = WablasTestingHelper::getTestingConfig();

  if (empty($config['loan_message'])) {
    throw new Exception("Reminder message template not configured!");
  }

  // Template untuk reminder
  $reminderTemplate = "🔔 *PENGINGAT PENGEMBALIAN BUKU*\n\n"
    . "Halo {memberName},\n\n"
    . "Buku yang Anda pinjam akan jatuh tempo:\n"
    . "⏰ *{dueDateTime}*\n\n"
    . "📖 *{title}*\n"
    . "Kode: {itemCode}\n"
    . "Tanggal Pinjam: {loanDateTime}\n\n"
    . "Mohon segera kembalikan atau perpanjang sebelum jatuh tempo untuk menghindari denda.\n\n"
    . "Terima kasih,\n"
    . "{libraryName}";

  // Get loans ready for reminder (automatic handling of testing/production mode)
  $loans = WablasTestingHelper::getLoansForReminder();

  if (empty($loans)) {
    $mode = $testConfig['enabled'] ? 'testing' : 'production';
    echo "✓ No loans ready for reminder in {$mode} mode.\n";
    WablasHelper::log("No loans ready for reminder ({$mode} mode)", 'INFO');
  } else {
    echo "Found " . count($loans) . " loan(s) to remind:\n\n";

    $stats = [
      'total' => count($loans),
      'queued' => 0,
      'skipped' => 0,
      'failed' => 0
    ];

    // Group by member
    $groupedLoans = [];
    foreach ($loans as $loan) {
      $memberId = $loan['member_id'];
      if (!isset($groupedLoans[$memberId])) {
        $groupedLoans[$memberId] = [
          'member' => [
            'id' => $loan['member_id'],
            'name' => $loan['member_name'],
            'phone' => $loan['member_phone']
          ],
          'loans' => []
        ];
      }
      $groupedLoans[$memberId]['loans'][] = $loan;
    }

    foreach ($groupedLoans as $memberId => $data) {
      $member = $data['member'];
      $memberLoans = $data['loans'];

      echo "Processing Member: {$member['name']} ({$member['id']})\n";
      echo "  Phone: {$member['phone']}\n";
      echo "  Loans: " . count($memberLoans) . "\n";

      // Display hours remaining in testing mode
      if ($testConfig['enabled']) {
        $hoursRemaining = round($memberLoans[0]['hours_remaining'], 1);
        echo "  ⏰ Due in: {$hoursRemaining} hours\n";
      }

      // Sanitize phone
      $phone = WablasHelper::sanitizePhone($member['phone']);
      if (!$phone) {
        echo "  ✗ Invalid phone number, skipped\n\n";
        $stats['skipped']++;
        continue;
      }

      // Build message dengan multiple items
      $itemsList = "";
      foreach ($memberLoans as $idx => $loan) {
        $dueDateTime = $testConfig['enabled']
          ? date('d M Y H:i', strtotime($loan['due_date']))
          : date('d M Y', strtotime($loan['due_date']));

        $loanDateTime = $testConfig['enabled']
          ? date('d M Y H:i', strtotime($loan['loan_date']))
          : date('d M Y', strtotime($loan['loan_date']));

        $itemsList .= ($idx + 1) . ". *{$loan['title']}*\n";
        $itemsList .= "   Kode: {$loan['item_code']}\n";
        $itemsList .= "   Pinjam: {$loanDateTime}\n";
        $itemsList .= "   Tempo: {$dueDateTime}\n";

        if ($testConfig['enabled']) {
          $hoursLeft = round($loan['hours_remaining'], 1);
          $itemsList .= "   ⏰ Sisa: {$hoursLeft} jam\n";
        }

        $itemsList .= "\n";
      }

      // Parse template untuk item pertama
      $firstLoan = $memberLoans[0];

      $dueDateTime = $testConfig['enabled']
        ? date('d M Y, H:i', strtotime($firstLoan['due_date'])) . ' WIB'
        : date('d M Y', strtotime($firstLoan['due_date']));

      $loanDateTime = $testConfig['enabled']
        ? date('d M Y, H:i', strtotime($firstLoan['loan_date']))
        : date('d M Y', strtotime($firstLoan['loan_date']));

      $messageData = [
        'memberID' => $member['id'],
        'memberName' => $member['name'],
        'itemCode' => $firstLoan['item_code'],
        'title' => count($memberLoans) > 1
          ? count($memberLoans) . " buku"
          : $firstLoan['title'],
        'dueDateTime' => $dueDateTime,
        'loanDateTime' => $loanDateTime,
        'libraryName' => $config['library_name'] ?? 'Perpustakaan'
      ];

      // Custom message untuk multiple items
      if (count($memberLoans) > 1) {
        $timeWarning = $testConfig['enabled']
          ? "dalam *beberapa jam* lagi"
          : "*BESOK* ({$messageData['dueDateTime']})";

        $message = "🔔 *PENGINGAT PENGEMBALIAN BUKU*\n\n"
          . "Halo *{$member['name']}*,\n\n"
          . "Anda memiliki *" . count($memberLoans) . " buku* yang akan jatuh tempo {$timeWarning}:\n\n"
          . $itemsList
          . "Mohon segera kembalikan atau perpanjang sebelum jatuh tempo untuk menghindari denda.\n\n"
          . "Terima kasih,\n"
          . $messageData['libraryName'];
      } else {
        $message = WablasHelper::parseTemplate($reminderTemplate, $messageData);
      }

      // Add to queue
      $queued = WablasHelper::addToQueue($phone, $message, 'reminder_h1');

      if ($queued) {
        // Mark sebagai sudah dikirim reminder
        foreach ($memberLoans as $loan) {
          $updateStmt = $db->prepare("
                        UPDATE loan 
                        SET reminder_h1_sent = 1, 
                            reminder_h1_sent_at = NOW() 
                        WHERE loan_id = :loan_id
                    ");
          $updateStmt->execute([':loan_id' => $loan['loan_id']]);

          // Log ke database
          WablasHelper::logNotification(
            $member['id'],
            $loan['loan_id'],
            $loan['item_code'],
            $phone,
            'reminder_h1',
            'queued',
            $message
          );
        }

        echo "  ✓ Queued for sending\n\n";
        $stats['queued']++;
      } else {
        echo "  ✗ Failed to queue\n\n";
        $stats['failed']++;
      }

      // Delay untuk avoid overload
      usleep(200000); // 0.2 detik
    }

    echo "\n========================================\n";
    echo "SUMMARY:\n";
    echo "  Total loans: {$stats['total']}\n";
    echo "  Members queued: {$stats['queued']}\n";
    echo "  Skipped: {$stats['skipped']}\n";
    echo "  Failed: {$stats['failed']}\n";
    echo "========================================\n\n";

    WablasHelper::log(
      "H-1 Reminder: {$stats['queued']} members queued, {$stats['skipped']} skipped, {$stats['failed']} failed",
      'INFO'
    );
  }

  // Process queue immediately
  echo "Processing message queue...\n";
  $queueResults = WablasHelper::processQueue(50);

  if ($queueResults) {
    echo "Queue processed:\n";
    echo "  - Processed: {$queueResults['processed']}\n";
    echo "  - Sent: {$queueResults['sent']}\n";
    echo "  - Failed: {$queueResults['failed']}\n";

    WablasHelper::log(
      "Queue processed: {$queueResults['sent']} sent, {$queueResults['failed']} failed",
      'INFO'
    );
  }
} catch (Exception $e) {
  echo "\n✗ ERROR: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  WablasHelper::log("ERROR: " . $e->getMessage(), 'ERROR');
  exit(1);
}

echo "\nScript finished at: " . date('Y-m-d H:i:s') . "\n";
WablasHelper::log("=== Cron H-1 Reminder Finished ===", 'INFO');
exit(0);
