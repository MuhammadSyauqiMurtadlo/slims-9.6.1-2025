<?php
/**
 * Log Pengiriman WhatsApp
 * File: /admin/modules/wa_notification/logs.php
 * 
 * FIXED VERSION - Filter berfungsi normal di iframe SLiMS
 */

// key to authenticate
define('INDEX_AUTH', '1');

// main system configuration
if (!defined('SB')) {
    require '../../../sysconfig.inc.php';
    // start the session
    require SB.'admin/default/session.inc.php';
}

// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-system');

require SB.'admin/default/session_check.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';

// privileges checking
$can_read = utility::havePrivilege('system', 'r');
$can_write = utility::havePrivilege('system', 'w');

if (!($can_read AND $can_write)) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
}

$page_title = 'WhatsApp Delivery Logs';

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Sanitize and validate input
 */
function getFilterValue($key, $default = '') {
    global $dbs;
    return isset($_GET[$key]) ? $dbs->escape_string(trim($_GET[$key])) : $default;
}

/**
 * Build WHERE clause from filters
 */
function buildWhereClause($filters) {
    $conditions = [];
    
    if (!empty($filters['status'])) {
        $conditions[] = "status = '{$filters['status']}'";
    }
    
    if (!empty($filters['type'])) {
        $conditions[] = "notification_type = '{$filters['type']}'";
    }
    
    if (!empty($filters['date'])) {
        $conditions[] = "DATE(sent_at) = '{$filters['date']}'";
    }
    
    if (!empty($filters['search'])) {
        $search = $filters['search'];
        $conditions[] = "(member_name LIKE '%{$search}%' OR member_id LIKE '%{$search}%' OR book_title LIKE '%{$search}%')";
    }
    
    return !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
}

/**
 * Get statistics for today
 */
function getTodayStatistics($dbs) {
    $query = $dbs->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM wa_logs 
        WHERE DATE(sent_at) = CURDATE()
    ");
    
    return $query->fetch_assoc();
}

// ============================================================================
// DATA PROCESSING
// ============================================================================

// Get filters
$filters = [
    'status' => getFilterValue('status'),
    'type' => getFilterValue('type'),
    'date' => getFilterValue('date'),
    'search' => getFilterValue('search')
];

// Sanitize date format
if (!empty($filters['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date'])) {
    $filters['date'] = ''; // Invalid format, reset
}

// Validate status values
if (!empty($filters['status']) && !in_array($filters['status'], ['success', 'failed', 'pending'])) {
    $filters['status'] = '';
}

// Validate type values
if (!empty($filters['type']) && !in_array($filters['type'], ['H-3', 'H-2', 'H-1', 'H+0'])) {
    $filters['type'] = '';
}

// Pagination setup
$records_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Build query
$where_clause = buildWhereClause($filters);

// Get total records
$count_query = $dbs->query("SELECT COUNT(*) as total FROM wa_logs {$where_clause}");
$count_row = $count_query->fetch_assoc();
$total_records = $count_row['total'];

