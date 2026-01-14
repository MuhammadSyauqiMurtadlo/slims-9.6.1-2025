<?php
/**
 * Pengaturan WhatsApp Notification
 * File: /admin/modules/wa_notification/settings.php
 */
// key to authenticate
define('INDEX_AUTH', '1');

require '../../../sysconfig.inc.php';
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';

// Privilege check
if ($_SESSION['uid'] != 1) {
    die('<div class="errorBox">You dont have enough privileges to access this area!</div>');
}

$page_title = 'Pengaturan WhatsApp Notification';
// require SB.'admin/default/header.inc.php';

// Handle form submission
if (isset($_POST['saveSettings'])) {
    $wablasApiUrl = trim($_POST['wablas_api_url']);
    $wablasToken = trim($_POST['wablas_token']);
    
    if (empty($wablasApiUrl)) {
        echo '<div class="errorBox">Base URL API tidak boleh kosong!</div>';
    } elseif (empty($wablasToken)) {
        echo '<div class="errorBox">Token API tidak boleh kosong!</div>';
    } else {
        $wablasApiUrl = $dbs->escape_string($wablasApiUrl);
        $wablasToken = $dbs->escape_string($wablasToken);
        
        // Update settings
        $dbs->query("UPDATE wa_settings SET setting_value = '{$wablasApiUrl}' WHERE setting_key = 'wablas_api_url'");
        $dbs->query("UPDATE wa_settings SET setting_value = '{$wablasToken}' WHERE setting_key = 'wablas_token'");
        
        echo '<div class="successBox">✓ Pengaturan berhasil disimpan!</div>';
    }
}

// Get current settings
$settings = [];
$query = $dbs->query("SELECT setting_key, setting_value FROM wa_settings WHERE setting_key IN ('wablas_api_url', 'wablas_token')");
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
            <p>Konfigurasi koneksi ke Wablas API. Token dapat diperoleh dari <a href="https://console.wablas.com" target="_blank">Dashboard Wablas</a>.</p>
        </div>
    </div>
</div>

<!-- <div id="mainContent"> -->
    <form method="POST" action="">
        <div class="infoBox">
            <h3>Konfigurasi Wablas API</h3>
            
            <div class="form-group" style="margin-top: 20px;">
                <label><strong>Base URL API *</strong></label>
                <input type="url" name="wablas_api_url" class="form-control" value="<?php echo htmlspecialchars($settings['wablas_api_url']); ?>" required placeholder="https://tegal.wablas.com/api">
                <small class="text-muted">
                    URL endpoint API Wablas.<br>
                    Contoh: <code>https://tegal.wablas.com/api</code> atau <code>https://console.wablas.com/api</code>
                </small>
            </div>
            
            <div class="form-group" style="margin-top: 20px;">
                <label><strong>Token API *</strong></label>
                <input type="text" name="wablas_token" class="form-control" value="<?php echo htmlspecialchars($settings['wablas_token']); ?>" required placeholder="Masukkan token dari dashboard Wablas">
                <small class="text-muted">
                    Token API dapat diperoleh dari dashboard Wablas. 
                    <a href="https://console.wablas.com" target="_blank">Login ke Wablas →</a>
                </small>
            </div>
            
            <div class="form-group" style="margin-top: 30px;">
                <button type="submit" name="saveSettings" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Pengaturan
                </button>
                <a href="index.php" class="btn btn-default">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </form>
<!-- </div> -->

<?php
// require SB.'admin/default/footer.inc.php';
?>