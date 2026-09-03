<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['superadmin']);

$siteName = getSiteName($pdo);

// Date filters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate   = $_GET['end_date']   ?? date('Y-m-d');
$activeTab = $_GET['tab']        ?? 'summary';

// ── 1. SUMMARY TAB DATA ──
// Issues in date range
$stmt = $pdo->prepare("SELECT COUNT(*) FROM book_issues WHERE DATE(issue_date) BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$summaryIssuesCount = $stmt->fetchColumn() ?: 0;

// Returns in date range
$stmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(fine_amount),0) FROM returns WHERE DATE(return_date) BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$retData = $stmt->fetch();
$summaryReturnsCount = $retData[0] ?: 0;
$summaryFinesCollected = $retData[1] ?: 0;

// New members in date range
$stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE DATE(join_date) BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$summaryNewMembers = $stmt->fetchColumn() ?: 0;

// Transactions list in range
$stmt = $pdo->prepare("
    SELECT bi.id, b.title, u.full_name, bi.issue_date, bi.due_date, bi.status,
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
$summaryTransactions = $stmt->fetchAll();

// ── 2. BOOK STATS DATA ──
// Most borrowed books
$stmt = $pdo->query("
    SELECT b.id, b.title, b.author, b.isbn, c.name as category_name, b.total_copies, b.available_copies,
           COUNT(bi.id) as borrow_count
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.id
    LEFT JOIN book_issues bi ON b.id = bi.book_id
    GROUP BY b.id
    ORDER BY borrow_count DESC, b.title ASC
    LIMIT 10
");
$mostBorrowed = $stmt->fetchAll();

// Category breakdown
$stmt = $pdo->query("
    SELECT c.name, COUNT(b.id) as book_count, COALESCE(SUM(b.total_copies),0) as total_copies
    FROM categories c
    LEFT JOIN books b ON c.id = b.category_id
    GROUP BY c.id
    ORDER BY book_count DESC
");
$categoryStats = $stmt->fetchAll();

// ── 3. MEMBER ACTIVITY DATA ──
// Top active members
$stmt = $pdo->query("
    SELECT m.member_id, u.full_name, u.email, m.membership_type, m.join_date,
           COUNT(bi.id) as total_borrows
    FROM members m
    JOIN users u ON m.user_id = u.id
    LEFT JOIN book_issues bi ON m.id = bi.member_id
    GROUP BY m.id
    ORDER BY total_borrows DESC
    LIMIT 10
");
$topMembers = $stmt->fetchAll();

// Members with overdue books
$stmt = $pdo->query("
    SELECT m.member_id, u.full_name, b.title, bi.issue_date, bi.due_date,
           DATEDIFF(CURDATE(), bi.due_date) as days_overdue
    FROM book_issues bi
    JOIN members m ON bi.member_id = m.id
    JOIN users u ON m.user_id = u.id
    JOIN books b ON bi.book_id = b.id
    WHERE bi.status = 'issued' AND bi.due_date < CURDATE()
    ORDER BY days_overdue DESC
");
$overdueMembers = $stmt->fetchAll();

// ── 4. FINE COLLECTION DATA ──
// Last 6 months fines
$monthlyFines = [];
for ($i = 5; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $monthEnd   = date('Y-m-t', strtotime("-$i months"));
    $monthName  = date('M Y', strtotime("-$i months"));

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(fine_amount),0) FROM returns WHERE return_date BETWEEN ? AND ?");
    $stmt->execute([$monthStart, $monthEnd]);
    $amt = (float)$stmt->fetchColumn();
    $monthlyFines[$monthName] = $amt;
}
$maxFineVal = max(array_values($monthlyFines)) ?: 100;

// Recent fines list
$stmt = $pdo->query("
    SELECT f.*, u.full_name, m.member_id, b.title as book_title
    FROM fines f
    JOIN members m ON f.member_id = m.id
    JOIN users u ON m.user_id = u.id
    LEFT JOIN book_issues bi ON f.issue_id = bi.id
    LEFT JOIN books b ON bi.book_id = b.id
    ORDER BY f.created_at DESC LIMIT 20
");
$finesList = $stmt->fetchAll();

// ── 5. STAFF ACTIVITY DATA ──
$stmt = $pdo->query("
    SELECT u.id, u.full_name, u.username, u.role,
           (SELECT COUNT(*) FROM book_issues WHERE issued_by = u.id) as issues_processed,
           (SELECT COUNT(*) FROM returns WHERE returned_by = u.id) as returns_processed
    FROM users u
    WHERE u.role IN ('staff', 'admin', 'superadmin')
    ORDER BY issues_processed DESC, returns_processed DESC
");
$staffActivity = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Reports — <?= htmlspecialchars($siteName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #0f172a;
            --sidebar: #111827;
            --card-bg: rgba(30, 41, 59, 0.85);
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg); color: var(--text-main); display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background-color: var(--sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; position: fixed; left: 0; top: 0; bottom: 0; z-index: 100; }
        .sidebar-header { padding: 24px 20px; text-align: center; border-bottom: 1px solid var(--border); }
        .sidebar-header h2 { font-size: 1.15rem; color: #4f46e5; font-weight: 700; }
        .nav-links { list-style: none; padding: 16px 12px; flex: 1; overflow-y: auto; }
        .nav-links li { margin-bottom: 4px; }
        .nav-links a { display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--text-muted); padding: 11px 16px; border-radius: 10px; font-size: 0.88rem; font-weight: 500; transition: all 0.2s; }
        .nav-links a:hover, .nav-links a.active { background: rgba(79, 70, 229, 0.15); color: #818cf8; }
        
        .main-content { margin-left: 260px; flex: 1; padding: 32px; overflow-y: auto; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
        .topbar h1 { font-size: 1.6rem; font-weight: 700; }
        
        .card { background: var(--card-bg); backdrop-filter: blur(14px); border-radius: 16px; border: 1px solid var(--border); padding: 24px; margin-bottom: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        
        /* Tab Nav */
        .tabs { display: flex; gap: 10px; border-bottom: 1px solid var(--border); padding-bottom: 14px; margin-bottom: 22px; flex-wrap: wrap; }
        .tab-btn { background: rgba(15,23,42,0.6); border: 1px solid var(--border); color: var(--text-muted); padding: 9px 18px; border-radius: 8px; cursor: pointer; font-size: 0.875rem; font-weight: 500; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .tab-btn.active, .tab-btn:hover { background: rgba(79, 70, 229, 0.2); color: #818cf8; border-color: var(--primary); }
        
        .tab-content { display: none; animation: fadeIn .3s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Buttons & Forms */
        .btn { padding: 9px 18px; border-radius: 8px; border: none; cursor: pointer; font-size: 0.875rem; font-weight: 600; color: white; transition: all 0.2s; display: inline-flex; gap: 8px; align-items: center; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #4f46e5, #7c3aed); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(79,70,229,0.4); }
        
        .date-form { display: flex; gap: 14px; margin-bottom: 22px; align-items: flex-end; flex-wrap: wrap; background: rgba(15, 23, 42, 0.5); padding: 16px; border-radius: 12px; border: 1px solid var(--border); }
        .form-control { background: #0f172a; border: 1px solid var(--border); padding: 9px 14px; border-radius: 8px; color: white; font-size: 0.875rem; outline: none; }
        .form-control:focus { border-color: var(--primary); }
        .form-label { display: block; font-size: 0.8rem; font-weight: 500; color: var(--text-muted); margin-bottom: 6px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border); border-radius: 14px; padding: 20px; position: relative; overflow: hidden; }
        .stat-card::before { content:''; position: absolute; top:0; left:0; right:0; height: 3px; }
        .stat-card.blue::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
        .stat-card.green::before { background: linear-gradient(90deg, #10b981, #34d399); }
        .stat-card.amber::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .stat-card.indigo::before { background: linear-gradient(90deg, #4f46e5, #818cf8); }
        .stat-val { font-size: 1.8rem; font-weight: 700; color: var(--text-main); margin-top: 6px; }
        .stat-lbl { font-size: 0.8rem; color: var(--text-muted); }
        
        /* Tables */
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { padding: 12px 14px; text-align: left; font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border); }
        td { padding: 13px 14px; font-size: 0.875rem; color: #cbd5e1; border-bottom: 1px solid rgba(255,255,255,0.03); }
        tr:hover td { background: rgba(255,255,255,0.02); }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; }
        .badge-success { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .badge-info { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        
        /* Bar Chart */
        .chart-container { height: 260px; display: flex; align-items: flex-end; gap: 20px; padding: 24px 10px 10px; border-bottom: 1px solid var(--border); margin: 20px 0; }
        .bar-wrapper { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; position: relative; }
        .bar { width: 44px; background: linear-gradient(180deg, #f59e0b, #d97706); border-radius: 6px 6px 0 0; transition: height 0.6s ease; position: relative; min-height: 4px; }
        .bar-label { margin-top: 10px; color: var(--text-muted); font-size: 0.78rem; text-align: center; }
        .bar-val { position: absolute; top: -24px; font-size: 0.78rem; font-weight: 700; width: 100%; text-align: center; color: #fbbf24; }

        @media print {
            .sidebar, .tabs, .date-form, .topbar button { display: none !important; }
            .main-content { margin-left: 0; padding: 0; }
            body { background: white; color: black; }
            .card { border: none; box-shadow: none; padding: 0; }
            th, td { color: black !important; border-color: #ddd !important; }
            .tab-content { display: block !important; margin-bottom: 30px; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>📚 <?= htmlspecialchars($siteName) ?></h2>
            <p style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">Super Admin Portal</p>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="admins.php"><i class="fas fa-users-cog"></i> Admins & Staff</a></li>
            <li><a href="settings.php"><i class="fas fa-cogs"></i> System Settings</a></li>
            <li><a href="backup.php"><i class="fas fa-database"></i> Backup & Restore</a></li>
            <li><a href="audit_logs.php"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
            <li><a href="reports.php" class="active"><i class="fas fa-chart-pie"></i> Advanced Reports</a></li>
            <li><a href="fine_config.php"><i class="fas fa-money-bill-wave"></i> Fine Config</a></li>
            <li><a href="../logout.php" style="color: var(--danger);"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="topbar">
            <h1>📊 Advanced Reports</h1>
            <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print Report</button>
        </div>
        
        <div class="card">
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn <?= $activeTab==='summary'?'active':'' ?>" onclick="switchTab('summary')"><i class="fas fa-chart-line"></i> Summary</button>
                <button class="tab-btn <?= $activeTab==='books'?'active':'' ?>" onclick="switchTab('books')"><i class="fas fa-book"></i> Book Stats</button>
                <button class="tab-btn <?= $activeTab==='members'?'active':'' ?>" onclick="switchTab('members')"><i class="fas fa-users"></i> Member Activity</button>
                <button class="tab-btn <?= $activeTab==='fines'?'active':'' ?>" onclick="switchTab('fines')"><i class="fas fa-hand-holding-usd"></i> Fine Collection</button>
                <button class="tab-btn <?= $activeTab==='staff'?'active':'' ?>" onclick="switchTab('staff')"><i class="fas fa-user-tie"></i> Staff Activity</button>
            </div>
            
            <!-- Date Filter Form -->
            <form method="GET" class="date-form" id="filterForm">
                <input type="hidden" name="tab" id="activeTabInput" value="<?= htmlspecialchars($activeTab) ?>">
                <div>
                    <label class="form-label"><i class="far fa-calendar-alt"></i> Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div>
                    <label class="form-label"><i class="far fa-calendar-alt"></i> End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Generate Report</button>
            </form>
            
            <!-- TAB 1: SUMMARY -->
            <div id="tab-summary" class="tab-content <?= $activeTab==='summary'?'active':'' ?>">
                <h3 style="margin-bottom: 16px;">📈 System Activity Summary (<?= date('M d, Y', strtotime($startDate)) ?> – <?= date('M d, Y', strtotime($endDate)) ?>)</h3>
                
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-lbl">Books Issued</div>
                        <div class="stat-val"><?= $summaryIssuesCount ?></div>
                    </div>
                    <div class="stat-card green">
                        <div class="stat-lbl">Books Returned</div>
                        <div class="stat-val"><?= $summaryReturnsCount ?></div>
                    </div>
                    <div class="stat-card amber">
                        <div class="stat-lbl">Fines Collected</div>
                        <div class="stat-val">$<?= number_format($summaryFinesCollected, 2) ?></div>
                    </div>
                    <div class="stat-card indigo">
                        <div class="stat-lbl">New Members</div>
                        <div class="stat-val"><?= $summaryNewMembers ?></div>
                    </div>
                </div>

                <h4 style="margin: 24px 0 10px; font-size: 1rem;">Recent Transactions in Period</h4>
                <?php if (empty($summaryTransactions)): ?>
                    <p style="color: var(--text-muted); padding: 20px 0;">No transactions recorded in this date range.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Book Title</th>
                                <th>Member Name</th>
                                <th>Issue Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Return Date</th>
                                <th>Fine</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summaryTransactions as $tx): ?>
                            <tr>
                                <td>#<?= $tx['id'] ?></td>
                                <td><strong><?= htmlspecialchars($tx['title']) ?></strong></td>
                                <td><?= htmlspecialchars($tx['full_name']) ?></td>
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
            
            <!-- TAB 2: BOOK STATS -->
            <div id="tab-books" class="tab-content <?= $activeTab==='books'?'active':'' ?>">
                <h3 style="margin-bottom: 16px;">📚 Top 10 Most Borrowed Books</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>ISBN</th>
                            <th>Total Copies</th>
                            <th>Available</th>
                            <th>Times Borrowed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mostBorrowed as $b): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($b['title']) ?></strong></td>
                            <td><?= htmlspecialchars($b['author']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($b['category_name'] ?? 'General') ?></span></td>
                            <td><?= htmlspecialchars($b['isbn'] ?? '—') ?></td>
                            <td><?= $b['total_copies'] ?></td>
                            <td><?= $b['available_copies'] ?></td>
                            <td><strong style="color: #818cf8;"><?= $b['borrow_count'] ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3 style="margin: 32px 0 16px;">📂 Inventory by Category</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Distinct Titles</th>
                            <th>Total Stock Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categoryStats as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                            <td><?= $c['book_count'] ?> titles</td>
                            <td><?= $c['total_copies'] ?> copies</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- TAB 3: MEMBERS -->
            <div id="tab-members" class="tab-content <?= $activeTab==='members'?'active':'' ?>">
                <h3 style="margin-bottom: 16px;">👥 Most Active Members</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Member ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Join Date</th>
                            <th>Total Books Borrowed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topMembers as $m): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($m['member_id']) ?></strong></td>
                            <td><?= htmlspecialchars($m['full_name']) ?></td>
                            <td><?= htmlspecialchars($m['email']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($m['membership_type']) ?></span></td>
                            <td><?= date('M d, Y', strtotime($m['join_date'])) ?></td>
                            <td><strong style="color: #34d399;"><?= $m['total_borrows'] ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3 style="margin: 32px 0 16px; color: #f87171;">⚠️ Members with Overdue Books</h3>
                <?php if (empty($overdueMembers)): ?>
                    <p style="color: #34d399;">✅ Great! There are no overdue books at this moment.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Member ID</th>
                                <th>Member Name</th>
                                <th>Book Title</th>
                                <th>Due Date</th>
                                <th>Days Overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($overdueMembers as $om): ?>
                            <tr>
                                <td><?= htmlspecialchars($om['member_id']) ?></td>
                                <td><strong><?= htmlspecialchars($om['full_name']) ?></strong></td>
                                <td><?= htmlspecialchars($om['title']) ?></td>
                                <td><?= date('M d, Y', strtotime($om['due_date'])) ?></td>
                                <td><span class="badge badge-danger">⚠️ <?= $om['days_overdue'] ?> days late</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- TAB 4: FINE COLLECTION -->
            <div id="tab-fines" class="tab-content <?= $activeTab==='fines'?'active':'' ?>">
                <h3 style="margin-bottom: 10px;">💰 Fine Collection History (Last 6 Months)</h3>
                <div class="chart-container">
                    <?php 
                    foreach ($monthlyFines as $mLabel => $mVal):
                        $h = $maxFineVal > 0 ? min(100, max(5, ($mVal / $maxFineVal) * 100)) : 5;
                    ?>
                    <div class="bar-wrapper">
                        <div class="bar" style="height: <?= $h ?>%;">
                            <span class="bar-val">$<?= number_format($mVal, 0) ?></span>
                        </div>
                        <span class="bar-label"><?= $mLabel ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <h3 style="margin: 32px 0 16px;">📋 Recent Fine Records</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Book</th>
                            <th>Amount</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($finesList as $f): ?>
                        <tr>
                            <td><?= htmlspecialchars($f['full_name']) ?> (<?= htmlspecialchars($f['member_id']) ?>)</td>
                            <td><?= htmlspecialchars($f['book_title'] ?? '—') ?></td>
                            <td><strong>$<?= number_format($f['amount'], 2) ?></strong></td>
                            <td><?= htmlspecialchars($f['reason'] ?? 'Overdue fine') ?></td>
                            <td>
                                <?php if ($f['status'] === 'paid'): ?>
                                    <span class="badge badge-success">Paid</span>
                                <?php elseif ($f['status'] === 'waived'): ?>
                                    <span class="badge badge-info">Waived</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($f['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- TAB 5: STAFF ACTIVITY -->
            <div id="tab-staff" class="tab-content <?= $activeTab==='staff'?'active':'' ?>">
                <h3 style="margin-bottom: 16px;">👔 Staff & Admin Transaction Processing Activity</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Username</th>
                            <th>System Role</th>
                            <th>Books Issued</th>
                            <th>Returns Processed</th>
                            <th>Total Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staffActivity as $st): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($st['full_name']) ?></strong></td>
                            <td><?= htmlspecialchars($st['username']) ?></td>
                            <td><span class="badge badge-info"><?= strtoupper($st['role']) ?></span></td>
                            <td><?= $st['issues_processed'] ?></td>
                            <td><?= $st['returns_processed'] ?></td>
                            <td><strong style="color: #818cf8;"><?= $st['issues_processed'] + $st['returns_processed'] ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
    
    <script>
        function switchTab(id) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            const target = document.getElementById('tab-' + id);
            if (target) target.classList.add('active');
            
            event.currentTarget.classList.add('active');
            document.getElementById('activeTabInput').value = id;
        }
    </script>
</body>
</html>
