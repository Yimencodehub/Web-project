<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../../config/db.php';
require_once '../../includes/functions.php';
requireRole(['admin','superadmin']);

$msg = '';
$msgType = 'success';

// Handle Add Fine (e.g. Damaged Book / Lost Book / Manual Penalty)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fine'])) {
    $member_id = (int)$_POST['member_id'];
    $amount    = (float)$_POST['amount'];
    $reason    = trim($_POST['reason'] ?? 'Damaged/Lost Book Penalty');

    if ($member_id > 0 && $amount > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO fines (member_id, amount, reason, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$member_id, $amount, $reason]);
            $msg = "✅ Fine of \$$amount assessed successfully for $reason.";
            $msgType = 'success';
        } catch (Exception $e) {
            $msg = "Error adding fine: " . htmlspecialchars($e->getMessage());
            $msgType = 'danger';
        }
    } else {
        $msg = "Please select a valid member and amount.";
        $msgType = 'danger';
    }
}

// Fetch all active members for dropdown
$members = $pdo->query("
    SELECT m.id as member_db_id, m.member_id, u.full_name 
    FROM members m 
    JOIN users u ON m.user_id = u.id 
    ORDER BY u.full_name ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fine & Damage Management (AD-12) — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="../../assets/js/main.js"></script>
    <style>
        :root {
            --bg: var(--bg-primary, #0f172a);
            --sidebar: var(--sidebar-bg, #111827);
            --card-bg: var(--bg-card, rgba(30, 41, 59, 0.85));
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --text-main: var(--text-primary, #f8fafc);
            --text-muted: var(--text-secondary, #94a3b8);
            --border: var(--border, #334155);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg); color: var(--text-main); display: flex; min-height: 100vh; }
        
        .sidebar { width: 260px; background-color: var(--sidebar); height: 100vh; display: flex; flex-direction: column; border-right: 1px solid var(--border); position: fixed; left: 0; top: 0; bottom: 0; }
        .sidebar-header { padding: 20px; font-size: 1.25rem; font-weight: bold; color: var(--primary); border-bottom: 1px solid var(--border); }
        .sidebar-nav { padding: 20px 0; flex-grow: 1; overflow-y: auto; }
        .sidebar-link { display: block; padding: 12px 20px; color: var(--text-muted); text-decoration: none; transition: 0.3s; }
        .sidebar-link:hover, .sidebar-link.active { background-color: var(--card-bg); color: var(--text-main); border-left: 3px solid var(--primary); }
        
        .main-wrapper { margin-left: 260px; flex-grow: 1; display: flex; flex-direction: column; min-height: 100vh; }
        .topbar { height: 64px; background-color: var(--bg); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; padding: 0 24px; }
        .topbar-right { display: flex; align-items: center; gap: 15px; }
        .role-badge { background: var(--primary); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .btn-logout { color: var(--danger); text-decoration: none; font-weight: 500; }
        
        .content { flex-grow: 1; padding: 28px; overflow-y: auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title { font-size: 1.5rem; font-weight: 600; }
        
        .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 20px; backdrop-filter: blur(10px); margin-bottom: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); font-size: 0.88rem; }
        th { color: var(--text-muted); font-weight: 600; font-size: 0.78rem; text-transform: uppercase; }
        tr:hover { background-color: rgba(255,255,255,0.02); }
        
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; cursor: pointer; border: none; font-size: 0.875rem; transition: 0.2s; }
        .btn-primary { background-color: var(--primary); color: white; }
        .btn-success { background-color: var(--success); color: white; }
        .btn-danger { background-color: var(--danger); color: white; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: var(--accent); }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .badge-info { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        
        .form-control { width: 100%; padding: 10px 12px; background: var(--input-bg, #0f172a); border: 1px solid var(--border); border-radius: 6px; color: var(--text-main); font-size: 0.88rem; }
        
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
        .alert-danger { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
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
            <a href="../renew/index.php" class="sidebar-link">Renew Book</a>
            <a href="../returns/index.php" class="sidebar-link">Process Return</a>
            <a href="../fines/index.php" class="sidebar-link active">Fines</a>
            <a href="../inventory/index.php" class="sidebar-link">Inventory</a>
            <a href="../reports/index.php" class="sidebar-link">Reports</a>
            <a href="../profile.php" class="sidebar-link">My Profile</a>
            <a href="../users/index.php" class="sidebar-link">Users</a>
                    <a href="../../logout.php" class="sidebar-link" style="color: #ef4444; margin-top: 15px; border-top: 1px solid var(--border);"><i class="fas fa-sign-out-alt"></i> 🚪 Logout</a>
        </div>
    </div>
    <div class="main-wrapper">
        <div class="topbar">
            <div></div>
            <div class="topbar-right">
                <button type="button" class="theme-toggle-btn" onclick="toggleTheme()">☀️ Light Mode</button>
                <?= renderUserAvatar(32) ?>
                <span><?= htmlspecialchars($_SESSION["full_name"] ?? "Admin User") ?></span>
                <span class="role-badge"><?= htmlspecialchars($_SESSION["role"] ?? "Admin") ?></span>
                <a href="../../logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
        <div class="content">
            <div class="page-header">
                <h1 class="page-title">💰 Fine & Damaged Book Management (AD-12)</h1>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
            <?php endif; ?>

            <!-- Add Damage / Penalty Form -->
            <div class="card" style="margin-bottom: 28px;">
                <h3 style="font-size:1.1rem; margin-bottom:12px; color:#818cf8;">➕ Issue Fine for Damaged or Lost Book</h3>
                <form method="POST" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:14px; align-items:end;">
                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted);">Select Member *</label>
                        <select name="member_id" class="form-control" required>
                            <option value="">Choose Member...</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?= $m['member_db_id'] ?>"><?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['member_id']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted);">Fine Amount ($) *</label>
                        <input type="number" step="0.01" min="1" name="amount" class="form-control" placeholder="e.g. 50.00" required>
                    </div>

                    <div>
                        <label style="font-size:0.8rem; color:var(--text-muted);">Reason / Notes *</label>
                        <input type="text" name="reason" class="form-control" placeholder="e.g. Damaged cover / Lost book replacement" required>
                    </div>

                    <div>
                        <button type="submit" name="add_fine" class="btn btn-primary" style="width:100%; justify-content:center;">
                            💵 Record Penalty
                        </button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3 style="font-size:1.1rem; margin-bottom:12px;">All Member Fine Records</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Fine ID</th>
                            <th>Member</th>
                            <th>Reason / Description</th>
                            <th>Amount</th>
                            <th>Date Issued</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $pdo->query("
                                SELECT f.*, u.full_name, m.member_id 
                                FROM fines f 
                                JOIN members m ON f.member_id = m.id 
                                JOIN users u ON m.user_id = u.id 
                                ORDER BY f.created_at DESC
                            ");
                            $fines = $stmt->fetchAll();
                            if (empty($fines)):
                        ?>
                            <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No fine records found.</td></tr>
                        <?php else:
                            foreach ($fines as $row):
                                $badge = $row['status'] === 'paid' ? 'badge-success' : ($row['status'] === 'pending' ? 'badge-warning' : 'badge-info');
                        ?>
                                <tr>
                                    <td>#<?= $row['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['full_name']) ?></strong><br>
                                        <span style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($row['member_id']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($row['reason'] ?? 'Overdue Fine') ?></td>
                                    <td><strong>$<?= number_format($row['amount'], 2) ?></strong></td>
                                    <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                    <td><span class="badge <?= $badge ?>"><?= strtoupper($row['status']) ?></span></td>
                                    <td>
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <a href="collect.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-success">💵 Pay & Print Receipt</a>
                                            <a href="waive.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger">🛡️ Waive</a>
                                        <?php else: ?>
                                            <a href="collect.php?id=<?= $row['id'] ?>" class="btn btn-sm" style="background:rgba(255,255,255,0.08);color:#94a3b8;">🖨️ View Receipt</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                        <?php 
                            endforeach;
                        endif;
                        } catch(Exception $e) {
                            echo "<tr><td colspan='7'>Error loading fines.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
