<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['superadmin']);

$msg = '';
$msgType = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_user') {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $fullname = trim($_POST['fullname']);
            $password = $_POST['password'];
            $role = $_POST['role'];
            
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, password, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$username, $email, $fullname, $hash, $role]);
                $msg = "User added successfully!";
                $msgType = "success";
                
                logAction($pdo, $_SESSION['user_id'], 'add_user', "Added new user: $username");
            } catch (PDOException $e) {
                $msg = "Error adding user: " . $e->getMessage();
                $msgType = "danger";
            }
        } elseif ($_POST['action'] === 'toggle_status') {
            $user_id = $_POST['user_id'];
            $new_status = $_POST['status'];
            // Prevent changing superadmin status
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $uRole = $stmt->fetchColumn();
            
            if ($uRole === 'superadmin') {
                $msg = "Cannot change status of a superadmin!";
                $msgType = "danger";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $user_id]);
                $msg = "User status updated!";
                $msgType = "success";
            }
        } elseif ($_POST['action'] === 'reset_password') {
            $user_id = $_POST['user_id'];
            $stmt = $pdo->prepare("SELECT role, username FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user['role'] === 'superadmin' && $user_id != $_SESSION['user_id']) {
                $msg = "Cannot reset another superadmin's password!";
                $msgType = "danger";
            } else {
                $temp_pass = substr(md5(uniqid()), 0, 8);
                $hash = password_hash($temp_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hash, $user_id]);
                $msg = "Password reset for {$user['username']}. New password: <strong>$temp_pass</strong>";
                $msgType = "success";
            }
        }
    }
}

// Fetch Users
$roleFilter = $_GET['role'] ?? '';
$query = "SELECT * FROM users";
$params = [];
if ($roleFilter) {
    $query .= " WHERE role = ?";
    $params[] = $roleFilter;
}
$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Admins & Staff</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg: #0f172a; --sidebar: #111827; --card-bg: rgba(30, 41, 59, 0.8); --primary: #4f46e5; --primary-hover: #4338ca; --accent: #f59e0b; --success: #10b981; --danger: #ef4444; --text-main: #f8fafc; --text-muted: #94a3b8; --border: rgba(255, 255, 255, 0.1); }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg); color: var(--text-main); display: flex; min-height: 100vh; }
        /* Sidebar styles matching dashboard */
        .sidebar { width: 250px; background-color: var(--sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid var(--border); }
        .sidebar-header h2 { font-size: 1.2rem; color: var(--text-main); }
        .nav-links { list-style: none; padding: 20px 0; flex: 1; }
        .nav-links li { padding: 0 20px; margin-bottom: 10px; }
        .nav-links a { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text-muted); padding: 12px 15px; border-radius: 8px; transition: all 0.3s; }
        .nav-links a:hover, .nav-links a.active { background-color: rgba(79, 70, 229, 0.1); color: var(--primary); }
        .nav-links a i { width: 20px; }
        
        .main-content { flex: 1; padding: 30px; overflow-y: auto; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        
        .card { background: var(--card-bg); backdrop-filter: blur(10px); border-radius: 12px; border: 1px solid var(--border); padding: 20px; }
        
        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.9rem; font-weight: 500; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; color: white; text-decoration: none; }
        .btn-primary { background: var(--primary); }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-danger { background: var(--danger); }
        .btn-success { background: var(--success); }
        .btn-sm { padding: 5px 10px; font-size: 0.8rem; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--success); }
        .alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--danger); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { color: var(--text-muted); font-weight: 500; font-size: 0.9rem; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .badge-superadmin { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .badge-admin { background: rgba(79, 70, 229, 0.1); color: var(--primary); }
        .badge-staff { background: rgba(245, 158, 11, 0.1); color: var(--accent); }
        .badge-active { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .badge-suspended { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); z-index: 100; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: var(--bg); border: 1px solid var(--border); padding: 30px; border-radius: 12px; width: 100%; max-width: 500px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close-modal { cursor: pointer; color: var(--text-muted); font-size: 1.5rem; }
        
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; color: var(--text-muted); font-size: 0.9rem; }
        .form-control { width: 100%; padding: 10px 15px; background: rgba(15, 23, 42, 0.5); border: 1px solid var(--border); border-radius: 6px; color: white; outline: none; }
        .form-control:focus { border-color: var(--primary); }
        select.form-control { appearance: none; }
        
        .filters { display: flex; gap: 10px; margin-bottom: 20px; }
        .filters select { background: var(--bg); color: white; border: 1px solid var(--border); padding: 8px; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-book-open" style="color: var(--primary);"></i> Library Admin</h2>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="admins.php" class="active"><i class="fas fa-users-cog"></i> Admins & Staff</a></li>
            <li><a href="settings.php"><i class="fas fa-cogs"></i> System Settings</a></li>
            <li><a href="backup.php"><i class="fas fa-database"></i> Backup & Restore</a></li>
            <li><a href="audit_logs.php"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-pie"></i> Advanced Reports</a></li>
            <li><a href="fine_config.php"><i class="fas fa-money-bill-wave"></i> Fine Config</a></li>
            <li><a href="../logout.php" style="color: var(--danger);"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="topbar">
            <h1>User Management</h1>
            <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')"><i class="fas fa-plus"></i> Add New User</button>
        </div>
        
        <?php if($msg): ?>
            <div class="alert alert-<?php echo $msgType; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="filters">
                <form method="GET" style="display:flex; gap:10px;">
                    <select name="role" onchange="this.form.submit()">
                        <option value="">All Roles</option>
                        <option value="superadmin" <?php echo $roleFilter=='superadmin'?'selected':''; ?>>Super Admin</option>
                        <option value="admin" <?php echo $roleFilter=='admin'?'selected':''; ?>>Admin</option>
                        <option value="staff" <?php echo $roleFilter=='staff'?'selected':''; ?>>Staff</option>
                    </select>
                </form>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $user['role']; ?>"><?php echo strtoupper($user['role']); ?></span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span>
                        </td>
                        <td>
                            <div style="display:flex; gap:5px;">
                                <?php if($user['role'] !== 'superadmin' || $user['id'] == $_SESSION['user_id']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-primary" title="Reset Password"><i class="fas fa-key"></i></button>
                                </form>
                                <?php endif; ?>
                                
                                <?php if($user['role'] !== 'superadmin'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="status" value="<?php echo $user['status'] == 'active' ? 'suspended' : 'active'; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $user['status'] == 'active' ? 'btn-danger' : 'btn-success'; ?>" title="<?php echo $user['status'] == 'active' ? 'Suspend' : 'Activate'; ?>">
                                        <i class="fas <?php echo $user['status'] == 'active' ? 'fa-ban' : 'fa-check'; ?>"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New User</h2>
                <span class="close-modal" onclick="document.getElementById('addModal').classList.remove('active')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="fullname" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control" required>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                        <option value="superadmin">Superadmin</option>
                    </select>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn" style="background:var(--border);" onclick="document.getElementById('addModal').classList.remove('active')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
