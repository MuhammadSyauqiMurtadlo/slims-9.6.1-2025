<?php
/**
 * Log Pengiriman WhatsApp
 * File: /admin/modules/wa_notification/logs.php
 * 
 * Menampilkan riwayat pengiriman notifikasi WhatsApp kepada anggota perpustakaan
 * dengan fitur filter, pagination, dan statistik.
 */

// key to authenticate
define('INDEX_AUTH', '1');

require '../../../sysconfig.inc.php';
require SB . 'admin/default/session.inc.php';
require SB . 'admin/default/session_check.inc.php';

// ============================================================================
// PRIVILEGE CHECK
// ============================================================================
if ($_SESSION['uid'] != 1) {
    die('<div class="errorBox">You don\'t have enough privileges to access this area!</div>');
}

// ============================================================================
// CONFIGURATION
// ============================================================================
$page_title = 'Log Pengiriman WhatsApp';
$records_per_page = 20;

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
 * Get current page number
 */
function getCurrentPage() {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    return max(1, $page);
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

/**
 * Build query string for pagination
 */
function buildQueryString($exclude = ['page']) {
    $params = $_GET;
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    $queryStr = http_build_query($params);
    return !empty($queryStr) ? '&' . $queryStr : '';
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

// Pagination setup
$current_page = getCurrentPage();
$offset = ($current_page - 1) * $records_per_page;

// Build query
$where_clause = buildWhereClause($filters);

// Get total records
$count_query = $dbs->query("SELECT COUNT(*) as total FROM wa_logs {$where_clause}");
$count_row = $count_query->fetch_assoc();
$total_records = $count_row['total'];
$total_pages = ceil($total_records / $records_per_page);

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

// ============================================================================
// HEADER
// ============================================================================
// require SB . 'admin/default/header.inc.php';
?>

<!-- ============================================================================ -->
<!-- PAGE HEADER -->
<!-- ============================================================================ -->
<div class="menuBox">
    <div class="menuBoxInner whatsappIcon">
        <div class="per_title">
            <h2><?php echo __('Log Pengiriman WhatsApp'); ?></h2>
        </div>
        <div class="infoBox">
            <p>Riwayat pengiriman notifikasi WhatsApp kepada anggota perpustakaan.</p>
        </div>
    </div>
</div>

<!-- <div id="mainContent"> -->
    
    <!-- ======================================================================== -->
    <!-- FILTER FORM -->
    <!-- ======================================================================== -->
    <div class="infoBox">
        <h4>Filter Log</h4>
        <form method="GET" action="" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            
            <!-- Status Filter -->
            <select name="status" class="form-control" style="width: auto;">
                <option value="">Semua Status</option>
                <option value="success" <?php echo $filters['status'] === 'success' ? 'selected' : ''; ?>>Berhasil</option>
                <option value="failed" <?php echo $filters['status'] === 'failed' ? 'selected' : ''; ?>>Gagal</option>
                <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
            </select>
            
            <!-- Type Filter -->
            <select name="type" class="form-control" style="width: auto;">
                <option value="">Semua Tipe</option>
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
                style="width: auto;" 
                value="<?php echo htmlspecialchars($filters['date']); ?>" 
                placeholder="Tanggal"
            >
            
            <!-- Search Filter -->
            <input 
                type="text" 
                name="search" 
                class="form-control" 
                style="width: 200px;" 
                value="<?php echo htmlspecialchars($filters['search']); ?>" 
                placeholder="Cari nama/ID/buku..."
            >
            
            <!-- Buttons -->
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="logs.php" class="btn btn-default">Reset</a>
        </form>
    </div>
    
    <!-- ======================================================================== -->
    <!-- STATISTICS -->
    <!-- ======================================================================== -->
    <div style="display: flex; gap: 15px; margin-bottom: 20px;">
        
        <!-- Success Count -->
        <div style="background: #050505; padding: 15px; border-radius: 5px; flex: 1;">
            <h3 style="margin: 0;"><?php echo number_format($statistics['success']); ?></h3>
            <p style="margin: 5px 0 0 0;">Berhasil Hari Ini</p>
        </div>
        
        <!-- Failed Count -->
        <div style="background: #050505; padding: 15px; border-radius: 5px; flex: 1;">
            <h3 style="margin: 0;"><?php echo number_format($statistics['failed']); ?></h3>
            <p style="margin: 5px 0 0 0;">Gagal Hari Ini</p>
        </div>
        
        <!-- Total Count -->
        <div style="background: #050505; padding: 15px; border-radius: 5px; flex: 1;">
            <h3 style="margin: 0;"><?php echo number_format($statistics['total']); ?></h3>
            <p style="margin: 5px 0 0 0;">Total Hari Ini</p>
        </div>
    </div>
    
    <!-- ======================================================================== -->
    <!-- LOGS TABLE -->
    <!-- ======================================================================== -->
    <table class="dataList">
        <thead>
            <tr>
                <th width="3%">No</th>
                <th width="12%">Waktu Kirim</th>
                <th width="12%">Anggota</th>
                <th width="10%">No. HP</th>
                <th width="25%">Buku</th>
                <th width="8%">Jatuh Tempo</th>
                <th width="8%">Tipe</th>
                <th width="8%">Status</th>
                <th width="7%">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($logs_query->num_rows == 0): ?>
                <!-- Empty State -->
                <tr>
                    <td colspan="9" style="text-align: center; padding: 30px;">
                        <strong>Tidak ada log pengiriman</strong>
                        <?php if (!empty(array_filter($filters))): ?>
                            <br><small>Coba ubah filter pencarian Anda</small>
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
                            <br><small>Kode: <?php echo htmlspecialchars($log['item_code']); ?></small>
                        </td>
                        
                        <!-- Due Date -->
                        <td><?php echo date('d-m-Y', strtotime($log['due_date'])); ?></td>
                        
                        <!-- Notification Type -->
                        <td><span class="badge"><?php echo htmlspecialchars($log['notification_type']); ?></span></td>
                        
                        <!-- Status -->
                        <td>
                            <?php if ($log['status'] === 'success'): ?>
                                <span style="color: green; font-weight: bold;">✓ Berhasil</span>
                            <?php elseif ($log['status'] === 'failed'): ?>
                                <span style="color: red; font-weight: bold;">✗ Gagal</span>
                            <?php else: ?>
                                <span style="color: orange; font-weight: bold;">⏳ Pending</span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Action -->
                        <td>
                            <a 
                                href="#" 
                                onclick="showDetail(<?php echo $log['log_id']; ?>); return false;" 
                                class="btn btn-sm btn-info"
                                title="Lihat detail lengkap"
                            >
                                Detail
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- ======================================================================== -->
    <!-- PAGINATION -->
    <!-- ======================================================================== -->
    <?php if ($total_pages > 1): ?>
        <div style="margin-top: 20px; text-align: center;">
            <?php $query_string = buildQueryString(); ?>
            
            <!-- First & Previous -->
            <?php if ($current_page > 1): ?>
                <a href="?page=1<?php echo $query_string; ?>" class="btn btn-sm">« First</a>
                <a href="?page=<?php echo $current_page - 1; ?><?php echo $query_string; ?>" class="btn btn-sm">‹ Prev</a>
            <?php endif; ?>
            
            <!-- Page Info -->
            <span style="padding: 0 15px;">
                Halaman <?php echo $current_page; ?> dari <?php echo $total_pages; ?>
                <small>(Total: <?php echo number_format($total_records); ?> record)</small>
            </span>
            
            <!-- Next & Last -->
            <?php if ($current_page < $total_pages): ?>
                <a href="?page=<?php echo $current_page + 1; ?><?php echo $query_string; ?>" class="btn btn-sm">Next ›</a>
                <a href="?page=<?php echo $total_pages; ?><?php echo $query_string; ?>" class="btn btn-sm">Last »</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
</div>

<!-- ============================================================================ -->
<!-- DETAIL MODAL -->
<!-- ============================================================================ -->
<div 
    id="detailModal" 
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;"
    onclick="if(event.target === this) closeDetail();"
>
    <div style="background: white; margin: 50px auto; padding: 20px; width: 80%; max-width: 800px; border-radius: 8px; max-height: 80vh; overflow-y: auto;">
        <h3>Detail Log Pengiriman</h3>
        <div id="detailContent">Loading...</div>
        <button onclick="closeDetail()" class="btn btn-default" style="margin-top: 15px;">Tutup</button>
    </div>
<!-- </div> -->

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
    content.innerHTML = '<p>Loading...</p>';
    
    fetch('processor.php?action=get_log_detail&log_id=' + logId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = buildDetailHTML(data.log);
            } else {
                content.innerHTML = '<div class="errorBox">' + (data.message || 'Terjadi kesalahan') + '</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="errorBox">Error: ' + error.message + '</div>';
        });
}

