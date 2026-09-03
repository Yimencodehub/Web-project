<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['staff','admin','superadmin']);

$today = date('Y-m-d');

// Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM book_issues WHERE DATE(issue_date) = ?");
$stmt->execute([$today]);
$issues_today = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM returns WHERE DATE(return_date) = ?");
$stmt->execute([$today]);
$returns_today = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending'");
$pending_res = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(amount) FROM fines WHERE status = 'pending'");
$active_fines = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) FROM book_issues WHERE status = 'issued' AND due_date < CURDATE()");
$overdue_count = $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Dashboard - Library System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #0f172a; --sidebar: #111827; --card-bg: rgba(30,41,59,0.8); --primary: #4f46e5; --accent: #f59e0b; --text: #f8fafc; --text-muted: #94a3b8; --border: #334155; --green: #10b981; --red: #ef4444; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: var(--sidebar); border-right: 1px solid var(--border); padding: 1.5rem; display: flex; flex-direction: column; }
        .sidebar h2 { color: var(--primary); margin-bottom: 2rem; font-size: 1.5rem; }
        .sidebar a { display: block; color: var(--text-muted); text-decoration: none; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s; }
        .sidebar a:hover, .sidebar a.active { background: var(--primary); color: white; }
        .main-content { flex: 1; padding: 2rem; overflow-y: auto; }
        .navbar { height: 64px; background: var(--sidebar); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: flex-end; padding: 0 2rem; position: sticky; top: 0; z-index: 10; margin: -2rem -2rem 2rem -2rem; }
        .card { background: var(--card-bg); backdrop-filter: blur(10px); border: 1px solid var(--border); border-radius: 1rem; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { text-align: center; padding: 1.5rem; border-radius: 1rem; background: var(--card-bg); border: 1px solid var(--border); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); border-color: var(--primary); }
        .stat-value { font-size: 2rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem; }
        .alert { background: rgba(239,68,68,0.1); color: var(--red); padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; border: 1px solid var(--red); }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>📚 Staff Panel</h2>
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="books.php">Search & Shelves (ST-04)</a>
        <a href="members.php">Member Info (ST-03)</a>
        <a href="checkout.php">Check Out (Issue)</a>
        <a href="checkin.php">Check In (Return)</a>
        <a href="fines.php">Collect Fines (ST-06)</a>
        <a href="reservations.php">Reservations</a>
        <a href="reports.php">Reports</a>
    </div>
    <div class="main-content">
        <div class="navbar">
            <span>Staff Portal | <a href="../logout.php" style="color:var(--red);">Logout</a></span>
        </div>
        
        <h1>Staff Dashboard</h1>
        <br>
        
        <?php if($overdue_count > 0): ?>
        <div class="alert">
            <strong>Attention:</strong> There are <?= $overdue_count ?> overdue books in the system.
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $issues_today ?></div>
                <div style="color: var(--text-muted)">Today's Check-outs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $returns_today ?></div>
                <div style="color: var(--text-muted)">Today's Returns</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--accent)"><?= $pending_res ?></div>
                <div style="color: var(--text-muted)">Pending Reservations</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--red)">$<?= number_format($active_fines, 2) ?></div>
                <div style="color: var(--text-muted)">Active Fines</div>
            </div>
        </div>

    </div>
</body>
</html>
