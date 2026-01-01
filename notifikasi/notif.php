<?php

/**
 * Notifikasi WhatsApp Otomatis - Reminder H-1 Jatuh Tempo
 * File ini dijalankan via CLI/Cron, TIDAK memerlukan session admin
 */
define('INDEX_AUTH', true);
define('DB_ACCESS', true);

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Tentukan base path SLiMS
$slimsBase = realpath(__DIR__ . '/../');

// HANYA load config database, JANGAN load session.inc.php!
require_once $slimsBase . '/sysconfig.inc.php';

// File log
$logFile = __DIR__ . '/log_api.txt';

// Buat fungsi helper untuk logging
function writeLog($message)
{
  global $logFile;
  $timestamp = date('Y-m-d H:i:s');
  file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Log start
writeLog(str_repeat('=', 70));
writeLog("START EXECUTION");
writeLog(str_repeat('=', 70));

$dbhost = 'localhost';           // Host database
$dbuser = 'root';                // Username database
$dbpass = '';                    // Password database (kosong untuk default XAMPP)
$dbname = 'slims_perpustakaan';        // Nama database SLiMS

// Koneksi database LANGSUNG (tanpa session check)
$db = @mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

if (!$db) {
  writeLog("❌ ERROR DB CONNECTION: " . mysqli_connect_error());
  die("Database connection failed. Check log_api.txt\n");
}

writeLog("✅ Database connected successfully");
mysqli_set_charset($db, 'utf8mb4');

// API Wablas Configuration
$wablas_url = "https://tegal.wablas.com/api/v2/send-message";
$token = "YzUXeFJCCTcRtfxBnK3fO7Pr6ImdAzWlCOPufuc1gwjXQN9J1J21hze";
$secret_key = "CoIaeNLW";

// Gabungkan token dan secret key dengan titik
$auth_token = $token . "." . $secret_key;

// Query untuk ambil data peminjaman yang jatuh tempo besok
// $sql = "
//     SELECT 
//         l.loan_id,
//         l.item_code,
//         l.due_date,
//         m.member_id,
//         m.member_name,
//         m.member_phone
//     FROM loan AS l
//     INNER JOIN member AS m ON m.member_id = l.member_id
//     WHERE DATE(l.due_date) = CURDATE() + INTERVAL 1 DAY
//     AND l.is_return = 0
//     AND l.is_lent = 1
//     AND m.member_phone IS NOT NULL
//     AND m.member_phone != ''
// ";
$sql = "
    SELECT
    l.loan_id,
    l.item_code,
    l.due_date,
    m.member_id,
    m.member_name,
    m.member_phone,
    b.title AS book_title
FROM loan AS l
INNER JOIN member AS m ON m.member_id = l.member_id
IINER JOIN item AS i ON i.item_code = l.item_code
INNER JOIN biblio AS b ON b.biblio_id = i.biblio_id
WHERE DATE(l.due_date) = CURDATE() + INTERVAL 1 DAY
AND l.is_return = 0
AND l.is_lent = 1
AND m.member_phone IS NOT NULL
AND m.member_phone != ''
";

writeLog("🔍 Executing query...");
$res = mysqli_query($db, $sql);

if (!$res) {
  writeLog("❌ QUERY ERROR: " . mysqli_error($db));
  mysqli_close($db);
  die("Query failed. Check log_api.txt\n");
}

$totalRows = mysqli_num_rows($res);
writeLog("📊 Total data found: $totalRows");

if ($totalRows == 0) {
  writeLog("ℹ️  No loans due tomorrow. Nothing to send.");
  writeLog(str_repeat('=', 70));
  mysqli_close($db);
  echo "No notifications to send.\n";
  exit(0);
}

// Counter
$successCount = 0;
$failedCount = 0;
$skippedCount = 0;

// Loop setiap data
while ($row = mysqli_fetch_assoc($res)) {
  $bookTitle = $row['book_title']; // Ambil judul buku dari query
  $nama = $row['member_name'];
  $phone = $row['member_phone'];
  $item = $row['item_code'];
  $due = $row['due_date'];
  $loanId = $row['loan_id'];

  writeLog("");
  writeLog("--- Processing Loan ID: $loanId ---");
  writeLog("Member: $nama");
  writeLog("Raw Phone: $phone");
  writeLog("Item Code: $item");

  // Validasi nomor tidak kosong
  if (empty(trim($phone))) {
    writeLog("⚠️  SKIPPED: Empty phone number");
    $skippedCount++;
    continue;
  }

  // Normalisasi nomor telepon
  $originalPhone = $phone;
  $phone = preg_replace('/[^0-9]/', '', $phone); // Hapus semua kecuali angka

  // Hapus spasi, dash, dan karakter lain
  $phone = str_replace([' ', '-', '+', '(', ')'], '', $phone);

  // Konversi ke format 62xxx
  if (substr($phone, 0, 1) === '0') {
    $phone = '62' . substr($phone, 1);
  } elseif (substr($phone, 0, 2) !== '62') {
    $phone = '62' . $phone;
  }

  // Validasi panjang nomor (minimal 10 digit setelah 62)
  if (strlen($phone) < 10) {
    writeLog("⚠️  SKIPPED: Invalid phone format (too short): $phone");
    $skippedCount++;
    continue;
  }

  writeLog("📱 Normalized Phone: $phone");

  // Format pesan WhatsApp
  $message  = "*PERPUSTAKAAN UNWAHA*\n\n";
  $message .= "Halo *$nama*,\n";
  $message .= "Ini adalah pengingat bahwa buku yang Anda pinjam akan *jatuh tempo besok*.\n\n";
  $message .= "*Judul Buku*: $bookTitile\n";
  $message .= "*Kode Buku*  : $item\n";
  $message .= "*Jatuh Tempo*: $due\n\n";
  $message .= "Mohon dikembalikan tepat waktu ya.\n\n";
  $message .= "Terima kasih.\n";
  $message .= "— *Pustakawan Unwaha*";
  // Send via Wablas API - FORMAT ARRAY
  $payload = json_encode([
    'data' => [
      [
        'phone' => $phone,
        'message' => $message
      ]
    ]
  ]);

  writeLog("📤 Sending to WhatsApp API...");

  // cURL Request
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $wablas_url,
    CURLOPT_HTTPHEADER => [
      "Authorization: $auth_token",
      "Content-Type: application/json"
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,           // Naikkan timeout
    CURLOPT_CONNECTTIMEOUT => 30,    // Naikkan connect timeout
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false, // Tambah ini
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    CURLOPT_FOLLOWLOCATION => true,  // Tambah ini
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1 // Tambah ini
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  // Log response
  writeLog("HTTP Code: $httpCode");

  if ($curlError) {
    writeLog("❌ cURL Error: $curlError");
    $failedCount++;
    continue;
  }

  writeLog("Response: " . ($response ?: 'Empty response'));

  // Parse response
  $responseData = json_decode($response, true);

  if ($httpCode == 200 && isset($responseData['status']) && $responseData['status'] == true) {
    writeLog("✅ SUCCESS: Message sent to $phone");
    $successCount++;
  } else {
    writeLog("❌ FAILED: " . ($responseData['message'] ?? 'Unknown error'));
    $failedCount++;
  }

  // Delay untuk menghindari rate limit (optional)
  usleep(500000); // 0.5 detik delay
}

// Summary
writeLog("");
writeLog(str_repeat('=', 70));
writeLog("EXECUTION SUMMARY");
writeLog("Total Records: $totalRows");
writeLog("✅ Success: $successCount");
writeLog("❌ Failed: $failedCount");
writeLog("⚠️  Skipped: $skippedCount");
writeLog("END EXECUTION");
writeLog(str_repeat('=', 70));

mysqli_close($db);

// Output ke console
echo "\n";
echo "==============================================\n";
echo "WhatsApp Notification Summary\n";
echo "==============================================\n";
echo "Total: $totalRows\n";
echo "Success: $successCount\n";
echo "Failed: $failedCount\n";
echo "Skipped: $skippedCount\n";
echo "==============================================\n";
echo "Check log_api.txt for detailed logs\n";
echo "\n";

exit(0);
