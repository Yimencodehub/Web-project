<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../../config/db.php';
require_once '../../includes/functions.php';
requireRole(['admin','superadmin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Issue Book - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --sidebar: #111827;
            --card-bg: rgba(30, 41, 59, 0.8);
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        
        .sidebar { width: 260px; background-color: var(--sidebar); height: 100vh; display: flex; flex-direction: column; border-right: 1px solid var(--border); }
        .sidebar-header { padding: 20px; font-size: 1.25rem; font-weight: bold; color: var(--primary); border-bottom: 1px solid var(--border); }
        .sidebar-nav { padding: 20px 0; flex-grow: 1; overflow-y: auto; }
        .sidebar-link { display: block; padding: 12px 20px; color: var(--text-muted); text-decoration: none; transition: 0.3s; }
        .sidebar-link:hover { background-color: var(--card-bg); color: var(--text-main); border-left: 3px solid var(--primary); }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .topbar { height: 64px; background-color: var(--bg); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; padding: 0 24px; }
        .topbar-right { display: flex; align-items: center; gap: 15px; }
        .role-badge { background: var(--primary); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .btn-logout { color: var(--danger); text-decoration: none; font-weight: 500; }
        
        .content { flex-grow: 1; padding: 24px; overflow-y: auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title { font-size: 1.5rem; font-weight: 600; }
        
        .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 20px; backdrop-filter: blur(10px); margin-bottom: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        .grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .stat-card { display: flex; flex-direction: column; border-top: 3px solid var(--primary); }
        .stat-card .value { font-size: 2rem; font-weight: 700; margin: 10px 0; color: var(--text-main); }
        .stat-card .label { color: var(--text-muted); font-size: 0.875rem; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { color: var(--text-muted); font-weight: 500; font-size: 0.875rem; text-transform: uppercase; }
        tr:hover { background-color: rgba(255,255,255,0.02); }
        
        .btn { display: inline-block; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; cursor: pointer; border: none; font-size: 0.875rem; transition: 0.2s; }
        .btn-primary { background-color: var(--primary); color: white; }
        .btn-primary:hover { background-color: var(--primary-hover); }
        .btn-success { background-color: var(--success); color: white; }
        .btn-danger { background-color: var(--danger); color: white; }
        .btn-warning { background-color: var(--accent); color: white; }
        .btn-info { background-color: #3b82f6; color: white; }
        .btn-sm { padding: 4px 8px; font-size: 0.75rem; }
        
        .badge { padding: 4px 8px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: var(--accent); }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .badge-info { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 8px; color: var(--text-muted); font-size: 0.875rem; }
        .form-control { width: 100%; padding: 10px 12px; background: rgba(15, 23, 42, 0.5); border: 1px solid var(--border); border-radius: 6px; color: var(--text-main); transition: 0.3s; }
        .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2); }
        select.form-control { appearance: none; }
        
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); }
        .alert-warning { background: rgba(245, 158, 11, 0.1); border: 1px solid var(--accent); color: var(--accent); }
        .alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); }
        
        .search-bar { display: flex; gap: 10px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><?= getSiteName($pdo) ?></div>
        <div class="sidebar-nav">
            <a href="../dashboard.php" class="sidebar-link">Dashboard</a>
            <a href="../books/index.php" class="sidebar-link">Books</a>
            <a href="../categories/index.php" class="sidebar-link">Categories</a>
            <a href="../members/index.php" class="sidebar-link">Members</a>
            <a href="../issue/index.php" class="sidebar-link">Issue Book</a>
            <a href="../returns/index.php" class="sidebar-link">Process Return</a>
            <a href="../fines/index.php" class="sidebar-link">Fines</a>
            <a href="../inventory/index.php" class="sidebar-link">Inventory</a>
            <a href="../reports/index.php" class="sidebar-link">Reports</a>
            <a href="../users/index.php" class="sidebar-link">Users</a>
                    <a href="../../logout.php" class="sidebar-link" style="color: #ef4444; margin-top: 15px; border-top: 1px solid var(--border);"><i class="fas fa-sign-out-alt"></i> 🚪 Logout</a>
        </div>
    </div>
    <div class="main-wrapper">
        <div class="topbar">
            <div></div>
            <div class="topbar-right">
                <span><?= htmlspecialchars($_SESSION["full_name"] ?? "Admin User") ?></span>
                <span class="role-badge"><?= htmlspecialchars($_SESSION["role"] ?? "Admin") ?></span>
                <a href="../../logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
        <div class="content">
            <div class="page-header"><h1 class="page-title">Issue Book</h1></div>
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $pdo->beginTransaction();
                    $book_id   = (int)$_POST['book_id'];
                    $member_id = (int)$_POST['member_id'];
                    $due_date  = $_POST['due_date'];
                    // Check book still available
                    $avail = $pdo->prepare("SELECT available_copies FROM books WHERE id=?");
                    $avail->execute([$book_id]);
                    if ((int)$avail->fetchColumn() < 1) {
                        echo "<div class='alert alert-danger'>This book is out of stock.</div>";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO book_issues (book_id, member_id, issued_by, issue_date, due_date, status) VALUES (?, ?, ?, CURDATE(), ?, 'issued')");
                        $stmt->execute([$book_id, $member_id, $_SESSION['user_id'], $due_date]);
                        $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ?")->execute([$book_id]);
                        $pdo->commit();
                        echo "<div class='alert alert-success'>✅ Book issued successfully!</div>";
                    }
                } catch(Exception $e) {
                    $pdo->rollBack();
                    error_log($e->getMessage());
                    echo "<div class='alert alert-danger'>Error issuing book: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
            ?>
            <div class="card" style="max-width: 800px;">
                <form method="POST">
                    <div class="form-group"><label>Member</label>
                            <select name="member_id" class="form-control" required>
                            <option value="">Select Member</option>
                            <?php
                            try {
                                // members has no full_name — join users
                                $mems = $pdo->query("
                                    SELECT m.id, m.member_id, u.full_name
                                    FROM members m
                                    JOIN users u ON m.user_id = u.id
                                    WHERE m.status='active'
                                    ORDER BY m.member_id
                                ")->fetchAll();
                                foreach ($mems as $m)
                                    echo "<option value='{$m['id']}'>{$m['member_id']} - " . htmlspecialchars($m['full_name']) . "</option>";
                            } catch(Exception $e) { error_log($e->getMessage()); }
                            ?>
                            </select>
                    </div>
                    <div class="form-group"><label>Book</label>
                        <select name="book_id" class="form-control" required>
                            <option value="">Select Book</option>
                            <?php try { $bks = $pdo->query("SELECT id, isbn, title FROM books WHERE available_copies > 0")->fetchAll(); foreach($bks as $b) echo "<option value='{$b['id']}'>{$b['isbn']} - ".htmlspecialchars($b['title'])."</option>"; } catch(Exception $e){} ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Due Date</label><input type="date" name="due_date" class="form-control" required value="<?=date('Y-m-d', strtotime('+14 days'))?>"></div>
                    <button type="submit" class="btn btn-warning">Issue Book</button>
                </form>
            </div>
            <div class="card"><h3 style="margin-bottom:16px">Recent Issues</h3>
                <table>
                    <thead><tr><th>Member</th><th>Book</th><th>Issue Date</th><th>Due Date</th></tr></thead>
                    <tbody>
                        <?php
                        try {
                            // members has no full_name — join users
                            $stmt = $pdo->query("
                                SELECT i.*, u.full_name, b.title
                                FROM book_issues i
                                JOIN members m ON i.member_id = m.id
                                JOIN users u ON m.user_id = u.id
                                JOIN books b ON i.book_id = b.id
                                ORDER BY i.issue_date DESC LIMIT 10
                            ");
                            while($row = $stmt->fetch())
                                echo "<tr><td>".htmlspecialchars($row['full_name'])."</td><td>".htmlspecialchars($row['title'])."</td><td>{$row['issue_date']}</td><td>{$row['due_date']}</td></tr>";
                        } catch(Exception $e){ error_log($e->getMessage()); }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
