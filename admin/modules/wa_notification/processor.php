<?php
/**
 * Ajax Processor for WhatsApp Notification
 * File: /admin/modules/wa_notification/processor.php
 */

// key to authenticate
define('INDEX_AUTH', '1');

require '../../../sysconfig.inc.php';
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';

// Load services
require 'lib/NotificationService.class.php';

// privileges checking
$can_read = utility::havePrivilege('system', 'r');
$can_write = utility::havePrivilege('system', 'w');

if (!($can_read AND $can_write)) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'run_manual':
        // Jalankan notifikasi manual
        try {
            $service = new NotificationService($dbs);
            $result = $service->processNotifications();
            
            echo json_encode([
                'success' => true,
                'total_processed' => $result['total_processed'],
                'success_count' => $result['success'],
                'failed' => $result['failed'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors']
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
        break;
    
    case 'get_log_detail':
        // Get detail log
        $logId = isset($_GET['log_id']) ? (int)$_GET['log_id'] : 0;
        
        if ($logId == 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid log ID']);
            exit;
        }
        
        $query = $dbs->query("SELECT * FROM wa_logs WHERE log_id = {$logId}");
        
        if ($query->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'Log not found']);
            exit;
        }
        
        $log = $query->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'log' => $log
        ]);
        break;
    
    case 'delete_old_logs':
        // Hapus log lama (lebih dari 90 hari)
        $dbs->query("DELETE FROM wa_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        
        echo json_encode([
            'success' => true,
            'message' => 'Old logs deleted successfully',
            'deleted_rows' => $dbs->affected_rows
        ]);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}