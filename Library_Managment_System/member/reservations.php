<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['member']);

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT m.id FROM members m WHERE m.user_id = ?");
$stmt->execute([$user_id]);
$member_db_id = $stmt->fetchColumn() ?: 0;

$msg = $err = '';

// Cancel reservation
if (isset($_POST['cancel_id']) && $member_db_id) {
    $stmt = $pdo->prepare("UPDATE reservations SET status='cancelled' WHERE id=? AND member_id=? AND status='pending'");
    $stmt->execute([$_POST['cancel_id'], $member_db_id]);
    $msg = 'Reservation cancelled.';
}

// New reservation
if (isset($_POST['book_id']) && $member_db_id) {
    $book_id = (int)$_POST['book_id'];
    // Check not already reserved
    $stmt = $pdo->prepare("SELECT id FROM reservations WHERE member_id=? AND book_id=? AND status IN ('pending','approved')");
    $stmt->execute([$member_db_id, $book_id]);
    if ($stmt->fetch()) {
        $err = 'You already have an active reservation for this book.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO reservations (book_id, member_id, reservation_date, status) VALUES (?,?,CURDATE(),'pending')");
        $stmt->execute([$book_id, $member_db_id]);
        $msg = 'Reservation requested successfully!';
    }
}

// Get reservations
$reservations = [];
if ($member_db_id) {
    $stmt = $pdo->prepare("
        SELECT r.*, b.title, b.author FROM reservations r
        JOIN books b ON r.book_id = b.id
        WHERE r.member_id = ? ORDER BY r.reservation_date DESC
    ");
    $stmt->execute([$member_db_id]);
    $reservations = $stmt->fetchAll();
}

// Books for search/request
$books = [];
if (isset($_GET['search']) && strlen(trim($_GET['search'])) > 1) {
    $sq = '%' . trim($_GET['search']) . '%';
    $stmt = $pdo->prepare("SELECT id, title, author FROM books WHERE (title LIKE ? OR author LIKE ?) AND available_copies > 0 LIMIT 10");
    $stmt->execute([$sq, $sq]);
    $books = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reservations - Library</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#0f172a;color:#f1f5f9;display:flex;min-height:100vh}
.sidebar{width:260px;background:#111827;min-height:100vh;position:fixed;left:0;top:0;bottom:0;overflow-y:auto;z-index:100}
.sidebar-logo{padding:24px 20px;border-bottom:1px solid rgba(255,255,255,.07)}
.sidebar-logo h2{font-size:1.1rem;font-weight:700;color:#4f46e5}
.sidebar-logo p{font-size:.75rem;color:#64748b;margin-top:2px}
.sidebar-nav{padding:16px 12px}
.sidebar-nav a{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;color:#94a3b8;text-decoration:none;font-size:.875rem;font-weight:500;margin-bottom:4px;transition:all .2s}
.sidebar-nav a:hover,.sidebar-nav a.active{background:rgba(79,70,229,.15);color:#818cf8}
.main{margin-left:260px;flex:1;display:flex;flex-direction:column}
.navbar{height:64px;background:#1e293b;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;justify-content:space-between;padding:0 28px;position:sticky;top:0;z-index:50}
.navbar-left{font-size:1.1rem;font-weight:600}
.navbar-right{display:flex;align-items:center;gap:14px}
.badge{padding:4px 12px;border-radius:20px;font-size:.7rem;font-weight:700;background:rgba(59,130,246,.2);color:#60a5fa}
.logout-btn{padding:8px 16px;background:rgba(239,68,68,.15);color:#f87171;border:none;border-radius:8px;font-size:.8rem;cursor:pointer;text-decoration:none}
.content{padding:28px;flex:1}
.page-header{margin-bottom:24px}
.page-header h1{font-size:1.5rem;font-weight:700}
.alert{padding:14px 18px;border-radius:10px;margin-bottom:18px;font-size:.875rem}
.alert-success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:#34d399}
.alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171}
.card{background:rgba(30,41,59,.8);border:1px solid rgba(255,255,255,.07);border-radius:16px;overflow:hidden;margin-bottom:24px}
.card-header{padding:18px 24px;border-bottom:1px solid rgba(255,255,255,.07)}
.card-header h3{font-size:1rem;font-weight:600}
.card-body{padding:20px 24px}
.search-form{display:flex;gap:10px;margin-bottom:16px}
.search-form input{flex:1;padding:10px 16px;background:#0f172a;border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#f1f5f9;font-size:.875rem;font-family:'Inter',sans-serif}
.search-form input:focus{outline:none;border-color:#4f46e5}
.btn{padding:10px 20px;border-radius:10px;border:none;cursor:pointer;font-size:.875rem;font-weight:500;font-family:'Inter',sans-serif;text-decoration:none;display:inline-block;transition:all .2s}
.btn-primary{background:#4f46e5;color:#fff}
.btn-primary:hover{background:#4338ca}
.btn-sm{padding:6px 14px;font-size:.8rem}
.btn-danger{background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3)}
.book-result{display:flex;align-items:center;justify-content:space-between;padding:12px;background:rgba(15,23,42,.5);border-radius:10px;margin-bottom:8px}
.book-result-info{font-size:.875rem}
.book-result-info strong{color:#f1f5f9;display:block}
.book-result-info span{color:#64748b;font-size:.8rem}
table{width:100%;border-collapse:collapse}
th{padding:12px 16px;text-align:left;font-size:.73rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid rgba(255,255,255,.07)}
td{padding:13px 16px;font-size:.875rem;color:#cbd5e1;border-bottom:1px solid rgba(255,255,255,.04)}
tr:hover td{background:rgba(255,255,255,.02)}
.sbadge{padding:4px 10px;border-radius:20px;font-size:.72rem;font-weight:600}
.pending{background:rgba(245,158,11,.15);color:#fbbf24}
.approved{background:rgba(16,185,129,.15);color:#34d399}
.cancelled{background:rgba(148,163,184,.15);color:#94a3b8}
.collected{background:rgba(79,70,229,.15);color:#818cf8}
.empty{padding:40px;text-align:center;color:#475569}
</style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo"><h2>📚 <?= getSiteName($pdo) ?></h2><p>Member Portal</p></div>
    <nav class="sidebar-nav">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="search.php">🔍 Search Books</a>
        <a href="borrow_history.php">📋 My Borrows</a>
        <a href="due_dates.php">📅 Due Dates</a>
        <a href="fines.php">💰 My Fines</a>
        <a href="reservations.php" class="active">🔖 Reservations</a>
        <a href="profile.php">👤 Profile</a>
        <a href="../logout.php">🚪 Logout</a>
    </nav>
</aside>
<div class="main">
    <nav class="navbar">
        <div class="navbar-left">Reservations</div>
        <div class="navbar-right">
            <span><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></span>
            <span class="badge">MEMBER</span>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>
    <div class="content">
        <div class="page-header"><h1>🔖 Book Reservations</h1></div>
        <?php if ($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-error">❌ <?= htmlspecialchars($err) ?></div><?php endif; ?>

        <!-- Search & Request -->
        <div class="card">
            <div class="card-header"><h3>Request a Reservation</h3></div>
            <div class="card-body">
                <form method="GET" class="search-form">
                    <input type="text" name="search" placeholder="Search book by title or author..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
                <?php foreach ($books as $bk): ?>
                <div class="book-result">
                    <div class="book-result-info">
                        <strong><?= htmlspecialchars($bk['title']) ?></strong>
                        <span>by <?= htmlspecialchars($bk['author']) ?></span>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="book_id" value="<?= $bk['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Reserve</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- My Reservations -->
        <div class="card">
            <div class="card-header"><h3>My Reservations</h3></div>
            <?php if (empty($reservations)): ?>
            <div class="empty">📭 No reservations yet.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Book</th><th>Author</th><th>Request Date</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($reservations as $r): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['title']) ?></strong></td>
                    <td><?= htmlspecialchars($r['author']) ?></td>
                    <td><?= date('M d, Y', strtotime($r['reservation_date'])) ?></td>
                    <td><span class="sbadge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="cancel_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Cancel this reservation?')">Cancel</button>
                        </form>
                        <?php else: ?>-<?php endif; ?>
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
