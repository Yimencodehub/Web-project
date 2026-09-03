<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['superadmin']);

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_fines'])) {
        $fine_per_day = (float)($_POST['fine_per_day'] ?? 5.00);
        $max_fine     = (float)($_POST['max_fine'] ?? 500.00);
        $grace_period = (int)($_POST['grace_period'] ?? 1);
        $calc_method  = trim($_POST['calc_method'] ?? 'flat');
        
        try {
            // Update or Insert into fine_settings table
            $stmt = $pdo->prepare("
                INSERT INTO fine_settings (id, fine_per_day, max_fine, grace_period_days, calc_method) 
                VALUES (1, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    fine_per_day = VALUES(fine_per_day),
                    max_fine = VALUES(max_fine),
                    grace_period_days = VALUES(grace_period_days),
                    calc_method = VALUES(calc_method)
            ");
            $stmt->execute([$fine_per_day, $max_fine, $grace_period, $calc_method]);
            
            $msg = "✅ Fine configuration updated successfully!";
            $msgType = "success";
            logAudit($pdo, $_SESSION['user_id'], 'update_fines', "Updated fine rate settings: $$fine_per_day/day, Grace: $grace_period days, Max: $$max_fine, Method: $calc_method");
        } catch (Exception $e) {
            $msg = "Error updating fine config: " . $e->getMessage();
            $msgType = "danger";
        }
    }
}

