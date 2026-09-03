<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['member']);

$user_id = $_SESSION['user_id'];
$message = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    try {
        $pdo->beginTransaction();

        // 1. Update basic info in users table
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute([$full_name, $email, $phone, $address, $user_id]);

        // Update session full_name
        $_SESSION['full_name'] = $full_name;

        // 2. Handle Profile Picture Upload
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
                $newFileName = 'user_' . $user_id . '_' . time() . '.' . $fileExt;
                $targetPath  = $targetDir . $newFileName;
                $dbPath      = 'uploads/profiles/' . $newFileName;

                if (move_uploaded_file($fileTmp, $targetPath)) {
                    $stmtImg = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                    $stmtImg->execute([$dbPath, $user_id]);
                    $_SESSION['profile_pic'] = $dbPath;
                }
            } else {
                $message = "Invalid file type for profile picture. Please upload JPG, PNG, or WEBP.";
                $msgType = 'danger';
            }
        }

        $pdo->commit();
        if (!$message) {
            $message = "✅ Profile updated successfully!";
            $msgType = 'success';
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        $message = "Error updating profile: " . htmlspecialchars($e->getMessage());
        $msgType = 'danger';
    }
}

// Fetch user data
$stmt = $pdo->prepare("SELECT u.*, m.member_id, m.membership_type, m.join_date, m.expiry_date FROM users u LEFT JOIN members m ON u.id = m.user_id WHERE u.id = ?");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Member Portal</title>
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
            --text: var(--text-primary, #f8fafc);
            --text-muted: var(--text-secondary, #94a3b8);
            --border: var(--border, rgba(255,255,255,0.08));
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; transition: background 0.3s, color 0.3s; }
        
        .sidebar { width: 260px; background: var(--sidebar); min-height: 100vh; position: fixed; left: 0; top: 0; bottom: 0; overflow-y: auto; z-index: 100; border-right: 1px solid var(--border); }
        .sidebar-logo { padding: 24px 20px; border-bottom: 1px solid var(--border); }
        .sidebar-logo h2 { font-size: 1.1rem; font-weight: 700; color: #4f46e5; }
        .sidebar-logo p { font-size: 0.75rem; color: var(--text-muted); margin-top: 2px; }
        .sidebar-nav { padding: 16px 12px; }
        .sidebar-nav a { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 10px; color: var(--text-muted); text-decoration: none; font-size: 0.875rem; font-weight: 500; margin-bottom: 4px; transition: all 0.2s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(79,70,229,0.15); color: #818cf8; }
        
        .main { margin-left: 260px; flex: 1; display: flex; flex-direction: column; }
        .navbar { height: 64px; background: var(--sidebar); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 28px; position: sticky; top: 0; z-index: 50; }
        .navbar-left { font-size: 1.1rem; font-weight: 600; }
        .navbar-right { display: flex; align-items: center; gap: 14px; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: .7rem; font-weight: 700; background: rgba(59,130,246,.2); color: #60a5fa; }
        .logout-btn { padding: 8px 16px; background: rgba(239,68,68,.15); color: #f87171; border: none; border-radius: 8px; font-size: .8rem; cursor: pointer; text-decoration: none; }
        
        .content { padding: 32px; flex: 1; }
        .card { max-width: 840px; background: var(--card-bg); backdrop-filter: blur(14px); border: 1px solid var(--border); border-radius: 18px; padding: 32px; display: flex; gap: 32px; box-shadow: var(--shadow); flex-wrap: wrap; }
        
        .avatar-section { display: flex; flex-direction: column; align-items: center; width: 220px; text-align: center; }
        .avatar { width: 130px; height: 130px; border-radius: 50%; background: linear-gradient(135deg, #4f46e5, #7c3aed); display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 700; color: white; margin-bottom: 16px; object-fit: cover; border: 3px solid #4f46e5; box-shadow: 0 4px 20px rgba(79,70,229,0.3); }
        
        .form-section { flex: 1; min-width: 280px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; color: var(--text-muted); margin-bottom: 6px; font-size: 0.85rem; font-weight: 500; }
        input[type=text], input[type=email], textarea { width: 100%; padding: 11px 14px; border-radius: 10px; border: 1px solid var(--border); background: var(--input-bg, #0f172a); color: var(--text); font-family: inherit; font-size: 0.9rem; outline: none; }
        input[type=file] { color: var(--text-muted); font-size: 0.82rem; }
        
        .btn { padding: 12px 24px; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: all 0.2s; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(79,70,229,0.4); }
        
        .meta-info { margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border); display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .meta-item { color: var(--text-muted); font-size: 0.88rem; }
        .meta-item strong { color: var(--text); display: block; margin-top: 2px; }
        
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 24px; font-size: 0.9rem; }
        .alert-success { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
        .alert-danger { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
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
            <a href="fines.php">💰 My Fines</a>
            <a href="reservations.php">🔖 Reservations</a>
            <a href="profile.php" class="active">👤 Profile</a>
            <a href="change_password.php">🔒 Change Password</a>
            <a href="../logout.php">🚪 Logout</a>
        </nav>
    </aside>

    <div class="main">
        <nav class="navbar">
            <div class="navbar-left">My Profile</div>
            <div class="navbar-right">
                <button type="button" class="theme-toggle-btn" onclick="toggleTheme()">☀️ Light Mode</button>
                <span><?= htmlspecialchars($user['full_name']) ?></span>
                <span class="badge">MEMBER</span>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </nav>

        <div class="content">
            <h1 style="font-size:1.5rem; font-weight:700; margin-bottom:20px;">👤 Profile Management</h1>

            <?php if ($message): ?>
                <div class="alert alert-<?= $msgType ?>"><?= $message ?></div>
            <?php endif; ?>

            <div class="card">
                <form method="POST" enctype="multipart/form-data" style="display:flex; width:100%; gap:32px; flex-wrap:wrap;">
                    <!-- Avatar Upload Section -->
                    <div class="avatar-section">
                        <?php if (!empty($user['profile_pic']) && file_exists(__DIR__ . '/../' . $user['profile_pic'])): ?>
                            <img src="../<?= htmlspecialchars($user['profile_pic']) ?>" alt="Avatar" class="avatar">
                        <?php else: ?>
                            <div class="avatar"><?= $initials ?></div>
                        <?php endif; ?>

                        <div class="form-group" style="width:100%;">
                            <label for="profile_pic">Change Profile Photo</label>
                            <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
                        </div>
                    </div>

                    <!-- Details Section -->
                    <div class="form-section">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" class="btn">💾 Save Profile & Photo</button>

                        <div class="meta-info">
                            <div class="meta-item">
                                Member ID:
                                <strong><?= htmlspecialchars($user['member_id'] ?? 'LIB-PENDING') ?></strong>
                            </div>
                            <div class="meta-item">
                                Membership Type:
                                <strong><?= htmlspecialchars($user['membership_type'] ?? 'Standard') ?></strong>
                            </div>
                            <div class="meta-item">
                                Member Since:
                                <strong><?= $user['join_date'] ? date('M d, Y', strtotime($user['join_date'])) : 'N/A' ?></strong>
                            </div>
                            <div class="meta-item">
                                Valid Until:
                                <strong><?= $user['expiry_date'] ? date('M d, Y', strtotime($user['expiry_date'])) : 'N/A' ?></strong>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
