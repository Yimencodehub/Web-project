<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['member']);

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: search.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmtMem = $pdo->prepare("SELECT id, member_id FROM members WHERE user_id = ?");
$stmtMem->execute([$user_id]);
$member = $stmtMem->fetch();
$member_db_id = $member['id'] ?? null;

$stmt = $pdo->prepare("SELECT b.*, c.name as category_name FROM books b LEFT JOIN categories c ON b.category_id = c.id WHERE b.id = ?");
$stmt->execute([$id]);
$book = $stmt->fetch();

if (!$book) {
    header("Location: search.php");
    exit;
}

$message = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve'])) {
    if (!$member_db_id) {
        $message = "Your member profile is not fully registered. Please contact the library administrator.";
        $msgType = 'error';
    } else {
        // Check if already reserved
        $stmt = $pdo->prepare("SELECT id, status FROM reservations WHERE member_id = ? AND book_id = ? AND status IN ('pending', 'approved')");
        $stmt->execute([$member_db_id, $id]);
        $existing = $stmt->fetch();

        if ($existing) {
            $message = "You already have an active reservation (Status: " . ucfirst($existing['status']) . ") for this book.";
            $msgType = 'error';
        } else {
            $stmt = $pdo->prepare("INSERT INTO reservations (member_id, book_id, reservation_date, status) VALUES (?, ?, CURDATE(), 'pending')");
            $stmt->execute([$member_db_id, $id]);
            $message = "🎉 Reservation requested successfully! The library staff will review and approve your request.";
            $msgType = 'success';
        }
    }
}

// Check existing reservation status for this book
$currentRes = null;
if ($member_db_id) {
    $stmt = $pdo->prepare("SELECT status, reservation_date FROM reservations WHERE member_id = ? AND book_id = ? AND status IN ('pending', 'approved')");
    $stmt->execute([$member_db_id, $id]);
    $currentRes = $stmt->fetch();
}

