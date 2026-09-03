<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['member']);

$user_id = $_SESSION['user_id'];

// Get member DB id
$stmt = $pdo->prepare("SELECT m.id as member_db_id FROM members m WHERE m.user_id = ?");
$stmt->execute([$user_id]);
$member_db_id = $stmt->fetchColumn() ?: 0;

$filter = $_GET['filter'] ?? 'all';

// Build query using correct columns
$query = "
    SELECT b.title, b.author, bi.issue_date, bi.due_date, bi.status,
           r.return_date, r.fine_amount
    FROM book_issues bi
    JOIN books b ON bi.book_id = b.id
    LEFT JOIN returns r ON r.issue_id = bi.id
    WHERE bi.member_id = ?
";
$params = [$member_db_id];

if ($filter === 'current')   $query .= " AND bi.status = 'issued'";
elseif ($filter === 'returned') $query .= " AND bi.status = 'returned'";
elseif ($filter === 'overdue')  $query .= " AND bi.status = 'issued' AND bi.due_date < CURDATE()";

$query .= " ORDER BY bi.issue_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$history = $stmt->fetchAll();

// Summary stats
$s1 = $pdo->prepare("SELECT COUNT(*) FROM book_issues WHERE member_id = ?"); $s1->execute([$member_db_id]);
$total = $s1->fetchColumn();
$s2 = $pdo->prepare("SELECT COUNT(*) FROM book_issues WHERE member_id = ? AND status = 'returned'"); $s2->execute([$member_db_id]);
$returned = $s2->fetchColumn();
$s3 = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fines WHERE member_id = ? AND status = 'paid'"); $s3->execute([$member_db_id]);
$fines_paid = $s3->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Borrow History - Library</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#0f172a;color:#f1f5f9;display:flex;min-height:100vh}
.sidebar{width:260px;background:#111827;min-height:100vh;padding:0;position:fixed;left:0;top:0;bottom:0;overflow-y:auto;z-index:100}
.sidebar-logo{padding:24px 20px;border-bottom:1px solid rgba(255,255,255,0.07)}
.sidebar-logo h2{font-size:1.1rem;font-weight:700;color:#4f46e5}
.sidebar-logo p{font-size:.75rem;color:#64748b;margin-top:2px}
.sidebar-nav{padding:16px 12px}
.sidebar-nav a{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;color:#94a3b8;text-decoration:none;font-size:.875rem;font-weight:500;margin-bottom:4px;transition:all .2s}
.sidebar-nav a:hover,.sidebar-nav a.active{background:rgba(79,70,229,.15);color:#818cf8}
.main{margin-left:260px;flex:1;display:flex;flex-direction:column}
.navbar{height:64px;background:#1e293b;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;justify-content:space-between;padding:0 28px;position:sticky;top:0;z-index:50}
.navbar-left{font-size:1.1rem;font-weight:600}
.navbar-right{display:flex;align-items:center;gap:14px}
.badge{padding:4px 12px;border-radius:20px;font-size:.7rem;font-weight:700;background:rgba(59,130,246,.2);color:#60a5fa}
.logout-btn{padding:8px 16px;background:rgba(239,68,68,.15);color:#f87171;border:none;border-radius:8px;font-size:.8rem;cursor:pointer;text-decoration:none}
.content{padding:28px;flex:1}
.page-header{margin-bottom:24px}
.page-header h1{font-size:1.5rem;font-weight:700}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px}
.stat-card{background:rgba(30,41,59,.8);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:18px}
.stat-num{font-size:1.8rem;font-weight:700;color:#f1f5f9}
.stat-label{font-size:.78rem;color:#64748b;margin-top:4px}
.filters{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap}
.filter-btn{padding:8px 18px;border-radius:8px;border:1px solid rgba(255,255,255,.1);background:transparent;color:#94a3b8;font-size:.82rem;cursor:pointer;text-decoration:none;transition:all .2s}
.filter-btn:hover,.filter-btn.active{background:rgba(79,70,229,.2);color:#818cf8;border-color:#4f46e5}
.card{background:rgba(30,41,59,.8);border:1px solid rgba(255,255,255,.07);border-radius:16px;overflow:hidden}
table{width:100%;border-collapse:collapse}
th{padding:12px 16px;text-align:left;font-size:.73rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid rgba(255,255,255,.07)}
td{padding:13px 16px;font-size:.875rem;color:#cbd5e1;border-bottom:1px solid rgba(255,255,255,.04)}
tr:hover td{background:rgba(255,255,255,.02)}
.sbadge{padding:4px 10px;border-radius:20px;font-size:.72rem;font-weight:600}
.issued{background:rgba(59,130,246,.15);color:#60a5fa}
.returned{background:rgba(16,185,129,.15);color:#34d399}
.overdue{background:rgba(239,68,68,.15);color:#f87171}
.empty{padding:48px;text-align:center;color:#475569}
</style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo"><h2>📚 <?= getSiteName($pdo) ?></h2><p>Member Portal</p></div>
    <nav class="sidebar-nav">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="search.php">🔍 Search Books</a>
        <a href="borrow_history.php" class="active">📋 My Borrows</a>
        <a href="due_dates.php">📅 Due Dates</a>
        <a href="fines.php">💰 My Fines</a>
        <a href="reservations.php">🔖 Reservations</a>
        <a href="profile.php">👤 Profile</a>
        <a href="change_password.php">🔒 Change Password</a>
        <a href="../logout.php">🚪 Logout</a>
    </nav>
</aside>
<div class="main">
    <nav class="navbar">
        <div class="navbar-left">Borrow History</div>
        <div class="navbar-right">
            <span><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></span>
            <span class="badge">MEMBER</span>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>
    <div class="content">
        <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h1>📋 My Borrow History</h1>
            <button onclick="window.print()" class="filter-btn" style="background:#4f46e5; color:white; border:none; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                🖨️ Print History (ME-06)
            </button>
        </div>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-num"><?= $total ?></div><div class="stat-label">Total Borrowed</div></div>
            <div class="stat-card"><div class="stat-num"><?= $returned ?></div><div class="stat-label">Returned</div></div>
            <div class="stat-card"><div class="stat-num">$<?= number_format($fines_paid,2) ?></div><div class="stat-label">Fines Paid</div></div>
        </div>
        <div class="filters">
            <a href="?filter=all" class="filter-btn <?= $filter==='all'?'active':'' ?>">All</a>
            <a href="?filter=current" class="filter-btn <?= $filter==='current'?'active':'' ?>">Current</a>
            <a href="?filter=returned" class="filter-btn <?= $filter==='returned'?'active':'' ?>">Returned</a>
            <a href="?filter=overdue" class="filter-btn <?= $filter==='overdue'?'active':'' ?>">Overdue</a>
        </div>
        <div class="card">
            <?php if(empty($history)): ?>
            <div class="empty">📭 No records found.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Book Title</th><th>Author</th><th>Issue Date</th><th>Due Date</th><th>Return Date</th><th>Status</th><th>Fine</th></tr></thead>
                <tbody>
                <?php foreach($history as $row):
                    $st = $row['status'];
                    if($st==='issued' && $row['due_date'] < date('Y-m-d')) $st='overdue';
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                    <td><?= htmlspecialchars($row['author']) ?></td>
                    <td><?= date('M d, Y',strtotime($row['issue_date'])) ?></td>
                    <td><?= date('M d, Y',strtotime($row['due_date'])) ?></td>
                    <td><?= $row['return_date'] ? date('M d, Y',strtotime($row['return_date'])) : '-' ?></td>
                    <td><span class="sbadge <?= $st ?>"><?= ucfirst($st) ?></span></td>
                    <td><?= $row['fine_amount'] ? '$'.number_format($row['fine_amount'],2) : '-' ?></td>
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
