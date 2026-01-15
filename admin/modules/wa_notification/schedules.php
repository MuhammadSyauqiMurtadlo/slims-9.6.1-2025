<?php
/**
 * Kelola Jadwal Notifikasi WhatsApp (Read-Only)
 * File: /admin/modules/wa_notification/schedules.php
 */

// key to authenticate
define('INDEX_AUTH', '1');

// main system configuration
require '../../../sysconfig.inc.php';

// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO.'simbio_DB/datagrid/simbio_dbgrid.inc.php';

// privileges checking
$can_read = utility::havePrivilege('system', 'r');

if (!$can_read) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
}

/* main content */
// Header
echo '<div class="menuBox">
        <div class="menuBoxInner whatsappIcon">
            <div class="per_title">
                <h2>'.__('WhatsApp Notification Schedules').'</h2>
            </div>
        </div>
      </div>';

// Cron status info
$cronQuery = $dbs->query("SELECT setting_value FROM wa_settings WHERE setting_key = 'cron_last_run'");
$cronRow = $cronQuery->fetch_assoc();
$lastRun = $cronRow['setting_value']??'';

if (empty($lastRun)) {
    echo '<div class="infoBox" style="background-color: #070706; border: 1px solid #ffc107; color: #856404;">
            <strong>⏰ '.__('Cron Job Status').':</strong><br>
            <span style="color: orange;">⚠️ '.__('Cron job has not been run yet').'</span>
          </div>';
} else {
    $diff = time() - strtotime($lastRun);
    $hours = floor($diff / 3600);
    
    $alertStyle = $hours > 24 ? 'background-color: #141414; border: 1px solid #e2b8b8; color: #662b2b;' : 'background-color: #e6f3ff; border: 1px solid #b3d9ff; color: #003d7a;';
    
    echo '<div class="infoBox" style="'.$alertStyle.'">
            <strong>⏰ '.__('Cron Job Status').':</strong><br>
            '.__('Last run').': <strong>'.date('d-m-Y H:i:s', strtotime($lastRun)).'</strong>';
    
    if ($hours > 24) {
        echo '<br><span style="color: red;">⚠️ '.__('Cron job has not been running for more than 24 hours!').'</span>';
    }
    
    echo '</div>';
}

// Info box
echo '<div class="infoBox">
        '.__('View WhatsApp notification schedules and their status').'
      </div>';

// Create datagrid
$datagrid = new simbio_datagrid();
$datagrid->setSQLColumn('notification_type AS \''.__('Notification Type').'\'',
    'days_before AS \''.__('Days Before/After').'\'',
    'send_time AS \''.__('Send Time').'\'',
    'is_active AS \''.__('Status').'\'',
    'updated_at AS \''.__('Last Update').'\'');

// Modify column content for better display
$datagrid->modifyColumnContent(1, 'callback{formatDays}');
$datagrid->modifyColumnContent(2, 'callback{formatTime}');
$datagrid->modifyColumnContent(3, 'callback{changeActive}');

$datagrid->setSQLorder('days_before DESC');

// Set table and table header attributes
$datagrid->table_attr = 'id="dataList" class="s-table table"';
$datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';

// Disable edit and delete - set to false for read-only
$datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];

// Put the result into variables (read-only mode)
$datagrid_result = $datagrid->createDataGrid($dbs, 'wa_schedules', 20, false);

echo $datagrid_result;

// Cron info
echo '<div class="infoBox note" style="background-color: #030303; border: 1px solid #b3d4fc; padding: 12px; border-radius: 5px; margin-top: 20px;">
        <strong>📘 '.__('Cron Job Command').':</strong>
        <pre style="background: #030303; padding: 10px; border-radius: 5px; margin-top: 10px; overflow-x: auto;">0 8 * * * /usr/bin/php '.dirname(__FILE__).'/cron.php >> '.dirname(__FILE__).'/cron.log 2>&1</pre>
        <p><small>'.__('The command above will run the cron every day at 08:00 WIB').'</small></p>
      </div>';

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

/**
 * Callback function to format days
 */
function formatDays($obj_db, $array_data, $col) {
    $days = $array_data[$col];
    if ($days < 0) {
        return '<span style="color: orange;">'.abs($days).' '.__('days before').'</span>';
    } else if ($days == 0) {
        return '<span style="color: blue; font-weight: bold;">'.__('Due date').'</span>';
    } else {
        return '<span style="color: red;">'.abs($days).' '.__('days after').'</span>';
    }
}

/**
 * Callback function to format time
 */
function formatTime($obj_db, $array_data, $col) {
    return date('H:i', strtotime($array_data[$col])).' WIB';
}
?>