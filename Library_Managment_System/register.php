<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config/db.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($fullName) || empty($username) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        try {
            // Check uniqueness
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $error = 'Username or email already exists.';
            } else {
                $pdo->beginTransaction();
                
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, full_name, email, phone, address, status, created_at) VALUES (?, ?, 'member', ?, ?, ?, ?, 'active', NOW())");
                $stmt->execute([$username, $hash, $fullName, $email, $phone, $address]);
                $userId = $pdo->lastInsertId();
                
                // Auto generate member ID (e.g. LIB001, LIB002) and insert into members table
                $memberId = generateMemberID($pdo);
                $joinDate = date('Y-m-d');
                $expiryDate = date('Y-m-d', strtotime('+1 year'));
                
                $stmtMem = $pdo->prepare("INSERT INTO members (user_id, member_id, membership_type, join_date, expiry_date, status) VALUES (?, ?, 'Standard', ?, ?, 'active')");
                $stmtMem->execute([$userId, $memberId, $joinDate, $expiryDate]);
                
                $pdo->commit();
                
                $_SESSION['success_msg'] = 'Registration successful! Please log in.';
                header('Location: login.php');
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'A database error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Library Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: #0b1121; color: #f8fafc; min-height: 100vh; display: flex; overflow-x: hidden; }
        
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
            position: fixed;
            width: 40%;
            height: 100vh;
        }
        .left-panel::before {
            content: ''; position: absolute; width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(245, 158, 11, 0.15) 0%, transparent 70%);
            bottom: -100px; right: -100px; border-radius: 50%;
        }
        
        .floating-element {
            position: absolute; background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px);
            border-radius: 12px; padding: 1rem; animation: float 6s ease-in-out infinite;
        }
        .float-1 { top: 20%; right: 20%; animation-delay: 0s; font-size: 2rem; }
        .float-2 { bottom: 30%; left: 15%; animation-delay: 2s; font-size: 2.5rem; }
        .float-3 { top: 60%; right: 15%; animation-delay: 4s; font-size: 1.5rem; }
        
        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
            100% { transform: translateY(0px) rotate(0deg); }
        }

        .brand-container { position: relative; z-index: 10; max-width: 500px; }
        .brand-logo { 
            font-size: 3rem; font-weight: 700; margin-bottom: 1rem;
            background: linear-gradient(to right, #f59e0b, #fbbf24);
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
            background: rgba(245, 158, 11, 0.2); color: #fbbf24;
            display: flex; justify-content: center; align-items: center;
            margin-right: 1rem; font-size: 1.2rem;
        }
        .feature-text { font-size: 1.1rem; font-weight: 500; color: #e2e8f0; }

        /* Right Panel */
        .right-panel {
            margin-left: 40%;
            width: 60%;
            display: flex; justify-content: center; align-items: center;
            background-color: #0b1121; padding: 4rem 2rem;
        }
        
        .register-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 3rem;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .register-header { text-align: center; margin-bottom: 2.5rem; }
        .register-title { font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; color: #f8fafc; }
        .register-subtitle { color: #94a3b8; font-size: 1rem; }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        .full-width { grid-column: span 2; }

        .form-group { margin-bottom: 0; position: relative; }
        .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 500; color: #cbd5e1; }
        .form-input {
            width: 100%; padding: 0.875rem 1rem;
            background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px; color: #f8fafc; font-size: 1rem;
            transition: all 0.3s ease; outline: none;
        }
        .form-input:focus { border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2); }
        
        .password-strength {
            height: 4px; border-radius: 2px; background: #1e293b;
            margin-top: 0.5rem; overflow: hidden; display: none;
        }
        .password-strength-bar {
            height: 100%; width: 0%; transition: all 0.3s ease;
        }
        .strength-weak { background: #ef4444; width: 33.33%; }
        .strength-medium { background: #f59e0b; width: 66.66%; }
        .strength-strong { background: #22c55e; width: 100%; }
        .strength-text { font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem; display: none; }

        .btn-submit {
            grid-column: span 2; width: 100%; padding: 1rem; margin-top: 1rem;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white; border: none; border-radius: 12px;
            font-size: 1rem; font-weight: 600; cursor: pointer;
            transition: all 0.3s ease; position: relative; overflow: hidden;
            display: flex; justify-content: center; align-items: center;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(245, 158, 11, 0.4);
        }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit::after {
            content: ''; position: absolute; top: 0; left: -100%;
            width: 50%; height: 100%;
            background: linear-gradient(to right, transparent, rgba(255,255,255,0.2), transparent);
            transform: skewX(-20deg);
            animation: shimmer 3s infinite;
        }

        .auth-links { grid-column: span 2; margin-top: 1.5rem; text-align: center; }
        .auth-link { color: #cbd5e1; text-decoration: none; font-size: 0.95rem; transition: color 0.3s; }
        .auth-link a { color: #4f46e5; text-decoration: none; font-weight: 500; }
        .auth-link a:hover { color: #6366f1; text-decoration: underline; }
        
        .alert {
            padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;
            font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; grid-column: span 2;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5;
        }

        @media (max-width: 992px) {
            .left-panel { display: none; }
            .right-panel { margin-left: 0; width: 100%; padding: 2rem 1rem; }
            .form-grid { grid-template-columns: 1fr; }
            .form-grid > div { grid-column: span 1; }
            .full-width, .btn-submit, .auth-links, .alert { grid-column: span 1; }
        }
    </style>
</head>
<body>

    <div class="split-layout">
        <div class="left-panel">
            <div class="floating-element float-1">🌟</div>
            <div class="floating-element float-2">📚</div>
            <div class="floating-element float-3">🤝</div>
            
            <div class="brand-container">
                <div class="brand-logo">Join Our Community</div>
                <div class="brand-tagline">Unlock access to thousands of books, journals, and digital resources. Your journey starts here.</div>
                
                <ul class="feature-list">
                    <li class="feature-item">
                        <div class="feature-icon">📖</div>
                        <div class="feature-text">Unlimited Reading</div>
                    </li>
                    <li class="feature-item">
                        <div class="feature-icon">🌐</div>
                        <div class="feature-text">Global Access</div>
                    </li>
                    <li class="feature-item">
                        <div class="feature-icon">💡</div>
                        <div class="feature-text">Curated Recommendations</div>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="right-panel">
            <div class="register-card">
                <div class="register-header">
                    <h1 class="register-title">Create Account</h1>
                    <p class="register-subtitle">Register as a member to start borrowing</p>
                </div>

                <form method="POST" action="register.php" id="registerForm" class="form-grid">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            ⚠️ &nbsp; <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group full-width">
                        <label class="form-label" for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" class="form-input" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" placeholder="John Doe">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="username">Username *</label>
                        <input type="text" id="username" name="username" class="form-input" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="johndoe123">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address *</label>
                        <input type="email" id="email" name="email" class="form-input" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="john@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-input" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="+1 234 567 8900">
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label" for="address">Address</label>
                        <input type="text" id="address" name="address" class="form-input" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>" placeholder="123 Library St, City">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Password *</label>
                        <input type="password" id="password" name="password" class="form-input" required placeholder="Min 8 characters">
                        <div class="password-strength" id="pwdStrengthCont">
                            <div class="password-strength-bar" id="pwdStrengthBar"></div>
                        </div>
                        <div class="strength-text" id="pwdStrengthText"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required placeholder="Confirm your password">
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        Register Account
                    </button>
                    
                    <div class="auth-links">
                        <div class="auth-link">
                            Already have an account? <a href="login.php">Sign In</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const pwdInput = document.getElementById('password');
        const pwdStrengthCont = document.getElementById('pwdStrengthCont');
        const pwdStrengthBar = document.getElementById('pwdStrengthBar');
        const pwdStrengthText = document.getElementById('pwdStrengthText');

        pwdInput.addEventListener('input', function() {
            const val = pwdInput.value;
            if(val.length > 0) {
                pwdStrengthCont.style.display = 'block';
                pwdStrengthText.style.display = 'block';
                
                let strength = 0;
                if(val.length >= 8) strength += 1;
                if(val.match(/[A-Z]/)) strength += 1;
                if(val.match(/[0-9]/)) strength += 1;
                if(val.match(/[^a-zA-Z0-9]/)) strength += 1;

                pwdStrengthBar.className = 'password-strength-bar';
                if(strength < 2) {
                    pwdStrengthBar.classList.add('strength-weak');
                    pwdStrengthText.textContent = 'Weak password';
                    pwdStrengthText.style.color = '#ef4444';
                } else if(strength === 2 || strength === 3) {
                    pwdStrengthBar.classList.add('strength-medium');
                    pwdStrengthText.textContent = 'Medium strength';
                    pwdStrengthText.style.color = '#f59e0b';
                } else {
                    pwdStrengthBar.classList.add('strength-strong');
                    pwdStrengthText.textContent = 'Strong password';
                    pwdStrengthText.style.color = '#22c55e';
                }
            } else {
                pwdStrengthCont.style.display = 'none';
                pwdStrengthText.style.display = 'none';
            }
        });
    </script>
</body>
</html>
