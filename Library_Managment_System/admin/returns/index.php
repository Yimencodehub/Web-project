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
    <title>Process Return - Admin</title>
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
            <div class="page-header"><h1 class="page-title">Process Return</h1></div>
            <?php
            $return_msg = '';
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_id'])) {
                try {
                    $pdo->beginTransaction();
                    $issue_id = (int)$_POST['issue_id'];

                    $stmt = $pdo->prepare("SELECT bi.*, b.title FROM book_issues bi JOIN books b ON bi.book_id=b.id WHERE bi.id = ? AND bi.status='issued'");
                    $stmt->execute([$issue_id]);
                    $issue = $stmt->fetch();

                    if ($issue) {
                        // 1. Mark issue as returned
                        $pdo->prepare("UPDATE book_issues SET status='returned' WHERE id=?")->execute([$issue_id]);
                        // 2. Restore book copy
                        $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id=?")->execute([$issue['book_id']]);
                        // 3. Calculate fine using fine_settings
                        $fine_row = $pdo->query("SELECT fine_per_day, grace_period_days, max_fine FROM fine_settings LIMIT 1")->fetch();
                        $fine_per_day    = $fine_row['fine_per_day'] ?? 5.00;
                        $grace           = $fine_row['grace_period_days'] ?? 1;
                        $max_fine        = $fine_row['max_fine'] ?? 500.00;
                        $due_ts  = strtotime($issue['due_date']);
                        $now_ts  = strtotime(date('Y-m-d'));
                        $days_late = max(0, floor(($now_ts - $due_ts) / 86400) - $grace);
                        $fine_amt = min($days_late * $fine_per_day, $max_fine);
                        // 4. Insert into returns table
                        $pdo->prepare("INSERT INTO returns (issue_id, return_date, fine_amount, fine_paid, returned_by) VALUES (?,CURDATE(),?,?,?)")
                            ->execute([$issue_id, $fine_amt, ($fine_amt > 0 ? 'no' : 'yes'), $_SESSION['user_id']]);
                        // 5. Create fine record if overdue
                        if ($fine_amt > 0) {
                            $pdo->prepare("INSERT INTO fines (member_id, issue_id, amount, reason, status) VALUES (?,?,?,?,'pending')")
                                ->execute([$issue['member_id'], $issue_id, $fine_amt, "Overdue: {$days_late} day(s) late"]);
                            $return_msg = "<div class='alert alert-warning'>✅ Book returned. Fine of \$$fine_amt applied ({$days_late} day(s) overdue).</div>";
                        } else {
                            $return_msg = "<div class='alert alert-success'>✅ Book returned successfully on time!</div>";
                        }
                        $pdo->commit();
                    } else {
                        $return_msg = "<div class='alert alert-danger'>Issue not found or already returned.</div>";
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log($e->getMessage());
                    $return_msg = "<div class='alert alert-danger'>Error processing return. Please try again.</div>";
                }
            }
            echo $return_msg;
            ?>
            <div class="card">
                <form class="search-bar" method="GET">
                    <input type="text" name="q" class="form-control" placeholder="Search by Issue ID, Member Name, or Book Title..." value="<?=htmlspecialchars($_GET['q']??'')?>">
                    <button type="submit" class="btn btn-info">Find Issue</button>
                </form>
                <table>
                    <thead><tr><th>Issue ID</th><th>Member</th><th>Book</th><th>Due Date</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php
                        try {
                            $q = $_GET['q'] ?? '';
                            // JOIN users via members to get full_name
                            $stmt = $pdo->prepare("
                                SELECT bi.*, u.full_name, b.title
                                FROM book_issues bi
                                JOIN members m ON bi.member_id = m.id
                                JOIN users u ON m.user_id = u.id
                                JOIN books b ON bi.book_id = b.id
                                WHERE bi.status='issued'
                                AND (bi.id LIKE :q OR u.full_name LIKE :q OR b.title LIKE :q OR m.member_id LIKE :q)
                                ORDER BY bi.due_date ASC
                                LIMIT 20
                            ");
                            $stmt->execute(['q' => "%$q%"]);
                            while ($row = $stmt->fetch()) {
                                $due   = strtotime($row['due_date']);
                                $now   = strtotime(date('Y-m-d'));
                                $color = $now > $due ? 'color:var(--danger);font-weight:600' : '';
                                $overdue_label = $now > $due ? ' ⚠️ OVERDUE' : '';
                                echo "<tr>
                                    <td>#{$row['id']}</td>
                                    <td>".htmlspecialchars($row['full_name'])."</td>
                                    <td>".htmlspecialchars($row['title'])."</td>
                                    <td style='{$color}'>{$row['due_date']}{$overdue_label}</td>
                                    <td>
                                        <form method='POST' style='display:inline'>
                                            <input type='hidden' name='issue_id' value='{$row['id']}'>
                                            <button type='submit' class='btn btn-sm btn-success' onclick=\"return confirm('Process return for: ".htmlspecialchars(addslashes($row['title']))."?')\">
                                                ↩ Return
                                            </button>
                                        </form>
                                    </td>
                                </tr>";
                            }
                        } catch (Exception $e) {
                            error_log($e->getMessage());
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
