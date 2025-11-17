<?php

/**
 * Database Configuration untuk Notifikasi WhatsApp
 * File ini standalone, tidak tergantung SLiMS session
 */

// Database credentials (sesuaikan dengan sysconfig.inc.php)
$dbhost = 'localhost';           // Host database
$dbuser = 'root';                // Username database
$dbpass = '';                    // Password database (kosong untuk default XAMPP)
$dbname = 'slims_perpustakaan';        // Nama database SLiMS

// Wablas API Configuration
$wablas_config = [
  'url' => 'https://tegal.wablas.com/api/v2/send-message',
  'token' => 'YzUXeFJCCTcRtfxBnK3fO7Pr6ImdAzWlCOPufuc1gwjXQN9J1J21hze',
  'secret_key' => 'CoIaeNLW'
];

// Timezone
date_default_timezone_set('Asia/Jakarta');
