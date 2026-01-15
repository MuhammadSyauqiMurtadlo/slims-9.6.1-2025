<?php
/**
 * Dashboard WhatsApp Notification
 * File: /admin/modules/wa_notification/index.php
 */
// key to authenticate
define('INDEX_AUTH', '1');

require '../../../sysconfig.inc.php';
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';

// Load Wablas API
require 'lib/WablasAPI.class.php';

// Privilege check
if ($_SESSION['uid'] != 1) {
    die('<div class="errorBox">You dont have enough privileges to access this area!</div>');
}

$page_title = 'WhatsApp Notification Dashboard';
// require SB.'admin/default/header.inc.php';

// Get Wablas status
$wablas = new WablasAPI($dbs);
$status = $wablas->getFullStatus();

?>

<div class="menuBox">
    <div class="menuBoxInner whatsappIcon">
        <div class="per_title">
            <h2><?php echo __('WhatsApp Notification Dashboard'); ?></h2>
        </div>
    </div>
</div>

<!-- <div id="mainContent"> -->
    <?php if (!$status['success']): ?>
        <!-- Error State -->
        <div class="errorBox">
            <h3>⚠️ Gagal Terhubung ke Wablas</h3>
            <p><?php echo htmlspecialchars($status['message']); ?></p>
            <p>Pastikan token API sudah dikonfigurasi dengan benar di menu <a href="settings.php">Pengaturan</a>.</p>
        </div>
    <?php else: ?>
        <!-- Status Gateway -->
        <div class="infoBox" style="<?php echo $status['is_expired'] ? 'background: #070707;' : 'background: #070707;'; ?>">
            <h3>Status WhatsApp Gateway</h3>
            <table class="dataList" style="margin-top: 15px;">
                <tr>
                    <td width="200"><strong>Status Device</strong></td>
                    <td>
                        <?php if ($status['status'] == 'connected'): ?>
                            <span style="color: green; font-weight: bold; font-size: 16px;">✓ Connected</span>
                        <?php else: ?>
                            <span style="color: red; font-weight: bold; font-size: 16px;">✗ Disconnected</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (!empty($status['name'])): ?>
                <tr>
                    <td><strong>Device Name</strong></td>
                    <td><?php echo htmlspecialchars($status['name']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($status['sender'])): ?>
                <tr>
                    <td><strong>Nomor WhatsApp</strong></td>
                    <td><?php echo htmlspecialchars($status['sender']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($status['package'])): ?>
                <tr>
                    <td><strong>Package</strong></td>
                    <td><?php echo htmlspecialchars($status['package']); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <!-- Kuota Pesan -->
        <div class="infoBox">
            <h3>Sisa Kuota Pesan</h3>
            <div style="text-align: center; padding: 30px;">
                <div style="font-size: 48px; font-weight: bold; color: <?php echo $status['quota'] > 100 ? '#4caf50' : ($status['quota'] > 50 ? '#ff9800' : '#f44336'); ?>;">
                    <?php echo number_format($status['quota']); ?>
                </div>
                <div style="font-size: 18px; color: #666; margin-top: 10px;">
                    pesan tersisa
                </div>
            </div>
            <?php if ($status['quota'] <= 100): ?>
            <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-top: 15px;">
                <strong>⚠️ Peringatan:</strong> Kuota pesan tersisa sedikit (<?php echo $status['quota']; ?> pesan). 
                Segera lakukan top-up di <a href="https://console.wablas.com" target="_blank">Dashboard Wablas</a> untuk menghindari gangguan layanan.
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Expired Date -->
        <div class="infoBox" style="<?php echo $status['is_expired'] ? 'background: #ffebee;' : ''; ?>">
            <h3>Masa Langganan</h3>
            <table class="dataList" style="margin-top: 15px;">
                <?php if ($status['expired_date']): ?>
                <tr>
                    <td width="200"><strong>Tanggal Expired</strong></td>
                    <td style="font-size: 16px; font-weight: bold;">
                        <?php echo date('d F Y', strtotime($status['expired_date'])); ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Status Langganan</strong></td>
                    <td>
                        <?php if ($status['is_expired']): ?>
                            <span style="color: red; font-weight: bold; font-size: 16px;">✗ EXPIRED</span>
                        <?php else: ?>
                            <span style="color: green; font-weight: bold; font-size: 16px;">✓ Aktif</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (!$status['is_expired']): ?>
                <tr>
                    <td><strong>Sisa Waktu</strong></td>
                    <td>
                        <span style="font-weight: bold; font-size: 18px; color: <?php echo $status['days_remaining'] > 30 ? '#4caf50' : ($status['days_remaining'] > 7 ? '#ff9800' : '#f44336'); ?>;">
                            <?php echo $status['days_remaining']; ?> hari
                        </span>
                    </td>
                </tr>
                <?php endif; ?>
                <?php else: ?>
                <tr>
                    <td colspan="2">
                        <p style="color: #666;">Informasi masa langganan tidak tersedia dari API.</p>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            
            <?php if ($status['is_expired']): ?>
            <div style="background: #f44336; color: white; padding: 15px; border-radius: 5px; margin-top: 15px;">
                <strong>⚠️ PERHATIAN!</strong><br>
                Langganan Wablas Anda sudah EXPIRED sejak <?php echo date('d F Y', strtotime($status['expired_date'])); ?>.<br>
                Sistem tidak dapat mengirim notifikasi WhatsApp.<br>
                Silakan perpanjang langganan di <a href="https://console.wablas.com" target="_blank" style="color: white; text-decoration: underline; font-weight: bold;">Dashboard Wablas</a>.
            </div>
            <?php elseif ($status['days_remaining'] <= 7): ?>
            <div style="background: #ff9800; color: white; padding: 15px; border-radius: 5px; margin-top: 15px;">
                <strong>⚠️ PERINGATAN!</strong><br>
                Langganan Wablas akan habis dalam <strong><?php echo $status['days_remaining']; ?> hari</strong> (<?php echo date('d F Y', strtotime($status['expired_date'])); ?>).<br>
                Segera perpanjang untuk menghindari gangguan layanan.
            </div>
            <?php elseif ($status['days_remaining'] <= 30): ?>
            <div style="background: #2196f3; color: white; padding: 15px; border-radius: 5px; margin-top: 15px;">
                <strong>ℹ️ Informasi:</strong><br>
                Langganan Wablas akan habis dalam <?php echo $status['days_remaining']; ?> hari (<?php echo date('d F Y', strtotime($status['expired_date'])); ?>).
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <!-- Quick Links -->
    <div class="infoBox">
        <h3>Menu Lainnya</h3>
        <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
            <a href="templates.php" class="btn btn-primary">
                <i class="fas fa-file-alt"></i> Template Pesan
            </a>
            <a href="schedules.php" class="btn btn-primary">
                <i class="fas fa-calendar-check"></i> Jadwal Notifikasi
            </a>
            <a href="logs.php" class="btn btn-primary">
                <i class="fas fa-history"></i> Log Pengiriman
            </a>
            <a href="settings.php" class="btn btn-primary">
                <i class="fas fa-cog"></i> Pengaturan
            </a>
        </div>
    </div>
    
    <!-- Debug Info (Hapus di production) -->
    <?php if (false): // Set true untuk debug ?>
    <div class="infoBox" style="margin-top: 20px;">
        <h3>Debug Info</h3>
        <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;">
<?php print_r($status); ?>
        </pre>
    </div>
    <?php endif; ?>
<!-- </div> -->

<?php
// require SB.'admin/default/footer.inc.php';
?>