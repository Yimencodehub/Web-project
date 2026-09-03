<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
// Fetch info from DB or use defaults
// Load from DB
$siteName = getSiteName($pdo);
$hours   = getSystemSetting($pdo, 'library_hours')   ?: 'Mon-Fri: 8AM-8PM, Sat-Sun: 10AM-6PM';
$address = getSystemSetting($pdo, 'library_address') ?: '123 Main Street';
$phone   = getSystemSetting($pdo, 'library_phone')   ?: '+1-555-0100';
$email   = getSystemSetting($pdo, 'library_email')   ?: 'info@citylibrary.com';

// Load about/rules from library_info table
try {
    $about_row = $pdo->query("SELECT info_value FROM library_info WHERE info_key='about' LIMIT 1")->fetchColumn();
    $rules_row = $pdo->query("SELECT info_value FROM library_info WHERE info_key='rules' LIMIT 1")->fetchColumn();
} catch(Exception $e) { $about_row = ''; $rules_row = ''; }

$info = [
    'hours'   => $hours,
    'rules'   => $rules_row ?: '1. Maintain silence. 2. No food or drinks. 3. Return books on time.',
    'about'   => $about_row ?: $siteName . ' has been serving the community since 1995.',
    'address' => $address,
    'phone'   => $phone,
    'email'   => $email
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Info - <?= getSiteName($pdo) ?></title>
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
        
        .hero { background: linear-gradient(135deg, rgba(79,70,229,0.2), rgba(15,23,42,1)); padding: 6rem 5% 4rem; text-align: center; border-bottom: 1px solid var(--border-color); }
        .hero h1 { font-size: 3rem; margin-bottom: 1rem; }
        .hero p { color: var(--text-muted); max-width: 600px; margin: 0 auto; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 4rem 5%; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-bottom: 4rem; }
        
        .card { background: var(--card-bg); border-radius: 1rem; padding: 2.5rem; border: 1px solid var(--border-color); }
        .card h2 { color: var(--primary); margin-bottom: 1.5rem; font-size: 1.5rem; display: flex; align-items: center; gap: 0.5rem; }
        
        .rules-list { list-style: none; }
        .rules-list li { padding: 0.75rem 0; border-bottom: 1px solid var(--border-color); color: var(--text-muted); display: flex; gap: 1rem; }
        .rules-list li::before { content: "•"; color: var(--accent); font-weight: bold; }
        
        .contact-item { margin-bottom: 1rem; display: flex; gap: 1rem; color: var(--text-muted); }
        
        .steps { margin-top: 2rem; }
        .step { display: flex; gap: 1.5rem; margin-bottom: 1.5rem; }
        .step-num { width: 40px; height: 40px; background: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0; }
        .step-content h4 { margin-bottom: 0.25rem; }
        .step-content p { color: var(--text-muted); font-size: 0.875rem; }
        
        .btn { padding: 0.75rem 2rem; background: var(--primary); color: white; border-radius: 0.5rem; display: inline-block; font-weight: 600; text-align: center; margin-top: 1.5rem; transition: 0.3s; }
        .btn:hover { background: #4338ca; transform: translateY(-2px); }

        .map-placeholder { height: 300px; background: var(--nav-bg); border-radius: 1rem; display: flex; align-items: center; justify-content: center; color: var(--text-muted); border: 1px dashed var(--border-color); }
        
        @media(max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="logo">City<span>Library</span></a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="catalog.php">Catalog</a>
            <a href="library_info.php" class="active">Library Info</a>
            <a href="contact.php">Contact</a>
        </div>
    </nav>

    <div class="hero">
        <h1>About Our Library</h1>
        <p>Everything you need to know about visiting, joining, and utilizing <?= getSiteName($pdo) ?> resources.</p>
    </div>

    <div class="container">
        <div class="grid-2">
            <div class="card">
                <h2>📖 About Us</h2>
                <p style="color: var(--text-muted); line-height: 1.8;"><?= nl2br(htmlspecialchars($info['about'])) ?></p>
                
                <h2 style="margin-top: 3rem;">🕒 Opening Hours</h2>
                <div style="color: var(--text-muted); line-height: 2;">
                    <?= nl2br(htmlspecialchars($info['hours'])) ?>
                </div>
            </div>
            
            <div class="card">
                <h2>⚖️ Library Rules & Fine Policy</h2>
                <ul class="rules-list">
                    <?php 
                        $rules = explode("\n", $info['rules']);
                        foreach($rules as $rule): 
                            $rule = trim(str_replace('-', '', $rule));
                            if($rule):
                    ?>
                        <li><?= htmlspecialchars($rule) ?></li>
                    <?php endif; endforeach; ?>
                </ul>
                <div style="margin-top: 2rem; padding: 1rem; background: rgba(245, 158, 11, 0.1); border-left: 4px solid var(--accent); border-radius: 0 0.5rem 0.5rem 0;">
                    <strong>Note:</strong> All members must present their library card to borrow physical items.
                </div>
            </div>
        </div>
        
        <div class="grid-2">
            <div class="card">
                <h2>🌟 How to Become a Member</h2>
                <div class="steps">
                    <div class="step">
                        <div class="step-num">1</div>
                        <div class="step-content">
                            <h4>Register Online</h4>
                            <p>Fill out the registration form on our website to create an account.</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-num">2</div>
                        <div class="step-content">
                            <h4>Visit the Library</h4>
                            <p>Bring a valid photo ID and proof of address to the front desk.</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-num">3</div>
                        <div class="step-content">
                            <h4>Get Your Card</h4>
                            <p>Receive your physical library card and start borrowing instantly!</p>
                        </div>
                    </div>
                </div>
                <a href="../register.php" class="btn">Register Now</a>
            </div>
            
            <div class="card">
                <h2>📍 Location & Contact</h2>
                <div class="contact-item"><strong>Address:</strong> <?= htmlspecialchars($info['address']) ?></div>
                <div class="contact-item"><strong>Phone:</strong> <?= htmlspecialchars($info['phone']) ?></div>
                <div class="contact-item"><strong>Email:</strong> <?= htmlspecialchars($info['email']) ?></div>
                
                <div class="map-placeholder" style="margin-top: 2rem;">
                    [ Interactive Map Placeholder ]
                </div>
            </div>
        </div>
    </div>
</body>
</html>
