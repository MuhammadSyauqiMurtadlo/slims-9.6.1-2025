<?php
/**
 * Kelola Template Pesan & Jadwal WhatsApp
 * File: /admin/modules/wa_notification/templates.php
 * 
 * DIPERBAIKI:
 * - Keamanan SQL Injection
 * - Validasi data lebih ketat
 * - Kode lebih rapi dan efisien
 * - Fixed encoding issue
 */

// key to authenticate
define('INDEX_AUTH', '1');

// main system configuration
require '../../../sysconfig.inc.php';

// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('system', 'r');
$can_write = utility::havePrivilege('system', 'w');

if (!($can_read AND $can_write)) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
}

/* RECORD OPERATION */
if (isset($_POST['saveData'])) {
    // Validasi dan sanitasi input
    $templateId = (int)$_POST['updateRecordID'];
    $templateMessage = trim($_POST['templateMessage']);
    $isActive = isset($_POST['isActive']) ? (int)$_POST['isActive'] : 0;
    
    // Validasi ID harus lebih dari 0
    if ($templateId <= 0) {
        utility::jsToastr(__('Error'), __('Invalid template ID'), 'error');
        exit();
    }
    
    // Validasi pesan tidak boleh kosong
    if (empty($templateMessage)) {
        utility::jsToastr(__('Template Message'), __('Template message can\'t be empty'), 'error');
        exit();
    }
    
    // Validasi status harus 0 atau 1
    if (!in_array($isActive, [0, 1])) {
        utility::jsToastr(__('Status'), __('Invalid status value'), 'error');
        exit();
    }
    
    // Cek apakah template dengan ID ini ada di database
    $check_query = $dbs->query("SELECT template_id FROM wa_templates WHERE template_id = ".$templateId);
    if ($check_query->num_rows == 0) {
        utility::jsToastr(__('Error'), __('Template not found'), 'error');
        exit();
    }
    
    // Siapkan data untuk update
    $data = array(
        'template_message' => $dbs->escape_string($templateMessage),
        'is_active' => $isActive,
        'updated_at' => date('Y-m-d H:i:s')
    );
    
    // Create sql operation object
    $sql_op = new simbio_dbop($dbs);
    
    // Update data ke database
    $update = $sql_op->update('wa_templates', $data, 'template_id='.$templateId);
    
    if ($update) {
        // Tulis log aktivitas
        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'system', 
            $_SESSION['realname'].' update template ('.$templateId.')', 'WA Template', 'Update');
        
        utility::jsToastr(__('Template Data'), __('Template & Schedule Successfully Updated'), 'success');
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'\');</script>';
    } else {
        utility::jsToastr(__('Template Data'), __('Template FAILED to Update. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error, 'error');
    }
    exit();
}
/* RECORD OPERATION END */

/* MAIN CONTENT */
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {
    /* RECORD FORM - EDIT TEMPLATE */
    
    // Ambil ID item yang akan diedit
    $itemID = (int)(isset($_POST['itemID']) ? $_POST['itemID'] : 0);
    
    // Validasi ID
    if ($itemID <= 0) {
        echo '<div class="errorBox">'.__('Invalid template ID').'</div>';
        exit();
    }
    
    // Query data template dari database
    $rec_q = $dbs->query('SELECT * FROM wa_templates WHERE template_id='.$itemID);
    
    // Cek apakah data ditemukan
    if ($rec_q->num_rows == 0) {
        echo '<div class="errorBox">'.__('Template not found').'</div>';
        exit();
    }
    
    $rec_d = $rec_q->fetch_assoc();

    // Buat form object
    $form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
    $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="btn btn-success"';

    // Form table attributes
    $form->table_attr = 'id="dataList" class="s-table table"';
    $form->table_header_attr = 'class="alterCell font-weight-bold"';
    $form->table_content_attr = 'class="alterCell2"';

    // Set edit mode
    $form->edit_mode = true;
    $form->record_id = $itemID;
    $form->record_title = $rec_d['notification_type'];
    $form->delete_button = false;

    /* FORM ELEMENTS */
    
    // 1. Notification Type (readonly)
    $form->addAnything(__('Notification Type'), '<strong>'.$rec_d['notification_type'].'</strong>');
    
    // 2. Send Time (readonly)
    $sendTimeText = '<strong>'.date('H:i', strtotime($rec_d['send_time'])).' WIB</strong>';
    $form->addAnything(__('Send Time'), $sendTimeText);
    
    // 3. Days Before/After (readonly)
    $days = (int)$rec_d['days_before'];
    if ($days < 0) {
        $daysText = '<span style="color: orange;">'.abs($days).' '.__('days before due date').'</span>';
    } else if ($days == 0) {
        $daysText = '<span style="color: blue; font-weight: bold;">'.__('Due date').'</span>';
    } else {
        $daysText = '<span style="color: red;">'.abs($days).' '.__('days after due date').'</span>';
    }
    $form->addAnything(__('Days Before/After'), $daysText);
    
    // 4. Template Message (editable)
    $form->addTextField('textarea', 'templateMessage', __('Template Message').'*', 
        $rec_d['template_message']??'', 
        'rows="10" class="form-control" style="font-family: monospace;"');
    
    // 5. Status Active/Inactive (editable dengan radio button)
    $currentStatus = isset($rec_d['is_active']) ? (int)$rec_d['is_active'] : 0;
    $radioActive = $currentStatus == 1 ? 'checked' : '';
    $radioInactive = $currentStatus == 0 ? 'checked' : '';

    $statusRadio = '
    <div class="form-check">
        <label class="form-check-label">
            <input type="radio" class="form-check-input" name="isActive" value="1" '.$radioActive.'>
            <span style="color: green; font-weight: bold;">&#x2714; '.__('Active').'</span>
        </label>
    </div>
    <div class="form-check">
        <label class="form-check-label">
            <input type="radio" class="form-check-input" name="isActive" value="0" '.$radioInactive.'>
            <span style="color: red; font-weight: bold;">&#x2716; '.__('Inactive').'</span>
        </label>
    </div>';

    $form->addAnything(__('Status').'*', $statusRadio);
    
    // Header halaman edit
    echo '<div class="menuBox">
            <div class="menuBoxInner whatsappIcon">
                <div class="per_title">
                    <h2>'.__('Edit Template & Schedule').'</h2>
                </div>
            </div>
          </div>';
    
    // Info box
    echo '<div class="infoBox">
            '.__('You are going to edit template').' : <b>'.$rec_d['notification_type'].'</b><br/>
            '.__('Last Update').' '.$rec_d['updated_at'].'
          </div>';
    
    // Panduan variabel yang bisa digunakan
    echo '<div class="infoBox note" style="background-color: #050505; border: 1px solid #b3d4fc; padding: 12px; border-radius: 5px; margin-bottom: 15px;">
            <strong>&#x1F4D8; '.__('Available Variables').':</strong>
            <ul style="margin: 10px 0;">
                <li><code>{member_name}</code> &rarr; '.__('Member name').'</li>
                <li><code>{member_id}</code> &rarr; '.__('Member ID').'</li>
                <li><code>{book_title}</code> &rarr; '.__('Book title').'</li>
                <li><code>{item_code}</code> &rarr; '.__('Item code').'</li>
                <li><code>{due_date}</code> &rarr; '.__('Due date').'</li>
            </ul>
            <p><em>'.__('Example').':</em><br>
            Halo {member_name}, buku {book_title} akan jatuh tempo pada {due_date}<br>
            &rarr; '.__('becomes').' &rarr; <strong>Halo John Doe, buku Introduction to Database akan jatuh tempo pada 15-01-2026</strong>
            </p>
          </div>';
    
    // Tampilkan form
    echo $form->printOut();
    
} else {
    /* TEMPLATE LIST - DAFTAR TEMPLATE */
    
    // Cek status cron job
    $cronQuery = $dbs->query("SELECT setting_value FROM wa_settings WHERE setting_key = 'cron_last_run'");
    $cronRow = $cronQuery->fetch_assoc();
    $lastRun = $cronRow['setting_value'] ?? '';
    
    // Header halaman
    echo '<div class="menuBox">
            <div class="menuBoxInner whatsappIcon">
                <div class="per_title">
                    <h2>'.__('WhatsApp Templates & Schedules').'</h2>
                </div>
            </div>
          </div>';
    
    // Status cron job
    if (empty($lastRun)) {
        echo '<div class="infoBox" style="background-color: #fff3cd; border: 1px solid #ffc107; color: #856404;">
                <strong>&#x23F0; '.__('Cron Job Status').':</strong><br>
                <span style="color: orange;">&#x26A0; '.__('Cron job has not been run yet').'</span>
              </div>';
    } else {
        $diff = time() - strtotime($lastRun);
        $hours = floor($diff / 3600);
        
        $alertStyle = $hours > 24 
            ? 'background-color: #ffebee; border: 1px solid #e2b8b8; color: #662b2b;' 
            : 'background-color: #e6f3ff; border: 1px solid #b3d9ff; color: #003d7a;';
        
        echo '<div class="infoBox" style="'.$alertStyle.'">
                <strong>&#x23F0; '.__('Cron Job Status').':</strong><br>
                '.__('Last run').': <strong>'.date('d-m-Y H:i:s', strtotime($lastRun)).'</strong>';
        
        if ($hours > 24) {
            echo '<br><span style="color: red;">&#x26A0; '.__('Cron job has not been running for more than 24 hours!').'</span>';
        }
        
        echo '</div>';
    }
    
    // Info box
    echo '<div class="infoBox">
            '.__('Manage WhatsApp notification message templates and schedules. Click').' <strong>'.__('Edit').'</strong> '.__('button to modify template content and send time').'
          </div>';
    
    // Buat tabel data
    $datagrid = new simbio_datagrid();
    $datagrid->setSQLColumn('template_id',
        'notification_type AS \''.__('Notification Type').'\'',
        'days_before AS \''.__('Days Before/After').'\'',
        'send_time AS \''.__('Send Time').'\'',
        'template_message AS \''.__('Template Message').'\'',
        'COALESCE(is_active, 0) AS \''.__('Status').'\'',
        'updated_at AS \''.__('Last Update').'\'');
    
    // Format kolom agar lebih bagus
    $datagrid->modifyColumnContent(2, 'callback{formatDays}');
    $datagrid->modifyColumnContent(3, 'callback{formatTime}');
    $datagrid->modifyColumnContent(4, 'callback{truncateMessage}');
    $datagrid->modifyColumnContent(5, 'callback{changeActive}');
    
    // Urutkan berdasarkan notification type
    $datagrid->setSQLorder('FIELD(notification_type, \'H-3\', \'H-2\', \'H-1\', \'H+0\')');

    // Set atribut tabel
    $datagrid->table_attr = 'id="dataList" class="s-table table"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';

    // Tampilkan tabel
    $datagrid_result = $datagrid->createDataGrid($dbs, 'wa_templates', 20, ($can_read AND $can_write));
    echo $datagrid_result;

    // CSS untuk menyembunyikan checkbox dan tombol delete
    echo '<style>
        /* Sembunyikan kolom checkbox */
        #dataList th:first-child,
        #dataList td:first-child {
            display: none;
        }
        /* Sembunyikan tombol Check All dan Uncheck All */
        .btn-group.check-all,
        input[value="Check All"],
        input[value="Uncheck All"],
        button[value="Check All"],
        button[value="Uncheck All"],
        input[name="itemAction"],
        input[value="Delete Selected Data"],
        button[value="Delete Selected Data"] {
            display: none !important;
        }
    </style>';
    
    // JavaScript untuk hapus tombol yang tidak perlu
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        const buttons = document.querySelectorAll("input[type=\'button\'], button");
        buttons.forEach(function(btn) {
            const value = btn.value || btn.textContent || "";
            if (value.includes("Check All") || value.includes("Uncheck All") || value.includes("Delete Selected")) {
                btn.remove();
            }
        });
    });
    </script>';
    
    // Info perintah cron job
    echo '<div class="infoBox note" style="background-color: #070707; border: 1px solid #b3d4fc; padding: 12px; border-radius: 5px; margin-top: 20px;">
            <strong>&#x1F4D8; '.__('Cron Job Command').':</strong>
            <pre style="background: #2d2d2d; color: #f8f8f2; padding: 10px; border-radius: 5px; margin-top: 10px; overflow-x: auto;">0 7 * * * /usr/bin/php '.dirname(__FILE__).'/cron.php >> '.dirname(__FILE__).'/cron.log 2>&1</pre>
            <p><small>'.__('The command above will run the cron every day at 07:00 WIB').'</small></p>
          </div>';
}
/* MAIN CONTENT END */

