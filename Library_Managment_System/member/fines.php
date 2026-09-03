<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['member']);

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT m.id, m.member_id FROM members m WHERE m.user_id = ?");
$stmt->execute([$user_id]);
$memberRow = $stmt->fetch();
$member_db_id = $memberRow['id'] ?? 0;
$member_code  = $memberRow['member_id'] ?? '';

$msg = '';
$msgType = 'success';

// Handle Receipt Upload POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_receipt'])) {
    $fine_id         = (int)$_POST['fine_id'];
    $payment_method  = trim($_POST['payment_method'] ?? 'Telebirr');
    $transaction_ref = trim($_POST['transaction_ref'] ?? '');

    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp  = $_FILES['receipt_image']['tmp_name'];
        $fileName = $_FILES['receipt_image']['name'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        if (in_array($fileExt, $allowed)) {
            $targetDir = __DIR__ . '/../uploads/receipts/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $newFileName = 'receipt_f' . $fine_id . '_' . time() . '.' . $fileExt;
            $targetPath  = $targetDir . $newFileName;
            $dbPath      = 'uploads/receipts/' . $newFileName;

            if (move_uploaded_file($fileTmp, $targetPath)) {
                try {
                    $stmtUpd = $pdo->prepare("
                        UPDATE fines 
                        SET payment_method = ?, receipt_image = ?, transaction_ref = ?, proof_status = 'pending' 
                        WHERE id = ? AND (member_id = ? OR member_id = ?)
                    ");
                    $stmtUpd->execute([$payment_method, $dbPath, $transaction_ref, $fine_id, $member_db_id, $member_code]);
                    $msg = "✅ Payment receipt uploaded successfully! Library admin will verify and approve your payment.";
                    $msgType = 'success';
                } catch (Exception $e) {
                    $msg = "Error recording receipt: " . htmlspecialchars($e->getMessage());
                    $msgType = 'danger';
                }
            }
        } else {
            $msg = "Invalid file type. Please upload an image (JPG, PNG, WEBP) or PDF receipt.";
            $msgType = 'danger';
        }
    } else {
        $msg = "Please choose a valid payment receipt screenshot or photo to upload.";
        $msgType = 'danger';
    }
}

// Fetch fines
$fines = [];
$total_pending = $total_paid = $total_waived = 0;

