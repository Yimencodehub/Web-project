<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['admin','superadmin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js"></script>
    <style>
        :root {
            --bg: var(--bg-primary, #0f172a);
            --sidebar: var(--sidebar-bg, #111827);
            --card-bg: var(--bg-card, rgba(30, 41, 59, 0.8));
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --text-main: var(--text-primary, #f8fafc);
            --text-muted: var(--text-secondary, #94a3b8);
            --border: var(--border, #334155);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; transition: background 0.3s, color 0.3s; }
        
        .sidebar { width: 260px; background-color: var(--sidebar); height: 100vh; display: flex; flex-direction: column; border-right: 1px solid var(--border); }
        .sidebar-header { padding: 20px; font-size: 1.25rem; font-weight: bold; color: var(--primary); border-bottom: 1px solid var(--border); }
        .sidebar-nav { padding: 20px 0; flex-grow: 1; overflow-y: auto; }
        .sidebar-link { display: block; padding: 12px 20px; color: var(--text-muted); text-decoration: none; transition: 0.3s; }
        .sidebar-link:hover { background-color: var(--card-bg); color: var(--text-main); border-left: 3px solid var(--primary); }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .topbar { height: 64px; background-color: var(--bg); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; padding: 0 24px; }
        .topbar-right { display: flex; align-items: center; gap: 15px; }
        .role-badge { background: var(--primary); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .btn-logout { color: var(--danger); text-decoration: none; font-weight: 500; }
        
        .content { flex-grow: 1; padding: 24px; overflow-y: auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title { font-size: 1.5rem; font-weight: 600; }
        
        .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 20px; backdrop-filter: blur(10px); margin-bottom: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        .grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .stat-card { display: flex; flex-direction: column; border-top: 3px solid var(--primary); }
        .stat-card .value { font-size: 2rem; font-weight: 700; margin: 10px 0; color: var(--text-main); }
        .stat-card .label { color: var(--text-muted); font-size: 0.875rem; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { color: var(--text-muted); font-weight: 500; font-size: 0.875rem; text-transform: uppercase; }
        tr:hover { background-color: rgba(255,255,255,0.02); }
        
        .btn { display: inline-block; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; cursor: pointer; border: none; font-size: 0.875rem; transition: 0.2s; }
        .btn-primary { background-color: var(--primary); color: white; }
        .btn-primary:hover { background-color: var(--primary-hover); }
        .btn-success { background-color: var(--success); color: white; }
        .btn-danger { background-color: var(--danger); color: white; }
        .btn-warning { background-color: var(--accent); color: white; }
        .btn-info { background-color: #3b82f6; color: white; }
        .btn-sm { padding: 4px 8px; font-size: 0.75rem; }
        
        .badge { padding: 4px 8px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: var(--accent); }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .badge-info { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 8px; color: var(--text-muted); font-size: 0.875rem; }
        .form-control { width: 100%; padding: 10px 12px; background: rgba(15, 23, 42, 0.5); border: 1px solid var(--border); border-radius: 6px; color: var(--text-main); transition: 0.3s; }
        .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2); }
        select.form-control { appearance: none; }
        
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); }
        .alert-warning { background: rgba(245, 158, 11, 0.1); border: 1px solid var(--accent); color: var(--accent); }
        .alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); }
        
        .search-bar { display: flex; gap: 10px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><?= getSiteName($pdo) ?></div>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-link">Dashboard</a>
            <a href="books/index.php" class="sidebar-link">Books</a>
            <a href="categories/index.php" class="sidebar-link">Categories</a>
            <a href="members/index.php" class="sidebar-link">Members</a>
            <a href="issue/index.php" class="sidebar-link">Issue Book</a>
            <a href="renew/index.php" class="sidebar-link">Renew Book</a>
            <a href="returns/index.php" class="sidebar-link">Process Return</a>
            <a href="fines/index.php" class="sidebar-link">Fines</a>
            <a href="inventory/index.php" class="sidebar-link">Inventory</a>
            <a href="reports/index.php" class="sidebar-link">Reports</a>
            <a href="profile.php" class="sidebar-link">My Profile</a>
            <a href="users/index.php" class="sidebar-link">Users</a>
            <a href="../logout.php" class="sidebar-link" style="color: #ef4444; margin-top: 15px; border-top: 1px solid var(--border);"><i class="fas fa-sign-out-alt"></i> 🚪 Logout</a>
        </div>
    </div>
    
    <div class="main-wrapper">
        <div class="topbar">
            <div></div>
            <div class="topbar-right">
                <button type="button" class="theme-toggle-btn" onclick="toggleTheme()">☀️ Light Mode</button>
                <?= renderUserAvatar(32) ?>
                <span><?= htmlspecialchars($_SESSION["full_name"] ?? "Admin User") ?></span>
                <span class="role-badge"><?= htmlspecialchars($_SESSION["role"] ?? "Admin") ?></span>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
        <div class="content">
            <div class="page-header">
                <h1 class="page-title">Dashboard</h1>
                <div>
                    <a href="books/add.php" class="btn btn-primary">Add Book</a>
                    <a href="members/add.php" class="btn btn-success">Add Member</a>
                    <a href="issue/index.php" class="btn btn-warning">Issue Book</a>
                    <a href="returns/index.php" class="btn btn-info">Process Return</a>
                </div>
            </div>
            
            <?php
            try {
                $stats = [
                    'books' => $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn() ?? 0,
                    'members' => $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn() ?? 0,
                    'issues' => $pdo->query("SELECT COUNT(*) FROM book_issues WHERE status='issued'")->fetchColumn() ?? 0,
                    'overdue' => $pdo->query("SELECT COUNT(*) FROM book_issues WHERE status='issued' AND due_date < CURDATE()")->fetchColumn() ?? 0,
                    'pending_fines' => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM fines WHERE status='pending'")->fetchColumn() ?? 0,
                    'collected_fines' => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM fines WHERE status='paid'")->fetchColumn() ?? 0
                ];
            } catch(PDOException $e) {
                $stats = ['books'=>0, 'members'=>0, 'issues'=>0, 'overdue'=>0, 'pending_fines'=>0, 'collected_fines'=>0];
            }
            ?>
            
            <div class="grid-4">
                <div class="card stat-card"><div class="label">Total Books</div><div class="value counter"><?= $stats['books'] ?></div></div>
                <div class="card stat-card"><div class="label">Total Members</div><div class="value counter"><?= $stats['members'] ?></div></div>
                <div class="card stat-card"><div class="label">Books Issued</div><div class="value counter"><?= $stats['issues'] ?></div></div>
                <div class="card stat-card" style="border-top-color: var(--danger);"><div class="label">Overdue Books</div><div class="value counter"><?= $stats['overdue'] ?></div></div>
                <div class="card stat-card" style="border-top-color: var(--accent);"><div class="label">Pending Fines</div><div class="value">$<span class="counter"><?= number_format($stats['pending_fines'], 2) ?></span></div></div>
                <div class="card stat-card" style="border-top-color: var(--success);"><div class="label">Total Fines Collected</div><div class="value">$<span class="counter"><?= number_format($stats['collected_fines'], 2) ?></span></div></div>
            </div>
            
            <div class="card">
                <h3 style="margin-bottom: 16px;">Recent Issues</h3>
                <table>
                    <thead><tr><th>Member</th><th>Book</th><th>Issue Date</th><th>Due Date</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $pdo->query("
                                SELECT i.*, u.full_name, b.title 
                                FROM book_issues i
                                LEFT JOIN members m ON i.member_id = m.id
                                LEFT JOIN users u ON m.user_id = u.id
                                LEFT JOIN books b ON i.book_id = b.id
                                ORDER BY i.issue_date DESC LIMIT 10
                            ");
                            while($row = $stmt->fetch()) {
                                $status = $row['status'] == 'issued' ? 'badge-warning' : 'badge-success';
                                if ($row['status'] == 'issued' && $row['due_date'] < date('Y-m-d')) $status = 'badge-danger';
                                echo "<tr>
                                    <td>" . htmlspecialchars($row['full_name'] ?? 'Unknown') . "</td>
                                    <td>" . htmlspecialchars($row['title'] ?? 'Unknown') . "</td>
                                    <td>" . $row['issue_date'] . "</td>
                                    <td>" . $row['due_date'] . "</td>
                                    <td><span class='badge {$status}'>" . ucfirst($row['status']) . "</span></td>
                                </tr>";
                            }
                        } catch(Exception $e) {}
                        ?>
                    </tbody>
                </table>
            </div>
            
            <script>
                const counters = document.querySelectorAll('.counter');
                counters.forEach(counter => {
                    const updateCount = () => {
                        const target = +counter.innerText;
                        const count = +counter.getAttribute('data-count') || 0;
                        const inc = target / 20;
                        if(count < target) {
                            counter.setAttribute('data-count', count + inc);
                            counter.innerText = Math.ceil(count + inc);
                            setTimeout(updateCount, 50);
                        } else {
                            counter.innerText = target;
                        }
                    };
                    updateCount();
                });
            </script>
        </div>
    </div>
</body>
</html>
