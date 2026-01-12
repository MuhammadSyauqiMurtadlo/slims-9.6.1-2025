<?php
/**
 * Pengaturan WhatsApp Notification
 * File: /admin/modules/wa_notification/settings.php
 */

require '../../../sysconfig.inc.php';
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';

// Load Wablas API
require 'lib/WablasAPI.class.php';

// Privilege check
if ($_SESSION['uid'] != 1) {
    die('<div class="errorBox">You dont have enough privileges to access this area!</div>');
}

$page_title = 'Pengaturan WhatsApp Notification';
require SB.'admin/default/header.inc.php';

// Handle form submission
if (isset($_POST['saveSettings'])) {
    $wablasToken = trim($_POST['wablas_token']);
    $expiredDate = trim($_POST['wablas_expired_date']);
    $notificationEnabled = isset($_POST['notification_enabled']) ? 1 : 0;
    
    if (empty($wablasToken)) {
        echo '<div class="errorBox">Token Wablas tidak boleh kosong!</div>';
    } elseif (empty($expiredDate)) {
        echo '<div class="errorBox">Tanggal expired tidak boleh kosong!</div>';
    } else {
        $wablasToken = $dbs->escape_string($wablasToken);
        $expiredDate = $dbs->escape_string($expiredDate);
        
        // Update settings
        $dbs->query("UPDATE wa_settings SET setting_value = '{$wablasToken}' WHERE setting_key = 'wablas_token'");
        $dbs->query("UPDATE wa_settings SET setting_value = '{$expiredDate}' WHERE setting_key = 'wablas_expired_date'");
        $dbs->query("UPDATE wa_settings SET setting_value = '{$notificationEnabled}' WHERE setting_key = 'notification_enabled'");
        
        echo '<div class="successBox">Pengaturan berhasil disimpan!</div>';
    }
}

// Handle test connection
if (isset($_POST['testConnection'])) {
    $wablas = new WablasAPI($dbs);
    $result = $wablas->getDeviceInfo();
    
    if ($result['success']) {
        echo '<div class="successBox">✓ Koneksi berhasil! Device terhubung.</div>';
    } else {
        echo '<div class="errorBox">✗ Koneksi gagal: ' . htmlspecialchars($result['message']) . '</div>';
    }
}

