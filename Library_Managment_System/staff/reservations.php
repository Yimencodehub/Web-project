<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['staff','admin','superadmin']);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_id'])) {
        $stmt = $pdo->prepare("UPDATE reservations SET status = 'approved' WHERE id = ?");
        $stmt->execute([$_POST['approve_id']]);
        $message = "<div style='color:#10b981; padding:1rem; background:rgba(16,185,129,0.1); border-radius:0.5rem; margin-bottom:1rem;'>Reservation approved. Notification sent.</div>";
    } elseif (isset($_POST['cancel_id'])) {
        $stmt = $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$_POST['cancel_id']]);
        $message = "<div style='color:#ef4444; padding:1rem; background:rgba(239,68,68,0.1); border-radius:0.5rem; margin-bottom:1rem;'>Reservation cancelled.</div>";
    } elseif (isset($_POST['collect_id'])) {
        $stmt = $pdo->prepare("UPDATE reservations SET status = 'collected' WHERE id = ?");
        $stmt->execute([$_POST['collect_id']]);
        $message = "<div style='color:#f59e0b; padding:1rem; background:rgba(245,158,11,0.1); border-radius:0.5rem; margin-bottom:1rem;'>Book marked as collected.</div>";
    }
}

$stmt = $pdo->query("
    SELECT r.*, u.full_name, b.title 
    FROM reservations r 
    JOIN members m ON r.member_id = m.id
    JOIN users u ON m.user_id = u.id 
    JOIN books b ON r.book_id = b.id 
    WHERE r.status IN ('pending', 'approved')
    ORDER BY r.reservation_date ASC
");
$reservations = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reservations - Staff</title>
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
        .badge { padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.8rem; }
        .btn-small { padding: 0.4rem 0.8rem; color: white; border: none; border-radius: 0.25rem; cursor: pointer; margin-right: 0.5rem; }
        .btn-approve { background: #10b981; }
        .btn-cancel { background: #ef4444; }
        .btn-collect { background: #f59e0b; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="color:var(--primary); margin-bottom: 2rem;">Staff Panel</h2>
        <a href="dashboard.php">Dashboard</a>
        <a href="checkout.php">Check Out</a>
        <a href="checkin.php">Check In</a>
        <a href="reservations.php" class="active">Reservations</a>
        <a href="reports.php">Reports</a>
    </div>
    <div class="main-content">
        <h1>Manage Reservations</h1>
        <br>
        <div class="card">
            <?= $message ?>
            <?php if(count($reservations) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Book</th>
                        <th>Request Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($reservations as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['full_name']) ?></td>
                        <td><?= htmlspecialchars($r['title']) ?></td>
                        <td><?= $r['reservation_date'] ?></td>
                        <td>
                            <?php 
                                $c = $r['status'] == 'pending' ? '#f59e0b' : '#10b981';
                            ?>
                            <span style="color:<?= $c ?>; font-weight:bold;"><?= ucfirst($r['status']) ?></span>
                        </td>
                        <td style="display:flex;">
                            <?php if($r['status'] == 'pending'): ?>
                                <form method="POST" style="margin:0;"><input type="hidden" name="approve_id" value="<?= $r['id'] ?>"><button class="btn-small btn-approve">Approve</button></form>
                                <form method="POST" style="margin:0;"><input type="hidden" name="cancel_id" value="<?= $r['id'] ?>"><button class="btn-small btn-cancel">Cancel</button></form>
                            <?php elseif($r['status'] == 'approved'): ?>
                                <form method="POST" style="margin:0;"><input type="hidden" name="collect_id" value="<?= $r['id'] ?>"><button class="btn-small btn-collect">Collected</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color: var(--text-muted)">No pending reservations.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
