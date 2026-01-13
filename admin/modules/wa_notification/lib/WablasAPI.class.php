<?php
/**
 * Class untuk integrasi dengan Wablas API
 * File: /admin/modules/wa_notification/lib/WablasAPI.class.php
 */

class WablasAPI {
    private $apiUrl;
    private $token;
    private $db;

    public function __construct($dbs) {
        $this->db = $dbs;
        $this->loadSettings();
    }

    /**
     * Load settings dari database
     */
    private function loadSettings() {
        $query = $this->db->query("SELECT setting_key, setting_value FROM wa_settings 
                                   WHERE setting_key IN ('wablas_api_url', 'wablas_token')");
        
        while ($row = $query->fetch_assoc()) {
            if ($row['setting_key'] == 'wablas_api_url') {
                $this->apiUrl = rtrim($row['setting_value'], '/');
            }
            if ($row['setting_key'] == 'wablas_token') {
                $this->token = $row['setting_value'];
            }
        }
    }

    /**
     * Kirim pesan WhatsApp
     * 
     * @param string $phone Nomor WhatsApp (format: 628xxx)
     * @param string $message Isi pesan
     * @return array Response dari API
     */
    public function sendMessage($phone, $message) {
        // Validasi nomor
        $phone = $this->formatPhoneNumber($phone);
        
        if (!$phone) {
            return [
                'success' => false,
                'message' => 'Invalid phone number'
            ];
        }

        // Endpoint Wablas
        $url = $this->apiUrl . '/send-message';
        
        // Payload
        $data = [
            'phone' => $phone,
            'message' => $message
        ];

        // Kirim request
        $response = $this->sendRequest($url, $data);
        
        return $response;
    }

    /**
     * Cek status device dan kuota
     * Mengambil info dari endpoint /device/info
     * 
     * @return array Info device
     */
    public function getDeviceInfo() {
        // Endpoint Wablas untuk device info
        $url = $this->apiUrl . '/device/info?token=' . $this->token;
        
        $response = $this->sendRequest($url, [], 'GET');
        
        // Parse response
        if ($response['success'] && isset($response['data']['status']) && $response['data']['status'] === true) {
            $data = $response['data']['data'];
            
            return [
                'success' => true,
                'status' => $data['status'] ?? 'unknown',
                'quota' => $data['quota'] ?? 0,
                'expired_date' => $data['expired_date'] ?? null,
                'name' => $data['name'] ?? 'Unknown',
                'sender' => $data['sender'] ?? null,
                'package' => $data['package'] ?? null,
                'active' => $data['active'] ?? false,
                'serial' => $data['serial'] ?? null,
                'raw_data' => $data
            ];
        }
        
        return [
            'success' => false,
            'message' => isset($response['message']) ? $response['message'] : 'Failed to get device info',
            'status' => 'disconnected',
            'quota' => 0,
            'expired_date' => null
        ];
    }

    /**
     * Format nomor telepon ke format WhatsApp
     * 
     * @param string $phone Nomor telepon
     * @return string|false Nomor terformat atau false jika invalid
     */
    private function formatPhoneNumber($phone) {
        // Hapus karakter non-digit
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Jika kosong
        if (empty($phone)) {
            return false;
        }
        
        // Jika diawali 0, ganti dengan 62
        if (substr($phone, 0, 1) == '0') {
            $phone = '62' . substr($phone, 1);
        }
        
        // Jika tidak diawali 62, tambahkan 62
        if (substr($phone, 0, 2) != '62') {
            $phone = '62' . $phone;
        }
        
        // Validasi panjang (min 10 digit setelah 62)
        if (strlen($phone) < 12) {
            return false;
        }
        
        return $phone;
    }

    /**
     * Kirim HTTP request ke Wablas API
     * 
     * @param string $url Endpoint URL
     * @param array $data Payload
     * @param string $method HTTP method (POST/GET)
     * @return array Response
     */
    private function sendRequest($url, $data = [], $method = 'POST') {
        $ch = curl_init();
        
        // Set options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Set headers
        $headers = [
            'Content-Type: application/json'
        ];
        
        // Untuk POST request, tambahkan Authorization header
        if ($method == 'POST') {
            $headers[] = 'Authorization: ' . $this->token;
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Set method dan data
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        // Execute
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        // Handle response
        if ($error) {
            return [
                'success' => false,
                'message' => 'CURL Error: ' . $error,
                'http_code' => $httpCode
            ];
        }
        
        $responseData = json_decode($response, true);
        
        // Jika response tidak valid JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'Invalid JSON response',
                'raw_response' => $response,
                'http_code' => $httpCode
            ];
        }
        
        // Success
        return [
            'success' => ($httpCode == 200 || $httpCode == 201),
            'http_code' => $httpCode,
            'data' => $responseData
        ];
    }

    /**
     * Cek apakah token valid dan mendapatkan status lengkap
     * 
     * @return array Status lengkap dari Wablas
     */
    public function getFullStatus() {
        $deviceInfo = $this->getDeviceInfo();
        
        if (!$deviceInfo['success']) {
            return [
                'success' => false,
                'status' => 'disconnected',
                'quota' => 0,
                'expired_date' => null,
                'is_expired' => true,
                'days_remaining' => 0,
                'message' => $deviceInfo['message']
            ];
        }
        
        // Parse expired date dari response Wablas
        $expiredDate = $deviceInfo['expired_date']; // Format: "2026-02-01"
        $today = date('Y-m-d');
        $isExpired = false;
        $daysRemaining = 0;
        
        if ($expiredDate) {
            $isExpired = ($today > $expiredDate);
            
            // Hitung sisa hari
            $diff = strtotime($expiredDate) - strtotime($today);
            $daysRemaining = floor($diff / (60 * 60 * 24));
        }
        
        return [
            'success' => true,
            'status' => $deviceInfo['status'], // "connected" dari response
            'quota' => $deviceInfo['quota'], // 839 dari response
            'expired_date' => $expiredDate, // "2026-02-01" dari response
            'is_expired' => $isExpired,
            'days_remaining' => $daysRemaining,
            'name' => $deviceInfo['name'],
            'sender' => $deviceInfo['sender'],
            'package' => $deviceInfo['package'],
            'active' => $deviceInfo['active']
        ];
    }
}
?>