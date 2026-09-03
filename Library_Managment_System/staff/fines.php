<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['staff','admin','superadmin']);

$msg = '';
$msgType = 'success';

// Handle Collect Fine / Approve Proof
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_fine'])) {
    $fine_id        = (int)$_POST['fine_id'];
    $payment_method = trim($_POST['payment_method'] ?? 'Cash');

    try {
        $stmt = $pdo->prepare("UPDATE fines SET status = 'paid', payment_method = ?, proof_status = 'approved' WHERE id = ?");
        $stmt->execute([$payment_method, $fine_id]);
        $msg = "✅ Fine #$fine_id collected successfully via $payment_method!";
        $msgType = 'success';
    } catch (Exception $e) {
        $msg = "Error collecting fine: " . htmlspecialchars($e->getMessage());
        $msgType = 'danger';
    }
}

// Reject proof
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_proof'])) {
    $fine_id = (int)$_POST['fine_id'];
    try {
        $stmt = $pdo->prepare("UPDATE fines SET proof_status = 'rejected' WHERE id = ?");
        $stmt->execute([$fine_id]);
        $msg = "Uploaded payment receipt rejected for Fine #$fine_id.";
        $msgType = 'warning';
    } catch (Exception $e) {
        $msg = "Error: " . htmlspecialchars($e->getMessage());
        $msgType = 'danger';
    }
}

// Fetch all fines
$stmt = $pdo->query("
    SELECT f.*, u.full_name, m.member_id 
    FROM fines f 
    JOIN members m ON f.member_id = m.id 
    JOIN users u ON m.user_id = u.id 
    ORDER BY f.created_at DESC
");
$fines = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Collect Fines & Receipts — Staff</title>
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
        
        .form-control { padding: 0.4rem 0.6rem; background: #0f172a; border: 1px solid var(--border); border-radius: 0.4rem; color: white; font-size: 0.8rem; }
        .btn { padding: 0.5rem 1rem; background: var(--primary); color: white; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 500; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.78rem; }
        .btn-success { background: #10b981; }
        .btn-danger { background: #ef4444; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .alert-success { background: rgba(16,185,129,0.1); color: var(--green); border: 1px solid rgba(16,185,129,0.3); }
        .alert-warning { background: rgba(245,158,11,0.1); color: var(--accent); border: 1px solid rgba(245,158,11,0.3); }
        .alert-danger { background: rgba(239,68,68,0.1); color: var(--red); border: 1px solid rgba(239,68,68,0.3); }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>📚 Staff Panel</h2>
        <a href="dashboard.php">Dashboard</a>
        <a href="books.php">Search & Shelves (ST-04)</a>
        <a href="members.php">Member Info (ST-03)</a>
        <a href="checkout.php">Check Out</a>
        <a href="checkin.php">Check In</a>
        <a href="fines.php" class="active">Collect Fines (ST-06)</a>
        <a href="reservations.php">Reservations</a>
        <a href="reports.php">Reports</a>
    </div>
    
    <div class="main-content">
        <div class="navbar">
            <button type="button" class="theme-toggle-btn" onclick="toggleTheme()">☀️ Light Mode</button>
            <span>Staff Portal (<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>) | <a href="../logout.php" style="color:var(--red); text-decoration:none;">Logout</a></span>
        </div>
        
        <h1 style="font-size:1.6rem; font-weight:700; margin-bottom:0.5rem;">💵 Fine Collection & Payment Verification (ST-06)</h1>
        <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.5rem;">View member fines, inspect online payment receipts (Telebirr/Mobile Banking), approve payments, and issue receipts.</p>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
        <?php endif; ?>

        <div class="card">
            <h3 style="font-size:1.1rem; margin-bottom:0.5rem;">Member Fines List</h3>
            <table>
                <thead>
                    <tr>
                        <th>Fine ID</th>
                        <th>Member</th>
                        <th>Reason / Description</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Payment Proof & Ref</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fines)): ?>
                        <tr><td colspan="8" style="text-align:center; color:var(--text-muted);">No fine records found.</td></tr>
                    <?php else:
                        foreach ($fines as $f): 
                            $st = $f['status'];
                            $proofSt = $f['proof_status'] ?? 'none';
                    ?>
                    <tr>
                        <td>#<?= $f['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($f['full_name']) ?></strong><br>
                            <span style="color:var(--text-muted); font-size:0.8rem;"><?= htmlspecialchars($f['member_id']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($f['reason'] ?? 'Overdue fine') ?></td>
                        <td><strong style="color:<?= $st==='paid'?'#34d399':($st==='pending'?'#fbbf24':'#60a5fa') ?>;">$<?= number_format($f['amount'], 2) ?></strong></td>
                        <td><?= date('M d, Y', strtotime($f['created_at'])) ?></td>
                        <td>
                            <?php if (!empty($f['receipt_image'])): ?>
                                <a href="../<?= htmlspecialchars($f['receipt_image']) ?>" target="_blank" style="color:#818cf8; text-decoration:underline;">
                                    🖼️ View Receipt
                                </a><br>
                                <span style="font-size:0.75rem; color:var(--text-muted);">Ref: <?= htmlspecialchars($f['transaction_ref'] ?: 'N/A') ?></span>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:0.8rem;">No file uploaded</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $st==='paid'?'badge-green':($st==='pending'?'badge-warning':'badge-info') ?>">
                                <?= strtoupper($st) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($st === 'pending'): ?>
                                <form method="POST" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                                    <input type="hidden" name="fine_id" value="<?= $f['id'] ?>">
                                    <select name="payment_method" class="form-control">
                                        <option value="Telebirr" <?= ($f['payment_method']??'')==='Telebirr'?'selected':'' ?>>Telebirr</option>
                                        <option value="Mobile Banking" <?= ($f['payment_method']??'')==='Mobile Banking'?'selected':'' ?>>Mobile Banking</option>
                                        <option value="Bank Slip Deposit" <?= ($f['payment_method']??'')==='Bank Slip Deposit'?'selected':'' ?>>Bank Deposit</option>
                                        <option value="Cash">Cash</option>
                                    </select>
                                    <button type="submit" name="collect_fine" class="btn btn-sm btn-success" onclick="return confirm('Approve & collect fine payment?')">
                                        ✅ Collect
                                    </button>
                                    <?php if (!empty($f['receipt_image'])): ?>
                                        <button type="submit" name="reject_proof" class="btn btn-sm btn-danger" onclick="return confirm('Reject uploaded receipt?')">
                                            ❌ Reject
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <a href="../admin/fines/collect.php?id=<?= $f['id'] ?>" target="_blank" class="btn btn-sm" style="background:rgba(255,255,255,0.08); color:#94a3b8;">
                                    🖨️ Receipt
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
