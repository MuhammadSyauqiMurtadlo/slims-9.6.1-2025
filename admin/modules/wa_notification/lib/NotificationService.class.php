<?php
/**
 * Class untuk logic pengiriman notifikasi
 * File: /admin/modules/wa_notification/lib/NotificationService.class.php
 */

require_once 'WablasAPI.class.php';

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

        // 1. Cek apakah notifikasi aktif
        if (!$this->isNotificationEnabled()) {
            $result['errors'][] = 'Notification is disabled in settings';
            return $result;
        }

        // 2. Cek status Wablas
        $status = $this->wablas->checkStatus();
        if ($status['is_expired']) {
            $result['errors'][] = 'Wablas subscription has expired';
            return $result;
        }

        // 3. Ambil semua jadwal aktif
        $schedules = $this->getActiveSchedules();

        // 4. Loop setiap jadwal
        foreach ($schedules as $schedule) {
            $loans = $this->getLoansForNotification($schedule);
            
            foreach ($loans as $loan) {
                $result['total_processed']++;
                
                // Cek apakah sudah pernah dikirim hari ini
                if ($this->isAlreadySent($loan['loan_id'], $schedule['notification_type'])) {
                    $result['skipped']++;
                    continue;
                }
                
                // Generate pesan
                $message = $this->generateMessage($loan, $schedule['notification_type']);
                
                // Kirim WhatsApp
                $sendResult = $this->sendNotification($loan, $message, $schedule['notification_type']);
                
                if ($sendResult['success']) {
                    $result['success']++;
                } else {
                    $result['failed']++;
                    $result['errors'][] = "Failed to send to {$loan['member_name']}: {$sendResult['error']}";
                }
            }
        }

        // 5. Update last run
        $this->updateLastRun();

        return $result;
    }

    /**
     * Cek apakah notifikasi diaktifkan
     * 
     * @return bool
     */
    private function isNotificationEnabled() {
        $query = $this->db->query("SELECT setting_value FROM wa_settings 
                                   WHERE setting_key = 'notification_enabled'");
        $row = $query->fetch_assoc();
        return ($row['setting_value'] == '1');
    }

    /**
     * Ambil jadwal notifikasi yang aktif
     * 
     * @return array List of schedules
     */
    private function getActiveSchedules() {
        $schedules = [];
        $query = $this->db->query("SELECT * FROM wa_schedules WHERE is_active = 1 ORDER BY days_before ASC");
        
        while ($row = $query->fetch_assoc()) {
            $schedules[] = $row;
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
        $daysBefore = $schedule['days_before'];
        
        // Query dengan JOIN ke tabel member, item, dan biblio
        $sql = "SELECT 
                    l.loan_id,
                    l.member_id,
                    l.item_code,
                    l.due_date,
                    m.member_name,
                    m.phone_num,
                    b.title as book_title
                FROM loan l
                INNER JOIN member m ON l.member_id = m.member_id
                INNER JOIN item i ON l.item_code = i.item_code
                INNER JOIN biblio b ON i.biblio_id = b.biblio_id
                WHERE l.is_lent = 1
                AND l.is_return = 0
                AND DATE(l.due_date) = DATE_ADD(CURDATE(), INTERVAL {$daysBefore} DAY)
                AND m.phone_num IS NOT NULL
                AND m.phone_num != ''";
        
        $query = $this->db->query($sql);
        
        if ($query) {
            while ($row = $query->fetch_assoc()) {
                $loans[] = $row;
            }
        }
        
        return $loans;
    }

    /**
     * Cek apakah notifikasi sudah pernah dikirim hari ini
     * 
     * @param string $loanId Loan ID
     * @param string $notificationType Notification type
     * @return bool
     */
    private function isAlreadySent($loanId, $notificationType) {
        $loanId = $this->db->real_escape_string($loanId);
        $notificationType = $this->db->real_escape_string($notificationType);
        
        $query = $this->db->query("SELECT log_id FROM wa_logs 
                                   WHERE loan_id = '{$loanId}' 
                                   AND notification_type = '{$notificationType}'
                                   AND DATE(created_at) = CURDATE()
                                   LIMIT 1");
        
        return ($query->num_rows > 0);
    }

    /**
     * Generate pesan dari template
     * 
     * @param array $loan Loan data
     * @param string $notificationType Notification type
     * @return string Generated message
     */
    private function generateMessage($loan, $notificationType) {
        // Ambil template
        $notificationType = $this->db->real_escape_string($notificationType);
        $query = $this->db->query("SELECT template_message FROM wa_templates 
                                   WHERE notification_type = '{$notificationType}' 
                                   AND is_active = 1 
                                   LIMIT 1");
        
        if ($query->num_rows == 0) {
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
        $sendResult = $this->wablas->sendMessage($loan['phone_num'], $message);
        
        // Prepare log data
        $logData = [
            'loan_id' => $this->db->real_escape_string($loan['loan_id']),
            'member_id' => $this->db->real_escape_string($loan['member_id']),
            'member_name' => $this->db->real_escape_string($loan['member_name']),
            'member_phone' => $this->db->real_escape_string($loan['phone_num']),
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
        $this->db->query("UPDATE wa_settings SET setting_value = '{$now}' 
                         WHERE setting_key = 'cron_last_run'");
    }

    /**
     * Get dashboard statistics
     * 
     * @return array Stats
     */
    public function getDashboardStats() {
        $stats = [];
        
        // Total pesan hari ini
        $query = $this->db->query("SELECT COUNT(*) as total FROM wa_logs 
                                   WHERE DATE(sent_at) = CURDATE()");
        $row = $query->fetch_assoc();
        $stats['today_total'] = $row['total'];
        
        // Success rate hari ini
        $query = $this->db->query("SELECT COUNT(*) as total FROM wa_logs 
                                   WHERE DATE(sent_at) = CURDATE() AND status = 'success'");
        $row = $query->fetch_assoc();
        $stats['today_success'] = $row['total'];
        
        // Failed hari ini
        $stats['today_failed'] = $stats['today_total'] - $stats['today_success'];
        
        // Total pesan bulan ini
        $query = $this->db->query("SELECT COUNT(*) as total FROM wa_logs 
                                   WHERE MONTH(sent_at) = MONTH(CURDATE()) 
                                   AND YEAR(sent_at) = YEAR(CURDATE())");
        $row = $query->fetch_assoc();
        $stats['month_total'] = $row['total'];
        
        // Pending notifications (loan yang akan jatuh tempo dalam 3 hari)
        $query = $this->db->query("SELECT COUNT(*) as total FROM loan 
                                   WHERE is_lent = 1 AND is_return = 0 
                                   AND DATE(due_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)");
        $row = $query->fetch_assoc();
        $stats['pending_notifications'] = $row['total'];
        
        // Wablas status
        $status = $this->wablas->checkStatus();
        $stats['wablas_status'] = $status;
        
        // Device info (kuota)
        $deviceInfo = $this->wablas->getDeviceInfo();
        $stats['device_info'] = $deviceInfo;
        
        return $stats;
    }
}
?>