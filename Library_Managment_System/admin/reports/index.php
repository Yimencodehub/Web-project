<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../../config/db.php';
require_once '../../includes/functions.php';
requireRole(['admin','superadmin']);

$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate   = $_GET['end_date']   ?? date('Y-m-d');

// Summary counts
$totalBooks   = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn() ?: 0;
$totalMembers = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn() ?: 0;
$totalIssues  = $pdo->query("SELECT COUNT(*) FROM book_issues")->fetchColumn() ?: 0;
$totalReturns = $pdo->query("SELECT COUNT(*) FROM returns")->fetchColumn() ?: 0;
$totalFines   = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM fines WHERE status='paid'")->fetchColumn() ?: 0;

// Transactions in selected period
$stmt = $pdo->prepare("
    SELECT bi.id, b.title, u.full_name, m.member_id, bi.issue_date, bi.due_date, bi.status,
           r.return_date, r.fine_amount
    FROM book_issues bi
    JOIN books b ON bi.book_id = b.id
    JOIN members m ON bi.member_id = m.id
    JOIN users u ON m.user_id = u.id
    LEFT JOIN returns r ON r.issue_id = bi.id
    WHERE DATE(bi.issue_date) BETWEEN ? AND ?
    ORDER BY bi.issue_date DESC LIMIT 50
");
$stmt->execute([$startDate, $endDate]);
$transactions = $stmt->fetchAll();

// Overdue books
$stmt = $pdo->query("
    SELECT bi.id, b.title, u.full_name, m.member_id, bi.issue_date, bi.due_date,
           DATEDIFF(CURDATE(), bi.due_date) as days_overdue
    FROM book_issues bi
    JOIN books b ON bi.book_id = b.id
    JOIN members m ON bi.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE bi.status = 'issued' AND bi.due_date < CURDATE()
    ORDER BY days_overdue DESC
");
$overdueList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports — Admin Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --sidebar: #111827;
            --card-bg: rgba(30, 41, 59, 0.8);
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        
        .sidebar { width: 260px; background-color: var(--sidebar); height: 100vh; display: flex; flex-direction: column; border-right: 1px solid var(--border); }
        .sidebar-header { padding: 20px; font-size: 1.25rem; font-weight: bold; color: var(--primary); border-bottom: 1px solid var(--border); }
        .sidebar-nav { padding: 20px 0; flex-grow: 1; overflow-y: auto; }
        .sidebar-link { display: block; padding: 12px 20px; color: var(--text-muted); text-decoration: none; transition: 0.3s; }
        .sidebar-link:hover, .sidebar-link.active { background-color: var(--card-bg); color: var(--text-main); border-left: 3px solid var(--primary); }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .topbar { height: 64px; background-color: var(--bg); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; padding: 0 24px; }
        .topbar-right { display: flex; align-items: center; gap: 15px; }
        .role-badge { background: var(--primary); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .btn-logout { color: var(--danger); text-decoration: none; font-weight: 500; }
        
        .content { flex-grow: 1; padding: 24px; overflow-y: auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title { font-size: 1.5rem; font-weight: 600; }
        
        .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 20px; backdrop-filter: blur(10px); margin-bottom: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        .grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { display: flex; flex-direction: column; border-top: 3px solid var(--primary); }
        .stat-card .value { font-size: 1.8rem; font-weight: 700; margin: 8px 0; color: var(--text-main); }
        .stat-card .label { color: var(--text-muted); font-size: 0.82rem; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--border); }
        th { color: var(--text-muted); font-weight: 600; font-size: 0.78rem; text-transform: uppercase; }
        tr:hover { background-color: rgba(255,255,255,0.02); }
        
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; cursor: pointer; border: none; font-size: 0.875rem; transition: 0.2s; }
        .btn-primary { background-color: var(--primary); color: white; }
        .btn-primary:hover { background-color: var(--primary-hover); }
        
        .badge { padding: 4px 8px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: var(--accent); }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .badge-info { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        
        .date-form { display: flex; gap: 12px; align-items: flex-end; margin-bottom: 20px; flex-wrap: wrap; background: rgba(15,23,42,0.4); padding: 14px; border-radius: 8px; border: 1px solid var(--border); }
        .form-control { padding: 8px 12px; background: rgba(15, 23, 42, 0.8); border: 1px solid var(--border); border-radius: 6px; color: var(--text-main); outline: none; }
        .form-label { display: block; margin-bottom: 4px; color: var(--text-muted); font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><?= getSiteName($pdo) ?></div>
        <div class="sidebar-nav">
            <a href="../dashboard.php" class="sidebar-link">Dashboard</a>
            <a href="../books/index.php" class="sidebar-link">Books</a>
            <a href="../categories/index.php" class="sidebar-link">Categories</a>
            <a href="../members/index.php" class="sidebar-link">Members</a>
            <a href="../issue/index.php" class="sidebar-link">Issue Book</a>
            <a href="../returns/index.php" class="sidebar-link">Process Return</a>
            <a href="../fines/index.php" class="sidebar-link">Fines</a>
            <a href="../inventory/index.php" class="sidebar-link">Inventory</a>
            <a href="../reports/index.php" class="sidebar-link active">Reports</a>
            <a href="../users/index.php" class="sidebar-link">Users</a>
                    <a href="../../logout.php" class="sidebar-link" style="color: #ef4444; margin-top: 15px; border-top: 1px solid var(--border);"><i class="fas fa-sign-out-alt"></i> 🚪 Logout</a>
        </div>
    </div>
    <div class="main-wrapper">
        <div class="topbar">
            <div></div>
            <div class="topbar-right">
                <span><?= htmlspecialchars($_SESSION["full_name"] ?? "Admin User") ?></span>
                <span class="role-badge"><?= htmlspecialchars($_SESSION["role"] ?? "Admin") ?></span>
                <a href="../../logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
        <div class="content">
            <div class="page-header">
                <h1 class="page-title">📊 Activity & Inventory Reports</h1>
                <button onclick="window.print()" class="btn btn-primary">🖨️ Print Report</button>
            </div>

            <!-- Stats -->
            <div class="grid-4">
                <div class="card stat-card" style="border-top-color:#3b82f6;">
                    <div class="label">Total Books Titles</div>
                    <div class="value"><?= $totalBooks ?></div>
                </div>
                <div class="card stat-card" style="border-top-color:#10b981;">
                    <div class="label">Registered Members</div>
                    <div class="value"><?= $totalMembers ?></div>
                </div>
                <div class="card stat-card" style="border-top-color:#818cf8;">
                    <div class="label">Total Issues Processed</div>
                    <div class="value"><?= $totalIssues ?></div>
                </div>
                <div class="card stat-card" style="border-top-color:#f59e0b;">
                    <div class="label">Total Fines Collected</div>
                    <div class="value">$<?= number_format($totalFines, 2) ?></div>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="card">
                <h3 style="margin-bottom: 12px;">📅 Transactions Report (Date Filter)</h3>
                <form method="GET" class="date-form">
                    <div>
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                    </div>
                    <div>
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                </form>

                <?php if (empty($transactions)): ?>
                    <p style="color: var(--text-muted); padding: 10px 0;">No transactions found in the selected date range.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Issue #</th>
                                <th>Book Title</th>
                                <th>Member</th>
                                <th>Issue Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Return Date</th>
                                <th>Fine</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td>#<?= $tx['id'] ?></td>
                                <td><strong><?= htmlspecialchars($tx['title']) ?></strong></td>
                                <td><?= htmlspecialchars($tx['full_name']) ?> (<?= htmlspecialchars($tx['member_id']) ?>)</td>
                                <td><?= date('M d, Y', strtotime($tx['issue_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($tx['due_date'])) ?></td>
                                <td>
                                    <?php if ($tx['status'] === 'issued'): ?>
                                        <span class="badge badge-info">Issued</span>
                                    <?php elseif ($tx['status'] === 'returned'): ?>
                                        <span class="badge badge-success">Returned</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger"><?= htmlspecialchars($tx['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $tx['return_date'] ? date('M d, Y', strtotime($tx['return_date'])) : '—' ?></td>
                                <td><?= $tx['fine_amount'] ? '$' . number_format($tx['fine_amount'], 2) : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Overdue Alert Table -->
            <div class="card">
                <h3 style="margin-bottom: 12px; color: #f87171;">⚠️ Current Overdue Books List</h3>
                <?php if (empty($overdueList)): ?>
                    <p style="color: var(--success);">✅ There are currently no overdue books.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Issue #</th>
                                <th>Member Name</th>
                                <th>Member ID</th>
                                <th>Book Title</th>
                                <th>Due Date</th>
                                <th>Days Overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($overdueList as $od): ?>
                            <tr>
                                <td>#<?= $od['id'] ?></td>
                                <td><strong><?= htmlspecialchars($od['full_name']) ?></strong></td>
                                <td><?= htmlspecialchars($od['member_id']) ?></td>
                                <td><?= htmlspecialchars($od['title']) ?></td>
                                <td><?= date('M d, Y', strtotime($od['due_date'])) ?></td>
                                <td><span class="badge badge-danger">⚠️ <?= $od['days_overdue'] ?> days overdue</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
