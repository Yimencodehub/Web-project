<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['staff','admin','superadmin']);

$date = $_GET['date'] ?? date('Y-m-d');

// Issues for the day
$stmt = $pdo->prepare("
    SELECT bi.*, b.title, u.full_name 
    FROM book_issues bi 
    JOIN books b ON bi.book_id = b.id 
    JOIN members m ON bi.member_id = m.id
    JOIN users u ON m.user_id = u.id 
    WHERE DATE(bi.issue_date) = ?
");
$stmt->execute([$date]);
$issues = $stmt->fetchAll();

// Returns for the day
$stmt = $pdo->prepare("
    SELECT r.*, b.title, u.full_name 
    FROM returns r 
    JOIN book_issues bi ON r.issue_id = bi.id 
    JOIN books b ON bi.book_id = b.id 
    JOIN members m ON bi.member_id = m.id
    JOIN users u ON m.user_id = u.id 
    WHERE DATE(r.return_date) = ?
");
$stmt->execute([$date]);
$returns = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - Staff</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #0f172a; --sidebar: #111827; --card-bg: rgba(30,41,59,0.8); --primary: #4f46e5; --text: #f8fafc; --text-muted: #94a3b8; --border: #334155; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: var(--sidebar); border-right: 1px solid var(--border); padding: 1.5rem; display: flex; flex-direction: column; }
        .sidebar a { display: block; color: var(--text-muted); text-decoration: none; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 0.5rem; }
        .sidebar a:hover, .sidebar a.active { background: var(--primary); color: white; }
        .main-content { flex: 1; padding: 2rem; overflow-y: auto; }
        .card { background: var(--card-bg); backdrop-filter: blur(10px); border: 1px solid var(--border); border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        .form-inline { display: flex; gap: 1rem; margin-bottom: 2rem; align-items: center; }
        input[type="date"] { padding: 0.5rem; border-radius: 0.5rem; border: 1px solid var(--border); background: var(--card-bg); color: white; }
        .btn { padding: 0.5rem 1rem; background: var(--primary); color: white; border: none; border-radius: 0.5rem; cursor: pointer; }
        @media print {
            .sidebar, .form-inline { display: none; }
            body { background: white; color: black; }
            .card { border: none; box-shadow: none; padding: 0; }
            table { border: 1px solid #ccc; }
            th, td { border-bottom: 1px solid #ccc; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="color:var(--primary); margin-bottom: 2rem;">Staff Panel</h2>
        <a href="dashboard.php">Dashboard</a>
        <a href="checkout.php">Check Out</a>
        <a href="checkin.php">Check In</a>
        <a href="reservations.php">Reservations</a>
        <a href="reports.php" class="active">Reports</a>
    </div>
    <div class="main-content">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h1>Daily Report</h1>
            <button class="btn" onclick="window.print()">Print Report</button>
        </div>
        <br>
        
        <form class="form-inline" method="GET">
            <label>Select Date:</label>
            <input type="date" name="date" value="<?= htmlspecialchars($date) ?>">
            <button type="submit" class="btn">View</button>
        </form>

        <div class="card">
            <h2 style="color: #f59e0b; margin-bottom:1rem;">Check-outs on <?= htmlspecialchars($date) ?></h2>
            <?php if(count($issues) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Member</th>
                        <th>Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($issues as $i): ?>
                    <tr>
                        <td><?= htmlspecialchars($i['title']) ?></td>
                        <td><?= htmlspecialchars($i['full_name']) ?></td>
                        <td><?= $i['due_date'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color:var(--text-muted)">No books issued on this date.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 style="color: #10b981; margin-bottom:1rem;">Check-ins on <?= htmlspecialchars($date) ?></h2>
            <?php if(count($returns) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Member</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($returns as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['title']) ?></td>
                        <td><?= htmlspecialchars($r['full_name']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color:var(--text-muted)">No books returned on this date.</p>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>