if ($member_db_id || $member_code) {
    $stmt = $pdo->prepare("
        SELECT f.*, b.title as book_title, bi.due_date
        FROM fines f
        LEFT JOIN book_issues bi ON f.issue_id = bi.id
        LEFT JOIN books b ON bi.book_id = b.id
        WHERE (f.member_id = ? OR f.member_id = ?)
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$member_db_id, $member_code]);
    $fines = $stmt->fetchAll();

    $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fines WHERE (member_id=? OR member_id=?) AND status='pending'");
    $s->execute([$member_db_id, $member_code]); $total_pending = $s->fetchColumn();
    $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fines WHERE (member_id=? OR member_id=?) AND status='paid'");
    $s->execute([$member_db_id, $member_code]); $total_paid = $s->fetchColumn();
    $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fines WHERE (member_id=? OR member_id=?) AND status='waived'");
    $s->execute([$member_db_id, $member_code]); $total_waived = $s->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Fines & Online Payment Receipt — Member</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js"></script>
    <style>
        :root {
            --bg: var(--bg-primary, #0f172a);
            --sidebar: var(--sidebar-bg, #111827);
            --card-bg: var(--bg-card, rgba(30,41,59,0.85));
            --primary: #4f46e5;
            --accent: #f59e0b;
            --text: var(--text-primary, #f1f5f9);
            --text-muted: var(--text-secondary, #94a3b8);
            --border: var(--border, rgba(255,255,255,0.08));
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); display:flex; min-height:100vh; transition: background 0.3s, color 0.3s; }
        
        .sidebar { width:260px; background:var(--sidebar); min-height:100vh; position:fixed; left:0; top:0; bottom:0; overflow-y:auto; z-index:100; border-right:1px solid var(--border); }
        .sidebar-logo { padding:24px 20px; border-bottom:1px solid var(--border); }
        .sidebar-logo h2 { font-size:1.1rem; font-weight:700; color:#4f46e5; }
        .sidebar-logo p { font-size:.75rem; color:var(--text-muted); margin-top:2px; }
        .sidebar-nav { padding:16px 12px; }
        .sidebar-nav a { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; color:var(--text-muted); text-decoration:none; font-size:.875rem; font-weight:500; margin-bottom:4px; transition:all .2s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background:rgba(79,70,229,.15); color:#818cf8; }
        
        .main { margin-left:260px; flex:1; display:flex; flex-direction:column; }
        .navbar { height:64px; background:var(--sidebar); border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; padding:0 28px; position:sticky; top:0; z-index:50; }
        .navbar-left { font-size:1.1rem; font-weight:600; }
        .navbar-right { display:flex; align-items:center; gap:14px; }
        .logout-btn { padding:8px 16px; background:rgba(239,68,68,.15); color:#f87171; border:none; border-radius:8px; font-size:.8rem; cursor:pointer; text-decoration:none; }
        
        .content { padding:28px; flex:1; }
        .page-header { margin-bottom:24px; }
        .page-header h1 { font-size:1.5rem; font-weight:700; }
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-bottom:24px; }
        .stat-card { background:var(--card-bg); border:1px solid var(--border); border-radius:14px; padding:18px; backdrop-filter:blur(10px); }
        .stat-num { font-size:1.8rem; font-weight:700; color:var(--text); }
        .stat-label { font-size:.78rem; color:var(--text-muted); margin-top:4px; }
        
        .card { background:var(--card-bg); border:1px solid var(--border); border-radius:16px; overflow:hidden; padding:24px; margin-bottom:24px; backdrop-filter:blur(10px); }
        
        .payment-methods-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px,1fr)); gap:16px; margin-bottom:20px; }
        .method-box { background:rgba(15,23,42,0.6); border:1px solid var(--border); border-radius:12px; padding:16px; }
        .method-box h4 { font-size:0.95rem; color:#818cf8; margin-bottom:6px; display:flex; align-items:center; gap:6px; }
        .method-box p { font-size:0.82rem; color:var(--text-muted); margin-top:2px; }
        .method-box strong { color:var(--text); font-weight:600; }
        
        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th { padding:12px 16px; text-align:left; font-size:.73rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid var(--border); }
        td { padding:13px 16px; font-size:.875rem; color:var(--text); border-bottom:1px solid var(--border); }
        tr:hover td { background:rgba(255,255,255,.02); }
        
        .sbadge { padding:4px 10px; border-radius:20px; font-size:.72rem; font-weight:600; display:inline-block; }
        .pending { background:rgba(245,158,11,.15); color:#fbbf24; }
        .paid { background:rgba(16,185,129,.15); color:#34d399; }
        .waived { background:rgba(59,130,246,.15); color:#60a5fa; }
        .proof-pending { background:rgba(168,85,247,0.2); color:#c084fc; border:1px solid rgba(168,85,247,0.4); }
        
        .btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:8px; font-size:0.82rem; font-weight:600; border:none; cursor:pointer; background:linear-gradient(135deg,#4f46e5,#7c3aed); color:white; text-decoration:none; }
        .form-control { width:100%; padding:8px 12px; background:var(--input-bg, #0f172a); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:0.85rem; }
        
        .alert { padding:14px 18px; border-radius:10px; margin-bottom:20px; font-size:0.9rem; }
        .alert-success { background:rgba(16,185,129,0.15); color:#34d399; border:1px solid rgba(16,185,129,0.3); }
        .alert-danger { background:rgba(239,68,68,0.15); color:#f87171; border:1px solid rgba(239,68,68,0.3); }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <h2>📚 <?= getSiteName($pdo) ?></h2>
            <p>Member Portal</p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="search.php">🔍 Search Books</a>
            <a href="borrow_history.php">📋 My Borrows</a>
            <a href="due_dates.php">📅 Due Dates</a>
            <a href="fines.php" class="active">💰 My Fines</a>
            <a href="reservations.php">🔖 Reservations</a>
            <a href="profile.php">👤 Profile</a>
            <a href="change_password.php">🔒 Change Password</a>
            <a href="../logout.php">🚪 Logout</a>
        </nav>
    </aside>

    <div class="main">
        <nav class="navbar">
            <div class="navbar-left">My Fines & Online Payment Receipt</div>
            <div class="navbar-right">
                <button type="button" class="theme-toggle-btn" onclick="toggleTheme()">☀️ Light Mode</button>
                <?= renderUserAvatar(32) ?>
                <span><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></span>
                <span class="badge" style="background:rgba(59,130,246,.2);color:#60a5fa;padding:4px 12px;border-radius:20px;font-size:0.7rem;font-weight:700;">MEMBER</span>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </nav>

        <div class="content">
            <div class="page-header">
                <h1>💰 My Fines & Payment Options</h1>
                <p style="color:var(--text-muted); font-size:0.9rem;">Pay fines via Telebirr, Mobile Banking, or Bank Transfer and upload your receipt screenshot for instant verification.</p>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card"><div class="stat-num" style="color:#f59e0b;">$<?= number_format($total_pending,2) ?></div><div class="stat-label">Pending Fines</div></div>
                <div class="stat-card"><div class="stat-num" style="color:#10b981;">$<?= number_format($total_paid,2) ?></div><div class="stat-label">Total Paid</div></div>
                <div class="stat-card"><div class="stat-num" style="color:#60a5fa;">$<?= number_format($total_waived,2) ?></div><div class="stat-label">Total Waived</div></div>
            </div>

            <!-- Payment Accounts Information Box -->
            <div class="card">
                <h3 style="font-size:1.1rem; margin-bottom:14px; color:#818cf8;">💳 Payment Methods & Bank Account Info</h3>
                <div class="payment-methods-grid">
                    <div class="method-box">
                        <h4>📱 Telebirr Payment</h4>
                        <p>Merchant / Phone: <strong>0911 22 33 44</strong></p>
                        <p>Account Name: <strong>City Public Library</strong></p>
                    </div>
                    <div class="method-box">
                        <h4>🏦 Commercial Bank of Ethiopia (CBE)</h4>
                        <p>CBE Account: <strong>1000123456789</strong></p>
                        <p>Account Name: <strong>City Public Library</strong></p>
                    </div>
                    <div class="method-box">
                        <h4>🏛️ Mobile Banking / CBE Birr</h4>
                        <p>Transfer to CBE Account <strong>1000123456789</strong></p>
                        <p>Include Fine ID in transfer description.</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 style="font-size:1.1rem; margin-bottom:14px;">Fine History & Receipt Upload</h3>
                <?php if (empty($fines)): ?>
                    <div style="padding:32px; text-align:center; color:var(--text-muted);">🎉 You have no fines recorded.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Fine ID</th>
                                <th>Book Title</th>
                                <th>Reason</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Payment Receipt Upload</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fines as $row): 
                                $st = $row['status'];
                                $proofSt = $row['proof_status'] ?? 'none';
                            ?>
                            <tr>
                                <td>#<?= $row['id'] ?></td>
                                <td><strong><?= htmlspecialchars($row['book_title'] ?? 'N/A') ?></strong></td>
                                <td><?= htmlspecialchars($row['reason'] ?? 'Overdue fine') ?></td>
                                <td><strong style="color:<?= $st==='paid'?'#34d399':($st==='pending'?'#fbbf24':'#60a5fa') ?>;">$<?= number_format($row['amount'],2) ?></strong></td>
                                <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                <td>
                                    <span class="sbadge <?= $st ?>"><?= strtoupper($st) ?></span>
                                    <?php if ($st === 'pending' && $proofSt === 'pending'): ?>
                                        <br><span class="sbadge proof-pending" style="margin-top:4px;">⏳ Verification Pending</span>
                                    <?php elseif ($st === 'pending' && $proofSt === 'rejected'): ?>
                                        <br><span class="sbadge" style="background:rgba(239,68,68,0.2); color:#f87171; margin-top:4px;">❌ Receipt Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($st === 'pending'): ?>
                                        <form method="POST" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:6px; max-width:260px;">
                                            <input type="hidden" name="fine_id" value="<?= $row['id'] ?>">
                                            <select name="payment_method" class="form-control" style="font-size:0.78rem; padding:4px 8px;" required>
                                                <option value="Telebirr">📱 Telebirr</option>
                                                <option value="Mobile Banking">🏦 Mobile Banking (CBE/Awash/Dashen)</option>
                                                <option value="Bank Slip Deposit">🏛️ Bank Deposit Slip</option>
                                            </select>
                                            <input type="text" name="transaction_ref" class="form-control" style="font-size:0.78rem; padding:4px 8px;" placeholder="Transaction Ref / Rec. No." value="<?= htmlspecialchars($row['transaction_ref'] ?? '') ?>">
                                            <input type="file" name="receipt_image" accept="image/*,.pdf" style="font-size:0.75rem; color:var(--text-muted);" required>
                                            <button type="submit" name="upload_receipt" class="btn" style="padding:4px 10px; font-size:0.78rem;">
                                                📤 Upload Receipt
                                            </button>
                                        </form>
                                        <?php if (!empty($row['receipt_image'])): ?>
                                            <div style="margin-top:6px;">
                                                <a href="../<?= htmlspecialchars($row['receipt_image']) ?>" target="_blank" style="color:#818cf8; font-size:0.78rem; text-decoration:underline;">
                                                    🖼️ View Uploaded Receipt
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#34d399; font-size:0.85rem;">✅ Payment Verified</span>
                                        <?php if (!empty($row['receipt_image'])): ?>
                                            <br><a href="../<?= htmlspecialchars($row['receipt_image']) ?>" target="_blank" style="color:#818cf8; font-size:0.75rem; text-decoration:underline;">View Receipt</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
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
