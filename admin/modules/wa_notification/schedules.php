<?php
/**
 * Kelola Jadwal Notifikasi
 * File: /admin/modules/wa_notification/schedules.php
 */

require '../../../sysconfig.inc.php';
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';

// Privilege check
if ($_SESSION['uid'] != 1) {
    die('<div class="errorBox">You dont have enough privileges to access this area!</div>');
}

$page_title = 'Jadwal Notifikasi WhatsApp';
require SB.'admin/default/header.inc.php';

// Handle form submission
if (isset($_POST['saveData'])) {
    $scheduleId = (int)$_POST['schedule_id'];
    $sendTime = trim($_POST['send_time']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($sendTime)) {
        echo '<div class="errorBox">Waktu pengiriman tidak boleh kosong!</div>';
    } else {
        $sendTime = $dbs->escape_string($sendTime);
        
        $sql = "UPDATE wa_schedules SET 
                send_time = '{$sendTime}',
                is_active = {$isActive},
                updated_at = NOW()
                WHERE schedule_id = {$scheduleId}";
        
        if ($dbs->query($sql)) {
            echo '<div class="successBox">Jadwal berhasil diupdate!</div>';
        } else {
            echo '<div class="errorBox">Gagal update jadwal: ' . $dbs->error . '</div>';
        }
    }
}

// Handle form mode
$itemID = isset($_GET['itemID']) ? (int)$_GET['itemID'] : 0;
$formMode = $itemID > 0 ? 'edit' : 'list';

?>

<div class="menuBox">
    <div class="menuBoxInner whatsappIcon">
        <div class="per_title">
            <h2><?php echo __('Jadwal Notifikasi WhatsApp'); ?></h2>
        </div>
        <div class="infoBox">
            <p>Kelola jadwal pengiriman notifikasi otomatis. Sistem akan mengirim pesan sesuai waktu yang ditentukan.</p>
            <p><strong>Catatan:</strong> Pastikan cron job sudah terpasang di server untuk menjalankan otomatis.</p>
        </div>
    </div>
</div>

<?php if ($formMode == 'list'): ?>
    <!-- List Schedules -->
    <div id="mainContent">
        <!-- Cron Status -->
        <?php
        $query = $dbs->query("SELECT setting_value FROM wa_settings WHERE setting_key = 'cron_last_run'");
        $row = $query->fetch_assoc();
        $lastRun = $row['setting_value'];
        ?>
        
        <div class="infoBox">
            <h4>Status Cron Job</h4>
            <?php if (empty($lastRun)): ?>
                <p style="color: orange;">⚠️ Cron job belum pernah dijalankan</p>
            <?php else: ?>
                <?php
                $diff = time() - strtotime($lastRun);
                $hours = floor($diff / 3600);
                ?>
                <p>Terakhir dijalankan: <strong><?php echo date('d-m-Y H:i:s', strtotime($lastRun)); ?></strong></p>
                <?php if ($hours > 24): ?>
                    <p style="color: red;">⚠️ Cron job tidak berjalan lebih dari 24 jam! Periksa konfigurasi cron.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Schedule Table -->
        <table class="dataList">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="15%">Tipe Notifikasi</th>
                    <th width="20%">Waktu Pengiriman</th>
                    <th width="30%">Deskripsi</th>
                    <th width="10%">Status</th>
                    <th width="10%">Terakhir Update</th>
                    <th width="10%">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $query = $dbs->query("SELECT * FROM wa_schedules ORDER BY days_before DESC");
                $no = 1;
                
                $descriptions = [
                    'H-3' => '3 hari sebelum jatuh tempo',
                    'H-2' => '2 hari sebelum jatuh tempo',
                    'H-1' => '1 hari sebelum jatuh tempo',
                    'H+0' => 'Hari jatuh tempo'
                ];
                
                while ($row = $query->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><strong><?php echo $row['notification_type']; ?></strong></td>
                    <td><?php echo date('H:i', strtotime($row['send_time'])); ?> WIB</td>
                    <td><?php echo $descriptions[$row['notification_type']]; ?></td>
                    <td>
                        <?php if ($row['is_active']): ?>
                            <span style="color: green;">✓ Aktif</span>
                        <?php else: ?>
                            <span style="color: red;">✗ Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('d-m-Y H:i', strtotime($row['updated_at'])); ?></td>
                    <td>
                        <a href="?itemID=<?php echo $row['schedule_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <!-- Cron Command Info -->
        <div class="infoBox" style="margin-top: 20px;">
            <h4>Perintah Cron Job</h4>
            <p>Tambahkan perintah berikut ke crontab server:</p>
            <pre style="background: #f5f5f5; padding: 10px; border-radius: 5px;">0 8 * * * /usr/bin/php <?php echo __DIR__; ?>/cron.php >> <?php echo __DIR__; ?>/cron.log 2>&1</pre>
            <p><small>Perintah di atas akan menjalankan cron setiap hari pukul 08:00 WIB</small></p>
        </div>
    </div>

<?php else: ?>
    <!-- Edit Schedule Form -->
    <?php
    $query = $dbs->query("SELECT * FROM wa_schedules WHERE schedule_id = {$itemID}");
    $schedule = $query->fetch_assoc();
    
    if (!$schedule) {
        echo '<div class="errorBox">Jadwal tidak ditemukan!</div>';
        exit;
    }
    ?>
    
    <div id="mainContent">
        <a href="schedules.php" class="btn btn-default">« Kembali</a>
        
        <form method="POST" action="" style="margin-top: 20px;">
            <input type="hidden" name="schedule_id" value="<?php echo $schedule['schedule_id']; ?>">
            
            <div class="form-group">
                <label><strong>Tipe Notifikasi</strong></label>
                <input type="text" class="form-control" value="<?php echo $schedule['notification_type']; ?>" disabled>
            </div>
            
            <div class="form-group">
                <label><strong>Hari Sebelum Jatuh Tempo</strong></label>
                <input type="text" class="form-control" value="<?php echo $schedule['days_before']; ?> hari" disabled>
            </div>
            
            <div class="form-group">
                <label><strong>Waktu Pengiriman (WIB)</strong></label>
                <input type="time" name="send_time" class="form-control" value="<?php echo date('H:i', strtotime($schedule['send_time'])); ?>" required>
                <small class="text-muted">Notifikasi akan dikirim pada waktu ini setiap harinya</small>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" <?php echo $schedule['is_active'] ? 'checked' : ''; ?>>
                    Aktifkan jadwal ini
                </label>
            </div>
            
            <div class="form-group">
                <button type="submit" name="saveData" class="btn btn-primary">Simpan Jadwal</button>
                <a href="schedules.php" class="btn btn-default">Batal</a>
            </div>
        </form>
    </div>

<?php endif; ?>

<?php
require SB.'admin/default/footer.inc.php';
?>