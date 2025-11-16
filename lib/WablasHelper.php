<?php

/**
 * Wablas Helper Class
 * Centralized helper untuk Wablas integration
 */

namespace SLiMS\Wablas;

use SLiMS\DB;
use PDO;
use Exception;

class WablasHelper
{
  private static $configCache = null;
  private static $db = null;

  /**
   * Get database instance
   */
  private static function getDB()
  {
    if (self::$db === null) {
      self::$db = DB::getInstance();
    }
    return self::$db;
  }

  /**
   * Get Wablas configuration (cached)
   */
  public static function getConfig()
  {
    if (self::$configCache === null) {
      $db = self::getDB();
      $stmt = $db->query("SELECT * FROM wablas_account ORDER BY id DESC LIMIT 1");
      self::$configCache = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!self::$configCache) {
        throw new Exception("Wablas configuration not found. Please configure it first.");
      }
    }
    return self::$configCache;
  }

  /**
   * Sanitize and format phone number
   */
  public static function sanitizePhone($phone)
  {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // Validate length
    if (strlen($phone) < 10 || strlen($phone) > 15) {
      return false;
    }

    // Convert 08xxx to 628xxx
    if (substr($phone, 0, 1) === '0') {
      $phone = '62' . substr($phone, 1);
    }

    // Ensure it starts with 62
    if (substr($phone, 0, 2) !== '62') {
      $phone = '62' . $phone;
    }

    return $phone;
  }

  /**
   * Add message to queue
   */
  public static function addToQueue($phone, $message, $type = 'reminder', $maxRetries = 3)
  {
    $db = self::getDB();

    $phone = self::sanitizePhone($phone);
    if (!$phone) {
      self::log("Invalid phone number: " . $phone, 'ERROR');
      return false;
    }

    try {
      $stmt = $db->prepare("
                INSERT INTO wablas_queue 
                (phone, message, type, status, max_retries, created_at) 
                VALUES (:phone, :message, :type, 'pending', :max_retries, NOW())
            ");

      $result = $stmt->execute([
        ':phone' => $phone,
        ':message' => $message,
        ':type' => $type,
        ':max_retries' => $maxRetries
      ]);

      if ($result) {
        self::log("Message queued for {$phone} (type: {$type})", 'INFO');
      }

      return $result;
    } catch (Exception $e) {
      self::log("Failed to queue message: " . $e->getMessage(), 'ERROR');
      return false;
    }
  }

  /**
   * Process queue and send messages
   */
  public static function processQueue($limit = 10)
  {
    $db = self::getDB();

    try {
      // Get pending messages
      $stmt = $db->prepare("
                SELECT * FROM wablas_queue 
                WHERE status = 'pending' 
                AND retry_count < max_retries 
                ORDER BY created_at ASC 
                LIMIT :limit
            ");
      $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
      $stmt->execute();
      $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $results = [
        'processed' => 0,
        'sent' => 0,
        'failed' => 0
      ];

      foreach ($messages as $msg) {
        $results['processed']++;

        $success = self::sendMessage($msg['phone'], $msg['message']);

        if ($success) {
          // Update status to sent
          $updateStmt = $db->prepare("
                        UPDATE wablas_queue 
                        SET status = 'sent', sent_at = NOW() 
                        WHERE id = :id
                    ");
          $updateStmt->execute([':id' => $msg['id']]);
          $results['sent']++;
        } else {
          // Increment retry count
          $updateStmt = $db->prepare("
                        UPDATE wablas_queue 
                        SET retry_count = retry_count + 1,
                            status = CASE 
                                WHEN retry_count + 1 >= max_retries THEN 'failed' 
                                ELSE 'pending' 
                            END,
                            error_message = :error
                        WHERE id = :id
                    ");
          $updateStmt->execute([
            ':id' => $msg['id'],
            ':error' => 'Failed to send message'
          ]);
          $results['failed']++;
        }

        // Delay antar message untuk avoid rate limit
        usleep(500000); // 0.5 detik
      }

      return $results;
    } catch (Exception $e) {
      self::log("Error processing queue: " . $e->getMessage(), 'ERROR');
      return false;
    }
  }

  /**
   * Send message via Wablas API
   */
  public static function sendMessage($phone, $message)
  {
    try {
      $config = self::getConfig();
      $token = $config['token'] . '.' . $config['secret_key'];
      $server = $config['wablas_host'];

      $phone = self::sanitizePhone($phone);
      if (!$phone) {
        self::log("Invalid phone format: {$phone}", 'ERROR');
        return false;
      }

      $jsonBody = json_encode([
        "data" => [[
          "phone"    => $phone,
          "message"  => $message,
          "secret"   => false,
          "retry"    => false,
          "isGroup"  => false
        ]]
      ]);

      $url = "https://{$server}.wablas.com/api/v2/send-message";
      $ch = curl_init($url);

      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonBody,
        CURLOPT_HTTPHEADER => [
          'Authorization: ' . $token,
          'Content-Type: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => true, // Enable SSL verification
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
      ]);

      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);
      curl_close($ch);

      if ($error) {
        self::log("cURL Error: {$error}", 'ERROR');
        return false;
      }

      if ($httpCode !== 200) {
        self::log("HTTP Error {$httpCode}: {$response}", 'ERROR');
        return false;
      }

      $result = json_decode($response, true);
      if (!$result || !isset($result['status']) || $result['status'] !== true) {
        self::log("API Error: " . json_encode($result), 'ERROR');
        return false;
      }

      self::log("Message sent successfully to {$phone}", 'INFO');
      return true;
    } catch (Exception $e) {
      self::log("Exception: " . $e->getMessage(), 'ERROR');
      return false;
    }
  }

  /**
   * Log notification to database
   */
  public static function logNotification($memberId, $loanId, $itemCode, $phone, $type, $status, $message, $apiResponse = null)
  {
    try {
      $db = self::getDB();
      $stmt = $db->prepare("
                INSERT INTO wablas_notification_log 
                (member_id, loan_id, item_code, phone, notification_type, status, message, api_response, created_at) 
                VALUES 
                (:member_id, :loan_id, :item_code, :phone, :type, :status, :message, :api_response, NOW())
            ");

      return $stmt->execute([
        ':member_id' => $memberId,
        ':loan_id' => $loanId,
        ':item_code' => $itemCode,
        ':phone' => $phone,
        ':type' => $type,
        ':status' => $status,
        ':message' => $message,
        ':api_response' => $apiResponse
      ]);
    } catch (Exception $e) {
      self::log("Failed to log notification: " . $e->getMessage(), 'ERROR');
      return false;
    }
  }

  /**
   * Simple file logger
   */
  public static function log($message, $level = 'INFO')
  {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
      mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/wablas_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";

    file_put_contents($logFile, $logMessage, FILE_APPEND);
  }

  /**
   * Parse message template
   */
  public static function parseTemplate($template, $data)
  {
    $replacements = [
      '{memberID}'   => $data['memberID'] ?? '',
      '{memberName}' => $data['memberName'] ?? '',
      '{itemCode}'   => $data['itemCode'] ?? '',
      '{title}'      => $data['title'] ?? '',
      '{dueDate}'    => $data['dueDate'] ?? '',
      '{loanDate}'   => $data['loanDate'] ?? '',
      '{daysLeft}'   => $data['daysLeft'] ?? '',
      '{libraryName}' => $data['libraryName'] ?? 'Perpustakaan',
    ];

    return strtr($template, $replacements);
  }
}
