<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['admin','staff','superadmin']);

$user_id = $_SESSION['user_id'];
$message = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute([$full_name, $email, $phone, $address, $user_id]);

        $_SESSION['full_name'] = $full_name;

        // Handle Profile Picture Upload
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $fileTmp  = $_FILES['profile_pic']['tmp_name'];
            $fileName = $_FILES['profile_pic']['name'];
            $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (in_array($fileExt, $allowed)) {
                $targetDir = __DIR__ . '/../uploads/profiles/';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                $newFileName = 'staff_' . $user_id . '_' . time() . '.' . $fileExt;
                $targetPath  = $targetDir . $newFileName;
                $dbPath      = 'uploads/profiles/' . $newFileName;

                if (move_uploaded_file($fileTmp, $targetPath)) {
                    $stmtImg = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                    $stmtImg->execute([$dbPath, $user_id]);
                    $_SESSION['profile_pic'] = $dbPath;
                }
            } else {
                $message = "Invalid file type. Please upload JPG, PNG, or WEBP.";
                $msgType = 'danger';
            }
        }

        $pdo->commit();
        if (!$message) {
            $message = "✅ Profile & Photo updated successfully!";
            $msgType = 'success';
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        $message = "Error: " . htmlspecialchars($e->getMessage());
        $msgType = 'danger';
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$initials = strtoupper(substr($user['full_name'], 0, 1));
if (strpos($user['full_name'], ' ') !== false) {
    $parts = explode(' ', $user['full_name']);
    $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile — Management Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js"></script>
    <style>
        :root {
            --bg: var(--bg-primary, #0f172a);
            --sidebar: var(--sidebar-bg, #111827);
            --card-bg: var(--bg-card, rgba(30, 41, 59, 0.85));
            --primary: #4f46e5;
            --accent: #f59e0b;
            --text-main: var(--text-primary, #f8fafc);
            --text-muted: var(--text-secondary, #94a3b8);
            --border: var(--border, #334155);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; transition: background 0.3s, color 0.3s; }
        
        .sidebar { width: 260px; background-color: var(--sidebar); height: 100vh; display: flex; flex-direction: column; border-right: 1px solid var(--border); }
        .sidebar-header { padding: 20px; font-size: 1.25rem; font-weight: bold; color: var(--primary); border-bottom: 1px solid var(--border); }
        .sidebar-nav { padding: 20px 0; flex-grow: 1; overflow-y: auto; }
        .sidebar-link { display: block; padding: 12px 20px; color: var(--text-muted); text-decoration: none; transition: 0.3s; }
        .sidebar-link:hover, .sidebar-link.active { background-color: var(--card-bg); color: var(--text-main); border-left: 3px solid var(--primary); }
        
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .topbar { height: 64px; background-color: var(--bg); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; padding: 0 24px; }
        .topbar-right { display: flex; align-items: center; gap: 15px; }
        .role-badge { background: var(--primary); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .btn-logout { color: #ef4444; text-decoration: none; font-weight: 500; }
        
        .content { flex-grow: 1; padding: 24px; overflow-y: auto; }
        .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 14px; padding: 28px; backdrop-filter: blur(10px); margin-bottom: 24px; max-width: 800px; }
        
        .avatar-section { display: flex; flex-direction: column; align-items: center; width: 220px; text-align: center; }
        .avatar { width: 130px; height: 130px; border-radius: 50%; background: linear-gradient(135deg, #4f46e5, #7c3aed); display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 700; color: white; margin-bottom: 16px; object-fit: cover; border: 3px solid #4f46e5; }
        
        .form-section { flex: 1; min-width: 280px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; color: var(--text-muted); margin-bottom: 6px; font-size: 0.85rem; font-weight: 500; }
        .form-control { width: 100%; padding: 10px 14px; background: var(--input-bg, #0f172a); border: 1px solid var(--border); border-radius: 8px; color: var(--text-main); font-size: 0.9rem; outline: none; }
        
        .btn { padding: 10px 20px; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; }
        .btn:hover { transform: translateY(-1px); }
        
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
        .alert-danger { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><?= getSiteName($pdo) ?></div>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-link">Dashboard</a>
            <a href="books/index.php" class="sidebar-link">Books</a>
            <a href="categories/index.php" class="sidebar-link">Categories</a>
            <a href="members/index.php" class="sidebar-link">Members</a>
            <a href="issue/index.php" class="sidebar-link">Issue Book</a>
            <a href="renew/index.php" class="sidebar-link">Renew Book</a>
            <a href="returns/index.php" class="sidebar-link">Process Return</a>
            <a href="fines/index.php" class="sidebar-link">Fines</a>
            <a href="inventory/index.php" class="sidebar-link">Inventory</a>
            <a href="reports/index.php" class="sidebar-link">Reports</a>
            <a href="profile.php" class="sidebar-link active">My Profile</a>
            <a href="users/index.php" class="sidebar-link">Users</a>
            <a href="../logout.php" class="sidebar-link" style="color: #ef4444; margin-top: 15px; border-top: 1px solid var(--border);"><i class="fas fa-sign-out-alt"></i> 🚪 Logout</a>
        </div>
    </div>
    
    <div class="main-wrapper">
        <div class="topbar">
            <div></div>
            <div class="topbar-right">
                <button type="button" class="theme-toggle-btn" onclick="toggleTheme()">☀️ Light Mode</button>
                <?php if (!empty($user['profile_pic']) && file_exists(__DIR__ . '/../' . $user['profile_pic'])): ?>
                    <img src="../<?= htmlspecialchars($user['profile_pic']) ?>" alt="Avatar" class="user-avatar-img">
                <?php endif; ?>
                <span><?= htmlspecialchars($user['full_name']) ?></span>
                <span class="role-badge"><?= strtoupper($user['role']) ?></span>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
        <div class="content">
            <h1 style="font-size:1.5rem; font-weight:600; margin-bottom:20px;">👤 Profile Management</h1>

            <?php if ($message): ?>
                <div class="alert alert-<?= $msgType ?>"><?= $message ?></div>
            <?php endif; ?>

            <div class="card">
                <form method="POST" enctype="multipart/form-data" style="display:flex; width:100%; gap:32px; flex-wrap:wrap;">
                    <!-- Avatar Upload -->
                    <div class="avatar-section">
                        <?php if (!empty($user['profile_pic']) && file_exists(__DIR__ . '/../' . $user['profile_pic'])): ?>
                            <img src="../<?= htmlspecialchars($user['profile_pic']) ?>" alt="Avatar" class="avatar">
                        <?php else: ?>
                            <div class="avatar"><?= $initials ?></div>
                        <?php endif; ?>

                        <div class="form-group" style="width:100%;">
                            <label for="profile_pic">Change Profile Photo</label>
                            <input type="file" name="profile_pic" id="profile_pic" accept="image/*" class="form-control">
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="form-section">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled style="opacity:0.7;">
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" class="btn">💾 Save Profile & Photo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
