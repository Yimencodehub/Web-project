<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['member']);

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT m.id FROM members m WHERE m.user_id = ?");
$stmt->execute([$user_id]);
$member_db_id = $stmt->fetchColumn() ?: 0;

$msg = '';
$msgType = 'success';

// Handle Self-Service Book Renewal (ME-07)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renew_issue_id']) && $member_db_id) {
    $issue_id = (int)$_POST['renew_issue_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT bi.*, b.title 
            FROM book_issues bi
            JOIN books b ON bi.book_id = b.id
            WHERE bi.id = ? AND bi.member_id = ? AND bi.status = 'issued'
        ");
        $stmt->execute([$issue_id, $member_db_id]);
        $issue = $stmt->fetch();

        if (!$issue) {
            $msg = 'Active issue not found.';
            $msgType = 'red';
        } else {
            // Check if another member is waiting on reservation queue
            $stmtRes = $pdo->prepare("SELECT id FROM reservations WHERE book_id = ? AND status = 'pending' AND member_id != ?");
            $stmtRes->execute([$issue['book_id'], $member_db_id]);
            if ($stmtRes->fetch()) {
                $msg = "Cannot renew \"{$issue['title']}\": Another member has requested a reservation for this title.";
                $msgType = 'amber';
            } else {
                $currentDue = strtotime($issue['due_date']);
                $baseDate = max(time(), $currentDue);
                $newDueDate = date('Y-m-d', $baseDate + (14 * 86400));

                $stmtUp = $pdo->prepare("UPDATE book_issues SET due_date = ? WHERE id = ?");
                $stmtUp->execute([$newDueDate, $issue_id]);

                logAudit($pdo, $user_id, 'member_renew_book', "Member renewed book '{$issue['title']}' to {$newDueDate}");

                $msg = "🎉 Successfully renewed \"{$issue['title']}\"! Your new due date is " . date('M d, Y', strtotime($newDueDate)) . ".";
                $msgType = 'green';
            }
        }
    } catch (Exception $e) {
        $msg = 'Error renewing book: ' . htmlspecialchars($e->getMessage());
        $msgType = 'red';
    }
}

