<?php
/**
 * Class untuk integrasi dengan Wablas API
 * File: /admin/modules/wa_notification/lib/WablasAPI.class.php
 * 
 * FIXED VERSION - Proper authentication untuk berbeda endpoint
 */

class WablasAPI {
    private $apiUrl;
    private $token;
    private $secretKey;
    private $db;
    private $debug = false; // Enable debug mode

    public function __construct($dbs) {
        $this->db = $dbs;
        $this->loadSettings();
    }

    /**
     * Load settings dari database (termasuk secret key)
     */
    private function loadSettings() {
        $query = $this->db->query("SELECT setting_key, setting_value FROM wa_settings 
                                   WHERE setting_key IN ('wablas_api_url', 'wablas_token', 'wablas_secret_key')");
        
        if ($query) {
            while ($row = $query->fetch_assoc()) {
                if ($row['setting_key'] == 'wablas_api_url') {
                    $this->apiUrl = rtrim($row['setting_value'], '/');
                }
                if ($row['setting_key'] == 'wablas_token') {
                    $this->token = $row['setting_value'];
                }
                if ($row['setting_key'] == 'wablas_secret_key') {
                    $this->secretKey = $row['setting_value'];
                }
            }
        }
    }

    /**
     * Generate auth token dengan format TOKEN.SECRET_KEY
     * Untuk endpoint yang butuh autentikasi penuh (send-message)
     * 
     * @return string Auth token
     */
    private function getAuthToken() {
        if (!empty($this->secretKey)) {
            return $this->token . '.' . $this->secretKey;
        }
        return $this->token;
    }

    /**
     * Kirim pesan WhatsApp
     * Menggunakan endpoint /v2/send-message dengan Authorization header
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
                'message' => 'Invalid phone number format'
            ];
        }

        if ($this->debug) {
            echo "    [DEBUG] Sending message...\n";
            echo "    [DEBUG] API URL: {$this->apiUrl}\n";
            echo "    [DEBUG] Phone: {$phone}\n";
            echo "    [DEBUG] Message length: " . strlen($message) . " chars\n";
        }

        // Endpoint untuk send message
        $url = $this->apiUrl . '/send-message';
        
        // Payload
        $data = [
            'phone' => $phone,
            'message' => $message
        ];

        // 🔥 Kirim dengan Authorization header (TOKEN.SECRET_KEY)
        $response = $this->sendRequest($url, $data, 'POST', true); // true = use auth token
        
        // Parse response
        if (!$response['success']) {
            return [
                'success' => false,
                'message' => $response['message'] ?? 'Unknown error',
                'http_code' => $response['http_code'] ?? 0,
                'raw_response' => $response['raw_response'] ?? null
            ];
        }

        $wablasData = $response['data'];
        
        if ($this->debug) {
            echo "    [DEBUG] Wablas Response: " . json_encode($wablasData) . "\n";
        }

        // Response Wablas format:
        // Success: {"status": true, "data": {"id": "...", ...}}
        // Failed: {"status": false, "message": "error message"}
        
        if (isset($wablasData['status']) && $wablasData['status'] === true) {
            return [
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => $wablasData['data'] ?? $wablasData
            ];
        } else {
            $errorMsg = $wablasData['message'] ?? 'Unknown Wablas error';
            return [
                'success' => false,
                'message' => 'Wablas Error: ' . $errorMsg,
                'data' => $wablasData
            ];
        }
    }

    /**
     * 🔥 FIX: Cek status device dan kuota
     * Menggunakan endpoint /device/info dengan query parameter token ONLY
     * TIDAK menggunakan secret key di sini!
     * 
     * @return array Info device
     */
    public function getDeviceInfo() {
        // 🔥 FIX: Gunakan TOKEN SAJA (tanpa secret key)
        // Endpoint device/info hanya butuh token murni di query parameter
        $url = $this->apiUrl . '/device/info?token=' . $this->token;
        
        if ($this->debug) {
            echo "    [DEBUG] Checking device info...\n";
            echo "    [DEBUG] URL: {$url}\n";
        }
        
        // 🔥 Kirim tanpa Authorization header (pakai query param)
        $response = $this->sendRequest($url, [], 'GET', false); // false = no auth header
        
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
            'message' => isset($response['data']['message']) ? $response['data']['message'] : 'Failed to get device info',
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
     * 🔥 FIX: Kirim HTTP request dengan auth logic yang berbeda per endpoint
     * 
     * @param string $url Endpoint URL
     * @param array $data Payload
     * @param string $method HTTP method (POST/GET)
     * @param bool $useAuthHeader Apakah pakai Authorization header
     * @return array Response
     */
    private function sendRequest($url, $data = [], $method = 'POST', $useAuthHeader = false) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        
        // Setup headers
        $headers = ['Content-Type: application/json'];
        
        // 🔥 FIX: Tambah Authorization header HANYA jika diperlukan
        if ($useAuthHeader) {
            $authToken = $this->getAuthToken(); // TOKEN.SECRET_KEY
            $headers[] = 'Authorization: ' . $authToken;
            
            if ($this->debug) {
                echo "    [DEBUG] Auth Token: " . substr($authToken, 0, 20) . "...\n";
            }
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Set method dan data
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $jsonData = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            
            if ($this->debug) {
                echo "    [DEBUG] Request Body: {$jsonData}\n";
            }
        }
        
        // Execute
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        // Error handling
        if ($error) {
            if ($this->debug) {
                echo "    [DEBUG] CURL Error: {$error}\n";
            }
            return [
                'success' => false,
                'message' => 'CURL Error: ' . $error,
                'http_code' => $httpCode
            ];
        }
        
        if ($this->debug) {
            echo "    [DEBUG] HTTP Code: {$httpCode}\n";
            echo "    [DEBUG] Raw Response: {$response}\n";
        }
        
        // Decode JSON
        $responseData = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'Invalid JSON response: ' . json_last_error_msg(),
                'raw_response' => $response,
                'http_code' => $httpCode
            ];
        }
        
        // Check HTTP status
        $isSuccess = ($httpCode >= 200 && $httpCode < 300);
        
        return [
            'success' => $isSuccess,
            'http_code' => $httpCode,
            'data' => $responseData,
            'message' => !$isSuccess ? "HTTP Error {$httpCode}" : null
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
        $expiredDate = $deviceInfo['expired_date'];
        $today = date('Y-m-d');
        $isExpired = false;
        $daysRemaining = 0;
        
        if ($expiredDate) {
            $isExpired = ($today > $expiredDate);
            $diff = strtotime($expiredDate) - strtotime($today);
            $daysRemaining = floor($diff / (60 * 60 * 24));
        }
        
        return [
            'success' => true,
            'status' => $deviceInfo['status'],
            'quota' => $deviceInfo['quota'],
            'expired_date' => $expiredDate,
            'is_expired' => $isExpired,
            'days_remaining' => $daysRemaining,
            'name' => $deviceInfo['name'],
            'sender' => $deviceInfo['sender'],
            'package' => $deviceInfo['package'],
            'active' => $deviceInfo['active']
        ];
    }
}