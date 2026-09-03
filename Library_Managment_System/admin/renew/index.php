<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../../config/db.php';
require_once '../../includes/functions.php';
requireRole(['admin','superadmin']);

$msg = '';
$msgType = 'success';

// Process Renewal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_id'])) {
    $issue_id = (int)$_POST['issue_id'];
    $extra_days = (int)($_POST['extra_days'] ?? 14);

    try {
        $stmt = $pdo->prepare("
            SELECT bi.*, b.title, u.full_name, m.member_id 
            FROM book_issues bi
            JOIN books b ON bi.book_id = b.id
            JOIN members m ON bi.member_id = m.id
            JOIN users u ON m.user_id = u.id
            WHERE bi.id = ? AND bi.status = 'issued'
        ");
        $stmt->execute([$issue_id]);
        $issue = $stmt->fetch();

        if (!$issue) {
            $msg = 'Active issue record not found or already returned.';
            $msgType = 'danger';
        } else {
            // Check if another member has reserved this book
            $stmtRes = $pdo->prepare("SELECT id FROM reservations WHERE book_id = ? AND status = 'pending' AND member_id != ?");
            $stmtRes->execute([$issue['book_id'], $issue['member_id']]);
            if ($stmtRes->fetch()) {
                $msg = 'Cannot renew: Another member is currently waiting for this book on the reservation queue.';
                $msgType = 'warning';
            } else {
                // Calculate new due date
                $currentDue = strtotime($issue['due_date']);
                $baseDate = max(time(), $currentDue);
                $newDueDate = date('Y-m-d', $baseDate + ($extra_days * 86400));

                $stmtUp = $pdo->prepare("UPDATE book_issues SET due_date = ? WHERE id = ?");
                $stmtUp->execute([$newDueDate, $issue_id]);

                logAudit($pdo, $_SESSION['user_id'], 'renew_book', "Renewed issue #{$issue_id} ({$issue['title']}) for {$issue['full_name']} to {$newDueDate}");

                $msg = "✅ Successfully renewed \"{$issue['title']}\" for {$issue['full_name']}. New Due Date: " . date('M d, Y', strtotime($newDueDate));
                $msgType = 'success';
            }
        }
    } catch (Exception $e) {
        $msg = 'Error processing renewal: ' . htmlspecialchars($e->getMessage());
        $msgType = 'danger';
    }
}

// Search & list active issues
$q = trim($_GET['q'] ?? '');
$query = "
    SELECT bi.*, b.title, b.isbn, u.full_name, m.member_id
    FROM book_issues bi
    JOIN books b ON bi.book_id = b.id
    JOIN members m ON bi.member_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE bi.status = 'issued'
";
$params = [];
if ($q !== '') {
    $query .= " AND (u.full_name LIKE ? OR m.member_id LIKE ? OR b.title LIKE ? OR b.isbn LIKE ?)";
    $params = ["%$q%", "%$q%", "%$q%", "%$q%"];
}
$query .= " ORDER BY bi.due_date ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$activeIssues = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Renew Book — Admin Portal</title>
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
        .sidebar-link:hover, .sidebar-link.active { background-color: var(--card-bg); color: var(--text-main); border-left: 3px solid var(--primary); }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .topbar { height: 64px; background-color: var(--bg); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; padding: 0 24px; }
        .topbar-right { display: flex; align-items: center; gap: 15px; }
        .role-badge { background: var(--primary); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .btn-logout { color: var(--danger); text-decoration: none; font-weight: 500; }
        
        .content { flex-grow: 1; padding: 24px; overflow-y: auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title { font-size: 1.5rem; font-weight: 600; }
        
        .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 20px; backdrop-filter: blur(10px); margin-bottom: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { color: var(--text-muted); font-weight: 500; font-size: 0.875rem; text-transform: uppercase; }
        tr:hover { background-color: rgba(255,255,255,0.02); }
        
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; cursor: pointer; border: none; font-size: 0.875rem; transition: 0.2s; }
        .btn-primary { background-color: var(--primary); color: white; }
        .btn-primary:hover { background-color: var(--primary-hover); }
        .btn-success { background-color: var(--success); color: white; }
        .btn-sm { padding: 5px 12px; font-size: 0.78rem; }
        
        .badge { padding: 4px 8px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: var(--accent); }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        
        .search-bar { display: flex; gap: 10px; margin-bottom: 20px; }
        .form-control { width: 100%; padding: 10px 12px; background: rgba(15, 23, 42, 0.5); border: 1px solid var(--border); border-radius: 6px; color: var(--text-main); }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); }
        .alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); }
        .alert-warning { background: rgba(245, 158, 11, 0.1); border: 1px solid var(--accent); color: var(--accent); }
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
            <a href="../renew/index.php" class="sidebar-link active">Renew Book</a>
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
            <div class="page-header">
                <h1 class="page-title">🔄 Renew Borrowed Books (AD-11)</h1>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
            <?php endif; ?>

            <div class="card">
                <form class="search-bar" method="GET">
                    <input type="text" name="q" class="form-control" placeholder="Search by Member Name, Member ID, Book Title, or ISBN..." value="<?= htmlspecialchars($q) ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if ($q): ?>
                        <a href="index.php" class="btn btn-sm" style="background:rgba(255,255,255,0.08);color:#94a3b8;">Clear</a>
                    <?php endif; ?>
                </form>

                <?php if (empty($activeIssues)): ?>
                    <p style="color: var(--text-muted); padding: 20px 0; text-align: center;">No active borrowed books found.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Issue #</th>
                                <th>Member Name</th>
                                <th>Member ID</th>
                                <th>Book Title</th>
                                <th>Issue Date</th>
                                <th>Current Due Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeIssues as $row): 
                                $dueTs = strtotime($row['due_date']);
                                $isOverdue = time() > $dueTs;
                            ?>
                            <tr>
                                <td>#<?= $row['id'] ?></td>
                                <td><strong><?= htmlspecialchars($row['full_name']) ?></strong></td>
                                <td><?= htmlspecialchars($row['member_id']) ?></td>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= date('M d, Y', strtotime($row['issue_date'])) ?></td>
                                <td style="<?= $isOverdue ? 'color:var(--danger);font-weight:bold;' : '' ?>">
                                    <?= date('M d, Y', $dueTs) ?>
                                    <?= $isOverdue ? ' ⚠️' : '' ?>
                                </td>
                                <td>
                                    <?php if ($isOverdue): ?>
                                        <span class="badge badge-danger">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline-flex; align-items:center; gap:6px;">
                                        <input type="hidden" name="issue_id" value="<?= $row['id'] ?>">
                                        <select name="extra_days" class="form-control" style="width: auto; padding: 4px 8px; font-size: 0.8rem;">
                                            <option value="7">+7 Days</option>
                                            <option value="14" selected>+14 Days</option>
                                            <option value="21">+21 Days</option>
                                            <option value="30">+30 Days</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Renew this book for <?= htmlspecialchars(addslashes($row['full_name'])) ?>?')">
                                            🔄 Extend
                                        </button>
                                    </form>
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
