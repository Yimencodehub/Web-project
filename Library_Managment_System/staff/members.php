<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['staff','admin','superadmin']);

$search = trim($_GET['q'] ?? '');

$query = "
    SELECT m.*, u.full_name, u.email, u.phone, u.address, u.status as user_status, u.profile_pic,
           (SELECT COUNT(*) FROM book_issues bi WHERE bi.member_id = m.id) as total_borrows,
           (SELECT COUNT(*) FROM book_issues bi WHERE bi.member_id = m.id AND bi.status = 'issued') as active_borrows,
           (SELECT COUNT(*) FROM book_issues bi WHERE bi.member_id = m.id AND bi.status = 'issued' AND bi.due_date < CURDATE()) as overdue_borrows,
           (SELECT COALESCE(SUM(amount),0) FROM fines f WHERE f.member_id = m.id AND f.status = 'pending') as pending_fines
    FROM members m
    JOIN users u ON m.user_id = u.id
    WHERE 1=1
";
$params = [];
if ($search !== '') {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR m.member_id LIKE ? OR u.phone LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}
$query .= " ORDER BY u.full_name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$members = $stmt->fetchAll();

// Selected member detail lookup
$selected_member = null;
$member_history  = [];
$member_active   = [];

if (isset($_GET['id'])) {
    $mId = (int)$_GET['id'];
    $stmtM = $pdo->prepare("
        SELECT m.*, u.full_name, u.email, u.phone, u.address, u.status as user_status, u.profile_pic
        FROM members m JOIN users u ON m.user_id = u.id WHERE m.id = ?
    ");
    $stmtM->execute([$mId]);
    $selected_member = $stmtM->fetch();

    if ($selected_member) {
        // Active borrows
        $stmtA = $pdo->prepare("
            SELECT bi.*, b.title, b.author, b.isbn 
            FROM book_issues bi JOIN books b ON bi.book_id = b.id 
            WHERE bi.member_id = ? AND bi.status = 'issued' 
            ORDER BY bi.due_date ASC
        ");
        $stmtA->execute([$mId]);
        $member_active = $stmtA->fetchAll();

        // History
        $stmtH = $pdo->prepare("
            SELECT bi.*, b.title, r.return_date, r.fine_amount
            FROM book_issues bi 
            JOIN books b ON bi.book_id = b.id 
            LEFT JOIN returns r ON bi.id = r.issue_id
            WHERE bi.member_id = ?
            ORDER BY bi.issue_date DESC LIMIT 20
        ");
        $stmtH->execute([$mId]);
        $member_history = $stmtH->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Information Lookup — Staff</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js"></script>
    <style>
        :root { --bg: #0f172a; --sidebar: #111827; --card-bg: rgba(30,41,59,0.85); --primary: #4f46e5; --accent: #f59e0b; --text: #f8fafc; --text-muted: #94a3b8; --border: #334155; --green: #10b981; --red: #ef4444; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: var(--sidebar); border-right: 1px solid var(--border); padding: 1.5rem; display: flex; flex-direction: column; position: fixed; left: 0; top: 0; bottom: 0; overflow-y: auto; }
        .sidebar h2 { color: var(--primary); margin-bottom: 2rem; font-size: 1.3rem; font-weight: 700; }
        .sidebar a { display: block; color: var(--text-muted); text-decoration: none; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 0.5rem; transition: all 0.2s; font-size: 0.9rem; font-weight: 500; }
        .sidebar a:hover, .sidebar a.active { background: var(--primary); color: white; }
        
        .main-content { margin-left: 260px; flex: 1; padding: 2rem; overflow-y: auto; }
        .navbar { height: 64px; background: var(--sidebar); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; position: sticky; top: 0; z-index: 10; margin: -2rem -2rem 2rem -2rem; }
        .card { background: var(--card-bg); backdrop-filter: blur(10px); border: 1px solid var(--border); border-radius: 1rem; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border); font-size: 0.88rem; }
        th { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 600; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        
        .badge { padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .badge-green { background: rgba(16,185,129,0.15); color: var(--green); }
        .badge-red { background: rgba(239,68,68,0.15); color: var(--red); }
        .badge-info { background: rgba(59,130,246,0.15); color: #60a5fa; }
        .badge-warning { background: rgba(245,158,11,0.15); color: var(--accent); }
        
        .form-control { padding: 0.6rem 0.8rem; background: #0f172a; border: 1px solid var(--border); border-radius: 0.5rem; color: white; font-size: 0.85rem; }
        .btn { padding: 0.6rem 1.2rem; background: var(--primary); color: white; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 500; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.78rem; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>📚 Staff Panel</h2>
        <a href="dashboard.php">Dashboard</a>
        <a href="books.php">Search & Shelves (ST-04)</a>
        <a href="members.php" class="active">Member Info (ST-03)</a>
        <a href="checkout.php">Check Out</a>
        <a href="checkin.php">Check In</a>
        <a href="fines.php">Collect Fines (ST-06)</a>
        <a href="reservations.php">Reservations</a>
        <a href="reports.php">Reports</a>
    </div>
    
    <div class="main-content">
        <div class="navbar">
            <button type="button" class="theme-toggle-btn" onclick="toggleTheme()">☀️ Light Mode</button>
            <span>Staff Portal (<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>) | <a href="../logout.php" style="color:var(--red); text-decoration:none;">Logout</a></span>
        </div>
        
        <h1 style="font-size:1.6rem; font-weight:700; margin-bottom:0.5rem;">👤 Member Information Lookup (ST-03)</h1>
        <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.5rem;">Search member profiles, view active/overdue loans, status, and borrowing history.</p>

        <!-- Search Bar -->
        <div class="card">
            <form method="GET" style="display:flex; gap:10px;">
                <input type="text" name="q" class="form-control" style="flex:1;" placeholder="Search by name, email, member ID, or phone..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn">🔍 Search Member</button>
                <?php if ($search !== ''): ?>
                    <a href="members.php" class="btn" style="background:rgba(255,255,255,0.08); color:#94a3b8;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($selected_member): ?>
            <!-- Detailed View for Single Member -->
            <div class="card" style="border:1px solid #4f46e5;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h3 style="font-size:1.2rem; color:#818cf8;">📋 Detailed Profile: <?= htmlspecialchars($selected_member['full_name']) ?></h3>
                    <a href="members.php" class="btn btn-sm" style="background:rgba(255,255,255,0.08); color:#94a3b8;">← Back to List</a>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:20px; font-size:0.9rem;">
                    <div>Member ID: <strong><?= htmlspecialchars($selected_member['member_id']) ?></strong></div>
                    <div>Email: <strong><?= htmlspecialchars($selected_member['email']) ?></strong></div>
                    <div>Phone: <strong><?= htmlspecialchars($selected_member['phone'] ?: 'N/A') ?></strong></div>
                    <div>Status: <span class="badge <?= $selected_member['status']==='active'?'badge-green':'badge-red' ?>"><?= strtoupper($selected_member['status']) ?></span></div>
                </div>

                <!-- Active Loans -->
                <h4 style="font-size:1rem; margin:16px 0 8px; color:var(--text);">Current Borrowed Books (Active Loans)</h4>
                <?php if (empty($member_active)): ?>
                    <p style="color:var(--text-muted); font-size:0.85rem; padding:10px 0;">No active borrowed books currently.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr><th>Book Title</th><th>Author</th><th>Issue Date</th><th>Due Date</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($member_active as $a): 
                                $isOverdue = strtotime($a['due_date']) < strtotime(date('Y-m-d'));
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($a['title']) ?></strong></td>
                                <td><?= htmlspecialchars($a['author']) ?></td>
                                <td><?= date('M d, Y', strtotime($a['issue_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($a['due_date'])) ?></td>
                                <td>
                                    <?php if ($isOverdue): ?>
                                        <span class="badge badge-red">⏰ OVERDUE</span>
                                    <?php else: ?>
                                        <span class="badge badge-green">ACTIVE</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="checkin.php?issue_id=<?= $a['id'] ?>" class="btn btn-sm" style="background:#10b981;">Return Book</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- History -->
                <h4 style="font-size:1rem; margin:24px 0 8px; color:var(--text);">Recent Borrowing History</h4>
                <?php if (empty($member_history)): ?>
                    <p style="color:var(--text-muted); font-size:0.85rem; padding:10px 0;">No borrowing history found.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr><th>Book Title</th><th>Issue Date</th><th>Due Date</th><th>Return Date</th><th>Fine Amount</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($member_history as $h): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($h['title']) ?></strong></td>
                                <td><?= date('M d, Y', strtotime($h['issue_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($h['due_date'])) ?></td>
                                <td><?= $h['return_date'] ? date('M d, Y', strtotime($h['return_date'])) : 'Not Returned' ?></td>
                                <td><?= $h['fine_amount'] ? '$'.number_format($h['fine_amount'], 2) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Members Table -->
        <div class="card">
            <h3 style="font-size:1.1rem; margin-bottom:0.5rem;">Library Members List</h3>
            <table>
                <thead>
                    <tr>
                        <th>Member Name</th>
                        <th>Member ID</th>
                        <th>Status</th>
                        <th>Total Borrowed</th>
                        <th>Active Loans</th>
                        <th>Pending Fines</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $m): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($m['full_name']) ?></strong><br>
                            <span style="color:var(--text-muted); font-size:0.8rem;"><?= htmlspecialchars($m['email']) ?></span>
                        </td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($m['member_id']) ?></span></td>
                        <td><span class="badge <?= $m['status']==='active'?'badge-green':'badge-red' ?>"><?= strtoupper($m['status']) ?></span></td>
                        <td><?= $m['total_borrows'] ?> books</td>
                        <td>
                            <?php if ($m['overdue_borrows'] > 0): ?>
                                <span class="badge badge-red"><?= $m['active_borrows'] ?> (<?= $m['overdue_borrows'] ?> Overdue)</span>
                            <?php else: ?>
                                <span class="badge badge-green"><?= $m['active_borrows'] ?> Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($m['pending_fines'] > 0): ?>
                                <span class="badge badge-warning">$<?= number_format($m['pending_fines'], 2) ?></span>
                            <?php else: ?>
                                <span style="color:var(--text-muted);">$0.00</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="members.php?id=<?= $m['id'] ?>" class="btn btn-sm">
                                👁️ View History
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
