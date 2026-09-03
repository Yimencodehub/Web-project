<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../../config/db.php';
require_once '../../includes/functions.php';
requireRole(['admin','superadmin']);

$fine_id = (int)($_GET['id'] ?? 0);

// Fetch fine record
$stmt = $pdo->prepare("
    SELECT f.*, u.full_name, u.email, m.member_id, b.title as book_title, bi.issue_date, bi.due_date
    FROM fines f
    JOIN members m ON f.member_id = m.id
    JOIN users u ON m.user_id = u.id
    LEFT JOIN book_issues bi ON f.issue_id = bi.id
    LEFT JOIN books b ON bi.book_id = b.id
    WHERE f.id = ?
");
$stmt->execute([$fine_id]);
$fine = $stmt->fetch();

if (!$fine) {
    header("Location: index.php");
    exit;
}

$paidSuccess = false;
$paymentMethod = 'Cash';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $paymentMethod = $_POST['payment_method'] ?? 'Cash';
    try {
        $stmt = $pdo->prepare("UPDATE fines SET status='paid', payment_method=?, proof_status='approved' WHERE id=?");
        $stmt->execute([$paymentMethod, $fine_id]);
        
        logAudit($pdo, $_SESSION['user_id'], 'collect_fine', "Collected fine #$fine_id (\${$fine['amount']}) from {$fine['full_name']} via {$paymentMethod}");
        
        $fine['status'] = 'paid';
        $fine['payment_method'] = $paymentMethod;
        $fine['proof_status'] = 'approved';
        $paidSuccess = true;
    } catch (Exception $e) {
        $error = "Error recording fine payment: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_proof'])) {
    try {
        $stmt = $pdo->prepare("UPDATE fines SET proof_status='rejected' WHERE id=?");
        $stmt->execute([$fine_id]);
        $fine['proof_status'] = 'rejected';
        $msgReject = "Uploaded payment receipt has been rejected. The member will be notified to re-upload.";
    } catch (Exception $e) {
        $error = "Error rejecting proof: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Collect Fine & Issue Receipt (AD-12) — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --sidebar: #111827;
            --card-bg: rgba(30, 41, 59, 0.85);
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
        
        .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 14px; padding: 28px; backdrop-filter: blur(10px); margin-bottom: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); max-width: 680px; }
        
        .receipt-box { background: rgba(15,23,42,0.6); border: 2px dashed rgba(255,255,255,0.15); border-radius: 12px; padding: 24px; margin-bottom: 24px; }
        .receipt-header { text-align: center; border-bottom: 1px solid var(--border); padding-bottom: 16px; margin-bottom: 16px; }
        .receipt-header h3 { font-size: 1.3rem; color: #818cf8; margin-bottom: 4px; }
        .receipt-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.9rem; }
        .receipt-row strong { color: var(--text-main); }
        .receipt-total { border-top: 1px solid var(--border); padding-top: 14px; margin-top: 14px; display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 700; color: #34d399; }
        
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; cursor: pointer; border: none; font-size: 0.9rem; transition: 0.2s; }
        .btn-primary { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white; }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .form-control { width: 100%; padding: 10px 14px; background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border); border-radius: 8px; color: var(--text-main); font-size: 0.9rem; margin-top: 6px; }
        
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 0.9rem; }
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        
        @media print {
            .sidebar, .topbar, .page-header, .btn, form, p.lead { display: none !important; }
            .main-wrapper { margin-left: 0; padding: 0; }
            body { background: white; color: black; }
            .card { border: none; box-shadow: none; padding: 0; max-width: 100%; }
            .receipt-box { border: 2px solid #000; color: black; background: white; }
            .receipt-row strong, .receipt-header h3 { color: black !important; }
            .receipt-total { color: black !important; }
        }
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
                <h1 class="page-title">💵 Collect Fine Payment (AD-12)</h1>
                <a href="index.php" class="btn" style="background:rgba(255,255,255,0.08);color:#94a3b8;">← Back to Fines</a>
            </div>

            <?php if ($paidSuccess): ?>
                <div class="alert alert-success">
                    ✅ <strong>Payment Received!</strong> Fine #<?= $fine_id ?> of $<?= number_format($fine['amount'], 2) ?> was marked as PAID.
                </div>
            <?php endif; ?>

            <div class="card">
                <!-- Receipt Box -->
                <div class="receipt-box">
                    <div class="receipt-header">
                        <h3>📚 <?= getSiteName($pdo) ?></h3>
                        <p style="color:var(--text-muted); font-size:0.85rem;">Official Library Fine Receipt</p>
                    </div>
                    
                    <div class="receipt-row">
                        <span>Receipt / Fine ID:</span>
                        <strong>#<?= $fine['id'] ?></strong>
                    </div>
                    <div class="receipt-row">
                        <span>Member Name:</span>
                        <strong><?= htmlspecialchars($fine['full_name']) ?></strong>
                    </div>
                    <div class="receipt-row">
                        <span>Member ID:</span>
                        <strong><?= htmlspecialchars($fine['member_id']) ?></strong>
                    </div>
                    <div class="receipt-row">
                        <span>Associated Book:</span>
                        <strong><?= htmlspecialchars($fine['book_title'] ?? 'N/A') ?></strong>
                    </div>
                    <div class="receipt-row">
                        <span>Reason:</span>
                        <strong><?= htmlspecialchars($fine['reason'] ?? 'Overdue fine') ?></strong>
                    </div>
                    <div class="receipt-row">
                        <span>Date Issued:</span>
                        <strong><?= date('M d, Y', strtotime($fine['created_at'])) ?></strong>
                    </div>
                    <div class="receipt-row">
                        <span>Payment Status:</span>
                        <strong style="color:<?= $fine['status']==='paid'?'#34d399':'#fbbf24' ?>; text-transform:uppercase;">
                            <?= $fine['status'] ?>
                        </strong>
                    </div>

                    <?php if (!empty($fine['receipt_image'])): ?>
                        <div style="margin-top:16px; padding-top:16px; border-top:1px dashed var(--border);">
                            <div class="receipt-row">
                                <span>Uploaded Payment Method:</span>
                                <strong><?= htmlspecialchars($fine['payment_method'] ?? 'Telebirr/Banking') ?></strong>
                            </div>
                            <div class="receipt-row">
                                <span>Transaction Reference / Rec No:</span>
                                <strong><?= htmlspecialchars($fine['transaction_ref'] ?? 'N/A') ?></strong>
                            </div>
                            <div style="margin-top:12px;">
                                <span style="font-size:0.85rem; color:var(--text-muted); display:block; margin-bottom:8px;">Uploaded Receipt Slip Screenshot:</span>
                                <a href="../../<?= htmlspecialchars($fine['receipt_image']) ?>" target="_blank">
                                    <img src="../../<?= htmlspecialchars($fine['receipt_image']) ?>" alt="Payment Receipt" style="max-width:100%; max-height:260px; border-radius:8px; border:1px solid var(--border);">
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="receipt-total">
                        <span>Total Fine Amount:</span>
                        <span>$<?= number_format($fine['amount'], 2) ?></span>
                    </div>
                </div>

                <?php if ($fine['status'] === 'pending'): ?>
                    <form method="POST">
                        <div style="margin-bottom: 20px;">
                            <label style="font-size:0.85rem; color:var(--text-muted); font-weight:500;">Select Verified Payment Method</label>
                            <select name="payment_method" class="form-control">
                                <option value="Telebirr" <?= ($fine['payment_method']??'')==='Telebirr'?'selected':'' ?>>📱 Telebirr</option>
                                <option value="Mobile Banking" <?= ($fine['payment_method']??'')==='Mobile Banking'?'selected':'' ?>>🏦 Mobile Banking (CBE Birr / Awash / Dashen)</option>
                                <option value="Bank Slip Deposit" <?= ($fine['payment_method']??'')==='Bank Slip Deposit'?'selected':'' ?>>🏛️ Bank Slip Deposit</option>
                                <option value="Cash">💵 Cash in Person</option>
                                <option value="Card">💳 Credit/Debit Card</option>
                            </select>
                        </div>
                        <div style="display: flex; gap: 12px; flex-wrap:wrap;">
                            <button type="submit" name="confirm_payment" class="btn btn-success" onclick="return confirm('Approve $<?= number_format($fine['amount'], 2) ?> payment receipt and mark as PAID?')">
                                ✅ Approve Receipt & Mark Paid
                            </button>
                            <?php if (!empty($fine['receipt_image'])): ?>
                                <button type="submit" name="reject_proof" class="btn" style="background:rgba(239,68,68,0.2);color:#f87171;" onclick="return confirm('Reject this uploaded receipt?')">
                                    ❌ Reject Receipt
                                </button>
                            <?php endif; ?>
                            <a href="waive.php?id=<?= $fine['id'] ?>" class="btn" style="background:rgba(255,255,255,0.08);color:#94a3b8;">
                                🛡️ Waive Fine
                            </a>
                        </div>
                    </form>
                                💵 Confirm Payment Received
                            </button>
                            <a href="waive.php?id=<?= $fine['id'] ?>" class="btn" style="background:rgba(239,68,68,0.2);color:#f87171;">
                                🛡️ Waive Fine Instead
                            </a>
                        </div>
                    </form>
                <?php else: ?>
                    <div style="display: flex; gap: 12px;">
                        <button onclick="window.print()" class="btn btn-primary">
                            🖨️ Print Fine Receipt Slip (AD-12)
                        </button>
                        <a href="index.php" class="btn" style="background:rgba(255,255,255,0.08);color:#94a3b8;">
                            Done / Return to List
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
