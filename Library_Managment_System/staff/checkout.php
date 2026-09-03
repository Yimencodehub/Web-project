<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['staff','admin','superadmin']);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_POST['member_id'];
    $book_id = $_POST['book_id'];
    $due_date = $_POST['due_date'];

    // Check available copies
    $stmt = $pdo->prepare("SELECT available_copies FROM books WHERE id = ?");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch();

    if ($book && $book['available_copies'] > 0) {
        // Issue book
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO book_issues (user_id, book_id, issue_date, due_date) VALUES (?, ?, CURDATE(), ?)");
            $stmt->execute([$member_id, $book_id, $due_date]);
            
            $stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ?");
            $stmt->execute([$book_id]);
            
            $pdo->commit();
            $message = "<div style='color: #10b981; padding: 1rem; background: rgba(16,185,129,0.1); border-radius: 0.5rem; margin-bottom:1rem;'>Book issued successfully!</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div style='color: #ef4444; padding: 1rem; background: rgba(239,68,68,0.1); border-radius: 0.5rem; margin-bottom:1rem;'>Error issuing book.</div>";
        }
    } else {
        $message = "<div style='color: #ef4444; padding: 1rem; background: rgba(239,68,68,0.1); border-radius: 0.5rem; margin-bottom:1rem;'>Book is not available.</div>";
    }
}

// Get lists for dropdowns (in real app, use AJAX for large datasets)
$members = $pdo->query("SELECT u.id, u.full_name, m.member_id FROM users u JOIN members m ON u.id = m.user_id")->fetchAll();
$books = $pdo->query("SELECT id, title, isbn, available_copies FROM books WHERE available_copies > 0")->fetchAll();

$default_due = date('Y-m-d', strtotime('+14 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Check Out - Staff</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #0f172a; --sidebar: #111827; --card-bg: rgba(30,41,59,0.8); --primary: #4f46e5; --text: #f8fafc; --text-muted: #94a3b8; --border: #334155; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: var(--sidebar); border-right: 1px solid var(--border); padding: 1.5rem; display: flex; flex-direction: column; }
        .sidebar a { display: block; color: var(--text-muted); text-decoration: none; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 0.5rem; }
        .sidebar a:hover, .sidebar a.active { background: var(--primary); color: white; }
        .main-content { flex: 1; padding: 2rem; overflow-y: auto; }
        .card { max-width: 600px; background: var(--card-bg); backdrop-filter: blur(10px); border: 1px solid var(--border); border-radius: 1rem; padding: 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; color: var(--text-muted); }
        select, input { width: 100%; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid var(--border); background: var(--bg); color: var(--text); }
        .btn { padding: 0.75rem 1.5rem; background: var(--primary); color: white; border: none; border-radius: 0.5rem; cursor: pointer; font-size: 1rem; width: 100%; }
        .btn:hover { background: #4338ca; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="color:var(--primary); margin-bottom: 2rem;">Staff Panel</h2>
        <a href="dashboard.php">Dashboard</a>
        <a href="checkout.php" class="active">Check Out</a>
        <a href="checkin.php">Check In</a>
        <a href="reservations.php">Reservations</a>
    </div>
    <div class="main-content">
        <h1>Check Out (Issue Book)</h1>
        <br>
        <div class="card">
            <?= $message ?>
            <form method="POST">
                <div class="form-group">
                    <label>Select Member</label>
                    <select name="member_id" required>
                        <option value="">-- Choose Member --</option>
                        <?php foreach($members as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['member_id']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select Book</label>
                    <select name="book_id" required>
                        <option value="">-- Choose Book --</option>
                        <?php foreach($books as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['title']) ?> (ISBN: <?= htmlspecialchars($b['isbn']) ?>) - <?= $b['available_copies'] ?> left</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" value="<?= $default_due ?>" required>
                </div>
                <button type="submit" class="btn">Issue Book</button>
            </form>
        </div>
    </div>
</body>
</html>
