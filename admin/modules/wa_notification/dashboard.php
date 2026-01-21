<?php
/**
 * Dashboard WhatsApp Notification
 * File: /admin/modules/wa_notification/index.php
 */

// key to authenticate
define('INDEX_AUTH', '1');

// main system configuration
require '../../../sysconfig.inc.php';

// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';

// Load Wablas API
require 'lib/WablasAPI.class.php';

// privileges checking
$can_read = utility::havePrivilege('system', 'r');
$can_write = utility::havePrivilege('system', 'w');

if (!($can_read AND $can_write)) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
}

$page_title = 'WhatsApp Notification Dashboard';

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

<?php if (!$status['success']): ?>
    <!-- Error State -->
    <div class="errorBox">
        <h3>⚠️ <?php echo __('Failed to Connect to Wablas'); ?></h3>
        <p><?php echo htmlspecialchars($status['message']); ?></p>
        <p><?php echo __('Make sure the API token is configured correctly in the'); ?> <a href="settings.php"><?php echo __('Settings'); ?></a> <?php echo __('menu'); ?>.</p>
    </div>
<?php else: ?>
    <!-- Status Gateway -->
    <div class="infoBox" style="<?php echo $status['is_expired'] ? 'background: #070707;' : 'background: #050505;'; ?>">
        <h3><?php echo __('WhatsApp Gateway Status'); ?></h3>
        <table class="dataList" style="margin-top: 15px;">
            <tr>
                <td width="200"><strong><?php echo __('Device Status'); ?></strong></td>
                <td>
                    <?php if ($status['status'] == 'connected'): ?>
                        <span style="color: green; font-weight: bold; font-size: 16px;">✓ <?php echo __('Connected'); ?></span>
                    <?php else: ?>
                        <span style="color: red; font-weight: bold; font-size: 16px;">✗ <?php echo __('Disconnected'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (!empty($status['name'])): ?>
            <tr>
                <td><strong><?php echo __('Device Name'); ?></strong></td>
                <td><?php echo htmlspecialchars($status['name']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($status['sender'])): ?>
            <tr>
                <td><strong><?php echo __('WhatsApp Number'); ?></strong></td>
                <td><?php echo htmlspecialchars($status['sender']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($status['package'])): ?>
            <tr>
                <td><strong><?php echo __('Package'); ?></strong></td>
                <td><?php echo htmlspecialchars($status['package']); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <!-- Kuota Pesan -->
    <div class="infoBox">
        <h3><?php echo __('Remaining Message Quota'); ?></h3>
        <div style="text-align: center; padding: 30px;">
            <div style="font-size: 48px; font-weight: bold; color: <?php echo $status['quota'] > 100 ? '#4caf50' : ($status['quota'] > 50 ? '#ff9800' : '#f44336'); ?>;">
                <?php echo number_format($status['quota']); ?>
            </div>
            <div style="font-size: 18px; color: #666; margin-top: 10px;">
                <?php echo __('messages remaining'); ?>
            </div>
        </div>
        <?php if ($status['quota'] <= 100): ?>
        <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-top: 15px;">
            <strong>⚠️ <?php echo __('Warning'); ?>:</strong> <?php echo __('Message quota is running low'); ?> (<?php echo $status['quota']; ?> <?php echo __('messages'); ?>). 
            <?php echo __('Please top up immediately at'); ?> <a href="https://console.wablas.com" target="_blank"><?php echo __('Wablas Dashboard'); ?></a> <?php echo __('to avoid service disruption'); ?>.
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Expired Date -->
    <div class="infoBox" style="<?php echo $status['is_expired'] ? 'background: #ffebee;' : ''; ?>">
        <h3><?php echo __('Subscription Period'); ?></h3>
        <table class="dataList" style="margin-top: 15px;">
            <?php if ($status['expired_date']): ?>
            <tr>
                <td width="200"><strong><?php echo __('Expiry Date'); ?></strong></td>
                <td style="font-size: 16px; font-weight: bold;">
                    <?php echo date('d F Y', strtotime($status['expired_date'])); ?>
                </td>
            </tr>
            <tr>
                <td><strong><?php echo __('Subscription Status'); ?></strong></td>
                <td>
                    <?php if ($status['is_expired']): ?>
                        <span style="color: red; font-weight: bold; font-size: 16px;">✗ <?php echo __('EXPIRED'); ?></span>
                    <?php else: ?>
                        <span style="color: green; font-weight: bold; font-size: 16px;">✓ <?php echo __('Active'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (!$status['is_expired']): ?>
            <tr>
                <td><strong><?php echo __('Time Remaining'); ?></strong></td>
                <td>
                    <span style="font-weight: bold; font-size: 18px; color: <?php echo $status['days_remaining'] > 30 ? '#4caf50' : ($status['days_remaining'] > 7 ? '#ff9800' : '#f44336'); ?>;">
                        <?php echo $status['days_remaining']; ?> <?php echo __('days'); ?>
                    </span>
                </td>
            </tr>
            <?php endif; ?>
            <?php else: ?>
            <tr>
                <td colspan="2">
                    <p style="color: #666;"><?php echo __('Subscription information is not available from the API'); ?>.</p>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        
        <?php if ($status['is_expired']): ?>
        <div style="background: #f44336; color: white; padding: 15px; border-radius: 5px; margin-top: 15px;">
            <strong>⚠️ <?php echo __('ATTENTION'); ?>!</strong><br>
            <?php echo __('Your Wablas subscription has EXPIRED since'); ?> <?php echo date('d F Y', strtotime($status['expired_date'])); ?>.<br>
            <?php echo __('The system cannot send WhatsApp notifications'); ?>.<br>
            <?php echo __('Please renew your subscription at'); ?> <a href="https://console.wablas.com" target="_blank" style="color: white; text-decoration: underline; font-weight: bold;"><?php echo __('Wablas Dashboard'); ?></a>.
        </div>
        <?php elseif ($status['days_remaining'] <= 7): ?>
        <div style="background: #ff9800; color: white; padding: 15px; border-radius: 5px; margin-top: 15px;">
            <strong>⚠️ <?php echo __('WARNING'); ?>!</strong><br>
            <?php echo __('Wablas subscription will expire in'); ?> <strong><?php echo $status['days_remaining']; ?> <?php echo __('days'); ?></strong> (<?php echo date('d F Y', strtotime($status['expired_date'])); ?>).<br>
            <?php echo __('Please renew immediately to avoid service disruption'); ?>.
        </div>
        <?php elseif ($status['days_remaining'] <= 30): ?>
        <div style="background: #2196f3; color: white; padding: 15px; border-radius: 5px; margin-top: 15px;">
            <strong>ℹ️ <?php echo __('Information'); ?>:</strong><br>
            <?php echo __('Wablas subscription will expire in'); ?> <?php echo $status['days_remaining']; ?> <?php echo __('days'); ?> (<?php echo date('d F Y', strtotime($status['expired_date'])); ?>).
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Quick Links -->
<div class="infoBox">
    <h3><?php echo __('Other Menus'); ?></h3>
    <div style="display:flex; gap:10px; margin-top:15px; flex-wrap:wrap;">

        <a href="<?php echo MWB; ?>wa_notification/templates.php" class="btn btn-primary">
            <i class="fas fa-file-alt"></i> <?php echo __('Message Templates'); ?>
        </a>
        <a href="<?php echo MWB; ?>wa_notification/logs.php" class="btn btn-primary">
            <i class="fas fa-history"></i> <?php echo __('Delivery Logs'); ?>
        </a>

        <a href="<?php echo MWB; ?>wa_notification/settings.php" class="btn btn-primary">
            <i class="fas fa-cog"></i> <?php echo __('Settings'); ?>
        </a>

    </div>
</div>

<!-- Debug Info (Remove in production) -->
<?php if (false): // Set true for debug ?>
<div class="infoBox" style="margin-top: 20px;">
    <h3><?php echo __('Debug Info'); ?></h3>
    <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;">
<?php print_r($status); ?>
    </pre>
</div>
<?php endif; ?>