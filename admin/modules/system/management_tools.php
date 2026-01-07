<?php
/**
 * Management Tools - Sistem Notifikasi Otomatis (WhatsApp Gateway)
 * Custom Module SLiMS
 */

//! Cek session dan privileges
// require '../../../sysconfig.inc.php';
// require SB. 'admin/default/session.inc.php';
// require SB. 'admin/default/session_check.inc.php';
// require SIMBIO. 'simbio_GUI/table/simbio_table.inc.php';
// require SIMBIO. 'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
// require LIB. 'module.inc.php';
//! Cek session dan privileges

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
//do_checkIP('smc-system');

// only administrator have privileges to change global settings
//if ($_SESSION['uid'] != 1) {
//  header('Location: '.MWB.'system/content.php');
//  die();
//}

require SB.'admin/default/session_check.inc.php';
require SIMBIO.'simbio_FILE/simbio_directory.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';
require LIB.'module.inc.php';

// Cek privilege - pastikan hanya Super User atau Admin yang bisa akses
// if ($_SESSION['uid'] != 1 && !$can_read) {
//     die('<div class="errorBox">You dont have enough privileges to access this area!</div>');
// }
?>

<div class="menuBox">
    <div class="menuBoxInner systemIcon">
        <div class="per_title">
            <h2><?php echo('Management Tools'); ?></h2>
        </div>
        <div class="infoBox">
            <?php echo('Deskripsi fitur kamu di sini'); ?>
        </div>
    </div>
</div>

<div id="mainContent">
    <?php
    // Konten fitur kamu di sini
    echo '<div class="infoBox">';
    echo 'Halo! Ini fitur baru kamu';
    echo '<br />Silakan kembangkan sesuai kebutuhan kamu.';
    echo '</div>';
    ?>
</div>