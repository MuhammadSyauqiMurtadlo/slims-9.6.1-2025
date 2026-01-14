<?php
/**
 * Kelola Template Pesan WhatsApp
 * File: /admin/modules/wa_notification/templates.php
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
    $templateId = (int)$_POST['updateRecordID'];
    $templateMessage = trim($_POST['templateMessage']);
    $isActive = isset($_POST['isActive']) ? 1 : 0;
    
    // check form validity
    if (empty($templateMessage)) {
        utility::jsToastr(__('Template Message'), __('Template message can\'t be empty'), 'error');
        exit();
    }
    
    $data['template_message'] = $dbs->escape_string($templateMessage);
    $data['is_active'] = $isActive;
    $data['updated_at'] = date('Y-m-d H:i:s');
    
    // create sql op object
    $sql_op = new simbio_dbop($dbs);
    
    /* UPDATE RECORD MODE */
    // update the data
    $update = $sql_op->update('wa_templates', $data, 'template_id='.$templateId);
    
    if ($update) {
        // write log
        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'system', $_SESSION['realname'].' update template ('.$templateId.')', 'WA Template', 'Update');
        utility::jsToastr(__('Template Data'), __('Template Successfully Updated'), 'success');
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'\');</script>';
    } else {
        utility::jsToastr(__('Template Data'), __('Template FAILED to Update. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error, 'error');
    }
    exit();
}
/* RECORD OPERATION END */

/* main content */
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {
    /* RECORD FORM */
    // try query
    $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
    $rec_q = $dbs->query('SELECT * FROM wa_templates WHERE template_id='.$itemID);
    $rec_d = $rec_q->fetch_assoc();

    // create new instance
    $form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
    $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="btn btn-success"';

    // form table attributes
    $form->table_attr = 'id="dataList" class="s-table table"';
    $form->table_header_attr = 'class="alterCell font-weight-bold"';
    $form->table_content_attr = 'class="alterCell2"';

    // edit mode flag set
    if ($rec_q->num_rows > 0) {
        $form->edit_mode = true;
        // form record id
        $form->record_id = $itemID;
        // form record title
        $form->record_title = $rec_d['notification_type'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="btn btn-success"';
    }

    /* Form Element(s) */
    // notification type (readonly)
    $form->addAnything(__('Notification Type'), '<strong>'.$rec_d['notification_type'].'</strong>');
    
    // description
    $descriptions = [
        'H-3' => '3 hari sebelum jatuh tempo',
        'H-2' => '2 hari sebelum jatuh tempo',
        'H-1' => '1 hari sebelum jatuh tempo',
        'H+0' => 'Hari jatuh tempo'
    ];
    $desc = isset($descriptions[$rec_d['notification_type']]) ? $descriptions[$rec_d['notification_type']] : '';
    $form->addAnything(__('Description'), '<em style="color: #666;">'.$desc.'</em>');
    
    // template message
    $form->addTextField('textarea', 'templateMessage', __('Template Message').'*', $rec_d['template_message']??'', 'rows="10" class="form-control" style="font-family: monospace;"');
    
    // is active
    $form->addCheckBox('isActive', __('Status'), array(array('1', __('Active'))), $rec_d['is_active']?array('1'):array());
    
    // edit mode message
    if ($form->edit_mode) {
        echo '<div class="menuBox">
                <div class="menuBoxInner whatsappIcon">
                    <div class="per_title">
                        <h2>'.__('Edit Template Message').'</h2>
                    </div>
                </div>
              </div>';
        
        echo '<div class="infoBox">
                '.__('You are going to edit template').' : <b>'.$rec_d['notification_type'].'</b><br/>
                '.__('Last Update').' '.$rec_d['updated_at'].'
              </div>';
        
        // Variables info box
        echo '<div class="infoBox note" style="background-color: #f0f8ff; border: 1px solid #b3d4fc; padding: 12px; border-radius: 5px; margin-bottom: 15px;">
                <strong>📘 '.__('Available Variables').':</strong>
                <ul style="margin: 10px 0;">
                    <li><code>{member_name}</code> → '.__('Member name').'</li>
                    <li><code>{member_id}</code> → '.__('Member ID').'</li>
                    <li><code>{book_title}</code> → '.__('Book title').'</li>
                    <li><code>{item_code}</code> → '.__('Item code').'</li>
                    <li><code>{due_date}</code> → '.__('Due date').'</li>
                </ul>
                <p><em>'.__('Example').':</em><br>
                Halo {member_name}, buku {book_title} akan jatuh tempo pada {due_date}<br>
                → '.__('becomes').' → <strong>Halo John Doe, buku Introduction to Database akan jatuh tempo pada 15-01-2026</strong>
                </p>
              </div>';
        
        // Live preview
        echo '<div class="infoBox preview" style="background-color: #fff9e6; border: 1px solid #ffd966; padding: 12px; border-radius: 5px; margin-bottom: 15px;">
                <strong>👁️ '.__('Live Preview').':</strong>
                <div id="livePreview" style="background: #f9f9f9; padding: 15px; border-radius: 5px; white-space: pre-wrap; font-family: monospace; border: 1px solid #ddd; margin-top: 10px; min-height: 80px;">
                </div>
              </div>';
    }
    
    // print out the form object
    echo $form->printOut();
    
    // JavaScript for live preview
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        const textarea = document.querySelector("textarea[name=\"templateMessage\"]");
        const previewDiv = document.getElementById("livePreview");
        
        function updatePreview() {
            let template = textarea.value;
            
            // Sample data
            let preview = template
                .replace(/{member_name}/g, "John Doe")
                .replace(/{member_id}/g, "M2023001")
                .replace(/{book_title}/g, "Introduction to Database Systems")
                .replace(/{item_code}/g, "B001234")
                .replace(/{due_date}/g, "15-01-2026");
            
            previewDiv.textContent = preview;
        }
        
        // Update on load
        updatePreview();
        
        // Update on typing
        textarea.addEventListener("input", updatePreview);
    });
    </script>';
    
} else {
    /* TEMPLATE LIST */
    
    // create datagrid
    $datagrid = new simbio_datagrid();
    $datagrid->setSQLColumn('template_id',
        'notification_type AS \''.__('Notification Type').'\'',
        'template_message AS \''.__('Template Message').'\'',
        'is_active AS \''.__('Status').'\'',
        'updated_at AS \''.__('Last Update').'\'');
    
    // modify column content for status
    $datagrid->modifyColumnContent(3, 'callback{changeActive}');
    
    $datagrid->setSQLorder('FIELD(notification_type, \'H-3\', \'H-2\', \'H-1\', \'H+0\')');

    // set table and table header attributes
    $datagrid->table_attr = 'id="dataList" class="s-table table"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];

    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, 'wa_templates', 20, ($can_read AND $can_write));
    
    // header
    echo '<div class="menuBox">
            <div class="menuBoxInner whatsappIcon">
                <div class="per_title">
                    <h2>'.__('WhatsApp Notification Templates').'</h2>
                </div>
            </div>
          </div>';
    
    // info box
    echo '<div class="infoBox">
            '.__('Manage WhatsApp notification message templates. Click').' <strong>'.__('Edit').'</strong> '.__('button to modify template content').'
          </div>';
    
    echo $datagrid_result;
}
/* main content end */

/**
 * Callback function to change active status display
 */
function changeActive($obj_db, $array_data, $col) {
    if ($array_data[$col] == 1) {
        return '<span style="color: green; font-weight: bold;">✓ '.__('Active').'</span>';
    } else {
        return '<span style="color: red; font-weight: bold;">✗ '.__('Inactive').'</span>';
    }
}
?>