// Fetch currently borrowed books
$borrowed = [];
if ($member_db_id) {
    $stmt = $pdo->prepare("
        SELECT bi.*, b.title, b.author
        FROM book_issues bi
        JOIN books b ON bi.book_id = b.id
        WHERE bi.member_id = ? AND bi.status = 'issued'
        ORDER BY bi.due_date ASC
    ");
    $stmt->execute([$member_db_id]);
    $borrowed = $stmt->fetchAll();
}

$has_overdue = false;
foreach ($borrowed as $b) {
    if ($b['due_date'] < date('Y-m-d')) {
        $has_overdue = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Due Dates & Renewals — Member Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#0f172a;color:#f1f5f9;display:flex;min-height:100vh}
.sidebar{width:260px;background:#111827;min-height:100vh;position:fixed;left:0;top:0;bottom:0;overflow-y:auto;z-index:100;border-right:1px solid rgba(255,255,255,.07)}
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
.alert{padding:16px 20px;border-radius:12px;margin-bottom:20px;font-size:.9rem;font-weight:500;line-height:1.5}
.alert-red{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171}
.alert-green{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:#34d399}
.alert-amber{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);color:#fbbf24}
.page-header{margin-bottom:24px}
.page-header h1{font-size:1.5rem;font-weight:700}
.books-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px}
.book-card{background:rgba(30,41,59,.85);border-radius:16px;padding:22px;border-left:4px solid #4f46e5;border:1px solid rgba(255,255,255,0.07);display:flex;flex-direction:column;box-shadow:0 4px 15px rgba(0,0,0,0.15)}
.book-card.overdue{border-left:4px solid #ef4444;background:rgba(239,68,68,.05)}
.book-card.warning{border-left:4px solid #f59e0b;background:rgba(245,158,11,.05)}
.book-card.ok{border-left:4px solid #10b981;background:rgba(16,185,129,.05)}
.book-title{font-size:1.05rem;font-weight:700;color:#f1f5f9;margin-bottom:4px}
.book-author{font-size:.82rem;color:#64748b;margin-bottom:16px}
.due-info{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;background:rgba(15,23,42,0.6);padding:12px 14px;border-radius:10px}
.due-label{font-size:.75rem;color:#64748b;text-transform:uppercase;font-weight:600}
.due-date{font-size:.9rem;font-weight:600;color:#f1f5f9}
.days-badge{padding:6px 14px;border-radius:20px;font-size:.8rem;font-weight:700}
.badge-red{background:rgba(239,68,68,.2);color:#f87171}
.badge-amber{background:rgba(245,158,11,.2);color:#fbbf24}
.badge-green{background:rgba(16,185,129,.2);color:#34d399}
.btn-renew{width:100%;padding:10px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:white;border:none;border-radius:8px;font-weight:600;font-size:0.875rem;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;justify-content:center;gap:6px}
.btn-renew:hover{transform:translateY(-1px);box-shadow:0 4px 15px rgba(79,70,229,0.4)}
.empty{padding:60px;text-align:center;color:#475569}
</style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo"><h2>📚 <?= getSiteName($pdo) ?></h2><p>Member Portal</p></div>
    <nav class="sidebar-nav">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="search.php">🔍 Search Books</a>
        <a href="borrow_history.php">📋 My Borrows</a>
        <a href="due_dates.php" class="active">📅 Due Dates</a>
        <a href="fines.php">💰 My Fines</a>
        <a href="reservations.php">🔖 Reservations</a>
        <a href="profile.php">👤 Profile</a>
        <a href="change_password.php">🔒 Change Password</a>
        <a href="../logout.php">🚪 Logout</a>
    </nav>
</aside>
<div class="main">
    <nav class="navbar">
        <div class="navbar-left">Due Dates & Book Renewals</div>
        <div class="navbar-right">
            <span><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></span>
            <span class="badge">MEMBER</span>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>
    <div class="content">
        <div class="page-header">
            <h1>📅 Borrowed Books & Due Dates</h1>
            <p style="color:#64748b; font-size:0.9rem; margin-top:4px;">Keep track of due dates and renew eligible books online (ME-07).</p>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
        <?php endif; ?>

        <?php if ($has_overdue): ?>
            <div class="alert alert-red">⚠️ You have overdue books! Please return them as soon as possible to avoid accumulating fines.</div>
        <?php elseif (!empty($borrowed)): ?>
            <div class="alert alert-green">✅ All your borrowed books are active and within the due dates.</div>
        <?php endif; ?>

        <?php if (empty($borrowed)): ?>
            <div class="empty">
                <span style="font-size:3rem; display:block; margin-bottom:10px;">📭</span>
                <h3>No books currently borrowed.</h3>
                <p style="margin-top:6px;"><a href="search.php" style="color:#818cf8;">Explore the library catalog</a> to borrow books.</p>
            </div>
        <?php else: ?>
        <div class="books-grid">
            <?php foreach ($borrowed as $b):
                $days = (strtotime($b['due_date']) - time()) / 86400;
                $is_overdue = $days < 0;
                $is_warning = !$is_overdue && $days <= 3;
                $cls = $is_overdue ? 'overdue' : ($is_warning ? 'warning' : 'ok');
                $badge_cls = $is_overdue ? 'badge-red' : ($is_warning ? 'badge-amber' : 'badge-green');
                $badge_txt = $is_overdue ? abs((int)$days).' days OVERDUE' : (int)$days.' days left';
            ?>
            <div class="book-card <?= $cls ?>">
                <div class="book-title"><?= htmlspecialchars($b['title']) ?></div>
                <div class="book-author">by <?= htmlspecialchars($b['author']) ?></div>
                <div class="due-info">
                    <div>
                        <div class="due-label">Issued On</div>
                        <div class="due-date"><?= date('M d, Y', strtotime($b['issue_date'])) ?></div>
                        <div class="due-label" style="margin-top:6px">Due Date</div>
                        <div class="due-date" style="<?= $is_overdue ? 'color:#f87171;' : '' ?>"><?= date('M d, Y', strtotime($b['due_date'])) ?></div>
                    </div>
                    <span class="days-badge <?= $badge_cls ?>"><?= $badge_txt ?></span>
                </div>

                <div style="margin-top: auto;">
                    <form method="POST">
                        <input type="hidden" name="renew_issue_id" value="<?= $b['id'] ?>">
                        <button type="submit" class="btn-renew" onclick="return confirm('Renew this book for another 14 days?')">
                            🔄 Renew Book (+14 Days)
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
