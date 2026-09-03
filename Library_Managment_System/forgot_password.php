<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/config/mail.php';

// Already logged in → go to dashboard
if (isset($_SESSION['user_id'])) {
    $r = $_SESSION['role'];
    header("Location: $r/dashboard.php"); exit;
}

$siteName = getSiteName($pdo);
$msg = $err = $devLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter a valid email address.';
    } else {
        // Check email exists
        $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Deliberately vague for security
            $msg = 'If that email is registered, a reset link has been sent.';
        } else {
            // Delete old unused tokens for this email
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

            // Generate cryptographically secure token
            $token     = bin2hex(random_bytes(32));   // 64-char hex string
            $expiresAt = date('Y-m-d H:i:s', time() + RESET_TOKEN_EXPIRY_MINUTES * 60);

            $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
                ->execute([$email, $token, $expiresAt]);

            $resetLink = BASE_URL . '/reset_password.php?token=' . $token;

            // Send email
            $result = sendPasswordResetEmail($email, $user['full_name'], $resetLink);

            if ($result['success']) {
                if (!empty($result['dev_link'])) {
                    // DEV MODE — show link on screen
                    $devLink = $result['dev_link'];
                    $msg = 'DEV MODE: Email sending is disabled. Use the link below to reset your password:';
                } else {
                    $msg = 'A password reset link has been sent to <strong>' . htmlspecialchars($email) . '</strong>. Check your inbox (and spam folder).';
                }
            } else {
                $err = 'Failed to send email: ' . htmlspecialchars($result['error'] ?? 'Unknown error');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — <?= htmlspecialchars($siteName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:'Inter',sans-serif;background:#0f172a;color:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  /* Animated background */
  body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse at 20% 50%,rgba(79,70,229,.15) 0,transparent 60%),radial-gradient(ellipse at 80% 20%,rgba(124,58,237,.12) 0,transparent 50%);pointer-events:none}
  .card{background:rgba(30,41,59,.85);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:44px 40px;width:100%;max-width:440px;box-shadow:0 25px 60px rgba(0,0,0,.4);animation:fadeUp .5s ease}
  @keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
  .logo{text-align:center;margin-bottom:30px}
  .logo-icon{font-size:2.4rem;margin-bottom:10px}
  .logo h1{font-size:1.4rem;font-weight:700;color:#f1f5f9}
  .logo p{font-size:.85rem;color:#64748b;margin-top:4px}
  h2{font-size:1.2rem;font-weight:600;color:#f1f5f9;margin-bottom:8px}
  .subtitle{font-size:.875rem;color:#64748b;margin-bottom:28px;line-height:1.5}
  .form-group{margin-bottom:20px}
  label{display:block;font-size:.82rem;font-weight:500;color:#94a3b8;margin-bottom:7px}
  input[type=email]{width:100%;padding:12px 16px;background:#0f172a;border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#f1f5f9;font-size:.9rem;font-family:'Inter',sans-serif;transition:all .2s;outline:none}
  input[type=email]:focus{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.2)}
  .btn{width:100%;padding:13px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none;border-radius:10px;font-size:.95rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:all .2s;position:relative;overflow:hidden}
  .btn:hover{transform:translateY(-1px);box-shadow:0 8px 25px rgba(79,70,229,.4)}
  .btn:active{transform:translateY(0)}
  .alert{padding:14px 16px;border-radius:10px;font-size:.875rem;margin-bottom:20px;line-height:1.5}
  .alert-success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:#34d399}
  .alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171}
  .alert-dev{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);color:#fbbf24}
  .dev-link{display:block;margin-top:10px;background:#0f172a;padding:12px;border-radius:8px;word-break:break-all;font-size:.8rem;color:#818cf8;border:1px solid rgba(79,70,229,.2)}
  .dev-link a{color:#818cf8}
  .back-link{display:block;text-align:center;margin-top:22px;color:#64748b;font-size:.85rem;text-decoration:none;transition:color .2s}
  .back-link:hover{color:#818cf8}
  .divider{display:flex;align-items:center;gap:12px;margin:20px 0;color:#334155;font-size:.8rem}
  .divider::before,.divider::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.07)}
  .expiry-note{font-size:.78rem;color:#475569;text-align:center;margin-top:14px}
  .expiry-note span{color:#f59e0b}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon">📚</div>
    <h1><?= htmlspecialchars($siteName) ?></h1>
    <p>Library Management System</p>
  </div>

  <h2>Forgot Password?</h2>
  <p class="subtitle">Enter your registered email address and we'll send you a secure link to reset your password.</p>

  <?php if ($err): ?>
    <div class="alert alert-error">❌ <?= htmlspecialchars($err) ?></div>
  <?php endif; ?>

  <?php if ($msg && !$devLink): ?>
    <div class="alert alert-success">✅ <?= $msg ?></div>
  <?php endif; ?>

  <?php if ($devLink): ?>
    <div class="alert alert-dev">
      ⚙️ <?= htmlspecialchars($msg) ?>
      <div class="dev-link">
        <a href="<?= htmlspecialchars($devLink) ?>"><?= htmlspecialchars($devLink) ?></a>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!$msg && !$devLink): ?>
  <form method="POST" autocomplete="off">
    <div class="form-group">
      <label for="email">Email Address</label>
      <input type="email" id="email" name="email" placeholder="you@example.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
    </div>
    <button type="submit" class="btn">🔗 Send Reset Link</button>
  </form>
  <p class="expiry-note">The link will expire in <span><?= RESET_TOKEN_EXPIRY_MINUTES ?> minutes</span>.</p>
  <?php endif; ?>

  <div class="divider">or</div>
  <a href="login.php" class="back-link">← Back to Login</a>
</div>
</body>
</html>
