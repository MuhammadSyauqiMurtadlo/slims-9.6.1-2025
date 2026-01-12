<?php
/**
 * Dashboard WhatsApp Notification
 * File: /admin/modules/wa_notification/index.php
 */

// key to authenticate
if (!defined('INDEX_AUTH')) {
  define('INDEX_AUTH', '1');
}

// key to get full database access
define('DB_ACCESS', 'fa');

if (!defined('SB')) {
  // main system configuration
  require '../../../sysconfig.inc.php';
  // start the session
  require SB.'admin/default/session.inc.php';
}
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');

require SB.'admin/default/session_check.inc.php';
require SIMBIO.'simbio_FILE/simbio_directory.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';
require LIB.'module.inc.php';

// Load notification service
require '../wa_notification/lib/NotificationService.class.php';

// Privilege check
if ($_SESSION['uid'] != 1) {
    die('<div class="errorBox">You dont have enough privileges to access this area!</div>');
}

$page_title = 'WhatsApp Notification Dashboard';
// require SB.'admin/default/header.inc.php';

// Get statistics
$service = new NotificationService($dbs);
$stats = $service->getDashboardStats();

?>

<div class="menuBox">
    <div class="menuBoxInner whatsappIcon">
        <div class="per_title">
            <h2><?php echo __('WhatsApp Notification Dashboard'); ?></h2>
        </div>
    </div>
</div>

<div id="mainContent">
    <!-- Status Gateway -->
    <div class="infoBox" style="<?php echo $stats['wablas_status']['is_expired'] ? 'background: #fee;' : 'background: #efe;'; ?>">
        <h3>Status WhatsApp Gateway</h3>
        <?php if ($stats['wablas_status']['is_expired']): ?>
            <p style="color: red; font-weight: bold;">⚠️ Langganan Wablas EXPIRED pada <?php echo date('d-m-Y', strtotime($stats['wablas_status']['expired_date'])); ?></p>
        <?php else: ?>
            <p style="color: green; font-weight: bold;">✓ Gateway Aktif (Sisa <?php echo $stats['wablas_status']['days_remaining']; ?> hari)</p>
            <p>Expired: <?php echo date('d-m-Y', strtotime($stats['wablas_status']['expired_date'])); ?></p>
        <?php endif; ?>
    </div>

    <!-- Kuota Pesan -->
    <div class="infoBox">
        <h3>Informasi Kuota Pesan</h3>
        <?php if ($stats['device_info']['success']): ?>
            <table class="dataList">
                <tr>
                    <td width="200"><strong>Status Device</strong></td>
                    <td>: <?php echo $stats['device_info']['data']['status'] ?? 'Unknown'; ?></td>
                </tr>
                <tr>
                    <td><strong>Sisa Kuota</strong></td>
                    <td>: <?php echo $stats['device_info']['data']['quota'] ?? 'N/A'; ?> pesan</td>
                </tr>
                <tr>
                    <td><strong>Device Name</strong></td>
                    <td>: <?php echo $stats['device_info']['data']['device_name'] ?? 'N/A'; ?></td>
                </tr>
            </table>
        <?php else: ?>
            <p style="color: red;">Gagal mengambil informasi kuota dari Wablas</p>
        <?php endif; ?>
    </div>

    <!-- Statistics -->
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h1><?php echo $stats['today_total']; ?></h1>
                    <p>Pesan Hari Ini</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h1 style="color: green;"><?php echo $stats['today_success']; ?></h1>
                    <p>Berhasil</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h1 style="color: red;"><?php echo $stats['today_failed']; ?></h1>
                    <p>Gagal</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h1><?php echo $stats['month_total']; ?></h1>
                    <p>Total Bulan Ini</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Notifications -->
    <div class="infoBox">
        <h3>Peminjaman Menunggu Notifikasi</h3>
        <p>Ada <strong><?php echo $stats['pending_notifications']; ?></strong> peminjaman yang akan jatuh tempo dalam 3 hari ke depan.</p>
    </div>

    <!-- Manual Trigger -->
    <div class="infoBox">
        <h3>Jalankan Manual</h3>
        <p>Cron job otomatis berjalan setiap hari pukul 08:00. Anda juga bisa menjalankan manual:</p>
        <button class="btn btn-primary" onclick="runManual()">Jalankan Sekarang</button>
        <div id="manualResult" style="margin-top: 10px;"></div>
    </div>
</div>

<script>
function runManual() {
    if (!confirm('Yakin ingin menjalankan pengiriman notifikasi sekarang?')) return;
    
    document.getElementById('manualResult').innerHTML = 'Processing...';
    
    fetch('processor.php?action=run_manual')
        .then(response => response.json())
        .then(data => {
            let html = '<div class="' + (data.success ? 'successBox' : 'errorBox') + '">';
            html += '<strong>Result:</strong><br>';
            html += 'Total Processed: ' + data.total_processed + '<br>';
            html += 'Success: ' + data.success_count + '<br>';
            html += 'Failed: ' + data.failed + '<br>';
            html += 'Skipped: ' + data.skipped + '<br>';
            if (data.errors && data.errors.length > 0) {
                html += '<br><strong>Errors:</strong><br>';
                data.errors.forEach(err => {
                    html += '- ' + err + '<br>';
                });
            }
            html += '</div>';
            document.getElementById('manualResult').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('manualResult').innerHTML = '<div class="errorBox">Error: ' + error + '</div>';
        });
}
</script>

<?php
// require SB.'admin/default/footer.inc.php';
?>