<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['member']);

$user_id = $_SESSION['user_id'];

// Get member record (members.id is what book_issues uses)
$stmt = $pdo->prepare("SELECT u.*, m.id as member_db_id, m.member_id, m.membership_type, m.join_date, m.expiry_date FROM users u LEFT JOIN members m ON u.id = m.user_id WHERE u.id = ?");
$stmt->execute([$user_id]);
$member = $stmt->fetch();
$member_db_id = $member['member_db_id'] ?? 0;

// Stats — book_issues.member_id = members.id
$total_borrowed = 0; $currently_borrowed = 0; $overdue_books = 0; $pending_fines = 0;

if ($member_db_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM book_issues WHERE member_id = ?");
    $stmt->execute([$member_db_id]);
    $total_borrowed = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM book_issues WHERE member_id = ? AND status = 'issued'");
    $stmt->execute([$member_db_id]);
    $currently_borrowed = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM book_issues WHERE member_id = ? AND status = 'issued' AND due_date < CURDATE()");
    $stmt->execute([$member_db_id]);
    $overdue_books = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fines WHERE member_id = ? AND status = 'pending'");
    $stmt->execute([$member_db_id]);
    $pending_fines = $stmt->fetchColumn();

    // Currently borrowed books
    $stmt = $pdo->prepare("
        SELECT bi.*, b.title, b.author
        FROM book_issues bi
        JOIN books b ON bi.book_id = b.id
        WHERE bi.member_id = ? AND bi.status = 'issued'
        ORDER BY bi.due_date ASC
    ");
    $stmt->execute([$member_db_id]);
    $borrowed = $stmt->fetchAll();
} else {
    $borrowed = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Member Dashboard - <?= getSiteName($pdo) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<script src="../assets/js/main.js"></script>
<style>
:root {
    --bg: var(--bg-primary, #0f172a);
    --sidebar: var(--sidebar-bg, #111827);
    --card-bg: var(--bg-card, rgba(30,41,59,0.8));
    --primary: #4f46e5;
    --text: var(--text-primary, #f1f5f9);
    --text-muted: var(--text-secondary, #64748b);
    --border: var(--border, rgba(255,255,255,0.07));
}
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); display:flex; min-height:100vh; transition: background 0.3s, color 0.3s; }
/* Sidebar */
.sidebar { width:260px; background:var(--sidebar); min-height:100vh; padding:0; position:fixed; left:0; top:0; bottom:0; overflow-y:auto; z-index:100; border-right:1px solid var(--border); }
.sidebar-logo { padding:24px 20px; border-bottom:1px solid var(--border); }
.sidebar-logo h2 { font-size:1.1rem; font-weight:700; color:#4f46e5; }
.sidebar-logo p { font-size:0.75rem; color:var(--text-muted); margin-top:2px; }
.sidebar-nav { padding:16px 12px; }
.sidebar-nav a { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:var(--text-muted); text-decoration:none; font-size:0.875rem; font-weight:500; margin-bottom:4px; transition:all 0.2s; }
.sidebar-nav a:hover, .sidebar-nav a.active { background:rgba(79,70,229,0.15); color:#818cf8; }
.sidebar-nav a .icon { font-size:1.1rem; width:20px; text-align:center; }
/* Top navbar */
.main { margin-left:260px; flex:1; display:flex; flex-direction:column; }
.navbar { height:64px; background:var(--sidebar); border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; padding:0 28px; position:sticky; top:0; z-index:50; }
.navbar-left { font-size:1.1rem; font-weight:600; color:var(--text); }
.navbar-right { display:flex; align-items:center; gap:14px; }
.badge { padding:4px 12px; border-radius:20px; font-size:0.7rem; font-weight:700; letter-spacing:.5px; text-transform:uppercase; background:rgba(59,130,246,0.2); color:#60a5fa; }
.logout-btn { padding:8px 16px; background:rgba(239,68,68,0.15); color:#f87171; border:none; border-radius:8px; font-size:0.8rem; cursor:pointer; text-decoration:none; font-family:'Inter',sans-serif; }
/* Content */
.content { padding:28px; flex:1; }
.page-header { margin-bottom:28px; }
.page-header h1 { font-size:1.6rem; font-weight:700; color:var(--text); }
.page-header p { color:var(--text-muted); margin-top:4px; font-size:0.9rem; }
/* No member card */
.alert-card { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); border-radius:14px; padding:20px 24px; color:#f87171; margin-bottom:24px; }
/* Stats */
.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:18px; margin-bottom:28px; }
.stat-card { background:var(--card-bg); border:1px solid var(--border); border-radius:16px; padding:22px; position:relative; overflow:hidden; }
.stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
.stat-card.blue::before { background:linear-gradient(90deg,#3b82f6,#60a5fa); }
.stat-card.indigo::before { background:linear-gradient(90deg,#4f46e5,#818cf8); }
.stat-card.red::before { background:linear-gradient(90deg,#ef4444,#f87171); }
.stat-card.amber::before { background:linear-gradient(90deg,#f59e0b,#fbbf24); }
.stat-icon { font-size:1.6rem; margin-bottom:10px; }
.stat-num { font-size:2rem; font-weight:700; color:var(--text); }
.stat-label { font-size:0.8rem; color:var(--text-muted); margin-top:4px; }
/* Table */
.card { background:var(--card-bg); border:1px solid var(--border); border-radius:16px; overflow:hidden; margin-bottom:24px; }
.card-header { padding:18px 24px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.card-header h3 { font-size:1rem; font-weight:600; color:var(--text); }
table { width:100%; border-collapse:collapse; }
th { padding:12px 16px; text-align:left; font-size:0.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid var(--border); }
td { padding:14px 16px; font-size:0.875rem; color:var(--text); border-bottom:1px solid var(--border); }
tr:hover td { background:rgba(255,255,255,0.02); }
.status-badge { padding:4px 10px; border-radius:20px; font-size:0.72rem; font-weight:600; }
.status-issued { background:rgba(59,130,246,0.15); color:#60a5fa; }
.status-overdue { background:rgba(239,68,68,0.15); color:#f87171; }
.status-ok { background:rgba(16,185,129,0.15); color:#34d399; }
.days-badge { padding:4px 10px; border-radius:20px; font-size:0.72rem; font-weight:600; }
.empty-state { padding:48px; text-align:center; color:var(--text-muted); }
.empty-state .emoji { font-size:2.5rem; margin-bottom:12px; }
.quick-links { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; margin-bottom:28px; }
.quick-link { background:var(--card-bg); border:1px solid var(--border); border-radius:14px; padding:18px; text-align:center; text-decoration:none; color:var(--text-muted); transition:all 0.2s; }
.quick-link:hover { border-color:#4f46e5; color:#818cf8; transform:translateY(-2px); }
.quick-link .ql-icon { font-size:1.8rem; margin-bottom:8px; }
.quick-link span { font-size:0.82rem; font-weight:500; display:block; }
</style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo">
        <h2>📚 <?= getSiteName($pdo) ?></h2>
        <p>Member Portal</p>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="active"><span class="icon">🏠</span> Dashboard</a>
        <a href="search.php"><span class="icon">🔍</span> Search Books</a>
        <a href="borrow_history.php"><span class="icon">📋</span> My Borrows</a>
        <a href="due_dates.php"><span class="icon">📅</span> Due Dates</a>
        <a href="fines.php"><span class="icon">💰</span> My Fines</a>
        <a href="reservations.php"><span class="icon">🔖</span> Reservations</a>
        <a href="profile.php"><span class="icon">👤</span> Profile</a>
        <a href="change_password.php"><span class="icon">🔒</span> Change Password</a>
        <a href="../logout.php"><span class="icon">🚪</span> Logout</a>
    </nav>
</aside>

<div class="main">
    <nav class="navbar">
        <div class="navbar-left">Member Dashboard</div>
        <div class="navbar-right">
            <button type="button" class="theme-toggle-btn" onclick="toggleTheme()">☀️ Light Mode</button>
            <?= renderUserAvatar(32) ?>
            <span><?= htmlspecialchars($_SESSION['full_name'] ?? 'Member') ?></span>
            <span class="badge">MEMBER</span>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="content">
        <div class="page-header">
            <h1>Welcome back, <?= htmlspecialchars($member['full_name'] ?? $_SESSION['full_name']) ?>! 👋</h1>
            <p><?= $member['member_id'] ? 'Member ID: <strong>' . htmlspecialchars($member['member_id']) . '</strong> &bull; ' . htmlspecialchars($member['membership_type'] ?? 'Standard') . ' Membership' : 'Your member profile is being set up.' ?></p>
        </div>

        <?php if (!$member_db_id): ?>
        <div class="alert-card">
            ⚠️ Your member profile has not been fully set up yet. Please contact the library admin to complete your registration.
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon">📚</div>
                <div class="stat-num"><?= $total_borrowed ?></div>
                <div class="stat-label">Total Borrowed</div>
            </div>
            <div class="stat-card indigo">
                <div class="stat-icon">📖</div>
                <div class="stat-num"><?= $currently_borrowed ?></div>
                <div class="stat-label">Currently Borrowed</div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon">⚠️</div>
                <div class="stat-num"><?= $overdue_books ?></div>
                <div class="stat-label">Overdue Books</div>
            </div>
            <div class="stat-card amber">
                <div class="stat-icon">💰</div>
                <div class="stat-num">$<?= number_format($pending_fines, 2) ?></div>
                <div class="stat-label">Pending Fines</div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="quick-links">
            <a href="search.php" class="quick-link"><div class="ql-icon">🔍</div><span>Search Books</span></a>
            <a href="due_dates.php" class="quick-link"><div class="ql-icon">📅</div><span>Due Dates</span></a>
            <a href="fines.php" class="quick-link"><div class="ql-icon">💰</div><span>View Fines</span></a>
            <a href="reservations.php" class="quick-link"><div class="ql-icon">🔖</div><span>Reservations</span></a>
            <a href="profile.php" class="quick-link"><div class="ql-icon">👤</div><span>My Profile</span></a>
        </div>

        <!-- Currently Borrowed -->
        <div class="card">
            <div class="card-header">
                <h3>📖 Currently Borrowed Books</h3>
            </div>
            <?php if (empty($borrowed)): ?>
            <div class="empty-state">
                <div class="emoji">📭</div>
                <p>No books currently borrowed.</p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Book Title</th>
                        <th>Author</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($borrowed as $b):
                    $days_left = (strtotime($b['due_date']) - time()) / 86400;
                    $is_overdue = $days_left < 0;
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($b['title']) ?></strong></td>
                        <td><?= htmlspecialchars($b['author']) ?></td>
                        <td><?= date('M d, Y', strtotime($b['issue_date'])) ?></td>
                        <td><?= date('M d, Y', strtotime($b['due_date'])) ?></td>
                        <td>
                            <?php if ($is_overdue): ?>
                                <span class="days-badge status-overdue">⚠️ <?= abs((int)$days_left) ?> days overdue</span>
                            <?php elseif ($days_left <= 3): ?>
                                <span class="days-badge" style="background:rgba(245,158,11,0.15);color:#fbbf24;">⏰ <?= (int)$days_left ?> days left</span>
                            <?php else: ?>
                                <span class="days-badge status-ok">✅ <?= (int)$days_left ?> days left</span>
                            <?php endif; ?>
                        </td>
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
