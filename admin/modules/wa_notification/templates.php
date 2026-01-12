<?php
/**
 * Kelola Template Pesan WhatsApp
 * File: /admin/modules/wa_notification/templates.php
 */

require '../../../sysconfig.inc.php';
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';

// Privilege check
if ($_SESSION['uid'] != 1) {
    die('<div class="errorBox">You dont have enough privileges to access this area!</div>');
}

$page_title = 'Template Pesan WhatsApp';
// require SB.'admin/default/header.inc.php';

// Handle form submission
if (isset($_POST['saveData'])) {
    $templateId = (int)$_POST['template_id'];
    $templateMessage = trim($_POST['template_message']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($templateMessage)) {
        echo '<div class="errorBox">Template pesan tidak boleh kosong!</div>';
    } else {
        $templateMessage = $dbs->escape_string($templateMessage);
        
        $sql = "UPDATE wa_templates SET 
                template_message = '{$templateMessage}',
                is_active = {$isActive},
                updated_at = NOW()
                WHERE template_id = {$templateId}";
        
        if ($dbs->query($sql)) {
            echo '<div class="successBox">Template berhasil diupdate!</div>';
        } else {
            echo '<div class="errorBox">Gagal update template: ' . $dbs->error . '</div>';
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
            <h2><?php echo __('Template Pesan WhatsApp'); ?></h2>
        </div>
        <div class="infoBox">
            <p>Kelola template pesan notifikasi WhatsApp. Gunakan variabel berikut:</p>
            <ul>
                <li><strong>{member_name}</strong> - Nama anggota/mahasiswa</li>
                <li><strong>{member_id}</strong> - ID anggota</li>
                <li><strong>{book_title}</strong> - Judul buku</li>
                <li><strong>{item_code}</strong> - Kode buku/barcode</li>
                <li><strong>{due_date}</strong> - Tanggal jatuh tempo</li>
            </ul>
        </div>
    </div>
</div>

<?php if ($formMode == 'list'): ?>
    <!-- List Templates -->
    <div id="mainContent">
        <table class="dataList">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="15%">Tipe Notifikasi</th>
                    <th width="50%">Template Pesan</th>
                    <th width="10%">Status</th>
                    <th width="10%">Terakhir Update</th>
                    <th width="10%">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $query = $dbs->query("SELECT * FROM wa_templates ORDER BY 
                                     FIELD(notification_type, 'H-3', 'H-2', 'H-1', 'H+0')");
                $no = 1;
                while ($row = $query->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><strong><?php echo $row['notification_type']; ?></strong></td>
                    <td style="white-space: pre-wrap; font-size: 11px;"><?php echo htmlspecialchars($row['template_message']); ?></td>
                    <td>
                        <?php if ($row['is_active']): ?>
                            <span style="color: green;">✓ Aktif</span>
                        <?php else: ?>
                            <span style="color: red;">✗ Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('d-m-Y H:i', strtotime($row['updated_at'])); ?></td>
                    <td>
                        <a href="?itemID=<?php echo $row['template_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>
    <!-- Edit Template Form -->
    <?php
    $query = $dbs->query("SELECT * FROM wa_templates WHERE template_id = {$itemID}");
    $template = $query->fetch_assoc();
    
    if (!$template) {
        echo '<div class="errorBox">Template tidak ditemukan!</div>';
        exit;
    }
    ?>
    
    <div id="mainContent">
        <a href="templates.php" class="btn btn-default">« Kembali</a>
        
        <form method="POST" action="" style="margin-top: 20px;">
            <input type="hidden" name="template_id" value="<?php echo $template['template_id']; ?>">
            
            <div class="form-group">
                <label><strong>Tipe Notifikasi</strong></label>
                <input type="text" class="form-control" value="<?php echo $template['notification_type']; ?>" disabled>
            </div>
            
            <div class="form-group">
                <label><strong>Template Pesan</strong></label>
                <textarea name="template_message" class="form-control" rows="10" required><?php echo htmlspecialchars($template['template_message']); ?></textarea>
                <small class="text-muted">
                    Variabel: {member_name}, {member_id}, {book_title}, {item_code}, {due_date}
                </small>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" <?php echo $template['is_active'] ? 'checked' : ''; ?>>
                    Aktifkan template ini
                </label>
            </div>
            
            <!-- Preview -->
            <div class="infoBox">
                <h4>Preview Pesan</h4>
                <div id="preview" style="background: #f9f9f9; padding: 15px; border-radius: 5px; white-space: pre-wrap;">
                    <!-- Will be generated by JavaScript -->
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" name="saveData" class="btn btn-primary">Simpan Template</button>
                <a href="templates.php" class="btn btn-default">Batal</a>
            </div>
        </form>
    </div>
    
    <script>
    // Generate preview
    function updatePreview() {
        let template = document.querySelector('textarea[name="template_message"]').value;
        
        // Sample data
        let preview = template
            .replace(/{member_name}/g, 'John Doe')
            .replace(/{member_id}/g, 'M2023001')
            .replace(/{book_title}/g, 'Introduction to Database Systems')
            .replace(/{item_code}/g, 'B001234')
            .replace(/{due_date}/g, '15-01-2026');
        
        document.getElementById('preview').textContent = preview;
    }
    
    // Update preview on load
    updatePreview();
    
    // Update preview on typing
    document.querySelector('textarea[name="template_message"]').addEventListener('input', updatePreview);
    </script>

<?php endif; ?>

<?php
// require SB.'admin/default/footer.inc.php';
?>