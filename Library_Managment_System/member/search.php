<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';
require_once '../includes/functions.php';
requireRole(['member']);

$user_id = $_SESSION['user_id'];
$stmtMem = $pdo->prepare("SELECT id, member_id FROM members WHERE user_id = ?");
$stmtMem->execute([$user_id]);
$member = $stmtMem->fetch();
$member_db_id = $member['id'] ?? null;

$search = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');

$query = "
    SELECT b.*, c.name as category_name 
    FROM books b 
    LEFT JOIN categories c ON b.category_id = c.id 
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category !== '') {
    $query .= " AND b.category_id = ?";
    $params[] = $category;
}

$query .= " ORDER BY b.title ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$books = $stmt->fetchAll();

// Get categories for dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Books — Member Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --sidebar: #111827;
            --card-bg: rgba(30, 41, 59, 0.85);
            --primary: #4f46e5;
            --accent: #f59e0b;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255,255,255,0.08);
            --green: #10b981;
            --red: #ef4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: var(--sidebar); min-height: 100vh; position: fixed; left: 0; top: 0; bottom: 0; overflow-y: auto; z-index: 100; border-right: 1px solid var(--border); }
        .sidebar-logo { padding: 24px 20px; border-bottom: 1px solid var(--border); }
        .sidebar-logo h2 { font-size: 1.1rem; font-weight: 700; color: #4f46e5; }
        .sidebar-logo p { font-size: 0.75rem; color: #64748b; margin-top: 2px; }
        .sidebar-nav { padding: 16px 12px; }
        .sidebar-nav a { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 10px; color: #94a3b8; text-decoration: none; font-size: 0.875rem; font-weight: 500; margin-bottom: 4px; transition: all 0.2s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(79,70,229,0.15); color: #818cf8; }
        
        .main-content { margin-left: 260px; flex: 1; padding: 32px; overflow-y: auto; }
        
        .page-header { margin-bottom: 24px; }
        .page-header h1 { font-size: 1.6rem; font-weight: 700; color: #f1f5f9; }
        .page-header p { color: var(--text-muted); font-size: 0.9rem; margin-top: 4px; }
        
        .search-form { display: flex; gap: 14px; margin-bottom: 28px; background: var(--card-bg); padding: 20px; border-radius: 14px; border: 1px solid var(--border); backdrop-filter: blur(10px); flex-wrap: wrap; }
        .search-form input, .search-form select { flex: 1; min-width: 200px; padding: 11px 16px; border-radius: 10px; border: 1px solid var(--border); background: #0f172a; color: var(--text); font-size: 0.9rem; outline: none; }
        .search-form input:focus, .search-form select:focus { border-color: var(--primary); }
        .btn { padding: 11px 24px; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white; border: none; border-radius: 10px; cursor: pointer; transition: all 0.2s; font-weight: 600; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(79,70,229,0.4); }
        
        .books-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 20px; }
        .book-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; transition: transform 0.2s, border-color 0.2s; display: flex; flex-direction: column; box-shadow: 0 4px 15px rgba(0,0,0,0.15); }
        .book-card:hover { transform: translateY(-4px); border-color: var(--primary); }
        .book-cover { height: 160px; background: linear-gradient(135deg, #1e293b, #0f172a); display: flex; align-items: center; justify-content: center; font-size: 3rem; border-bottom: 1px solid var(--border); }
        .book-info { padding: 20px; flex: 1; display: flex; flex-direction: column; }
        .book-title { font-weight: 700; font-size: 1.05rem; margin-bottom: 4px; color: #f1f5f9; }
        .book-author { color: var(--text-muted); margin-bottom: 14px; font-size: 0.85rem; }
        
        .book-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; font-size: 0.8rem; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; }
        .badge-cat { background: rgba(59,130,246,0.15); color: #60a5fa; }
        .badge-available { background: rgba(16,185,129,0.15); color: #34d399; }
        .badge-unavailable { background: rgba(239,68,68,0.15); color: #f87171; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .empty-state span { font-size: 3rem; display: block; margin-bottom: 12px; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <h2>📚 City Public Library</h2>
            <p>Member Portal</p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php">🏠 Dashboard</a>
            <a href="search.php" class="active">🔍 Search Books</a>
            <a href="borrow_history.php">📋 My Borrows</a>
            <a href="due_dates.php">📅 Due Dates</a>
            <a href="fines.php">💰 My Fines</a>
            <a href="reservations.php">🔖 Reservations</a>
            <a href="profile.php">👤 Profile</a>
            <a href="change_password.php">🔒 Change Password</a>
            <a href="../logout.php">🚪 Logout</a>
        </nav>
    </aside>

    <div class="main-content">
        <div class="page-header">
            <h1>🔍 Search Books Catalog</h1>
            <p>Explore library books, check availability, and request reservations online.</p>
        </div>

        <form class="search-form" method="GET">
            <input type="text" name="q" placeholder="Search by title, author, or ISBN..." value="<?= htmlspecialchars($search) ?>">
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">Search Books</button>
            <?php if ($search !== '' || $category !== ''): ?>
                <a href="search.php" class="btn" style="background: rgba(255,255,255,0.08); color: #94a3b8;">Clear Filters</a>
            <?php endif; ?>
        </form>

        <?php if (empty($books)): ?>
            <div class="empty-state">
                <span>📭</span>
                <h3>No books found</h3>
                <p>Try searching with different keywords or selecting a different category.</p>
            </div>
        <?php else: ?>
            <div class="books-grid">
                <?php foreach ($books as $b): 
                    $isAvailable = (int)$b['available_copies'] > 0;
                ?>
                <div class="book-card">
                    <div class="book-cover">📖</div>
                    <div class="book-info">
                        <div class="book-title"><?= htmlspecialchars($b['title']) ?></div>
                        <div class="book-author">by <?= htmlspecialchars($b['author']) ?></div>
                        
                        <div class="book-meta">
                            <span class="badge badge-cat"><?= htmlspecialchars($b['category_name'] ?: 'General') ?></span>
                            <?php if ($isAvailable): ?>
                                <span class="badge badge-available">Available (<?= $b['available_copies'] ?>)</span>
                            <?php else: ?>
                                <span class="badge badge-unavailable">Out of Stock</span>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin-top: auto;">
                            <a href="book_details.php?id=<?= $b['id'] ?>" class="btn" style="width: 100%;">
                                View Details & Reserve
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
