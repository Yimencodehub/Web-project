<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password, role, full_name, status FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    $error = 'Your account is inactive or suspended. Please contact support.';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['profile_pic'] = $user['profile_pic'] ?? '';
                    
                    // Audit log could go here
                    try {
                        $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
                        $logStmt->execute([$user['id'], 'login', 'User logged in successfully']);
                    } catch (PDOException $e) {
                        // ignore log failure if table doesn't exist
                    }
                    
                    // Redirect
                    $role = $user['role'];
                    if($role === 'superadmin') header('Location: superadmin/dashboard.php');
                    elseif($role === 'admin') header('Location: admin/dashboard.php');
                    elseif($role === 'staff') header('Location: staff/dashboard.php');
                    elseif($role === 'member') header('Location: member/dashboard.php');
                    else header('Location: index.php');
                    exit;
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'A database error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Library Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: #0f172a; color: #f8fafc; min-height: 100vh; display: flex; overflow: hidden; }
        
        .split-layout { display: flex; width: 100%; min-height: 100vh; }
        
        /* Left Panel */
        .left-panel {
            flex: 1;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }
        .left-panel::before {
            content: ''; position: absolute; width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(79, 70, 229, 0.15) 0%, transparent 70%);
            top: -100px; left: -100px; border-radius: 50%;
        }
        
        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 1rem;
            animation: float 6s ease-in-out infinite;
        }
        .float-1 { top: 15%; right: 15%; animation-delay: 0s; font-size: 2rem; }
        .float-2 { bottom: 25%; left: 20%; animation-delay: 2s; font-size: 2.5rem; }
        .float-3 { top: 50%; right: 25%; animation-delay: 4s; font-size: 1.5rem; }
        
        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
            100% { transform: translateY(0px) rotate(0deg); }
        }

        .brand-container { position: relative; z-index: 10; max-width: 500px; }
        .brand-logo { 
            font-size: 3rem; font-weight: 700; margin-bottom: 1rem;
            background: linear-gradient(to right, #818cf8, #f59e0b);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .brand-tagline { font-size: 1.2rem; color: #94a3b8; margin-bottom: 3rem; line-height: 1.6; }
        
        .feature-list { list-style: none; }
        .feature-item { 
            display: flex; align-items: center; margin-bottom: 1.5rem;
            padding: 1rem; background: rgba(255,255,255,0.02);
            border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);
            transition: transform 0.3s ease;
        }
        .feature-item:hover { transform: translateX(10px); background: rgba(255,255,255,0.04); }
        .feature-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: rgba(79, 70, 229, 0.2); color: #818cf8;
            display: flex; justify-content: center; align-items: center;
            margin-right: 1rem; font-size: 1.2rem;
        }
        .feature-text { font-size: 1.1rem; font-weight: 500; color: #e2e8f0; }

        /* Right Panel */
        .right-panel {
            flex: 1; display: flex; justify-content: center; align-items: center;
            background-color: #0b1121; padding: 2rem; position: relative;
        }
        
        .login-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 3rem;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-header { text-align: center; margin-bottom: 2.5rem; }
        .login-title { font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; color: #f8fafc; }
        .login-subtitle { color: #94a3b8; font-size: 1rem; }

        .form-group { margin-bottom: 1.5rem; position: relative; }
        .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 500; color: #cbd5e1; }
        .form-input {
            width: 100%; padding: 0.875rem 1rem;
            background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px; color: #f8fafc; font-size: 1rem;
            transition: all 0.3s ease; outline: none;
        }
        .form-input:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }
        
        .password-toggle {
            position: absolute; right: 1rem; top: 2.3rem;
            background: none; border: none; color: #94a3b8;
            cursor: pointer; font-size: 1rem; transition: color 0.3s ease;
        }
        .password-toggle:hover { color: #f8fafc; }

        .form-options {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 2rem; font-size: 0.9rem;
        }
        .checkbox-label { display: flex; align-items: center; cursor: pointer; color: #cbd5e1; }
        .checkbox-label input { margin-right: 0.5rem; cursor: pointer; accent-color: #4f46e5; }
        .forgot-link { color: #818cf8; text-decoration: none; transition: color 0.3s; }
        .forgot-link:hover { color: #a5b4fc; text-decoration: underline; }

        .btn-submit {
            width: 100%; padding: 1rem;
            background: linear-gradient(135deg, #4f46e5, #4338ca);
            color: white; border: none; border-radius: 12px;
            font-size: 1rem; font-weight: 600; cursor: pointer;
            transition: all 0.3s ease; position: relative; overflow: hidden;
            display: flex; justify-content: center; align-items: center;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4);
        }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit::after {
            content: ''; position: absolute; top: 0; left: -100%;
            width: 50%; height: 100%;
            background: linear-gradient(to right, transparent, rgba(255,255,255,0.2), transparent);
            transform: skewX(-20deg);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            20% { left: 200%; }
            100% { left: 200%; }
        }

        .auth-links { margin-top: 2rem; text-align: center; display: flex; flex-direction: column; gap: 0.75rem; }
        .auth-link { color: #cbd5e1; text-decoration: none; font-size: 0.95rem; transition: color 0.3s; }
        .auth-link a { color: #f59e0b; text-decoration: none; font-weight: 500; }
        .auth-link a:hover { color: #fbbf24; text-decoration: underline; }
        
        .btn-guest {
            display: inline-block; padding: 0.75rem 1.5rem; margin-top: 0.5rem;
            background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1);
            color: #e2e8f0; border-radius: 8px; text-decoration: none;
            transition: all 0.3s; font-size: 0.9rem;
        }
        .btn-guest:hover { background: rgba(255, 255, 255, 0.1); border-color: rgba(255, 255, 255, 0.2); }

        .alert {
            padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;
            font-size: 0.9rem; font-weight: 500; display: flex; align-items: center;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2); color: #86efac;
        }

        /* Loading spinner */
        .spinner {
            display: none; width: 20px; height: 20px;
            border: 2px solid rgba(255,255,255,0.3); border-radius: 50%;
            border-top-color: white; animation: spin 1s ease-in-out infinite; margin-left: 10px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .loading .spinner { display: inline-block; }
        .loading .btn-text { opacity: 0.8; }

        @media (max-width: 992px) {
            .left-panel { display: none; }
        }
    </style>
</head>
<body>

    <div class="split-layout">
        <div class="left-panel">
            <div class="floating-element float-1">📚</div>
            <div class="floating-element float-2">📖</div>
            <div class="floating-element float-3">🎓</div>
            
            <div class="brand-container">
                <div class="brand-logo">Library Managment System</div>
                <div class="brand-tagline">Experience the future of library management. Seamlessly browse, borrow, and manage your intellectual journey.</div>
                
                <ul class="feature-list">
                    <li class="feature-item">
                        <div class="feature-icon">🔍</div>
                        <div class="feature-text">Quick Search</div>
                    </li>
                    <li class="feature-item">
                        <div class="feature-icon">📱</div>
                        <div class="feature-text">Digital Management</div>
                    </li>
                    <li class="feature-item">
                        <div class="feature-icon">🕰️</div>
                        <div class="feature-text">24/7 Access</div>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="right-panel">
            <div class="login-card">
                <div class="login-header">
                    <h1 class="login-title">Welcome Back</h1>
                    <p class="login-subtitle">Sign in to your account to continue</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        ⚠️ &nbsp; <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success">
                        ✅ &nbsp; <?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" id="loginForm" onsubmit="return submitForm()">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-input" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="Enter your username">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-input" required placeholder="Enter your password">
                        <button type="button" class="password-toggle" onclick="togglePassword()" title="Toggle password visibility">
                            👁️
                        </button>
                    </div>
                    
                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember"> Remember Me
                        </label>
                        <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span class="btn-text">Sign In</span>
                        <span class="spinner"></span>
                    </button>
                </form>
                
                <div class="auth-links">
                    <div class="auth-link">
                        New to the library? <a href="register.php">Register as Member</a>
                    </div>
                    <div>
                        <a href="guest/index.php" class="btn-guest">Continue as Guest</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const pwdInput = document.getElementById('password');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
            } else {
                pwdInput.type = 'password';
            }
        }
        
        function submitForm() {
            const btn = document.getElementById('submitBtn');
            btn.classList.add('loading');
            return true;
        }
    </script>
</body>
</html>
