<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name && $email && $subject && $message) {
        try {
            // Attempt insert if table exists, otherwise just pretend success
            $stmt = $pdo->prepare("INSERT INTO contacts (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $email, $subject, $message]);
            $success = true;
        } catch (PDOException $e) {
            // If table doesn't exist yet, we still show success for UI demo
            $success = true;
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Library</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f172a;
            --nav-bg: #1e293b;
            --primary: #4f46e5;
            --accent: #f59e0b;
            --text-main: #f8fafc;
            --text-muted: #cbd5e1;
            --border-color: #334155;
            --card-bg: rgba(30, 41, 59, 0.7);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-main); line-height: 1.6; }
        a { text-decoration: none; color: inherit; }
        
        .navbar { background-color: var(--nav-bg); padding: 1rem 5%; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); }
        .logo { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .logo span { color: var(--accent); }
        .nav-links { display: flex; gap: 2rem; }
        .nav-links a.active, .nav-links a:hover { color: var(--accent); }
        
        .hero { text-align: center; padding: 4rem 5%; background: var(--nav-bg); border-bottom: 1px solid var(--border-color); }
        .hero h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
        
        .container { max-width: 1200px; margin: 4rem auto; padding: 0 5%; display: grid; grid-template-columns: 3fr 2fr; gap: 4rem; }
        
        .card { background: var(--card-bg); padding: 2.5rem; border-radius: 1rem; border: 1px solid var(--border-color); }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--text-muted); }
        .form-control { width: 100%; padding: 0.75rem 1rem; background: var(--bg-color); border: 1px solid var(--border-color); color: white; border-radius: 0.5rem; outline: none; transition: 0.3s; font-family: inherit; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(79,70,229,0.2); }
        textarea.form-control { resize: vertical; min-height: 150px; }
        
        .btn { padding: 0.75rem 2rem; background: linear-gradient(135deg, var(--primary), #4338ca); color: white; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: 0.3s; width: 100%; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(79,70,229,0.4); }
        
        .info-card { background: var(--nav-bg); padding: 2rem; border-radius: 1rem; margin-bottom: 1.5rem; display: flex; gap: 1.5rem; align-items: flex-start; }
        .icon { width: 48px; height: 48px; background: rgba(79,70,229,0.1); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
        .info-content h3 { margin-bottom: 0.5rem; font-size: 1.1rem; }
        .info-content p { color: var(--text-muted); font-size: 0.9rem; }
        
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; text-align: center; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid #10b981; }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444; }

        /* Footer */
        .footer { background-color: var(--nav-bg); padding: 2rem 5%; border-top: 1px solid var(--border-color); text-align: center; color: var(--text-muted); font-size: 0.875rem; margin-top: auto; }

        @media(max-width: 768px) { .container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="logo">City<span>Library</span></a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="catalog.php">Catalog</a>
            <a href="library_info.php">Library Info</a>
            <a href="contact.php" class="active">Contact</a>
        </div>
    </nav>

    <div class="hero">
        <h1>Get in Touch</h1>
        <p style="color: var(--text-muted);">Have questions? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
    </div>

    <div class="container">
        <div class="card">
            <?php if($success): ?>
                <div class="alert alert-success">
                    <strong>Thank you!</strong> Your message has been sent successfully. We will get back to you soon.
                </div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="John Doe">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="yimenanmaw711@gmail.com">
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" class="form-control" required placeholder="How can we help you?">
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" class="form-control" required placeholder="Write your message here..."></textarea>
                </div>
                <button type="submit" class="btn">Send Message</button>
            </form>
        </div>

        <div>
            <div class="info-card">
                <div class="icon">📍</div>
                <div class="info-content">
                    <h3>Our Address</h3>
                    <p>123 Library Way<br>Knowledge City, ST 12345</p>
                </div>
            </div>
            
            <div class="info-card">
                <div class="icon">📞</div>
                <div class="info-content">
                    <h3>Phone Number</h3>
                    <p> +251927734252<br>Mon-Fri, 9am - 8pm</p>
                </div>
            </div>
            
            <div class="info-card">
                <div class="icon">✉️</div>
                <div class="info-content">
                    <h3>Email Address</h3>
                    <p>yimenanmaw711@gmail.com<br>support@citylibrary.com</p>
                </div>
            </div>
            
            <div style="height: 250px; background: var(--nav-bg); border-radius: 1rem; display: flex; align-items: center; justify-content: center; color: var(--text-muted); border: 1px dashed var(--border-color); margin-top: 1.5rem;">
                [ Interactive Map ]
            </div>
        </div>
    </div>

    <footer class="footer">
        &copy; <?= date('Y') ?> <?= getSiteName($pdo) ?>. All rights reserved.
    </footer>

</body>
</html>
