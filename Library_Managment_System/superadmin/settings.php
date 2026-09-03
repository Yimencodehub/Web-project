<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['superadmin']);

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        try {
            $pdo->beginTransaction();
            
            // 1. Update system_settings
            $settings = [
                'library_name'         => trim($_POST['library_name'] ?? ''),
                'library_address'      => trim($_POST['library_address'] ?? ''),
                'library_phone'        => trim($_POST['library_phone'] ?? ''),
                'library_email'        => trim($_POST['library_email'] ?? ''),
                'library_hours'        => trim($_POST['library_hours'] ?? ''),
                'max_borrow_days'      => (int)($_POST['max_borrow_days'] ?? 14),
                'max_books_per_member' => (int)($_POST['max_books_per_member'] ?? 5)
            ];
            
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            foreach ($settings as $key => $val) {
                $stmt->execute([$key, $val]);
            }
            
            // 2. Update library_info table (about and rules)
            $stmtInfo = $pdo->prepare("INSERT INTO library_info (info_key, info_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE info_value = VALUES(info_value)");
            if (isset($_POST['about_text'])) {
                $stmtInfo->execute(['about', trim($_POST['about_text'])]);
            }
            if (isset($_POST['rules_text'])) {
                $stmtInfo->execute(['rules', trim($_POST['rules_text'])]);
            }
            if (isset($_POST['library_hours'])) {
                $stmtInfo->execute(['timings', trim($_POST['library_hours'])]);
            }
            
            $pdo->commit();
            $msg = "✅ Global settings updated successfully!";
            $msgType = "success";
            
            logAudit($pdo, $_SESSION['user_id'], 'update_settings', "Updated global system settings & library info");
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "Error updating settings: " . $e->getMessage();
            $msgType = "danger";
        }
    }
}

// Fetch current system_settings
$current_settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}

// Fetch library_info
$info = [];
try {
    $stmt = $pdo->query("SELECT info_key, info_value FROM library_info");
    while ($row = $stmt->fetch()) {
        $info[$row['info_key']] = $row['info_value'];
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Global System Settings — Superadmin</title>
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
            --primary-hover: #4338ca;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
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
        
        .card { background: var(--card-bg); backdrop-filter: blur(10px); border-radius: 14px; border: 1px solid var(--border); padding: 30px; max-width: 840px; box-shadow: var(--shadow); }
        
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; color: var(--text-muted); font-weight: 500; font-size: 0.9rem; }
        .form-control { width: 100%; padding: 12px 14px; background: var(--input-bg, #0f172a); border: 1px solid var(--border); border-radius: 8px; color: var(--text-main); outline: none; transition: border-color 0.3s; font-size: 0.9rem; }
        .form-control:focus { border-color: var(--primary); }
        textarea.form-control { min-height: 100px; resize: vertical; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .btn { padding: 12px 24px; border-radius: 8px; border: none; cursor: pointer; font-size: 0.95rem; font-weight: 600; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; color: white; background: linear-gradient(135deg, #4f46e5, #7c3aed); }
        .btn:hover { transform: translateY(-1px); }
        
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 24px; font-size: 0.9rem; }
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        .alert-danger { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
        
        .section-title { font-size: 1.15rem; margin: 30px 0 15px 0; border-bottom: 1px solid var(--border); padding-bottom: 10px; color: var(--accent); font-weight: 600; }
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
            <li><a href="settings.php" class="active"><i class="fas fa-cogs"></i> System Settings</a></li>
            <li><a href="backup.php"><i class="fas fa-database"></i> Backup & Restore</a></li>
            <li><a href="audit_logs.php"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-pie"></i> Advanced Reports</a></li>
            <li><a href="fine_config.php"><i class="fas fa-money-bill-wave"></i> Fine Config</a></li>
            <li style="margin-top:auto;"><a href="../logout.php" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="topbar">
            <div>
                <h1 style="font-size: 1.6rem; font-weight: 700;">Global System Settings</h1>
                <p style="color: var(--text-muted); font-size: 0.88rem; margin-top: 4px;">Manage library details, borrowing limits, and public info pages.</p>
            </div>
            <div>
                <button type="button" class="theme-toggle-btn" onclick="toggleTheme()">☀️ Light Mode</button>
            </div>
        </div>
        
        <?php if($msg): ?>
            <div class="alert alert-<?php echo $msgType; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <form method="POST">
                <input type="hidden" name="update_settings" value="1">
                
                <h3 class="section-title" style="margin-top:0;">📖 Basic Information</h3>
                <div class="form-group">
                    <label class="form-label">Library Name</label>
                    <input type="text" name="library_name" class="form-control" value="<?php echo htmlspecialchars($current_settings['library_name'] ?? 'City Public Library'); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" name="library_address" class="form-control" value="<?php echo htmlspecialchars($current_settings['library_address'] ?? ''); ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Contact Phone</label>
                        <input type="text" name="library_phone" class="form-control" value="<?php echo htmlspecialchars($current_settings['library_phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Email</label>
                        <input type="email" name="library_email" class="form-control" value="<?php echo htmlspecialchars($current_settings['library_email'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Opening Hours</label>
                    <input type="text" name="library_hours" class="form-control" value="<?php echo htmlspecialchars($current_settings['library_hours'] ?? 'Mon-Fri: 8AM-8PM, Sat-Sun: 10AM-6PM'); ?>" placeholder="e.g., Mon-Fri: 8AM-8PM, Sat-Sun: 10AM-6PM">
                </div>
                
                <h3 class="section-title">⚖️ Borrowing Policies</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Max Borrow Days</label>
                        <input type="number" name="max_borrow_days" class="form-control" value="<?php echo htmlspecialchars($current_settings['max_borrow_days'] ?? '14'); ?>" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Books Per Member</label>
                        <input type="number" name="max_books_per_member" class="form-control" value="<?php echo htmlspecialchars($current_settings['max_books_per_member'] ?? '5'); ?>" min="1" required>
                    </div>
                </div>
                
                <h3 class="section-title">📄 Library Info & Public Rules</h3>
                <div class="form-group">
                    <label class="form-label">About Us Section</label>
                    <textarea name="about_text" class="form-control"><?php echo htmlspecialchars($info['about'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Library Rules</label>
                    <textarea name="rules_text" class="form-control"><?php echo htmlspecialchars($info['rules'] ?? ''); ?></textarea>
                </div>
                
                <div style="margin-top: 30px;">
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
