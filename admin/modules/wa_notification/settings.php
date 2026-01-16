<?php
/**
 * Pengaturan WhatsApp Notification
 * File: /admin/modules/wa_notification/settings.php
 */

// key to authenticate
define('INDEX_AUTH', '1');

// main system configuration
require '../../../sysconfig.inc.php';

// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('system', 'r');
$can_write = utility::havePrivilege('system', 'w');

if (!($can_read AND $can_write)) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
}

$page_title = 'WhatsApp Notification Settings';
$message = '';

// Handle form submission
if (isset($_POST['saveSettings'])) {
    $wablasApiUrl = trim($_POST['wablas_api_url']);
    $wablasToken = trim($_POST['wablas_token']);
    
    // Validation
    if (empty($wablasApiUrl)) {
        utility::jsToastr(__('Settings'), __('Base URL API cannot be empty!'), 'error');
        exit();
    } elseif (empty($wablasToken)) {
        utility::jsToastr(__('Settings'), __('API Token cannot be empty!'), 'error');
        exit();
    } else {
        $wablasApiUrl = $dbs->escape_string($wablasApiUrl);
        $wablasToken = $dbs->escape_string($wablasToken);
        
        // create sql op object
        $sql_op = new simbio_dbop($dbs);
        
        // Update Base URL
        $data_url = array('setting_value' => $wablasApiUrl);
        $update_url = $sql_op->update('wa_settings', $data_url, "setting_key='wablas_api_url'");
        
        // Update Token
        $data_token = array('setting_value' => $wablasToken);
        $update_token = $sql_op->update('wa_settings', $data_token, "setting_key='wablas_token'");
        
        if ($update_url && $update_token) {
            // write log
            utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'system', $_SESSION['realname'].' update WhatsApp notification settings', 'WA Settings', 'Update');
            utility::jsToastr(__('Settings'), __('Settings successfully saved!'), 'success');
            echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'\');</script>';
        } else {
            utility::jsToastr(__('Settings'), __('Failed to save settings. Please contact system administrator')."\nDEBUG : ".$sql_op->error, 'error');
        }
    }
    exit();
}

// Get current settings
$settings = array();
$query = $dbs->query("SELECT setting_key, setting_value FROM wa_settings WHERE setting_key IN ('wablas_api_url', 'wablas_token')");
while ($row = $query->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

?>

<div class="menuBox">
    <div class="menuBoxInner whatsappIcon">
        <div class="per_title">
            <h2><?php echo __('WhatsApp Notification Settings'); ?></h2>
        </div>
    </div>
</div>

<div class="infoBox">
    <p><?php echo __('Configure connection to Wablas API. Token can be obtained from'); ?> <a href="https://console.wablas.com" target="_blank"><?php echo __('Wablas Dashboard'); ?></a>.</p>
</div>

<form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="blindSubmit">
    <div class="contentDesc">
        <h3><?php echo __('Wablas API Configuration'); ?></h3>
        
        <table class="bordered" style="width:100%; border-collapse: collapse; margin-top: 15px;">
            <tr>
                <td width="20%" style="padding: 12px; vertical-align: top;">
                    <strong><?php echo __('Base URL API'); ?> *</strong>
                </td>
                <td style="padding: 12px;">
                    <input type="url" 
                           name="wablas_api_url" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($settings['wablas_api_url']??''); ?>" 
                           required 
                           placeholder="https://tegal.wablas.com/api"
                           style="width: 100%;">
                    <small style="color: #666;">
                        <?php echo __('Wablas API endpoint URL'); ?>.<br>
                        <?php echo __('Example'); ?>: <code>https://tegal.wablas.com/api</code> <?php echo __('or'); ?> <code>https://console.wablas.com/api</code>
                    </small>
                </td>
            </tr>
            
            <tr>
                <td style="padding: 12px; vertical-align: top;">
                    <strong><?php echo __('API Token'); ?> *</strong>
                </td>
                <td style="padding: 12px;">
                    <input type="text" 
                           name="wablas_token" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($settings['wablas_token']??''); ?>" 
                           required 
                           placeholder="<?php echo __('Enter token from Wablas dashboard'); ?>"
                           style="width: 100%;">
                    <small style="color: #666;">
                        <?php echo __('API token can be obtained from Wablas dashboard'); ?>. 
                        <a href="https://console.wablas.com" target="_blank"><?php echo __('Login to Wablas'); ?> →</a>
                    </small>
                </td>
            </tr>
            
            <tr>
                <td colspan="2" style="text-align:center; padding: 15px; background: #070707;">
                    <button type="submit" name="saveSettings" class="btn btn-success">
                        💾 <?php echo __('Save Settings'); ?>
                    </button>
                    <!-- <a href="index.php" class="btn btn-default">
                        ← <?php echo __('Back to Dashboard'); ?>
                    </a> -->
                </td>
            </tr>
        </table>
    </div>
</form>

<!-- Help Section -->
<div class="infoBox note" style="background-color: #080808; border: 1px solid #b3d4fc; padding: 12px; border-radius: 5px; margin-top: 20px;">
    <strong>📘 <?php echo __('How to Setup Wablas Token'); ?>:</strong>
    <ol style="margin: 10px 0;">
        <li><?php echo __('Login to Wablas dashboard at'); ?> <a href="https://console.wablas.com" target="_blank">https://console.wablas.com</a></li>
        <li><?php echo __('Select active device'); ?></li>
        <li><?php echo __('Copy the displayed API token'); ?></li>
        <li><?php echo __('Paste the token in the form above'); ?></li>
        <li><?php echo __('Click'); ?> "<?php echo __('Save Settings'); ?>"</li>
        <li><?php echo __('Go back to Dashboard to verify connection'); ?></li>
    </ol>
</div>

<style>
.contentDesc table td {
    padding: 12px;
    border: 1px solid #ddd;
}

.form-control {
    box-sizing: border-box;
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    border-color: #0078d7;
    outline: none;
    box-shadow: 0 0 5px rgba(0, 120, 215, 0.3);
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
    display: inline-block;
}

.btn-success {
    background-color: #28a745;
    color: #fff;
    font-weight: bold;
}

.btn-success:hover {
    background-color: #218838;
}

.btn-default {
    background-color: #6c757d;
    color: #fff;
}

.btn-default:hover {
    background-color: #5a6268;
}

.infoBox.note {
    font-size: 13px;
}

.infoBox.note code {
    background: #eef3f8;
    padding: 2px 5px;
    border-radius: 3px;
    font-family: monospace;
}

table.bordered {
    border: 1px solid #ddd;
}
</style>