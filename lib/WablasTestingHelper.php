<?php

/**
 * Wablas Testing Helper
 * Override due date untuk testing purposes
 */

namespace SLiMS\Wablas;

use SLiMS\DB;
use PDO;

class WablasTestingHelper
{
  /**
   * Check if testing mode is enabled
   */
  public static function isTestingMode()
  {
    try {
      $config = WablasHelper::getConfig();
      return !empty($config['testing_mode']);
    } catch (\Exception $e) {
      return false;
    }
  }

  /**
   * Get testing configuration
   */
  public static function getTestingConfig()
  {
    $config = WablasHelper::getConfig();

    return [
      'enabled' => !empty($config['testing_mode']),
      'loan_duration' => (int)($config['testing_loan_duration'] ?? 2),
      'reminder_before' => (int)($config['testing_reminder_before'] ?? 1)
    ];
  }

  /**
   * Calculate due date based on testing mode
   * 
   * @param string $loanDate Format: Y-m-d H:i:s
   * @return string Due date in Y-m-d H:i:s format
   */
  public static function calculateDueDate($loanDate = null)
  {
    $testConfig = self::getTestingConfig();

    if (!$testConfig['enabled']) {
      // Production mode: 7 days
      return date('Y-m-d H:i:s', strtotime('+7 days', strtotime($loanDate ?? 'now')));
    }

    // Testing mode: X hours
    $hours = $testConfig['loan_duration'];
    return date('Y-m-d H:i:s', strtotime("+{$hours} hours", strtotime($loanDate ?? 'now')));
  }

  /**
   * Override due date untuk loan yang baru dibuat
   * Harus dipanggil SETELAH loan created
   * 
   * @param int $loanId
   * @param string $originalDueDate
   * @return bool
   */
  public static function overrideLoanDueDate($loanId, $originalDueDate = null)
  {
    $testConfig = self::getTestingConfig();

    if (!$testConfig['enabled']) {
      // Production mode, skip override
      return false;
    }

    try {
      $db = DB::getInstance();

      // Get loan date
      $stmt = $db->prepare("SELECT loan_date FROM loan WHERE loan_id = :id");
      $stmt->execute([':id' => $loanId]);
      $loan = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$loan) {
        WablasHelper::log("Loan ID {$loanId} not found for due date override", 'WARNING');
        return false;
      }

      // Calculate new due date
      $newDueDate = self::calculateDueDate($loan['loan_date']);

      // Update due date
      $update = $db->prepare("
                UPDATE loan 
                SET due_date = :due_date 
                WHERE loan_id = :id
            ");

      $result = $update->execute([
        ':due_date' => $newDueDate,
        ':id' => $loanId
      ]);

      if ($result) {
        WablasHelper::log(
          "Testing Mode: Loan #{$loanId} due date overridden to {$newDueDate} ({$testConfig['loan_duration']} hours)",
          'INFO'
        );
      }

      return $result;
    } catch (\Exception $e) {
      WablasHelper::log("Error overriding due date: " . $e->getMessage(), 'ERROR');
      return false;
    }
  }

