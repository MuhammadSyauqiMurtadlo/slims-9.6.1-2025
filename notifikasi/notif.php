<?php

require_once __DIR__ . '/../sysconfig.inc.php'; // sesuaikan path jika berbeda
require_once SB . 'admin/default/session.inc.php';
require_once SIMBIO . 'simbio2/simbio.inc.php';

// Koneksi database SLiMS
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
  die("Koneksi gagal: " . mysqli_connect_error());
}

// API Wablas
$wablas_url = "https://tegal.wablas.com/api/v2/send-message";
$token = "YzUXeFJCCTcRtfxBnK3fO7Pr6ImdAzWlCOPufuc1gwjXQN9J1J21hze";
$secret_key = "CoIaeNLW";

// Ambil data yang due_date = besok
$sql = "
    SELECT l.*, m.member_name, m.member_phone
    FROM loan AS l
    LEFT JOIN member AS m ON m.member_id = l.member_id
    WHERE DATE(l.due_date) = CURDATE() + INTERVAL 1 DAY
    AND l.is_return = 0
    AND l.is_lent = 1
";

$res = mysqli_query($db, $sql);

while ($row = mysqli_fetch_assoc($res)) {
  $nama = $row['member_name'];
  $phone = $row['member_phone'];
  $item = $row['item_code'];
  $due = $row['due_date'];

  // Format pesan
  $message = "Halo $nama, 👋\n"
    . "Ini pengingat bahwa buku dengan kode *$item* akan jatuh tempo besok.\n"
    . "Tanggal jatuh tempo: *$due*\n\n"
    . "Mohon dikembalikan tepat waktu ya 😊\n—Perpustakaan Unwaha";

  // Payload API
  $payload = [
    "phone" => $phone,
    "message" => $message,
  ];

  // Kirim ke Wablas
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $wablas_url);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: $token",
    "secret-key: $secret_key",
    "Content-Type: application/json"
  ]);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($ch);
  curl_close($ch);

  echo "Notifikasi ke $phone → $response \n\n";
}

echo "Selesai.";
