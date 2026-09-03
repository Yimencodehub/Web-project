<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$book = null;
$related = [];

try {
    $stmt = $pdo->prepare("
        SELECT b.*, c.name as category_name 
        FROM books b 
        LEFT JOIN categories c ON b.category_id = c.id 
        WHERE b.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($book) {
        $stmt = $pdo->prepare("
            SELECT b.*, c.name as category_name 
            FROM books b 
            LEFT JOIN categories c ON b.category_id = c.id 
            WHERE b.category_id = :cat_id AND b.id != :id 
            LIMIT 4
        ");
        $stmt->execute([':cat_id' => $book['category_id'], ':id' => $id]);
        $related = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}

// Fallback for demo if no DB connection
if (!$book && !isset($pdo)) {
    $book = [
        'title' => 'Sample Book Title',
        'author' => 'John Doe',
        'isbn' => '978-3-16-148410-0',
        'publisher' => 'Tech Press',
        'year_published' => 2023,
        'category_name' => 'Fiction',
        'shelf_location' => 'A1-B2',
        'total_copies' => 5,
        'available_copies' => 3,
        'description' => 'This is a detailed description of the book. It covers various aspects and provides an overview of the content inside. A must-read for enthusiasts.'
    ];
}

if (!$book) {
    header("Location: catalog.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($book['title']) ?> - Library</title>
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
        body { background-color: var(--bg-color); color: var(--text-main); line-height: 1.6; }
        a { text-decoration: none; color: inherit; }
        
        /* Navbar (Same as others) */
        .navbar { background-color: var(--nav-bg); padding: 1rem 5%; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); }
        .logo { font-size: 1.5rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 0.5rem; }
        .logo span { color: var(--accent); }
        .nav-links { display: flex; gap: 2rem; }
        .nav-links a:hover { color: var(--accent); }
        .nav-actions { display: flex; gap: 1rem; }
        .btn { padding: 0.5rem 1.5rem; border-radius: 0.375rem; font-weight: 600; cursor: pointer; border: none; display: inline-block; text-align: center; transition: 0.3s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-outline { background: transparent; border: 1px solid var(--accent); color: var(--accent); }
        
        /* Container */
        .container { max-width: 1200px; margin: 0 auto; padding: 3rem 5%; }
        
        .back-link { display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text-muted); margin-bottom: 2rem; transition: color 0.3s; }
        .back-link:hover { color: var(--primary); }
        
        /* Book Detail Card */
        .detail-card { background: var(--card-bg); border-radius: 1rem; border: 1px solid var(--border-color); display: flex; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.2); backdrop-filter: blur(10px); }
        .detail-cover { width: 300px; background: linear-gradient(135deg, var(--nav-bg), var(--primary)); display: flex; align-items: center; justify-content: center; font-size: 8rem; color: rgba(255,255,255,0.3); font-weight: bold; flex-shrink: 0; }
        .detail-info { padding: 3rem; flex: 1; }
        
        .detail-title { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; line-height: 1.2; }
        .detail-author { font-size: 1.25rem; color: var(--text-muted); margin-bottom: 1.5rem; }
        
        .badge-large { display: inline-block; padding: 0.5rem 1rem; border-radius: 2rem; font-weight: 600; margin-bottom: 2rem; }
        .avail { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .unavail { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        .meta-item { border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; }
        .meta-label { color: var(--text-muted); font-size: 0.875rem; margin-bottom: 0.25rem; }
        .meta-val { font-weight: 500; }
        
        .description { line-height: 1.8; color: var(--text-muted); margin-bottom: 3rem; }
        
        .action-btns { display: flex; gap: 1rem; }
        
        /* Related */
        .related-section { margin-top: 5rem; }
        .related-title { font-size: 1.5rem; margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; }
        .book-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem; }
        
        @media (max-width: 768px) {
            .detail-card { flex-direction: column; }
            .detail-cover { width: 100%; height: 300px; }
            .meta-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="logo">City<span>Library</span></a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="catalog.php">Catalog</a>
            <a href="library_info.php">Library Info</a>
        </div>
        <div class="nav-actions">
            <a href="../login.php" class="btn btn-outline">Login</a>
        </div>
    </nav>

    <div class="container">
        <a href="catalog.php" class="back-link">← Back to Catalog</a>
        
        <div class="detail-card">
            <div class="detail-cover">
                <?= strtoupper(substr($book['title'], 0, 1)) ?>
            </div>
            <div class="detail-info">
                <?php 
                    $available = (isset($book['available_copies']) && $book['available_copies'] > 0); 
                ?>
                <div class="badge-large <?= $available ? 'avail' : 'unavail' ?>">
                    <?= $available ? '● Available' : '○ Currently Unavailable' ?>
                </div>
                
                <h1 class="detail-title"><?= htmlspecialchars($book['title']) ?></h1>
                <div class="detail-author">by <?= htmlspecialchars($book['author']) ?></div>
                
                <div class="description">
                    <?= nl2br(htmlspecialchars($book['description'] ?? 'No description available for this book.')) ?>
                </div>
                
                <div class="meta-grid">
                    <div class="meta-item">
                        <div class="meta-label">Category</div>
                        <div class="meta-val"><?= htmlspecialchars($book['category_name'] ?? 'N/A') ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">ISBN</div>
                        <div class="meta-val"><?= htmlspecialchars($book['isbn'] ?? 'N/A') ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Publisher</div>
                        <div class="meta-val"><?= htmlspecialchars($book['publisher'] ?? 'N/A') ?> (<?= htmlspecialchars($book['year_published'] ?? 'N/A') ?>)</div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Shelf Location</div>
                        <div class="meta-val"><?= htmlspecialchars($book['shelf_location'] ?? 'Ask Librarian') ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Total Copies</div>
                        <div class="meta-val"><?= (int)($book['total_copies'] ?? 0) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Available Copies</div>
                        <div class="meta-val"><?= (int)($book['available_copies'] ?? 0) ?></div>
                    </div>
                </div>
                
                <div class="action-btns">
                    <a href="../login.php?msg=borrow" class="btn btn-primary">Login to Borrow</a>
                    <a href="../login.php?msg=reserve" class="btn btn-outline">Reserve/Request</a>
                </div>
            </div>
        </div>

        <?php if(!empty($related)): ?>
        <div class="related-section">
            <h2 class="related-title">Similar Books in <?= htmlspecialchars($book['category_name']) ?></h2>
            <div class="book-grid">
                <?php foreach($related as $rb): ?>
                    <div style="background: var(--card-bg); border-radius: 0.75rem; overflow: hidden; border: 1px solid var(--border-color); padding: 1rem;">
                        <h4 style="margin-bottom: 0.5rem;"><?= htmlspecialchars($rb['title']) ?></h4>
                        <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1rem;"><?= htmlspecialchars($rb['author']) ?></p>
                        <a href="book_details.php?id=<?= $rb['id'] ?>" class="btn btn-outline" style="width: 100%; font-size: 0.875rem;">View Details</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</body>
</html>