  /**
   * Bulk override due dates untuk existing loans (untuk testing)
   * WARNING: Use with caution!
   * 
   * @param array $loanIds Array of loan IDs
   * @return array Results
   */
  public static function bulkOverrideDueDates($loanIds = [])
  {
    $testConfig = self::getTestingConfig();

    if (!$testConfig['enabled']) {
      return ['error' => 'Testing mode is not enabled'];
    }

    $db = DB::getInstance();
    $results = [
      'success' => 0,
      'failed' => 0,
      'skipped' => 0
    ];

    try {
      // If no loan IDs specified, get all active loans
      if (empty($loanIds)) {
        $stmt = $db->query("
                    SELECT loan_id, loan_date 
                    FROM loan 
                    WHERE is_lent = 1 
                    AND is_return = 0
                ");
        $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
      } else {
        $placeholders = str_repeat('?,', count($loanIds) - 1) . '?';
        $stmt = $db->prepare("
                    SELECT loan_id, loan_date 
                    FROM loan 
                    WHERE loan_id IN ($placeholders)
                    AND is_lent = 1 
                    AND is_return = 0
                ");
        $stmt->execute($loanIds);
        $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
      }

      foreach ($loans as $loan) {
        $newDueDate = self::calculateDueDate($loan['loan_date']);

        $update = $db->prepare("
                    UPDATE loan 
                    SET due_date = :due_date,
                        reminder_h1_sent = 0,
                        reminder_h1_sent_at = NULL
                    WHERE loan_id = :id
                ");

        if ($update->execute([':due_date' => $newDueDate, ':id' => $loan['loan_id']])) {
          $results['success']++;
        } else {
          $results['failed']++;
        }
      }

      WablasHelper::log(
        "Bulk override: {$results['success']} success, {$results['failed']} failed",
        'INFO'
      );
    } catch (\Exception $e) {
      WablasHelper::log("Bulk override error: " . $e->getMessage(), 'ERROR');
      $results['error'] = $e->getMessage();
    }

    return $results;
  }

  /**
   * Get reminder time (when to send reminder)
   * 
   * @param string $dueDate
   * @return string Reminder datetime
   */
  public static function calculateReminderTime($dueDate)
  {
    $testConfig = self::getTestingConfig();

    if (!$testConfig['enabled']) {
      // Production: H-1 (1 day before)
      return date('Y-m-d H:i:s', strtotime('-1 day', strtotime($dueDate)));
    }

    // Testing: X hours before
    $hoursBefore = $testConfig['reminder_before'];
    return date('Y-m-d H:i:s', strtotime("-{$hoursBefore} hours", strtotime($dueDate)));
  }

  /**
   * Get loans ready for reminder
   * Based on testing or production mode
   */
  public static function getLoansForReminder()
  {
    $testConfig = self::getTestingConfig();
    $db = DB::getInstance();

    if (!$testConfig['enabled']) {
      // Production mode: due tomorrow
      $targetDate = date('Y-m-d', strtotime('+1 day'));
      $condition = "DATE(l.due_date) = :target_date";
    } else {
      // Testing mode: due in X hours
      $hoursRemaining = $testConfig['reminder_before'];
      $fromTime = date('Y-m-d H:i:s');
      $toTime = date('Y-m-d H:i:s', strtotime("+{$hoursRemaining} hours"));

      $condition = "l.due_date BETWEEN :from_time AND :to_time";
    }

    $sql = "
            SELECT 
                l.loan_id,
                l.item_code,
                l.member_id,
                l.loan_date,
                l.due_date,
                m.member_name,
                m.member_phone,
                b.title,
                TIMESTAMPDIFF(HOUR, NOW(), l.due_date) as hours_remaining
            FROM loan l
            INNER JOIN member m ON l.member_id = m.member_id
            INNER JOIN item i ON l.item_code = i.item_code
            INNER JOIN biblio b ON i.biblio_id = b.biblio_id
            WHERE 
                {$condition}
                AND l.is_lent = 1
                AND l.is_return = 0
                AND (l.reminder_h1_sent IS NULL OR l.reminder_h1_sent = 0)
                AND m.member_phone IS NOT NULL
                AND m.member_phone != ''
            ORDER BY l.member_id, l.due_date
        ";

    $stmt = $db->prepare($sql);

    if (!$testConfig['enabled']) {
      $stmt->execute([':target_date' => $targetDate]);
    } else {
      $stmt->execute([
        ':from_time' => $fromTime,
        ':to_time' => $toTime
      ]);
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Display testing mode info
   */
  public static function displayTestingInfo()
  {
    $testConfig = self::getTestingConfig();

    if (!$testConfig['enabled']) {
      return "⚙️ Production Mode (7 days loan period)\n";
    }

    $info = "🧪 TESTING MODE ACTIVE\n";
    $info .= "═══════════════════════════════════════\n";
    $info .= "Loan Duration: {$testConfig['loan_duration']} hours\n";
    $info .= "Reminder Before: {$testConfig['reminder_before']} hours\n";
    $info .= "═══════════════════════════════════════\n\n";

    $info .= "Example Timeline:\n";
    $now = date('Y-m-d H:i:s');
    $dueDate = self::calculateDueDate($now);
    $reminderTime = self::calculateReminderTime($dueDate);

    $info .= "• Loan Time    : {$now}\n";
    $info .= "• Reminder At  : {$reminderTime}\n";
    $info .= "• Due Date     : {$dueDate}\n\n";

    return $info;
  }
}
