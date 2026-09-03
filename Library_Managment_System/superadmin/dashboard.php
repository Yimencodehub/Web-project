<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['superadmin']);

// Fetch stats
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM books");
$totalBooks = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM members");
$totalMembers = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM book_issues WHERE status = 'issued'");
$activeIssues = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM book_issues WHERE status = 'issued' AND due_date < CURDATE()");
$overdue = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM fines WHERE status = 'pending'");
$finesPending = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM fines WHERE status = 'paid'");
$finesCollected = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM categories");
$totalCategories = $stmt->fetchColumn();

// Recent logs
$stmt = $pdo->query("SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 15");
$logs = $stmt->fetchAll();

// System info
$phpVersion = phpversion();
$mysqlVersion = $pdo->query('select version()')->fetchColumn();
$serverTime = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Superadmin Dashboard - Library System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #0f172a;
            --sidebar: #111827;
            --card-bg: rgba(30, 41, 59, 0.8);
            --primary: #4f46e5;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg); color: var(--text-main); display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background-color: var(--sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid var(--border); }
        .sidebar-header h2 { font-size: 1.2rem; color: var(--text-main); }
        .nav-links { list-style: none; padding: 20px 0; flex: 1; }
        .nav-links li { padding: 0 20px; margin-bottom: 10px; }
        .nav-links a { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text-muted); padding: 12px 15px; border-radius: 8px; transition: all 0.3s; }
        .nav-links a:hover, .nav-links a.active { background-color: rgba(79, 70, 229, 0.1); color: var(--primary); }
        .nav-links a i { width: 20px; }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .role-badge { background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; border: 1px solid rgba(239, 68, 68, 0.2); }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--card-bg); backdrop-filter: blur(10px); padding: 20px; border-radius: 12px; border: 1px solid var(--border); display: flex; align-items: center; gap: 15px; transition: transform 0.3s, box-shadow 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .stat-icon.primary { background: rgba(79, 70, 229, 0.1); color: var(--primary); }
        .stat-icon.accent { background: rgba(245, 158, 11, 0.1); color: var(--accent); }
        .stat-icon.success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .stat-icon.danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .stat-info h3 { font-size: 1.5rem; font-weight: 700; margin-bottom: 5px; }
        .stat-info p { color: var(--text-muted); font-size: 0.9rem; }
        
        .dashboard-row { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .card { background: var(--card-bg); backdrop-filter: blur(10px); border-radius: 12px; border: 1px solid var(--border); padding: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
        .card-header h3 { font-size: 1.1rem; font-weight: 600; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { color: var(--text-muted); font-weight: 500; font-size: 0.9rem; }
        tr:last-child td { border-bottom: none; }
        
        .sys-info-item { display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--border); }
        .sys-info-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .sys-info-label { color: var(--text-muted); }
        .sys-info-value { font-weight: 500; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-book-open" style="color: var(--primary);"></i> Library Admin</h2>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="admins.php"><i class="fas fa-users-cog"></i> Admins & Staff</a></li>
            <li><a href="settings.php"><i class="fas fa-cogs"></i> System Settings</a></li>
            <li><a href="backup.php"><i class="fas fa-database"></i> Backup & Restore</a></li>
            <li><a href="audit_logs.php"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-pie"></i> Advanced Reports</a></li>
            <li><a href="fine_config.php"><i class="fas fa-money-bill-wave"></i> Fine Config</a></li>
            <li><a href="../logout.php" style="color: var(--danger);"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="topbar">
            <div>
                <h1 style="font-size: 1.8rem; font-weight: 700;">Superadmin Dashboard</h1>
                <p style="color: var(--text-muted); margin-top: 5px;">Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></p>
            </div>
            <div>
                <span class="role-badge">SUPER ADMIN</span>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($totalUsers); ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon accent"><i class="fas fa-book"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($totalBooks); ?></h3>
                    <p>Total Books</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success"><i class="fas fa-id-card"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($totalMembers); ?></h3>
                    <p>Total Members</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon danger"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($overdue); ?></h3>
                    <p>Overdue Books</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon primary"><i class="fas fa-hand-holding"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($activeIssues); ?></h3>
                    <p>Active Issues</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon accent"><i class="fas fa-money-bill"></i></div>
                <div class="stat-info">
                    <h3>$<?php echo number_format($finesPending, 2); ?></h3>
                    <p>Fines Pending</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success"><i class="fas fa-coins"></i></div>
                <div class="stat-info">
                    <h3>$<?php echo number_format($finesCollected, 2); ?></h3>
                    <p>Fines Collected</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon primary"><i class="fas fa-tags"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($totalCategories); ?></h3>
                    <p>Total Categories</p>
                </div>
            </div>
        </div>
        
        <div class="dashboard-row">
            <div class="card">
                <div class="card-header">
                    <h3>Recent Audit Logs</h3>
                    <a href="audit_logs.php" style="color: var(--primary); text-decoration: none; font-size: 0.9rem;">View All</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;"><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;"><?php echo htmlspecialchars($log['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($logs)): ?>
                        <tr><td colspan="4" style="text-align:center;">No recent logs.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>System Health</h3>
                </div>
                <div class="sys-info-item">
                    <span class="sys-info-label">PHP Version</span>
                    <span class="sys-info-value" style="color: var(--success);"><?php echo htmlspecialchars($phpVersion); ?></span>
                </div>
                <div class="sys-info-item">
                    <span class="sys-info-label">MySQL Version</span>
                    <span class="sys-info-value" style="color: var(--accent);"><?php echo htmlspecialchars($mysqlVersion); ?></span>
                </div>
                <div class="sys-info-item">
                    <span class="sys-info-label">Server Time</span>
                    <span class="sys-info-value"><?php echo htmlspecialchars($serverTime); ?></span>
                </div>
                <div class="sys-info-item">
                    <span class="sys-info-label">Memory Usage</span>
                    <span class="sys-info-value"><?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
