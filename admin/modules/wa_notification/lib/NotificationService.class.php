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

        foreach ($schedules as $schedule) {
    echo "\n[SCHEDULE] " . $schedule['notification_type'] . " (days_before: " . $schedule['days_before'] . ")\n";
    $loans = $this->getLoansForNotification($schedule);
    echo "Found " . count($loans) . " loan(s)\n";
    
    // 🔥 GROUP loans by member_id
    $groupedLoans = [];
    foreach ($loans as $loan) {
        $memberId = $loan['member_id'];
        if (!isset($groupedLoans[$memberId])) {
            $groupedLoans[$memberId] = [
                'member_data' => [
                    'member_id' => $loan['member_id'],
                    'member_name' => $loan['member_name'],
                    'member_phone' => $loan['member_phone']
                ],
                'books' => []
            ];
        }
        $groupedLoans[$memberId]['books'][] = [
            'loan_id' => $loan['loan_id'],
            'book_title' => $loan['book_title'],
            'item_code' => $loan['item_code'],
            'due_date' => $loan['due_date']
        ];
    }
    
    // 🔥 Process per member (bukan per loan)
    foreach ($groupedLoans as $memberId => $data) {
        $memberData = $data['member_data'];
        $books = $data['books'];
        
        echo "  → Processing: {$memberData['member_name']} (" . count($books) . " book(s))\n";
        $result['total_processed']++;
        
        // Generate pesan untuk semua buku member ini
        $message = $this->generateMessageForMultipleBooks($memberData, $books, $schedule['notification_type']);
        
        if (empty($message)) {
            echo "    ✗ Failed: Empty message (template inactive?)\n";
            $result['failed']++;
            $result['errors'][] = "Empty message for {$memberData['member_name']}";
            continue;
        }
        
        echo "    Sending to: {$memberData['member_phone']}\n";
        
        // Kirim WhatsApp (1x untuk semua buku member ini)
        $sendResult = $this->sendNotificationForMultipleBooks($memberData, $books, $message, $schedule['notification_type']);
        
        if ($sendResult['success']) {
            echo "    ✓ SUCCESS\n";
            $result['success']++;
        } else {
            echo "    ✗ FAILED: {$sendResult['error']}\n";
            $result['failed']++;
            $result['errors'][] = "Failed to send to {$memberData['member_name']}: {$sendResult['error']}";
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
    
    // 🔥 FIX: Jika H+0, ambil semua yang jatuh tempo hari ini DAN yang sudah lewat
    if ($daysBefore == 0) {
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
                  AND DATE(l.due_date) <= CURDATE()
                  AND m.member_phone IS NOT NULL
                  AND m.member_phone != ''";
    } else {
        // Untuk H-3, H-2, H-1 tetap pakai logika lama
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
    }
        
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

    private function generateMessageForMultipleBooks($memberData, $books, $notificationType) {
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
    
    // 🔥 Buat daftar buku
    $bookList = '';
    foreach ($books as $index => $book) {
        $no = $index + 1;
        $bookList .= "{$no}. {$book['book_title']} (Kode: {$book['item_code']}, Jatuh Tempo: " . date('d-m-Y', strtotime($book['due_date'])) . ")\n";
    }
    
    // Replace variabel - untuk multiple books, {book_title} jadi list
    $message = str_replace(
        ['{member_name}', '{member_id}', '{book_title}', '{item_code}', '{due_date}'],
        [
            $memberData['member_name'],
            $memberData['member_id'],
            "\n" . $bookList, // 🔥 Ganti dengan list
            count($books) . ' buku', // Info jumlah buku
            date('d-m-Y', strtotime($books[0]['due_date'])) // Due date pertama
        ],
        $template
    );
    
    return $message;
}

/**
 * 🔥 NEW: Kirim notifikasi untuk multiple books dan simpan log per buku
 * 
 * @param array $memberData Member data
 * @param array $books Array of books
 * @param string $message Message to send
 * @param string $notificationType Notification type
 * @return array Result
 */
private function sendNotificationForMultipleBooks($memberData, $books, $message, $notificationType) {
    // Kirim WhatsApp HANYA 1x
    $sendResult = $this->wablas->sendMessage($memberData['member_phone'], $message);
    
    // 🔥 Simpan log untuk SETIAP buku (tracking purpose)
    foreach ($books as $book) {
        $logData = [
            'loan_id' => $this->db->real_escape_string($book['loan_id']),
            'member_id' => $this->db->real_escape_string($memberData['member_id']),
            'member_name' => $this->db->real_escape_string($memberData['member_name']),
            'member_phone' => $this->db->real_escape_string($memberData['member_phone']),
            'book_title' => $this->db->real_escape_string($book['book_title']),
            'item_code' => $this->db->real_escape_string($book['item_code']),
            'due_date' => $book['due_date'],
            'notification_type' => $this->db->real_escape_string($notificationType),
            'message_sent' => $this->db->real_escape_string($message),
            'status' => $sendResult['success'] ? 'success' : 'failed',
            'wablas_response' => $this->db->real_escape_string(json_encode($sendResult)),
            'wablas_message_id' => isset($sendResult['data']['id']) ? $this->db->real_escape_string($sendResult['data']['id']) : '',
            'error_message' => !$sendResult['success'] ? $this->db->real_escape_string($sendResult['message']) : '',
            'sent_at' => date('Y-m-d H:i:s')
        ];
        
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
    }
    
    return [
        'success' => $sendResult['success'],
        'error' => !$sendResult['success'] ? $sendResult['message'] : ''
    ];
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