// Related books
$stmt = $pdo->prepare("SELECT * FROM books WHERE category_id = ? AND id != ? LIMIT 4");
$stmt->execute([$book['category_id'], $id]);
$related = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($book['title']) ?> — Book Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --sidebar: #111827;
            --card-bg: rgba(30, 41, 59, 0.85);
            --primary: #4f46e5;
            --accent: #f59e0b;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255,255,255,0.08);
            --green: #10b981;
            --red: #ef4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
        
        .sidebar { width: 260px; background: var(--sidebar); min-height: 100vh; position: fixed; left: 0; top: 0; bottom: 0; overflow-y: auto; z-index: 100; border-right: 1px solid var(--border); }
        .sidebar-logo { padding: 24px 20px; border-bottom: 1px solid var(--border); }
        .sidebar-logo h2 { font-size: 1.1rem; font-weight: 700; color: #4f46e5; }
        .sidebar-logo p { font-size: 0.75rem; color: #64748b; margin-top: 2px; }
        .sidebar-nav { padding: 16px 12px; }
        .sidebar-nav a { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 10px; color: #94a3b8; text-decoration: none; font-size: 0.875rem; font-weight: 500; margin-bottom: 4px; transition: all 0.2s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(79,70,229,0.15); color: #818cf8; }
        
        .main-content { margin-left: 260px; flex: 1; padding: 32px; overflow-y: auto; }
        .back-link { color: #818cf8; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 500; font-size: 0.9rem; margin-bottom: 20px; }
        .back-link:hover { text-decoration: underline; }
        
        .card { background: var(--card-bg); backdrop-filter: blur(14px); border: 1px solid var(--border); border-radius: 18px; padding: 32px; display: flex; gap: 32px; margin-bottom: 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        .cover { width: 220px; height: 300px; background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 14px; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 1px solid var(--border); font-size: 3.5rem; }
        .details { flex: 1; }
        .title { font-size: 1.8rem; font-weight: 700; color: #f1f5f9; margin-bottom: 6px; }
        .author { color: var(--text-muted); font-size: 1.1rem; margin-bottom: 22px; }
        
        .meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 24px; padding: 18px; background: rgba(15,23,42,0.6); border-radius: 12px; border: 1px solid var(--border); }
        .meta-item { display: flex; flex-direction: column; }
        .meta-label { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-bottom: 4px; }
        .meta-val { font-size: 0.95rem; font-weight: 600; color: #f1f5f9; }
        
        .desc-box { background: rgba(15,23,42,0.4); padding: 16px; border-radius: 10px; border: 1px solid var(--border); margin-bottom: 24px; font-size: 0.9rem; line-height: 1.6; color: #cbd5e1; }
        
        .btn { padding: 12px 28px; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 0.95rem; font-weight: 600; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 20px rgba(79,70,229,0.4); }
        .btn-disabled { opacity: 0.6; cursor: not-allowed; }
        
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 0.9rem; line-height: 1.5; }
        .alert-success { background: rgba(16,185,129,0.1); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
        .alert-error { background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
        .badge-green { background: rgba(16,185,129,0.15); color: #34d399; }
        .badge-red { background: rgba(239,68,68,0.15); color: #f87171; }
        .badge-amber { background: rgba(245,158,11,0.15); color: #fbbf24; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <h2>📚 City Public Library</h2>
            <p>Member Portal</p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="search.php" class="active">🔍 Search Books</a>
            <a href="borrow_history.php">📋 My Borrows</a>
            <a href="due_dates.php">📅 Due Dates</a>
            <a href="fines.php">💰 My Fines</a>
            <a href="reservations.php">🔖 Reservations</a>
            <a href="profile.php">👤 Profile</a>
            <a href="change_password.php">🔒 Change Password</a>
            <a href="../logout.php">🚪 Logout</a>
        </nav>
    </aside>

    <div class="main-content">
        <a href="search.php" class="back-link">&larr; Back to Search Catalog</a>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="cover">
                📖
                <div style="font-size: 0.8rem; color: #64748b; margin-top: 10px;">Library Book</div>
            </div>
            
            <div class="details">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 8px;">
                    <h1 class="title"><?= htmlspecialchars($book['title']) ?></h1>
                    <?php if ($book['available_copies'] > 0): ?>
                        <span class="badge badge-green">Available (<?= $book['available_copies'] ?> copies)</span>
                    <?php else: ?>
                        <span class="badge badge-red">Out of Stock</span>
                    <?php endif; ?>
                </div>
                
                <div class="author">by <strong><?= htmlspecialchars($book['author']) ?></strong></div>
                
                <div class="meta">
                    <div class="meta-item">
                        <span class="meta-label">ISBN</span>
                        <span class="meta-val"><?= htmlspecialchars($book['isbn'] ?: 'N/A') ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Category</span>
                        <span class="meta-val"><?= htmlspecialchars($book['category_name'] ?: 'General') ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Publisher & Year</span>
                        <span class="meta-val"><?= htmlspecialchars($book['publisher'] ?: 'Standard') ?> (<?= htmlspecialchars($book['year'] ?: '—') ?>)</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Shelf Location</span>
                        <span class="meta-val"><?= htmlspecialchars($book['shelf_location'] ?: 'General Stacks') ?></span>
                    </div>
                </div>

                <?php if (!empty($book['description'])): ?>
                <div class="desc-box">
                    <strong style="color:#f1f5f9; display:block; margin-bottom: 6px;">Description:</strong>
                    <?= nl2br(htmlspecialchars($book['description'])) ?>
                </div>
                <?php endif; ?>

                <?php if ($currentRes): ?>
                    <div class="alert alert-success" style="display: inline-block;">
                        🔖 You reserved this book on <?= date('M d, Y', strtotime($currentRes['reservation_date'])) ?>. Status: <strong style="text-transform: capitalize;"><?= htmlspecialchars($currentRes['status']) ?></strong>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <button type="submit" name="reserve" class="btn">🔖 Request Book Reservation</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