/**
 * Build HTML for detail view
 */
function buildDetailHTML(log) {
    let html = '<table class="dataList" style="margin-top: 15px;">';
    
    // Basic info
    const fields = [
        { label: 'Loan ID', value: log.loan_id },
        { label: 'Member ID', value: log.member_id },
        { label: 'Nama Anggota', value: log.member_name },
        { label: 'No. HP', value: log.member_phone },
        { label: 'Judul Buku', value: log.book_title },
        { label: 'Kode Item', value: log.item_code },
        { label: 'Jatuh Tempo', value: log.due_date },
        { label: 'Tipe Notifikasi', value: log.notification_type },
        { label: 'Status', value: log.status },
        { label: 'Waktu Kirim', value: log.sent_at }
    ];
    
    fields.forEach(field => {
        html += '<tr><td width="200"><strong>' + field.label + '</strong></td><td>' + escapeHtml(field.value) + '</td></tr>';
    });
    
    // Wablas Message ID (if exists)
    if (log.wablas_message_id) {
        html += '<tr><td><strong>Wablas Message ID</strong></td><td>' + escapeHtml(log.wablas_message_id) + '</td></tr>';
    }
    
    // Error message (if exists)
    if (log.error_message) {
        html += '<tr><td><strong>Error</strong></td><td style="color: red;">' + escapeHtml(log.error_message) + '</td></tr>';
    }
    
    html += '</table>';
    
    // Message content
    html += '<h4 style="margin-top: 20px;">Isi Pesan yang Dikirim</h4>';
    html += '<pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; white-space: pre-wrap;">' + escapeHtml(log.message_sent) + '</pre>';
    
    // Wablas response
    html += '<h4 style="margin-top: 20px;">Response dari Wablas</h4>';
    html += '<pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; white-space: pre-wrap; max-height: 200px; overflow-y: auto;">' + escapeHtml(log.wablas_response) + '</pre>';
    
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

<?php
// require SB . 'admin/default/footer.inc.php';
 ?>