// Get log records
$logs_query = $dbs->query("
    SELECT * 
    FROM wa_logs 
    {$where_clause} 
    ORDER BY created_at DESC 
    LIMIT {$records_per_page} 
    OFFSET {$offset}
");

// Get statistics
$statistics = getTodayStatistics($dbs);

// Build query string for pagination (exclude 'page' parameter)
$query_params = $_GET;
unset($query_params['page']);
$query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';

?>

<!-- ============================================================================ -->
<!-- PAGE HEADER -->
<!-- ============================================================================ -->
<div class="menuBox">
    <div class="menuBoxInner whatsappIcon">
        <div class="per_title">
            <h2><?php echo __('WhatsApp Delivery Logs'); ?></h2>
        </div>
    </div>
</div>

<div class="infoBox">
    <p><?php echo __('History of WhatsApp notifications sent to library members'); ?>.</p>
</div>

<!-- ======================================================================== -->
<!-- FILTER FORM -->
<!-- ======================================================================== -->
<div class="infoBox">
    <h4><?php echo __('Filter Logs'); ?></h4>
    <!-- 🔥 FIX: Form action langsung ke file ini, pattern sama dengan membership -->
    <form name="filterLogs" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="filterLogs" method="get" class="form-inline" style="gap: 10px;">
        
        <!-- Status Filter -->
        <select name="status" class="form-control">
            <option value=""><?php echo __('All Status'); ?></option>
            <option value="success" <?php echo $filters['status'] === 'success' ? 'selected' : ''; ?>><?php echo __('Success'); ?></option>
            <option value="failed" <?php echo $filters['status'] === 'failed' ? 'selected' : ''; ?>><?php echo __('Failed'); ?></option>
            <!-- <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>><?php echo __('Pending'); ?></option> -->
        </select>
        
        <!-- Type Filter -->
        <select name="type" class="form-control">
            <option value=""><?php echo __('All Types'); ?></option>
            <option value="H-3" <?php echo $filters['type'] === 'H-3' ? 'selected' : ''; ?>>H-3</option>
            <option value="H-2" <?php echo $filters['type'] === 'H-2' ? 'selected' : ''; ?>>H-2</option>
            <option value="H-1" <?php echo $filters['type'] === 'H-1' ? 'selected' : ''; ?>>H-1</option>
            <option value="H+0" <?php echo $filters['type'] === 'H+0' ? 'selected' : ''; ?>>H+0</option>
        </select>
        
        <!-- Date Filter -->
        <input 
            type="date" 
            name="date" 
            class="form-control" 
            value="<?php echo htmlspecialchars($filters['date']); ?>" 
            placeholder="<?php echo __('Date'); ?>"
        >
        
        <!-- Search Filter -->
        <input 
            type="text" 
            name="search" 
            class="form-control col-md-3" 
            value="<?php echo htmlspecialchars($filters['search']); ?>" 
            placeholder="<?php echo __('Search name/ID/book'); ?>..."
        >
        
        <!-- Buttons -->
        <!-- 🔥 FIX: Button syntax yang benar -->
        <input type="submit" value="<?php echo __('Filter'); ?>" class="btn btn-primary" />
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-default"><?php echo __('Reset'); ?></a>
    </form>
</div>

<!-- ======================================================================== -->
<!-- STATISTICS -->
<!-- ======================================================================== -->
<div style="display: flex; gap: 15px; margin-bottom: 20px;">
    
    <!-- Success Count -->
    <div style="background: #e8f5e9; padding: 15px; border-radius: 5px; flex: 1; border: 1px solid #c8e6c9;">
        <h3 style="margin: 0; color: #2e7d32;"><?php echo number_format($statistics['success']); ?></h3>
        <p style="margin: 5px 0 0 0; color: #555;"><?php echo __('Success Today'); ?></p>
    </div>
    
    <!-- Failed Count -->
    <div style="background: #ffebee; padding: 15px; border-radius: 5px; flex: 1; border: 1px solid #ffcdd2;">
        <h3 style="margin: 0; color: #c62828;"><?php echo number_format($statistics['failed']); ?></h3>
        <p style="margin: 5px 0 0 0; color: #555;"><?php echo __('Failed Today'); ?></p>
    </div>
    
    <!-- Total Count -->
    <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; flex: 1; border: 1px solid #bbdefb;">
        <h3 style="margin: 0; color: #1565c0;"><?php echo number_format($statistics['total']); ?></h3>
        <p style="margin: 5px 0 0 0; color: #555;"><?php echo __('Total Today'); ?></p>
    </div>
</div>

<!-- ======================================================================== -->
<!-- SEARCH RESULT INFO -->
<!-- ======================================================================== -->
<?php if (!empty(array_filter($filters))): ?>
<div class="infoBox">
    <?php 
    $filter_info = [];
    if ($filters['status']) $filter_info[] = __('Status') . ': ' . ucfirst($filters['status']);
    if ($filters['type']) $filter_info[] = __('Type') . ': ' . $filters['type'];
    if ($filters['date']) $filter_info[] = __('Date') . ': ' . date('d-m-Y', strtotime($filters['date']));
    if ($filters['search']) $filter_info[] = __('Search') . ': "' . htmlspecialchars($filters['search']) . '"';
    
    echo __('Filter active') . ': <strong>' . implode(', ', $filter_info) . '</strong> ';
    echo '| ' . __('Found') . ' <strong>' . number_format($total_records) . '</strong> ' . __('records');
    ?>
</div>
<?php endif; ?>

<!-- ======================================================================== -->
<!-- LOGS TABLE -->
<!-- ======================================================================== -->
<table class="dataList">
    <thead>
        <tr>
            <th width="3%"><?php echo __('No'); ?></th>
            <th width="12%"><?php echo __('Sent Time'); ?></th>
            <th width="12%"><?php echo __('Member'); ?></th>
            <th width="10%"><?php echo __('Phone'); ?></th>
            <th width="25%"><?php echo __('Book'); ?></th>
            <th width="8%"><?php echo __('Due Date'); ?></th>
            <th width="8%"><?php echo __('Type'); ?></th>
            <th width="8%"><?php echo __('Status'); ?></th>
            <!-- <th width="7%"><?php echo __('Action'); ?></th> -->
        </tr>
    </thead>
    <tbody>
        <?php if ($logs_query->num_rows == 0): ?>
            <!-- Empty State -->
            <tr>
                <td colspan="9" style="text-align: center; padding: 30px; color: #999;">
                    <strong><?php echo __('No delivery logs'); ?></strong>
                    <?php if (!empty(array_filter($filters))): ?>
                        <br><small><?php echo __('Try changing your search filters'); ?></small>
                    <?php endif; ?>
                </td>
            </tr>
        <?php else: ?>
            <!-- Data Rows -->
            <?php 
            $row_number = $offset + 1;
            while ($log = $logs_query->fetch_assoc()): 
            ?>
                <tr>
                    <!-- Number -->
                    <td><?php echo $row_number++; ?></td>
                    
                    <!-- Sent Time -->
                    <td><?php echo date('d-m-Y H:i:s', strtotime($log['sent_at'])); ?></td>
                    
                    <!-- Member Info -->
                    <td>
                        <strong><?php echo htmlspecialchars($log['member_name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($log['member_id']); ?></small>
                    </td>
                    
                    <!-- Phone Number -->
                    <td><?php echo htmlspecialchars($log['member_phone']); ?></td>
                    
                    <!-- Book Info -->
                    <td>
                        <?php 
                        $book_title = htmlspecialchars($log['book_title']);
                        echo strlen($book_title) > 50 ? substr($book_title, 0, 50) . '...' : $book_title;
                        ?>
                        <br><small><?php echo __('Code'); ?>: <?php echo htmlspecialchars($log['item_code']); ?></small>
                    </td>
                    
                    <!-- Due Date -->
                    <td><?php echo date('d-m-Y', strtotime($log['due_date'])); ?></td>
                    
                    <!-- Notification Type -->
                    <td><span class="badge"><?php echo htmlspecialchars($log['notification_type']); ?></span></td>
                    
                    <!-- Status -->
                    <td>
                        <?php if ($log['status'] === 'success'): ?>
                            <span style="color: green; font-weight: bold;">✓ <?php echo __('Success'); ?></span>
                        <?php elseif ($log['status'] === 'failed'): ?>
                            <span style="color: red; font-weight: bold;">✗ <?php echo __('Failed'); ?></span>
                        <?php else: ?>
                            <span style="color: orange; font-weight: bold;">⏳ <?php echo __('Pending'); ?></span>
                        <?php endif; ?>
                    </td>
                    
                    <!-- Action -->
                    <!-- <td>
                        <a 
                            href="#" 
                            onclick="showDetail(<?php echo $log['log_id']; ?>); return false;" 
                            class="btn btn-sm btn-primary"
                            title="<?php echo __('View details'); ?>"
                        >
                            <?php echo __('Detail'); ?>
                        </a>
                    </td> -->
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
    </tbody>
</table>

<!-- ======================================================================== -->
<!-- PAGINATION -->
<!-- ======================================================================== -->
<?php
// 🔥 FIX: Pagination pakai $_SERVER['PHP_SELF'] biar konsisten
// create pagination object
$paging = new simbio_paging($total_records);
$paging->setValue('paging_link', $_SERVER['PHP_SELF'] . '?page=[page_number]' . $query_string);
$paging->setPagingRange($records_per_page);

// show pagination
if ($total_records > $records_per_page) {
    echo '<div class="paging" style="margin-top: 20px;">';
    echo $paging->makePaging($current_page);
    echo '</div>';
}
?>

<!-- ============================================================================ -->
<!-- DETAIL MODAL -->
<!-- ============================================================================ -->
<div 
    id="detailModal" 
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;"
    onclick="if(event.target === this) closeDetail();"
>
    <div style="background: white; margin: 50px auto; padding: 20px; width: 80%; max-width: 800px; border-radius: 8px; max-height: 80vh; overflow-y: auto;">
        <h3><?php echo __('Delivery Log Details'); ?></h3>
        <div id="detailContent"><?php echo __('Loading'); ?>...</div>
        <button onclick="closeDetail()" class="btn btn-default" style="margin-top: 15px;"><?php echo __('Close'); ?></button>
    </div>
</div>

<!-- ============================================================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================================================ -->
<script>
/**
 * Show detail modal
 */
function showDetail(logId) {
    const modal = document.getElementById('detailModal');
    const content = document.getElementById('detailContent');
    
    modal.style.display = 'block';
    content.innerHTML = '<p><?php echo __('Loading'); ?>...</p>';
    
    fetch('processor.php?action=get_log_detail&log_id=' + logId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = buildDetailHTML(data.log);
            } else {
                content.innerHTML = '<div class="errorBox">' + (data.message || '<?php echo __('An error occurred'); ?>') + '</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="errorBox"><?php echo __('Error'); ?>: ' + error.message + '</div>';
        });
}

/**
 * Build HTML for detail view
 */
function buildDetailHTML(log) {
    let html = '<table class="dataList" style="margin-top: 15px;">';
    
    // Basic info
    const fields = [
        { label: '<?php echo __('Loan ID'); ?>', value: log.loan_id },
        { label: '<?php echo __('Member ID'); ?>', value: log.member_id },
        { label: '<?php echo __('Member Name'); ?>', value: log.member_name },
        { label: '<?php echo __('Phone Number'); ?>', value: log.member_phone },
        { label: '<?php echo __('Book Title'); ?>', value: log.book_title },
        { label: '<?php echo __('Item Code'); ?>', value: log.item_code },
        { label: '<?php echo __('Due Date'); ?>', value: log.due_date },
        { label: '<?php echo __('Notification Type'); ?>', value: log.notification_type },
        { label: '<?php echo __('Status'); ?>', value: log.status },
        { label: '<?php echo __('Sent Time'); ?>', value: log.sent_at }
    ];
    
    fields.forEach(field => {
        html += '<tr><td width="200"><strong>' + field.label + '</strong></td><td>' + escapeHtml(field.value) + '</td></tr>';
    });
    
    // Wablas Message ID (if exists)
    if (log.wablas_message_id) {
        html += '<tr><td><strong><?php echo __('Wablas Message ID'); ?></strong></td><td>' + escapeHtml(log.wablas_message_id) + '</td></tr>';
    }
    
    // Error message (if exists)
    if (log.error_message) {
        html += '<tr><td><strong><?php echo __('Error'); ?></strong></td><td style="color: red;">' + escapeHtml(log.error_message) + '</td></tr>';
    }
    
    html += '</table>';
    
    // Message content
    html += '<h4 style="margin-top: 20px;"><?php echo __('Message Content'); ?></h4>';
    html += '<pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; white-space: pre-wrap; font-family: monospace;">' + escapeHtml(log.message_sent) + '</pre>';
    
    // Wablas response
    html += '<h4 style="margin-top: 20px;"><?php echo __('Wablas Response'); ?></h4>';
    html += '<pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; white-space: pre-wrap; max-height: 200px; overflow-y: auto; font-family: monospace;">' + escapeHtml(log.wablas_response) + '</pre>';
    
    return html;
}

/**
 * Close detail modal
 */
function closeDetail() {
    document.getElementById('detailModal').style.display = 'none';
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDetail();
    }
});
</script>

<style>
.form-inline {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
}

.form-inline .form-control {
    margin-right: 5px;
    margin-bottom: 5px;
}

.badge {
    background: #007bff;
    color: white;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
}

.paging {
    text-align: center;
}

.paging a,
.paging span {
    padding: 5px 10px;
    margin: 0 2px;
    border: 1px solid #ddd;
    text-decoration: none;
    background: #fff;
    color: #333;
}

.paging a:hover {
    background: #007bff;
    color: #fff;
    border-color: #007bff;
}

.paging .current {
    background: #007bff;
    color: #fff;
    border-color: #007bff;
    font-weight: bold;
}
</style>