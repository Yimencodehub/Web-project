<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['staff','admin','superadmin']);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_id'])) {
    $issue_id = (int)$_POST['issue_id'];

    // Fetch the issue (status='issued' means not yet returned)
    $stmt = $pdo->prepare("SELECT bi.*, b.title FROM book_issues bi JOIN books b ON bi.book_id=b.id WHERE bi.id = ? AND bi.status = 'issued'");
    $stmt->execute([$issue_id]);
    $issue = $stmt->fetch();

    if ($issue) {
        $pdo->beginTransaction();
        try {
            // 1. Mark issue as returned (no return_date column in book_issues)
            $pdo->prepare("UPDATE book_issues SET status='returned' WHERE id=?")->execute([$issue_id]);

            // 2. Restore book copy count
            $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id=?")->execute([$issue['book_id']]);

            // 3. Calculate fine from fine_settings
            $fine_row = $pdo->query("SELECT fine_per_day, grace_period_days, max_fine FROM fine_settings LIMIT 1")->fetch();
            $fine_per_day = $fine_row['fine_per_day'] ?? 5.00;
            $grace        = $fine_row['grace_period_days'] ?? 1;
            $max_fine     = $fine_row['max_fine'] ?? 500.00;
            $due_ts   = strtotime($issue['due_date']);
            $now_ts   = strtotime(date('Y-m-d'));
            $days_late = max(0, floor(($now_ts - $due_ts) / 86400) - $grace);
            $fine_amount = min($days_late * $fine_per_day, $max_fine);

            // 4. Insert into returns table with correct columns
            $pdo->prepare("INSERT INTO returns (issue_id, return_date, fine_amount, fine_paid, returned_by) VALUES (?,CURDATE(),?,?,?)")
                ->execute([$issue_id, $fine_amount, ($fine_amount > 0 ? 'no' : 'yes'), $_SESSION['user_id']]);

            // 5. Create fine record if overdue (use member_id, status='pending')
            if ($fine_amount > 0) {
                $pdo->prepare("INSERT INTO fines (member_id, issue_id, amount, reason, status) VALUES (?,?,?,?,'pending')")
                    ->execute([$issue['member_id'], $issue_id, $fine_amount, "Overdue: {$days_late} day(s) late"]);
                $message = "<div class='alert' style='background:rgba(245,158,11,0.1);color:#f59e0b;border:1px solid #f59e0b;'>&#9888; Returned with fine of $" . number_format($fine_amount,2) . " ({$days_late} day(s) late)</div>";
            } else {
                $message = "<div class='alert' style='background:rgba(16,185,129,0.1);color:#10b981;border:1px solid #10b981;'>&#10003; Book returned successfully on time!</div>";
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log($e->getMessage());
            $message = "<div class='alert' style='color:#ef4444;border:1px solid #ef4444;padding:10px;border-radius:6px;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $message = "<div class='alert' style='color:#f87171;'>Issue not found or already returned.</div>";
    }
}

// Active issues — book_issues has no user_id; get full_name via members→users
$stmt = $pdo->query("
    SELECT bi.id, b.title, u.full_name, bi.issue_date, bi.due_date
    FROM book_issues bi
    JOIN books b ON bi.book_id = b.id
    JOIN members m ON bi.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE bi.status = 'issued'
    ORDER BY bi.due_date ASC
");
$active_issues = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Check In - Staff</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #0f172a; --sidebar: #111827; --card-bg: rgba(30,41,59,0.8); --primary: #4f46e5; --text: #f8fafc; --text-muted: #94a3b8; --border: #334155; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: var(--sidebar); border-right: 1px solid var(--border); padding: 1.5rem; display: flex; flex-direction: column; }
        .sidebar a { display: block; color: var(--text-muted); text-decoration: none; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 0.5rem; }
        .sidebar a:hover, .sidebar a.active { background: var(--primary); color: white; }
        .main-content { flex: 1; padding: 2rem; overflow-y: auto; }
        .card { background: var(--card-bg); backdrop-filter: blur(10px); border: 1px solid var(--border); border-radius: 1rem; padding: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        .btn-small { padding: 0.5rem 1rem; background: var(--primary); color: white; border: none; border-radius: 0.25rem; cursor: pointer; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .overdue { color: #ef4444; font-weight: bold; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="color:var(--primary); margin-bottom: 2rem;">Staff Panel</h2>
        <a href="dashboard.php">Dashboard</a>
        <a href="checkout.php">Check Out</a>
        <a href="checkin.php" class="active">Check In</a>
        <a href="reservations.php">Reservations</a>
    </div>
    <div class="main-content">
        <h1>Check In (Return Book)</h1>
        <br>
        <div class="card">
            <?= $message ?>
            <table>
                <thead>
                    <tr>
                        <th>Book Title</th>
                        <th>Member</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($active_issues as $ai): 
                        $is_overdue = (new DateTime()) > (new DateTime($ai['due_date']));
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($ai['title']) ?></td>
                        <td><?= htmlspecialchars($ai['full_name']) ?></td>
                        <td><?= $ai['issue_date'] ?></td>
                        <td class="<?= $is_overdue ? 'overdue' : '' ?>"><?= $ai['due_date'] ?></td>
                        <td>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="issue_id" value="<?= $ai['id'] ?>">
                                <button type="submit" class="btn-small">Return</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