// Fetch current settings
$settings = ['fine_per_day' => 5.00, 'max_fine' => 500.00, 'grace_period_days' => 1, 'calc_method' => 'flat'];
try {
    $stmt = $pdo->query("SELECT * FROM fine_settings WHERE id = 1");
    if ($row = $stmt->fetch()) {
        $settings = array_merge($settings, $row);
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fine Rate Configuration — Superadmin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js"></script>
    <style>
        :root {
            --bg: var(--bg-primary, #0f172a);
            --sidebar: var(--sidebar-bg, #111827);
            --card-bg: var(--bg-card, rgba(30, 41, 59, 0.85));
            --primary: #4f46e5;
            --accent: #f59e0b;
            --danger: #ef4444;
            --success: #10b981;
            --text-main: var(--text-primary, #f8fafc);
            --text-muted: var(--text-secondary, #94a3b8);
            --border: var(--border, rgba(255, 255, 255, 0.1));
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg); color: var(--text-main); display: flex; min-height: 100vh; transition: background 0.3s, color 0.3s; }
        .sidebar { width: 260px; background-color: var(--sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; position: fixed; left: 0; top: 0; bottom: 0; overflow-y: auto; }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid var(--border); }
        .sidebar-header h2 { font-size: 1.2rem; color: var(--text-main); font-weight: 700; }
        .nav-links { list-style: none; padding: 20px 0; flex: 1; }
        .nav-links li { padding: 0 16px; margin-bottom: 6px; }
        .nav-links a { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text-muted); padding: 12px 16px; border-radius: 10px; transition: all 0.2s; font-size: 0.9rem; font-weight: 500; }
        .nav-links a:hover, .nav-links a.active { background-color: rgba(79, 70, 229, 0.15); color: var(--primary); }
        .nav-links a i { width: 20px; }
        
        .main-content { margin-left: 260px; flex: 1; padding: 30px; overflow-y: auto; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        
        .grid-layout { display: grid; grid-template-columns: 2fr 1.2fr; gap: 30px; }
        @media (max-width: 900px) { .grid-layout { grid-template-columns: 1fr; } }
        
        .card { background: var(--card-bg); backdrop-filter: blur(10px); border-radius: 14px; border: 1px solid var(--border); padding: 28px; box-shadow: var(--shadow); }
        .card-title { font-size: 1.15rem; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px; font-weight: 600; }
        
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; color: var(--text-muted); font-size: 0.9rem; font-weight: 500; }
        .form-control { width: 100%; padding: 12px 14px; background: var(--input-bg, #0f172a); border: 1px solid var(--border); border-radius: 8px; color: var(--text-main); outline: none; transition: 0.3s; font-size: 0.9rem; }
        .form-control:focus { border-color: var(--primary); }
        select.form-control { appearance: none; }
        
        .btn { padding: 12px 24px; border-radius: 8px; border: none; cursor: pointer; font-size: 0.95rem; font-weight: 600; color: white; background: linear-gradient(135deg, #4f46e5, #7c3aed); transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn:hover { transform: translateY(-1px); }
        
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 24px; font-size: 0.9rem; }
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        .alert-danger { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
        
        /* Preview Calculator */
        .preview-box { background: rgba(15, 23, 42, 0.6); padding: 24px; border-radius: 12px; border: 1px dashed var(--accent); text-align: center; }
        .result-amount { font-size: 2.4rem; color: var(--accent); font-weight: 700; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-book-open" style="color: var(--primary);"></i> <?= getSiteName($pdo) ?></h2>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="admins.php"><i class="fas fa-users-cog"></i> Admins & Staff</a></li>
            <li><a href="settings.php"><i class="fas fa-cogs"></i> System Settings</a></li>
            <li><a href="backup.php"><i class="fas fa-database"></i> Backup & Restore</a></li>
            <li><a href="audit_logs.php"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-pie"></i> Advanced Reports</a></li>
            <li><a href="fine_config.php" class="active"><i class="fas fa-money-bill-wave"></i> Fine Config</a></li>
            <li style="margin-top:auto;"><a href="../logout.php" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="topbar">
            <div>
                <h1 style="font-size: 1.6rem; font-weight: 700;">Fine Rate Configuration</h1>
                <p style="color: var(--text-muted); font-size: 0.88rem; margin-top: 4px;">Set daily overdue penalty rates, grace periods, and maximum fine caps.</p>
            </div>
            <div>
                <button type="button" class="theme-toggle-btn" onclick="toggleTheme()">☀️ Light Mode</button>
            </div>
        </div>
        
        <?php if($msg): ?>
            <div class="alert alert-<?php echo $msgType; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <div class="grid-layout">
            <div class="card">
                <h3 class="card-title">⚙️ Edit Fine Parameters</h3>
                <form method="POST">
                    <input type="hidden" name="update_fines" value="1">
                    <div class="form-group">
                        <label class="form-label">Fine Per Day (Amount in $)</label>
                        <input type="number" step="0.01" name="fine_per_day" id="i_fine" class="form-control" value="<?php echo htmlspecialchars($settings['fine_per_day']); ?>" oninput="calculatePreview()" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Grace Period (Days)</label>
                        <input type="number" name="grace_period" id="i_grace" class="form-control" value="<?php echo htmlspecialchars($settings['grace_period_days']); ?>" oninput="calculatePreview()" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Maximum Fine Cap Amount (0 for no limit)</label>
                        <input type="number" step="0.01" name="max_fine" id="i_max" class="form-control" value="<?php echo htmlspecialchars($settings['max_fine']); ?>" oninput="calculatePreview()" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Calculation Method</label>
                        <select name="calc_method" id="i_method" class="form-control" onchange="calculatePreview()">
                            <option value="flat" <?php echo ($settings['calc_method'] ?? 'flat') === 'flat' ? 'selected' : ''; ?>>Flat Rate (Amount * Days)</option>
                            <option value="progressive" <?php echo ($settings['calc_method'] ?? 'flat') === 'progressive' ? 'selected' : ''; ?>>Progressive (Doubles after 7 days)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Save Configuration</button>
                </form>
            </div>
            
            <div class="card">
                <h3 class="card-title">🧮 Live Preview Calculator</h3>
                <div class="form-group">
                    <label class="form-label">Simulate Days Overdue</label>
                    <input type="number" id="test_days" class="form-control" value="5" min="0" oninput="calculatePreview()">
                </div>
                <div class="preview-box">
                    <p style="color: var(--text-muted); font-size:0.9rem;">Estimated Overdue Fine</p>
                    <div class="result-amount" id="preview_result">$20.00</div>
                    <p style="font-size: 0.82rem; color: #34d399;" id="preview_note">Standard calculation applied.</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function calculatePreview() {
            var days = parseFloat(document.getElementById('test_days').value) || 0;
            var finePerDay = parseFloat(document.getElementById('i_fine').value) || 0;
            var grace = parseFloat(document.getElementById('i_grace').value) || 0;
            var maxFine = parseFloat(document.getElementById('i_max').value) || 0;
            var method = document.getElementById('i_method').value;
            
            var billableDays = Math.max(0, days - grace);
            var total = 0;
            
            if (billableDays > 0) {
                if (method === 'progressive' && billableDays > 7) {
                    total = (7 * finePerDay) + ((billableDays - 7) * finePerDay * 2);
                } else {
                    total = billableDays * finePerDay;
                }
            }
            
            if (maxFine > 0 && total > maxFine) {
                total = maxFine;
            }
            
            document.getElementById('preview_result').innerText = '$' + total.toFixed(2);
            document.getElementById('preview_note').innerText = (billableDays === 0) ? 'Within grace period (No fine).' : 'Calculated for ' + billableDays + ' billable day(s).';
        }
        calculatePreview();
    </script>
</body>
</html>
