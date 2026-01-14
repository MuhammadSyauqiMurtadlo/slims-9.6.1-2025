<?php
/**
 * Kelola Jadwal Notifikasi
 * File: /admin/modules/wa_notification/schedules.php
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

$page_title = 'Jadwal Notifikasi WhatsApp';
$message = '';

// Handle Add New Schedule
if (isset($_POST['addSchedule'])) {
    $notificationType = trim($_POST['notification_type']);
    $daysBefore = (int)$_POST['days_before'];
    $sendTime = trim($_POST['send_time']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($notificationType) || empty($sendTime)) {
        $message = '<div class="errorBox">Semua field harus diisi!</div>';
    } else {
        $notificationType = $dbs->escape_string($notificationType);
        $sendTime = $dbs->escape_string($sendTime);
        
        // Cek apakah notification type sudah ada
        $check = $dbs->query("SELECT schedule_id FROM wa_schedules WHERE notification_type = '{$notificationType}'");
        
        if ($check->num_rows > 0) {
            $message = '<div class="errorBox">Tipe notifikasi ini sudah ada!</div>';
        } else {
            $sql = "INSERT INTO wa_schedules 
                    (notification_type, days_before, send_time, is_active, created_at) 
                    VALUES 
                    ('{$notificationType}', {$daysBefore}, '{$sendTime}', {$isActive}, NOW())";
            
            if ($dbs->query($sql)) {
                $message = '<div class="successBox">✓ Jadwal berhasil ditambahkan!</div>';
            } else {
                $message = '<div class="errorBox">Gagal menambahkan jadwal: ' . $dbs->error . '</div>';
            }
        }
    }
}

// Handle Update All Schedules
if (isset($_POST['saveSchedules'])) {
    $schedules = $_POST['schedules'];
    
    $allSuccess = true;
    
    foreach ($schedules as $scheduleId => $data) {
        $scheduleId = (int)$scheduleId;
        $sendTime = trim($data['send_time']);
        $isActive = isset($data['is_active']) ? 1 : 0;
        
        if (empty($sendTime)) {
            $message = '<div class="errorBox">Waktu pengiriman tidak boleh kosong!</div>';
            $allSuccess = false;
            break;
        }
        
        $sendTime = $dbs->escape_string($sendTime);
        
        $sql = "UPDATE wa_schedules SET 
                send_time = '{$sendTime}',
                is_active = {$isActive},
                updated_at = NOW()
                WHERE schedule_id = {$scheduleId}";
        
        if (!$dbs->query($sql)) {
            $message = '<div class="errorBox">Gagal update jadwal: ' . $dbs->error . '</div>';
            $allSuccess = false;
            break;
        }
    }
    
    if ($allSuccess) {
        $message = '<div class="successBox">✓ Semua jadwal berhasil disimpan!</div>';
    }
}

// Handle Delete Schedule
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $scheduleId = (int)$_GET['id'];
    
    if ($dbs->query("DELETE FROM wa_schedules WHERE schedule_id = {$scheduleId}")) {
        $message = '<div class="successBox">✓ Jadwal berhasil dihapus!</div>';
    } else {
        $message = '<div class="errorBox">Gagal menghapus jadwal!</div>';
    }
}

// Ambil semua jadwal
$query = $dbs->query("SELECT * FROM wa_schedules ORDER BY days_before DESC");
$schedules = [];
while ($row = $query->fetch_assoc()) {
    $schedules[] = $row;
}

// Cek status cron
$cronQuery = $dbs->query("SELECT setting_value FROM wa_settings WHERE setting_key = 'cron_last_run'");
$cronRow = $cronQuery->fetch_assoc();
$lastRun = $cronRow['setting_value'];

?>

<div class="menuBox">
    <div class="menuBoxInner whatsappIcon">
        <div class="per_title">
            <h2><?php echo $page_title; ?></h2>
        </div>
    </div>
</div>

<div class="contentDesc">
    <?php echo $message; ?>
    
    <!-- Cron Status -->
    <div class="infoBox <?php echo empty($lastRun) ? 'warning' : 'info'; ?>">
        <strong>⏰ Status Cron Job:</strong><br>
        <?php if (empty($lastRun)): ?>
            <span style="color: orange;">⚠️ Cron job belum pernah dijalankan</span>
        <?php else: ?>
            <?php
            $diff = time() - strtotime($lastRun);
            $hours = floor($diff / 3600);
            ?>
            Terakhir dijalankan: <strong><?php echo date('d-m-Y H:i:s', strtotime($lastRun)); ?></strong>
            <?php if ($hours > 24): ?>
                <br><span style="color: red;">⚠️ Cron job tidak berjalan lebih dari 24 jam!</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Form Edit Semua Jadwal -->
    <form method="POST" action="">
        <table class="bordered" style="width:100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f5f5f5;">
                    <th width="15%">Tipe Notifikasi</th>
                    <th width="10%">Hari</th>
                    <th width="15%">Waktu Kirim</th>
                    <th width="10%">Status</th>
                    <th width="20%">Terakhir Update</th>
                    <th width="10%">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($schedules)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 30px; color: #999;">
                        Belum ada jadwal notifikasi
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($schedules as $schedule): ?>
                    <tr>
                        <td style="padding: 12px;">
                            <strong><?php echo htmlspecialchars($schedule['notification_type']); ?></strong>
                        </td>
                        <td style="padding: 12px;">
                            <?php echo $schedule['days_before']; ?> hari
                        </td>
                        <td style="padding: 12px;">
                            <input 
                                type="time" 
                                name="schedules[<?php echo $schedule['schedule_id']; ?>][send_time]" 
                                value="<?php echo date('H:i', strtotime($schedule['send_time'])); ?>" 
                                class="form-control"
                                required
                            >
                        </td>
                        <td style="padding: 12px;">
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="schedules[<?php echo $schedule['schedule_id']; ?>][is_active]" 
                                    value="1" 
                                    <?php echo $schedule['is_active'] ? 'checked' : ''; ?>
                                >
                                <span style="color: <?php echo $schedule['is_active'] ? 'green' : 'red'; ?>;">
                                    <?php echo $schedule['is_active'] ? '✓ Aktif' : '✗ Nonaktif'; ?>
                                </span>
                            </label>
                        </td>
                        <td style="padding: 12px;">
                            <small><?php echo date('d-m-Y H:i', strtotime($schedule['updated_at'])); ?></small>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <a href="?action=delete&id=<?php echo $schedule['schedule_id']; ?>" 
                               class="btn-delete" 
                               onclick="return confirm('Yakin ingin menghapus jadwal ini?')">
                                🗑️ Hapus
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            
            <?php if (!empty($schedules)): ?>
            <tfoot>
                <tr>
                    <td colspan="6" style="text-align:center; padding: 15px; background: #f9f9f9;">
                        <button type="submit" name="saveSchedules" class="button primary">
                            💾 Simpan Semua Jadwal
                        </button>
                    </td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </form>
    
    <!-- Form Tambah Jadwal Baru -->
    <div class="infoBox add-new" style="margin-top: 30px;">
        <h3>➕ Tambah Jadwal Baru</h3>
        <form method="POST" action="">
            <table class="bordered" style="width:100%; margin-top: 10px;">
                <tr>
                    <td width="20%"><strong>Tipe Notifikasi *</strong></td>
                    <td>
                        <input type="text" name="notification_type" class="form-control" placeholder="Contoh: H-5, H-10, H+2" required>
                        <small style="color: #666;">Format: H-X (sebelum) atau H+X (setelah jatuh tempo)</small>
                    </td>
                </tr>
                <tr>
                    <td><strong>Hari Sebelum/Sesudah *</strong></td>
                    <td>
                        <input type="number" name="days_before" class="form-control" placeholder="-5, -3, 0, 1, 2" required>
                        <small style="color: #666;">Negatif = sebelum, 0 = hari H, Positif = setelah</small>
                    </td>
                </tr>
                <tr>
                    <td><strong>Waktu Pengiriman *</strong></td>
                    <td>
                        <input type="time" name="send_time" class="form-control" value="08:00" required>
                    </td>
                </tr>
                <tr>
                    <td><strong>Status</strong></td>
                    <td>
                        <label>
                            <input type="checkbox" name="is_active" value="1" checked>
                            Aktifkan jadwal ini
                        </label>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:center; padding: 15px;">
                        <button type="submit" name="addSchedule" class="button success">
                            ➕ Tambah Jadwal
                        </button>
                    </td>
                </tr>
            </table>
        </form>
    </div>
    
    <!-- Cron Info -->
    <div class="infoBox note">
        <strong>📘 Perintah Cron Job:</strong>
        <pre style="background: #f5f5f5; padding: 10px; border-radius: 5px; margin-top: 10px; overflow-x: auto;">0 8 * * * /usr/bin/php <?php echo __DIR__; ?>/cron.php >> <?php echo __DIR__; ?>/cron.log 2>&1</pre>
        <p><small>Perintah di atas akan menjalankan cron setiap hari pukul 08:00 WIB</small></p>
    </div>
</div>

<style>
.contentDesc table td {
    padding: 12px;
    border: 1px solid #ddd;
}

.successBox {
    background-color: #e7f7e7;
    border: 1px solid #b8e2b8;
    color: #2b662b;
    padding: 12px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.errorBox {
    background-color: #ffe7e7;
    border: 1px solid #e2b8b8;
    color: #662b2b;
    padding: 12px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.infoBox.info {
    background-color: #e6f3ff;
    border: 1px solid #b3d9ff;
    color: #003d7a;
    padding: 12px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.infoBox.warning {
    background-color: #fff3cd;
    border: 1px solid #ffc107;
    color: #856404;
    padding: 12px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.infoBox.add-new {
    background-color: #f0f8e6;
    border: 1px solid #c3e6a0;
    color: #2d5016;
    padding: 15px;
    border-radius: 5px;
}

.infoBox.note {
    background-color: #f0f8ff;
    border: 1px solid #b3d4fc;
    color: #003366;
    padding: 12px;
    border-radius: 5px;
    margin-top: 20px;
    font-size: 13px;
}

.form-control {
    box-sizing: border-box;
    padding: 6px 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    width: 100%;
}

.button.primary {
    background-color: #0078d7;
    color: #fff;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
}

.button.primary:hover {
    background-color: #005fa3;
}

.button.success {
    background-color: #28a745;
    color: #fff;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
}

.button.success:hover {
    background-color: #218838;
}

.btn-delete {
    color: #dc3545;
    text-decoration: none;
    font-size: 12px;
}

.btn-delete:hover {
    color: #a71d2a;
    text-decoration: underline;
}

table.bordered {
    border: 1px solid #ddd;
}

thead tr {
    background: #f5f5f5;
    font-weight: bold;
}
</style>

<script>
// Konfirmasi sebelum submit
document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
    if (e.submitter.name === 'saveSchedules') {
        if (!confirm('Yakin ingin menyimpan semua perubahan jadwal?')) {
            e.preventDefault();
        }
    }
});
</script>