/**
 * Fungsi untuk menampilkan status Active/Inactive
 */
function changeActive($obj_db, $array_data, $col) {
    $status = isset($array_data[$col]) ? (int)$array_data[$col] : 0;
    
    if ($status == 1) {
        return '<span style="color: green; font-weight: bold;">&#x2714; '.__('Active').'</span>';
    } else {
        return '<span style="color: red; font-weight: bold;">&#x2716; '.__('Inactive').'</span>';
    }
}

/**
 * Fungsi untuk format tampilan hari
 */
function formatDays($obj_db, $array_data, $col) {
    $days = (int)$array_data[$col];
    
    if ($days < 0) {
        return '<span style="color: orange;">'.abs($days).' '.__('days before').'</span>';
    } else if ($days == 0) {
        return '<span style="color: blue; font-weight: bold;">'.__('Due date').'</span>';
    } else {
        return '<span style="color: red;">'.abs($days).' '.__('days after').'</span>';
    }
}

/**
 * Fungsi untuk format waktu
 */
function formatTime($obj_db, $array_data, $col) {
    return '<strong>'.date('H:i', strtotime($array_data[$col])).' WIB</strong>';
}

/**
 * Fungsi untuk memotong pesan yang terlalu panjang
 */
function truncateMessage($obj_db, $array_data, $col) {
    $message = $array_data[$col];
    $maxLength = 80;
    
    if (strlen($message) > $maxLength) {
        return '<span style="font-size: 11px;">'.htmlspecialchars(substr($message, 0, $maxLength)).'...</span>';
    }
    
    return '<span style="font-size: 11px;">'.htmlspecialchars($message).'</span>';
}
?>