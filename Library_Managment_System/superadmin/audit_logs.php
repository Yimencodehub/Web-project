<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['superadmin']);

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    // Delete logs older than 30 days
    $stmt = $pdo->prepare("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $msg = "Logs older than 30 days cleared.";
    $msgType = "success";
    logAction($pdo, $_SESSION['user_id'], 'clear_logs', "Cleared old audit logs");
}

// Pagination & Filtering
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$action_filter = $_GET['action_filter'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = "1=1";
$params = [];

if ($search) {
    $where .= " AND (u.username LIKE ? OR a.details LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($action_filter) {
    $where .= " AND a.action = ?";
    $params[] = $action_filter;
}
if ($date_from) {
    $where .= " AND DATE(a.created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where .= " AND DATE(a.created_at) <= ?";
    $params[] = $date_to;
}

$countQuery = "SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE $where";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalRows = $stmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

$query = "SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE $where ORDER BY a.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique actions for filter
$actionsList = $pdo->query("SELECT DISTINCT action FROM audit_logs")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg: #0f172a; --sidebar: #111827; --card-bg: rgba(30, 41, 59, 0.8); --primary: #4f46e5; --accent: #f59e0b; --danger: #ef4444; --text-main: #f8fafc; --text-muted: #94a3b8; --border: rgba(255, 255, 255, 0.1); }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg); color: var(--text-main); display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background-color: var(--sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid var(--border); }
        .sidebar-header h2 { font-size: 1.2rem; color: var(--text-main); }
        .nav-links { list-style: none; padding: 20px 0; flex: 1; }
        .nav-links li { padding: 0 20px; margin-bottom: 10px; }
        .nav-links a { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text-muted); padding: 12px 15px; border-radius: 8px; transition: all 0.3s; }
        .nav-links a:hover, .nav-links a.active { background-color: rgba(79, 70, 229, 0.1); color: var(--primary); }
        
        .main-content { flex: 1; padding: 30px; overflow-y: auto; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        
        .card { background: var(--card-bg); backdrop-filter: blur(10px); border-radius: 12px; border: 1px solid var(--border); padding: 20px; }
        
        .filters { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; background: rgba(15, 23, 42, 0.5); padding: 15px; border-radius: 8px; border: 1px solid var(--border); }
        .form-control { background: var(--bg); color: white; border: 1px solid var(--border); padding: 8px 12px; border-radius: 6px; outline: none; }
        
        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.9rem; font-weight: 500; color: white; transition: 0.3s; }
        .btn-primary { background: var(--primary); }
        .btn-danger { background: var(--danger); }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { color: var(--text-muted); font-weight: 500; font-size: 0.9rem; }
        .badge { background: rgba(79, 70, 229, 0.1); color: var(--primary); padding: 3px 8px; border-radius: 12px; font-size: 0.75rem; border: 1px solid rgba(79, 70, 229, 0.2); }
        
        .pagination { display: flex; gap: 5px; margin-top: 20px; justify-content: flex-end; }
        .page-link { padding: 8px 12px; background: var(--card-bg); border: 1px solid var(--border); color: var(--text-main); text-decoration: none; border-radius: 4px; }
        .page-link.active { background: var(--primary); border-color: var(--primary); }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-book-open" style="color: var(--primary);"></i> Library Admin</h2>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="admins.php"><i class="fas fa-users-cog"></i> Admins & Staff</a></li>
            <li><a href="settings.php"><i class="fas fa-cogs"></i> System Settings</a></li>
            <li><a href="backup.php"><i class="fas fa-database"></i> Backup & Restore</a></li>
            <li><a href="audit_logs.php" class="active"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-pie"></i> Advanced Reports</a></li>
            <li><a href="fine_config.php"><i class="fas fa-money-bill-wave"></i> Fine Config</a></li>
            <li><a href="../logout.php" style="color: var(--danger);"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="topbar">
            <h1>Audit Logs</h1>
            <form method="POST" onsubmit="return confirm('Clear logs older than 30 days?');">
                <input type="hidden" name="clear_logs" value="1">
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Clear Old Logs</button>
            </form>
        </div>
        
        <?php if($msg): ?>
            <div style="background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.2); color:#10b981; padding:15px; border-radius:8px; margin-bottom:20px;">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <form method="GET" class="filters">
                <input type="text" name="search" class="form-control" placeholder="Search user or details..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="action_filter" class="form-control">
                    <option value="">All Actions</option>
                    <?php foreach($actionsList as $act): ?>
                        <option value="<?php echo $act; ?>" <?php echo $action_filter == $act ? 'selected' : ''; ?>><?php echo $act; ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <a href="audit_logs.php" class="btn" style="background:var(--border);">Reset</a>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($logs as $log): ?>
                    <tr>
                        <td><?php echo $log['id']; ?></td>
                        <td><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></td>
                        <td><span class="badge"><?php echo htmlspecialchars($log['action']); ?></span></td>
                        <td><?php echo htmlspecialchars($log['details']); ?></td>
                        <td style="color:var(--text-muted);"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                        <td style="color:var(--text-muted);"><?php echo $log['created_at']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($logs)): ?>
                    <tr><td colspan="6" style="text-align:center;">No logs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if($totalPages > 1): ?>
            <div class="pagination">
                <?php for($i=1; $i<=$totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&action_filter=<?php echo urlencode($action_filter); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="page-link <?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
