<?php
/**
 * Class untuk logic pengiriman notifikasi
 * File: /admin/modules/wa_notification/lib/NotificationService.class.php
 * 
 * FIXED VERSION - Skip hanya untuk status SUCCESS
 */

require_once __DIR__ . '/WablasAPI.class.php';

class NotificationService {
    private $db;
    private $wablas;

    public function __construct($dbs) {
        $this->db = $dbs;
        $this->wablas = new WablasAPI($dbs);
    }

    /**
     * Proses utama: Jalankan pengiriman notifikasi
     * Dipanggil oleh cron job
     * 
     * @return array Result summary
     */
    public function processNotifications() {
        $result = [
            'total_processed' => 0,
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        // 1. Cek status Wablas
        echo "Checking Wablas status...\n";
        $status = $this->wablas->getFullStatus();
        if (!$status['success']) {
            $result['errors'][] = 'Failed to connect to Wablas: ' . $status['message'];
            return $result;
        }
    
        if ($status['is_expired']) {
            $result['errors'][] = 'Wablas subscription has expired on ' . $status['expired_date'];
            return $result;
        }
        echo "Wablas status: OK (Quota: {$status['quota']})\n";

        // 2. Ambil semua jadwal aktif
        echo "Getting active schedules...\n";
        $schedules = $this->getActiveSchedules();
        
        if (empty($schedules)) {
            $result['errors'][] = 'No active schedules found';
            return $result;
        }
        echo "Found " . count($schedules) . " active schedules\n";

        // 3. Loop setiap jadwal
        foreach ($schedules as $schedule) {
            echo "\n[SCHEDULE] " . $schedule['notification_type'] . " (days_before: " . $schedule['days_before'] . ")\n";
            $loans = $this->getLoansForNotification($schedule);
            echo "Found " . count($loans) . " loan(s)\n";
            
            foreach ($loans as $loan) {
                echo "  → Processing: {$loan['member_name']} (loan_id: {$loan['loan_id']})\n";
                $result['total_processed']++;
                
                //! 🔥 FIX: Cek apakah sudah SUKSES dikirim hari ini
                // if ($this->isAlreadySentSuccessfully($loan['loan_id'], $schedule['notification_type'])) {
                //     echo "    ✓ Already sent successfully today, skipping...\n";
                //     $result['skipped']++;
                //     continue;
                // }
                
                // Generate pesan
                $message = $this->generateMessage($loan, $schedule['notification_type']);
                
                if (empty($message)) {
                    echo "    ✗ Failed: Empty message (template inactive?)\n";
                    $result['failed']++;
                    $result['errors'][] = "Empty message for {$loan['member_name']} (loan_id: {$loan['loan_id']})";
                    continue;
                }
                
                echo "    Sending to: {$loan['member_phone']}\n";
                
                // Kirim WhatsApp
                $sendResult = $this->sendNotification($loan, $message, $schedule['notification_type']);
                
                if ($sendResult['success']) {
                    echo "    ✓ SUCCESS\n";
                    $result['success']++;
                } else {
                    echo "    ✗ FAILED: {$sendResult['error']}\n";
                    $result['failed']++;
                    $result['errors'][] = "Failed to send to {$loan['member_name']}: {$sendResult['error']}";
                }
            }
        }

        // 4. Update last run
        $this->updateLastRun();

        return $result;
    }

    /**
     * Ambil jadwal notifikasi yang aktif
     * 
     * @return array List of schedules
     */
    private function getActiveSchedules() {
        $schedules = [];
        
        $query = $this->db->query("SELECT 
                                    template_id as schedule_id,
                                    notification_type,
                                    days_before,
                                    send_time,
                                    is_active
                                   FROM wa_templates 
                                   WHERE is_active = 1 
                                   ORDER BY days_before DESC");
        
        if ($query) {
            while ($row = $query->fetch_assoc()) {
                $schedules[] = $row;
            }
        }
        
        return $schedules;
    }

    /**
     * Ambil data peminjaman yang perlu dikirim notifikasi
     * 
     * @param array $schedule Schedule data
     * @return array List of loans
     */
    private function getLoansForNotification($schedule) {
        $loans = [];
        $daysBefore = (int)$schedule['days_before'];
        
        // Konversi days_before ke interval yang benar
        // H-3: days_before = -3 → check due_date 3 hari dari sekarang
        // H-1: days_before = -1 → check due_date 1 hari dari sekarang (besok)
        // H+0: days_before = 0 → check due_date hari ini
        $intervalDays = abs($daysBefore);
        
        $sql = "SELECT 
                    l.loan_id,
                    l.member_id,
                    l.item_code,
                    l.due_date,
                    m.member_name,
                    m.member_phone,
                    b.title as book_title
                FROM loan l
                INNER JOIN member m ON l.member_id = m.member_id
                INNER JOIN item i ON l.item_code = i.item_code
                INNER JOIN biblio b ON i.biblio_id = b.biblio_id
                WHERE l.is_lent = 1
                  AND l.is_return = 0
                  AND DATE(l.due_date) = DATE_ADD(CURDATE(), INTERVAL {$intervalDays} DAY)
                  AND m.member_phone IS NOT NULL
                  AND m.member_phone != ''";
        
        $query = $this->db->query($sql);
        
        if ($query) {
            while ($row = $query->fetch_assoc()) {
                $loans[] = $row;
            }
        }
        
        return $loans;
    }

    /**
     * 🔥 FIX: Cek apakah notifikasi sudah SUKSES dikirim hari ini
     * Hanya skip jika status = 'success', bukan 'failed'
     * 
     * @param string $loanId Loan ID
     * @param string $notificationType Notification type
     * @return bool
     */
    private function isAlreadySentSuccessfully($loanId, $notificationType) {
        $loanId = $this->db->real_escape_string($loanId);
        $notificationType = $this->db->real_escape_string($notificationType);
        
        // 🔥 TAMBAHKAN kondisi status = 'success'
        $query = $this->db->query("SELECT log_id FROM wa_logs 
                                   WHERE loan_id = '{$loanId}' 
                                   AND notification_type = '{$notificationType}'
                                   AND status = 'success'
                                   AND DATE(created_at) = CURDATE()
                                   LIMIT 1");
        
        return ($query && $query->num_rows > 0);
    }

    /**
     * Generate pesan dari template
     * 
     * @param array $loan Loan data
     * @param string $notificationType Notification type
     * @return string Generated message
     */
    private function generateMessage($loan, $notificationType) {
        $notificationType = $this->db->real_escape_string($notificationType);
        $query = $this->db->query("SELECT template_message FROM wa_templates 
                                   WHERE notification_type = '{$notificationType}' 
                                   AND is_active = 1 
                                   LIMIT 1");
        
        if (!$query || $query->num_rows == 0) {
            return '';
        }
        
        $row = $query->fetch_assoc();
        $template = $row['template_message'];
        
        // Replace variabel
        $message = str_replace(
            ['{member_name}', '{member_id}', '{book_title}', '{item_code}', '{due_date}'],
            [
                $loan['member_name'],
                $loan['member_id'],
                $loan['book_title'],
                $loan['item_code'],
                date('d-m-Y', strtotime($loan['due_date']))
            ],
            $template
        );
        
        return $message;
    }

    /**
     * Kirim notifikasi WhatsApp dan simpan log
     * 
     * @param array $loan Loan data
     * @param string $message Message to send
     * @param string $notificationType Notification type
     * @return array Result
     */
    private function sendNotification($loan, $message, $notificationType) {
        // Kirim via Wablas
        $sendResult = $this->wablas->sendMessage($loan['member_phone'], $message);
        
        // Prepare log data
        $logData = [
            'loan_id' => $this->db->real_escape_string($loan['loan_id']),
            'member_id' => $this->db->real_escape_string($loan['member_id']),
            'member_name' => $this->db->real_escape_string($loan['member_name']),
            'member_phone' => $this->db->real_escape_string($loan['member_phone']),
            'book_title' => $this->db->real_escape_string($loan['book_title']),
            'item_code' => $this->db->real_escape_string($loan['item_code']),
            'due_date' => $loan['due_date'],
            'notification_type' => $this->db->real_escape_string($notificationType),
            'message_sent' => $this->db->real_escape_string($message),
            'status' => $sendResult['success'] ? 'success' : 'failed',
            'wablas_response' => $this->db->real_escape_string(json_encode($sendResult)),
            'wablas_message_id' => isset($sendResult['data']['id']) ? $this->db->real_escape_string($sendResult['data']['id']) : '',
            'error_message' => !$sendResult['success'] ? $this->db->real_escape_string($sendResult['message']) : '',
            'sent_at' => date('Y-m-d H:i:s')
        ];
        
        // Insert log
        $sql = "INSERT INTO wa_logs 
                (loan_id, member_id, member_name, member_phone, book_title, item_code, 
                 due_date, notification_type, message_sent, status, wablas_response, 
                 wablas_message_id, error_message, sent_at, created_at)
                VALUES 
                ('{$logData['loan_id']}', '{$logData['member_id']}', '{$logData['member_name']}', 
                 '{$logData['member_phone']}', '{$logData['book_title']}', '{$logData['item_code']}', 
                 '{$logData['due_date']}', '{$logData['notification_type']}', '{$logData['message_sent']}', 
                 '{$logData['status']}', '{$logData['wablas_response']}', '{$logData['wablas_message_id']}', 
                 '{$logData['error_message']}', '{$logData['sent_at']}', NOW())";
        
        $this->db->query($sql);
        
        return [
            'success' => $sendResult['success'],
            'error' => $logData['error_message']
        ];
    }

    /**
     * Update waktu terakhir cron dijalankan
     */
    private function updateLastRun() {
        $now = date('Y-m-d H:i:s');
        
        $check = $this->db->query("SELECT setting_key FROM wa_settings WHERE setting_key = 'cron_last_run'");
        
        if ($check && $check->num_rows > 0) {
            $this->db->query("UPDATE wa_settings SET setting_value = '{$now}' WHERE setting_key = 'cron_last_run'");
        } else {
            $this->db->query("INSERT INTO wa_settings (setting_key, setting_value, setting_description) VALUES ('cron_last_run', '{$now}', 'Last cron execution time')");
        }
    }
}