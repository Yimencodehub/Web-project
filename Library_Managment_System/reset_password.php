<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in → go to dashboard
if (isset($_SESSION['user_id'])) {
    $r = $_SESSION['role'];
    header("Location: $r/dashboard.php");
    exit;
}

$siteName = getSiteName($pdo);
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$isValidToken = false;
$resetRecord = null;
$err = '';
$success = false;

// Validate token
if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $resetRecord = $stmt->fetch();

    if ($resetRecord) {
        $isValidToken = true;
    } else {
        $err = 'Invalid or expired password reset link. Please request a new one.';
    }
} else {
    $err = 'No reset token provided. Please use the link sent to your email.';
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isValidToken) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $err = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $err = 'Passwords do not match.';
    } else {
        try {
            $pdo->beginTransaction();

            // Hash password securely with bcrypt
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            // Update user's password
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $resetRecord['email']]);

            // Mark token as used
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);

            // Log action if user id exists
            $stmtUser = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmtUser->execute([$resetRecord['email']]);
            $user = $stmtUser->fetch();
            if ($user && function_exists('logAction')) {
                logAction($pdo, $user['id'], 'password_reset', 'Password reset successfully using email token');
            }

            $pdo->commit();
            $success = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log($e->getMessage());
            $err = 'An error occurred while resetting your password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — <?= htmlspecialchars($siteName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:'Inter',sans-serif;background:#0f172a;color:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse at 20% 50%,rgba(79,70,229,.15) 0,transparent 60%),radial-gradient(ellipse at 80% 20%,rgba(124,58,237,.12) 0,transparent 50%);pointer-events:none}
  .card{background:rgba(30,41,59,.85);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:44px 40px;width:100%;max-width:440px;box-shadow:0 25px 60px rgba(0,0,0,.4);animation:fadeUp .5s ease}
  @keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
  .logo{text-align:center;margin-bottom:28px}
  .logo-icon{font-size:2.4rem;margin-bottom:10px}
  .logo h1{font-size:1.4rem;font-weight:700;color:#f1f5f9}
  .logo p{font-size:.85rem;color:#64748b;margin-top:4px}
  h2{font-size:1.25rem;font-weight:600;color:#f1f5f9;margin-bottom:8px}
  .subtitle{font-size:.875rem;color:#64748b;margin-bottom:26px;line-height:1.5}
  .form-group{margin-bottom:18px}
  label{display:block;font-size:.82rem;font-weight:500;color:#94a3b8;margin-bottom:7px}
  .input-wrapper{position:relative}
  input[type=password], input[type=text]{width:100%;padding:12px 42px 12px 16px;background:#0f172a;border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#f1f5f9;font-size:.9rem;font-family:'Inter',sans-serif;transition:all .2s;outline:none}
  input[type=password]:focus, input[type=text]:focus{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.2)}
  .toggle-pwd{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#64748b;cursor:pointer;font-size:1rem}
  .toggle-pwd:hover{color:#94a3b8}
  .btn{width:100%;padding:13px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none;border-radius:10px;font-size:.95rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:all .2s;text-align:center;text-decoration:none;display:inline-block}
  .btn:hover{transform:translateY(-1px);box-shadow:0 8px 25px rgba(79,70,229,.4)}
  .btn:active{transform:translateY(0)}
  .alert{padding:14px 16px;border-radius:10px;font-size:.875rem;margin-bottom:20px;line-height:1.5}
  .alert-success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:#34d399}
  .alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171}
  .back-link{display:block;text-align:center;margin-top:22px;color:#64748b;font-size:.85rem;text-decoration:none;transition:color .2s}
  .back-link:hover{color:#818cf8}
  .divider{display:flex;align-items:center;gap:12px;margin:20px 0;color:#334155;font-size:.8rem}
  .divider::before,.divider::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.07)}
  .hint{font-size:.78rem;color:#64748b;margin-top:5px}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon">📚</div>
    <h1><?= htmlspecialchars($siteName) ?></h1>
    <p>Library Management System</p>
  </div>

  <h2>Set New Password</h2>

  <?php if ($success): ?>
    <div class="alert alert-success">
      🎉 <strong>Success!</strong> Your password has been reset successfully. You can now login with your new password.
    </div>
    <a href="login.php" class="btn">🚀 Proceed to Login</a>
  <?php else: ?>

    <?php if ($err): ?>
      <div class="alert alert-error">❌ <?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <?php if ($isValidToken): ?>
      <p class="subtitle">Enter a new secure password for <strong><?= htmlspecialchars($resetRecord['email']) ?></strong>.</p>
      <form method="POST" autocomplete="off">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        
        <div class="form-group">
          <label for="password">New Password</label>
          <div class="input-wrapper">
            <input type="password" id="password" name="password" required minlength="6" placeholder="Enter new password">
            <button type="button" class="toggle-pwd" onclick="toggleVisibility('password')">👁️</button>
          </div>
          <p class="hint">Minimum 6 characters</p>
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm New Password</label>
          <div class="input-wrapper">
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Confirm new password">
            <button type="button" class="toggle-pwd" onclick="toggleVisibility('confirm_password')">👁️</button>
          </div>
        </div>

        <button type="submit" class="btn">💾 Update Password</button>
      </form>
    <?php else: ?>
      <div style="text-align:center;margin-top:10px;">
        <a href="forgot_password.php" class="btn">🔄 Request New Reset Link</a>
      </div>
    <?php endif; ?>

    <div class="divider">or</div>
    <a href="login.php" class="back-link">← Back to Login</a>
  <?php endif; ?>
</div>

<script>
function toggleVisibility(id) {
  const input = document.getElementById(id);
  input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
