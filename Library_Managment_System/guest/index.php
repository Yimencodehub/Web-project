<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';

// Fetch stats
$stats = [
    'books' => 0,
    'members' => 0,
    'categories' => 0,
    'years' => 10
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM books");
    $stats['books'] = $stmt->fetchColumn() ?: 12500;
    
    // We might not have members table yet, let's just do try-catch per stat
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'");
        $stats['members'] = $stmt->fetchColumn() ?: 3400;
    } catch (Exception $e) { $stats['members'] = 3400; }
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
        $stats['categories'] = $stmt->fetchColumn() ?: 45;
    } catch (Exception $e) { $stats['categories'] = 45; }
    
    // Recent books
    $recent_books = [];
    try {
        $stmt = $pdo->query("SELECT b.*, c.name as category_name FROM books b LEFT JOIN categories c ON b.category_id = c.id ORDER BY b.created_at DESC LIMIT 6");
        $recent_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback fake data if tables don't exist
    }

} catch (PDOException $e) {
    // Silent fail for guest page, defaults will be used
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= getSiteName($pdo) ?> - Home</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f172a;
            --nav-bg: #1e293b;
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --accent: #f59e0b;
            --accent-hover: #d97706;
            --text-main: #f8fafc;
            --text-muted: #cbd5e1;
            --border-color: #334155;
            --card-bg: rgba(30, 41, 59, 0.7);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-main); line-height: 1.6; overflow-x: hidden; }
        a { text-decoration: none; color: inherit; }
        
        /* Navbar */
        .navbar { background-color: var(--nav-bg); padding: 1rem 5%; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; border-bottom: 1px solid var(--border-color); }
        .logo { font-size: 1.5rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 0.5rem; }
        .logo span { color: var(--accent); }
        .nav-links { display: flex; gap: 2rem; }
        .nav-links a { font-weight: 500; transition: color 0.3s; }
        .nav-links a:hover, .nav-links a.active { color: var(--accent); }
        .nav-actions { display: flex; gap: 1rem; }
        .btn { padding: 0.5rem 1.5rem; border-radius: 0.375rem; font-weight: 600; transition: all 0.3s; cursor: pointer; display: inline-block; text-align: center; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-hover)); color: white; border: none; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4); }
        .btn-outline { background: transparent; color: var(--accent); border: 1px solid var(--accent); }
        .btn-outline:hover { background: var(--accent); color: white; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4); }
        .btn-lg { padding: 0.75rem 2rem; font-size: 1.1rem; }

        /* Hero */
        .hero { min-height: 80vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 2rem 5%; position: relative; overflow: hidden; background: radial-gradient(circle at center, #1e293b 0%, var(--bg-color) 100%); }
        .hero-content { position: relative; z-index: 10; max-width: 800px; }
        .hero h1 { font-size: 4rem; font-weight: 700; margin-bottom: 1rem; background: linear-gradient(to right, #fff, var(--text-muted)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero p { font-size: 1.25rem; color: var(--text-muted); margin-bottom: 2.5rem; }
        .hero-btns { display: flex; gap: 1rem; justify-content: center; }
        
        /* Floating elements */
        .floating-book { position: absolute; width: 60px; height: 80px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); opacity: 0.6; animation: float 6s ease-in-out infinite; }
        .fb-1 { top: 20%; left: 15%; animation-delay: 0s; transform: rotate(-15deg); }
        .fb-2 { top: 60%; left: 10%; animation-delay: 1s; transform: rotate(20deg); }
        .fb-3 { top: 30%; right: 15%; animation-delay: 2s; transform: rotate(15deg); }
        .fb-4 { top: 70%; right: 10%; animation-delay: 3s; transform: rotate(-20deg); }
        @keyframes float { 0% { transform: translateY(0px) rotate(var(--rot)); } 50% { transform: translateY(-20px) rotate(var(--rot)); } 100% { transform: translateY(0px) rotate(var(--rot)); } }

        /* Stats Bar */
        .stats-bar { display: flex; justify-content: space-around; flex-wrap: wrap; padding: 3rem 5%; background: rgba(30, 41, 59, 0.4); border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); }
        .stat-item { text-align: center; padding: 1rem; }
        .stat-num { font-size: 2.5rem; font-weight: 700; color: var(--accent); margin-bottom: 0.25rem; }
        .stat-label { color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 1px; font-size: 0.875rem; }

        /* Sections */
        .section { padding: 5rem 5%; }
        .section-header { text-align: center; margin-bottom: 3rem; }
        .section-title { font-size: 2.25rem; font-weight: 700; margin-bottom: 1rem; }
        .section-subtitle { color: var(--text-muted); max-width: 600px; margin: 0 auto; }

        /* Quick Search */
        .quick-search { max-width: 600px; margin: -2rem auto 4rem; position: relative; z-index: 20; background: var(--card-bg); padding: 0.5rem; border-radius: 2rem; display: flex; border: 1px solid var(--border-color); box-shadow: 0 10px 25px rgba(0,0,0,0.3); backdrop-filter: blur(10px); }
        .quick-search input { flex: 1; background: transparent; border: none; padding: 1rem 1.5rem; color: white; outline: none; font-size: 1.1rem; }
        .quick-search button { border-radius: 2rem; padding: 0 2rem; }

        /* Features */
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }
        .feature-card { background: var(--card-bg); border-radius: 1rem; padding: 2.5rem 2rem; text-align: center; border: 1px solid var(--border-color); transition: transform 0.3s; }
        .feature-card:hover { transform: translateY(-10px); border-color: var(--primary); }
        .feature-icon { width: 64px; height: 64px; background: rgba(79, 70, 229, 0.1); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 1.5rem; font-weight: bold; }
        .feature-title { font-size: 1.25rem; margin-bottom: 1rem; }

        /* Book Grid */
        .book-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 2rem; }
        .book-card { background: var(--card-bg); border-radius: 0.75rem; overflow: hidden; border: 1px solid var(--border-color); transition: transform 0.3s, box-shadow 0.3s; display: flex; flex-direction: column; }
        .book-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.4); border-color: var(--primary); }
        .book-cover { height: 250px; background: linear-gradient(45deg, var(--nav-bg), var(--border-color)); display: flex; align-items: center; justify-content: center; font-size: 4rem; color: var(--text-muted); font-weight: 700; position: relative; }
        .book-info { padding: 1.25rem; flex: 1; display: flex; flex-direction: column; }
        .book-title { font-weight: 600; font-size: 1.1rem; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .book-author { color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1rem; }
        .book-badge { display: inline-block; padding: 0.25rem 0.5rem; background: rgba(245, 158, 11, 0.1); color: var(--accent); border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; margin-bottom: 1rem; align-self: flex-start; }
        .book-status { font-size: 0.875rem; font-weight: 500; margin-top: auto; }
        .status-avail { color: #10b981; }
        .status-unavail { color: #ef4444; }

        /* Footer */
        .footer { background-color: var(--nav-bg); padding: 4rem 5% 1.5rem; border-top: 1px solid var(--border-color); }
        .footer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 3rem; margin-bottom: 3rem; }
        .footer-col h3 { color: white; margin-bottom: 1.5rem; font-size: 1.1rem; }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 0.75rem; }
        .footer-col ul li a { color: var(--text-muted); transition: color 0.3s; }
        .footer-col ul li a:hover { color: var(--accent); }
        .footer-bottom { text-align: center; padding-top: 1.5rem; border-top: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.875rem; }

        @media (max-width: 768px) {
            .hero h1 { font-size: 2.5rem; }
            .nav-links { display: none; }
            .hero-btns { flex-direction: column; }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <a href="index.php" class="logo">City<span>Library</span></a>
        <div class="nav-links">
            <a href="index.php" class="active">Home</a>
            <a href="catalog.php">Catalog</a>
            <a href="library_info.php">Library Info</a>
            <a href="contact.php">Contact</a>
        </div>
        <div class="nav-actions">
            <a href="../login.php" class="btn btn-outline">Login</a>
            <a href="../register.php" class="btn btn-primary">Register</a>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero">
        <div class="floating-book fb-1" style="--rot: -15deg"></div>
        <div class="floating-book fb-2" style="--rot: 20deg"></div>
        <div class="floating-book fb-3" style="--rot: 15deg"></div>
        <div class="floating-book fb-4" style="--rot: -20deg"></div>
        
        <div class="hero-content">
            <h1>Welcome to <?= getSiteName($pdo) ?></h1>
            <p>Discover millions of books, audiobooks, and digital resources. Join our community of readers and lifelong learners.</p>
            <div class="hero-btns">
                <a href="catalog.php" class="btn btn-primary btn-lg">Browse Catalog</a>
                <a href="../register.php" class="btn btn-outline btn-lg">Become a Member</a>
            </div>
        </div>
    </section>

    <!-- Quick Search -->
    <form action="catalog.php" method="GET" class="quick-search">
        <input type="text" name="q" placeholder="Search books by title, author, or ISBN...">
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <!-- Stats -->
    <section class="stats-bar">
        <div class="stat-item">
            <div class="stat-num"><?= number_format($stats['books']) ?>+</div>
            <div class="stat-label">Total Books</div>
        </div>
        <div class="stat-item">
            <div class="stat-num"><?= number_format($stats['members']) ?>+</div>
            <div class="stat-label">Active Members</div>
        </div>
        <div class="stat-item">
            <div class="stat-num"><?= $stats['categories'] ?></div>
            <div class="stat-label">Categories</div>
        </div>
        <div class="stat-item">
            <div class="stat-num"><?= $stats['years'] ?>+</div>
            <div class="stat-label">Years of Service</div>
        </div>
    </section>

    <!-- Features -->
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">Why Join Our Library?</h2>
            <p class="section-subtitle">We offer a wide range of services to our community, providing access to knowledge and a quiet place to learn.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">📚</div>
                <h3 class="feature-title">Extensive Catalog</h3>
                <p class="text-muted">Access thousands of physical and digital books spanning every genre and topic imaginable.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💻</div>
                <h3 class="feature-title">Digital Resources</h3>
                <p class="text-muted">Read e-books and listen to audiobooks from anywhere using our seamless online platform.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🤝</div>
                <h3 class="feature-title">Community Events</h3>
                <p class="text-muted">Join reading clubs, author talks, and workshops designed for all ages.</p>
            </div>
        </div>
    </section>

    <!-- Recent Books -->
    <section class="section" style="background-color: var(--nav-bg);">
        <div class="section-header">
            <h2 class="section-title">Recently Added</h2>
            <p class="section-subtitle">Check out the latest additions to our growing collection.</p>
        </div>
        <div class="book-grid">
            <?php if(empty($recent_books)): ?>
                <!-- Dummy Data for Preview -->
                <?php for($i=1; $i<=6; $i++): ?>
                <a href="book_details.php?id=<?= $i ?>" class="book-card">
                    <div class="book-cover"><?= chr(64+$i) ?></div>
                    <div class="book-info">
                        <span class="book-badge">Fiction</span>
                        <h4 class="book-title">Sample Book Title <?= $i ?></h4>
                        <p class="book-author">Author Name</p>
                        <div class="book-status status-avail">Available</div>
                    </div>
                </a>
                <?php endfor; ?>
            <?php else: ?>
                <?php foreach($recent_books as $book): ?>
                <a href="book_details.php?id=<?= $book['id'] ?>" class="book-card">
                    <div class="book-cover"><?= strtoupper(substr($book['title'], 0, 1)) ?></div>
                    <div class="book-info">
                        <span class="book-badge"><?= htmlspecialchars($book['category_name'] ?? 'General') ?></span>
                        <h4 class="book-title"><?= htmlspecialchars($book['title']) ?></h4>
                        <p class="book-author"><?= htmlspecialchars($book['author']) ?></p>
                        <?php 
                            $available = ($book['available_copies'] > 0); 
                            $statusClass = $available ? 'status-avail' : 'status-unavail';
                            $statusText = $available ? 'Available' : 'Unavailable';
                        ?>
                        <div class="book-status <?= $statusClass ?>"><?= $statusText ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="text-center" style="margin-top: 3rem;">
            <a href="catalog.php" class="btn btn-outline">View All Books</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-grid">
            <div class="footer-col">
                <a href="index.php" class="logo mb-4" style="display:inline-flex;">City<span>Library</span></a>
                <p class="text-muted" style="margin-top: 1rem;">Empowering the community through free access to knowledge, information, and technology.</p>
            </div>
            <div class="footer-col">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="catalog.php">Browse Catalog</a></li>
                    <li><a href="library_info.php">Library Info</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Account</h3>
                <ul>
                    <li><a href="../login.php">Member Login</a></li>
                    <li><a href="../register.php">Register</a></li>
                    <li><a href="#">Forgot Password</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Visit Us</h3>
                <ul>
                    <li class="text-muted">123 Library Way</li>
                    <li class="text-muted">Knowledge City, ST 12345</li>
                    <li class="text-muted">Mon - Fri: 9am - 8pm</li>
                    <li class="text-muted">Sat - Sun: 10am - 5pm</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> <?= getSiteName($pdo) ?>. All rights reserved.
        </div>
    </footer>
</body>
</html>