// Get current settings
$settings = [];
$query = $dbs->query("SELECT setting_key, setting_value FROM wa_settings");
while ($row = $query->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

?>

<div class="menuBox">
    <div class="menuBoxInner whatsappIcon">
        <div class="per_title">
            <h2><?php echo __('Pengaturan WhatsApp Notification'); ?></h2>
        </div>
        <div class="infoBox">
            <p>Konfigurasi koneksi ke Wablas API dan pengaturan sistem notifikasi.</p>
        </div>
    </div>
</div>

<div id="mainContent">
    <form method="POST" action="">
        <!-- API Settings -->
        <div class="infoBox">
            <h3>Konfigurasi Wablas API</h3>
            
            <div class="form-group">
                <label><strong>Base URL API</strong></label>
                <input type="text" name="wablas_api_url" class="form-control" value="<?php echo htmlspecialchars($settings['wablas_api_url']); ?>" disabled>
                <small class="text-muted">URL default dari Wablas (tidak dapat diubah)</small>
            </div>
            
            <div class="form-group">
                <label><strong>Token API Wablas *</strong></label>
                <input type="text" name="wablas_token" class="form-control" value="<?php echo htmlspecialchars($settings['wablas_token']); ?>" required placeholder="Masukkan token dari dashboard Wablas">
                <small class="text-muted">
                    Token API dapat diperoleh dari dashboard Wablas Anda. 
                    <a href="https://console.wablas.com" target="_blank">Login ke Wablas →</a>
                </small>
            </div>
            
            <div class="form-group">
                <label><strong>Tanggal Expired Langganan *</strong></label>
                <input type="date" name="wablas_expired_date" class="form-control" value="<?php echo $settings['wablas_expired_date']; ?>" required>
                <small class="text-muted">Sistem akan memberi peringatan sebelum langganan habis</small>
            </div>
        </div>
        
        <!-- System Settings -->
        <div class="infoBox">
            <h3>Pengaturan Sistem</h3>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="notification_enabled" value="1" <?php echo $settings['notification_enabled'] == 1 ? 'checked' : ''; ?>>
                    <strong>Aktifkan Notifikasi Otomatis</strong>
                </label>
                <br>
                <small class="text-muted">Jika dinonaktifkan, sistem tidak akan mengirim notifikasi meskipun cron job berjalan</small>
            </div>
        </div>
        
        <!-- Save Button -->
        <div class="form-group">
            <button type="submit" name="saveSettings" class="btn btn-primary">Simpan Pengaturan</button>
        </div>
    </form>
    
    <!-- Test Connection -->
    <div class="infoBox" style="margin-top: 20px;">
        <h3>Test Koneksi API</h3>
        <p>Klik tombol di bawah untuk menguji koneksi ke Wablas API:</p>
        <form method="POST" action="">
            <button type="submit" name="testConnection" class="btn btn-info">Test Koneksi</button>
        </form>
    </div>
    
    <!-- System Info -->
    <div class="infoBox" style="margin-top: 20px;">
        <h3>Informasi Sistem</h3>
        <table class="dataList">
            <tr>
                <td width="250"><strong>PHP Version</strong></td>
                <td>: <?php echo phpversion(); ?></td>
            </tr>
            <tr>
                <td><strong>cURL Extension</strong></td>
                <td>: <?php echo function_exists('curl_init') ? '<span style="color: green;">✓ Installed</span>' : '<span style="color: red;">✗ Not Installed</span>'; ?></td>
            </tr>
            <tr>
                <td><strong>JSON Extension</strong></td>
                <td>: <?php echo function_exists('json_encode') ? '<span style="color: green;">✓ Installed</span>' : '<span style="color: red;">✗ Not Installed</span>'; ?></td>
            </tr>
            <tr>
                <td><strong>Database Connection</strong></td>
                <td>: <?php echo $dbs->ping() ? '<span style="color: green;">✓ Connected</span>' : '<span style="color: red;">✗ Disconnected</span>'; ?></td>
            </tr>
            <tr>
                <td><strong>Cron Last Run</strong></td>
                <td>: <?php echo !empty($settings['cron_last_run']) ? date('d-m-Y H:i:s', strtotime($settings['cron_last_run'])) : 'Belum pernah dijalankan'; ?></td>
            </tr>
        </table>
    </div>
    
    <!-- Help -->
    <div class="infoBox" style="margin-top: 20px;">
        <h3>Bantuan</h3>
        <h4>Cara Setup Token Wablas:</h4>
        <ol>
            <li>Login ke dashboard Wablas di <a href="https://console.wablas.com" target="_blank">https://console.wablas.com</a></li>
            <li>Pilih device yang aktif</li>
            <li>Copy token API yang ditampilkan</li>
            <li>Paste token di form di atas</li>
            <li>Klik "Simpan Pengaturan"</li>
            <li>Test koneksi dengan tombol "Test Koneksi"</li>
        </ol>
        
        <h4>Cara Setup Cron Job:</h4>
        <ol>
            <li>Login ke server via SSH</li>
            <li>Jalankan perintah: <code>crontab -e</code></li>
            <li>Tambahkan baris:
                <pre style="background: #f5f5f5; padding: 10px; margin: 10px 0;">0 8 * * * /usr/bin/php <?php echo __DIR__; ?>/cron.php >> <?php echo __DIR__; ?>/cron.log 2>&1</pre>
            </li>
            <li>Save dan exit</li>
            <li>Cron akan berjalan otomatis setiap hari pukul 08:00</li>
        </ol>
    </div>
</div>

<?php
require SB.'admin/default/footer.inc.php';
?>