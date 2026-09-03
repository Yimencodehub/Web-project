<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['superadmin']);

$msg = '';
$msgType = '';
$backupDir = __DIR__ . '/backups/';

if (!file_exists($backupDir)) {
    mkdir($backupDir, 0777, true);
}

// 1. Handle Download Backup
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $filePath = $backupDir . $file;
    if (file_exists($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'sql') {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

// 2. Handle Delete Backup
if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $filePath = $backupDir . $file;
    if (file_exists($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'sql') {
        unlink($filePath);
        $msg = "Backup file $file deleted successfully.";
        $msgType = "success";
    }
}

// 3. Handle Create Backup (PHP Native Database Dumper)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    try {
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . $filename;
        
        $sqlDump  = "-- ========================================================\n";
        $sqlDump .= "-- Library Management System Database Backup\n";
        $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sqlDump .= "-- Host: localhost | Database: " . DB_NAME . "\n";
        $sqlDump .= "-- ========================================================\n\n";
        $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sqlDump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sqlDump .= "START TRANSACTION;\n";
        $sqlDump .= "SET time_zone = \"+00:00\";\n\n";

        // Get all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $sqlDump .= "-- --------------------------------------------------------\n";
            $sqlDump .= "-- Table structure for table `$table`\n";
            $sqlDump .= "-- --------------------------------------------------------\n";
            $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n";
            
            $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $sqlDump .= $createTable[1] . ";\n\n";

            // Fetch table data
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $sqlDump .= "-- Dumping data for table `$table`\n";
                foreach ($rows as $row) {
                    $fields = array_keys($row);
                    $values = array_values($row);
                    
                    $escapedValues = array_map(function($v) use ($pdo) {
                        if ($v === null) return "NULL";
                        return $pdo->quote($v);
                    }, $values);

                    $sqlDump .= "INSERT INTO `$table` (`" . implode("`, `", $fields) . "`) VALUES (" . implode(", ", $escapedValues) . ");\n";
                }
                $sqlDump .= "\n";
            }
        }

        $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $sqlDump .= "COMMIT;\n";

        file_put_contents($filepath, $sqlDump);

        $msg = "✅ Backup created successfully! File: $filename (" . round(filesize($filepath) / 1024, 2) . " KB)";
        $msgType = "success";
        logAudit($pdo, $_SESSION['user_id'], 'backup', "Created database backup $filename");
    } catch (Exception $e) {
        $msg = "Error creating backup: " . $e->getMessage();
        $msgType = "danger";
    }
}

// 4. Handle Restore Backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['backup_file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));
        
        if ($ext === 'sql') {
            try {
                $sqlContent = file_get_contents($tmpName);
                $pdo->exec($sqlContent);
                $msg = "✅ Database restored successfully from uploaded backup!";
                $msgType = "success";
                logAudit($pdo, $_SESSION['user_id'], 'restore', "Restored database from uploaded backup");
            } catch (Exception $e) {
                $msg = "Error restoring database: " . $e->getMessage();
                $msgType = "danger";
            }
        } else {
            $msg = "Invalid file type. Please upload a valid .sql file.";
            $msgType = "danger";
        }
    }
}

// Get DB stats
$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()");
$tablesCount = $stmt->fetchColumn();

// List existing backups
$backups = [];
if ($handle = opendir($backupDir)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != ".." && pathinfo($entry, PATHINFO_EXTENSION) == 'sql') {
            $backups[] = [
                'name' => $entry,
                'size' => round(filesize($backupDir . $entry) / 1024, 2), // KB
                'date' => date("Y-m-d H:i:s", filemtime($backupDir . $entry))
            ];
        }
    }
    closedir($handle);
}
usort($backups, function($a, $b) { return $b['date'] <=> $a['date']; });
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Backup & Restore — Superadmin</title>
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
        
        .card { background: var(--card-bg); backdrop-filter: blur(10px); border-radius: 14px; border: 1px solid var(--border); padding: 25px; margin-bottom: 25px; box-shadow: var(--shadow); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card-title { font-size: 1.2rem; font-weight: 600; }
        
        .btn { padding: 10px 18px; border-radius: 8px; border: none; cursor: pointer; font-size: 0.9rem; font-weight: 600; color: white; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #4f46e5, #7c3aed); }
        .btn-success { background: #10b981; }
        .btn-danger { background: #ef4444; }
        
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
        .alert-warning { background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); color: #fbbf24; }
        .alert-danger { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border); font-size: 0.88rem; }
        th { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 600; }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }
        
        .form-control { padding: 8px 12px; background: var(--input-bg, #0f172a); border: 1px solid var(--border); border-radius: 6px; color: var(--text-main); }
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
            <li><a href="backup.php" class="active"><i class="fas fa-database"></i> Backup & Restore</a></li>
            <li><a href="audit_logs.php"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-pie"></i> Advanced Reports</a></li>
            <li><a href="fine_config.php"><i class="fas fa-money-bill-wave"></i> Fine Config</a></li>
            <li style="margin-top:auto;"><a href="../logout.php" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="topbar">
            <div>
                <h1 style="font-size: 1.6rem; font-weight: 700;">Database Backup & Restore</h1>
                <p style="color: var(--text-muted); font-size: 0.88rem; margin-top: 4px;">Safely backup full SQL schema and data, download copies, or restore from files.</p>
            </div>
            <div>
                <button type="button" class="theme-toggle-btn" onclick="toggleTheme()">☀️ Light Mode</button>
            </div>
        </div>
        
        <?php if($msg): ?>
            <div class="alert alert-<?php echo $msgType; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> <strong>Important:</strong> Regular backups are crucial for preventing data loss. Download your backups and store them securely.
        </div>
        
        <!-- Backup Action Card -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Current Database Status</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px;">Total Tables: <strong><?php echo $tablesCount; ?></strong></p>
                </div>
                <form method="POST">
                    <button type="submit" name="create_backup" class="btn btn-primary"><i class="fas fa-download"></i> Create New Backup</button>
                </form>
            </div>
        </div>

        <!-- Restore Backup Card -->
        <div class="card">
            <h3 class="card-title" style="margin-bottom: 15px;">📥 Restore Database from SQL File</h3>
            <form method="POST" enctype="multipart/form-data" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <input type="file" name="backup_file" accept=".sql" class="form-control" required>
                <button type="submit" name="restore_backup" class="btn btn-success" onclick="return confirm('⚠️ Restoring will overwrite existing tables. Are you sure you want to proceed?')">
                    <i class="fas fa-upload"></i> Restore Database
                </button>
            </form>
        </div>
        
        <!-- Existing Backups List -->
        <div class="card">
            <h3 class="card-title">Existing Backups</h3>
            <table>
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Size</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($backups)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 25px;">No backups found yet. Click "Create New Backup" above.</td></tr>
                    <?php else: ?>
                        <?php foreach($backups as $b): ?>
                            <tr>
                                <td><i class="fas fa-file-code" style="color:#818cf8; margin-right:6px;"></i> <strong><?php echo htmlspecialchars($b['name']); ?></strong></td>
                                <td><?php echo $b['size']; ?> KB</td>
                                <td><?php echo $b['date']; ?></td>
                                <td>
                                    <a href="backup.php?download=<?php echo urlencode($b['name']); ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 0.8rem;"><i class="fas fa-download"></i> Download</a>
                                    <a href="backup.php?delete=<?php echo urlencode($b['name']); ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 0.8rem;" onclick="return confirm('Delete backup <?php echo $b['name']; ?>?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
